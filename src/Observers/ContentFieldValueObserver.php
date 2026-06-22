<?php

namespace Backstage\UploadcareField\Observers;

use Backstage\Media\Models\Media;
use Backstage\Models\ContentFieldValue;
use Illuminate\Support\Facades\DB;

class ContentFieldValueObserver
{
    public function saved(ContentFieldValue $contentFieldValue): void
    {
        if (! $this->isValidField($contentFieldValue)) {
            return;
        }

        $value = $contentFieldValue->getAttribute('value');

        // Normalize initial value: it could be a raw JSON string or already an array/object
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                return; // Invalid JSON
            }
        }

        if (empty($value) || ! is_array($value)) {
            return;
        }

        $mediaData = [];
        $modifiedValue = $this->processValueRecursively($value, $mediaData);

        $this->syncRelationships($contentFieldValue, $mediaData, $modifiedValue);
    }

    private function isValidField(ContentFieldValue $contentFieldValue): bool
    {
        if (! $contentFieldValue->relationLoaded('field')) {
            $contentFieldValue->load('field');
        }

        return $contentFieldValue->field && in_array(($contentFieldValue->field->field_type ?? ''), [
            'uploadcare',
            'repeater',
            'builder',
        ]);
    }

    /**
     * Recursively traverses the value to find Uploadcare data.
     * Returns the modified structure (with ULIDs replacing Uploadcare objects).
     * Populates $mediaData by reference.
     */
    private function processValueRecursively(mixed $data, array &$mediaData): mixed
    {
        if (is_string($data) && (str_starts_with($data, '[') || str_starts_with($data, '{'))) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if ($this->isUploadcareValue($decoded)) {
                    $data = $decoded;
                } else {
                    $data = $decoded;
                }
            }
        }

        if (! is_array($data)) {
            return $data;
        }

        // Check if this specific array node is an Uploadcare File object
        if ($this->isUploadcareValue($data)) {
            $isList = array_is_list($data);
            $items = $isList ? $data : [$data];
            $newUlids = [];

            foreach ($items as $item) {
                [$uuid, $meta] = $this->parseItem($item);
                if ($uuid) {
                    $mediaUlid = $this->resolveMediaUlid($uuid);
                    if ($mediaUlid) {
                        $mediaData[] = [
                            'media_ulid' => $mediaUlid,
                            'position' => count($mediaData),
                            'meta' => ! empty($meta) ? json_encode($meta) : null,
                        ];
                        $newUlids[] = $mediaUlid;
                    }
                }
            }

            return $isList ? $newUlids : ($newUlids[0] ?? null);
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->processValueRecursively($value, $mediaData);
        }

        return $data;
    }

    private function isUploadcareValue(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        // If it's a list, check the first item
        if (array_is_list($data)) {
            $first = $data[0];

            if (is_array($first) && isset($first['uuid'])) {
                return true;
            }

            if (is_string($first)) {
                // UUID strings or URLs containing UUIDs
                if (preg_match('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $first)) {
                    return true;
                }
            }

            return false;
        }

        // It's an associative array (single object). Check for uuid/cdnUrl.
        return isset($data['uuid']) || (isset($data['cdnUrl']) && is_string($data['cdnUrl']));
    }

    private function parseItem(mixed $item): array
    {
        $uuid = null;
        $meta = [];

        if (is_string($item)) {
            if (filter_var($item, FILTER_VALIDATE_URL)) {
                preg_match('/([a-f0-9-]{36})/i', $item, $matches, PREG_OFFSET_CAPTURE);
                $uuid = $matches[1][0] ?? null;
                if ($uuid) {
                    $uuidOffset = $matches[1][1] ?? null;
                    $uuidLen = strlen($uuid);
                    $modifiers = ($uuidOffset !== null) ? substr($item, $uuidOffset + $uuidLen) : '';
                    if (! empty($modifiers) && $modifiers[0] === '/') {
                        $modifiers = substr($modifiers, 1);
                    }
                    $meta = [
                        'cdnUrl' => $item,
                        'cdnUrlModifiers' => $modifiers,
                        'uuid' => $uuid,
                    ];
                } else {
                    $uuid = $item;
                }
            } else {
                $uuid = $item;
            }
        } elseif (is_array($item)) {
            $uuid = $item['uuid'] ?? ($item['fileInfo']['uuid'] ?? null);
            $meta = $item;

            // Try to extract modifiers from cdnUrl if not explicitly present or if we want to be sure
            if (isset($item['cdnUrl']) && is_string($item['cdnUrl']) && filter_var($item['cdnUrl'], FILTER_VALIDATE_URL)) {
                preg_match('/([a-f0-9-]{36})/i', $item['cdnUrl'], $matches, PREG_OFFSET_CAPTURE);
                $foundUuid = $matches[1][0] ?? null;
                if ($foundUuid) {
                    $uuidOffset = $matches[1][1] ?? null;
                    $uuidLen = strlen($foundUuid);
                    $modifiers = ($uuidOffset !== null) ? substr($item['cdnUrl'], $uuidOffset + $uuidLen) : '';

                    if (! empty($modifiers) && $modifiers[0] === '/') {
                        $modifiers = substr($modifiers, 1);
                    }
                    if (! empty($modifiers)) {
                        $meta['cdnUrlModifiers'] = $meta['cdnUrlModifiers'] ?? $modifiers;
                        $meta['cdnUrl'] = $item['cdnUrl']; // Ensure url matches
                    }
                }
            }
        }

        return [$uuid, $meta];
    }

    private function resolveMediaUlid(string $uuid): ?string
    {
        if (strlen($uuid) === 26) {
            // Only treat 26-char strings as Media ULIDs if they exist.
            // Builder/repeater data can contain Content ULIDs as well; those must NOT be attached as media.
            $mediaModel = config('backstage.media.model', Media::class);
            $media = $mediaModel::where('ulid', $uuid)->first();

            return $media?->ulid;
        }

        $mediaModel = config('backstage.media.model', Media::class);
        $media = $mediaModel::where('filename', $uuid)->first();

        return $media?->ulid;
    }

    private function syncRelationships(ContentFieldValue $contentFieldValue, array $mediaData, mixed $modifiedValue): void
    {
        DB::transaction(function () use ($contentFieldValue, $mediaData, $modifiedValue) {
            $contentFieldValue->media()->detach();

            if (! empty($mediaData)) {
                foreach ($mediaData as $data) {
                    $contentFieldValue->media()->attach($data['media_ulid'], [
                        'position' => $data['position'],
                        'meta' => $data['meta'],
                    ]);
                }
            }

            $contentFieldValue->updateQuietly(['value' => json_encode($modifiedValue)]);
        });
    }
}
