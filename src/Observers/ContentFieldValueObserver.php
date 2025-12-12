<?php

namespace Backstage\UploadcareField\Observers;

use Backstage\Media\Models\Media;
use Backstage\Models\ContentFieldValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        // Even if empty($mediaData), we might have cleared a field, so we should sync (detach all)
        // But if nothing was modified (strict check?), maybe we skip?
        // Actually, if it's a repeater saving, we always want to ensure we catch the latest state.
        // Let's rely on mediaData being collected.

        if (empty($mediaData) && $value === $modifiedValue) {
            // If no media found and value didn't change (structure-wise substitutions), might be nothing to do.
            // However, detached images need to be handled.
            // If we found no media, we sync an empty array, which detaches everything.
        }

        Log::info('Syncing Media Data', ['count' => count($mediaData)]);

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
        // Handle JSON strings that might contain Uploadcare data
        if (is_string($data) && (str_starts_with($data, '[') || str_starts_with($data, '{'))) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Determine if this decoded data is an Uploadcare value
                if ($this->isUploadcareValue($decoded)) {
                    // It is! Process it as such.
                    // We recurse on the DECODED value, which falls into the array handling below.
                    // But wait, the array handling below expects to traverse keys if it's not a value?
                    // No, let's just pass $decoded to a recursive call?
                    // Or just handle it right here to avoid logic duplication.

                    // Actually, if it is an Uploadcare value, we want to run the extraction logic.
                    // The extraction logic is inside current function's "isUploadcareValue" block.
                    // So let's normalize $data to $decoded and proceed.
                    $data = $decoded;
                } else {
                    // It's a JSON string but NOT an Uploadcare value (maybe a nested repeater encoded as string?).
                    // We should probably traverse it too?
                    // Only if we want to fix ALL nested JSON strings.
                    // The user asked about "repeaters and builders".
                    // If a repeater inside a repeater is stored as a string, we should decode it to find the deeper files.
                    $data = $decoded;
                }
            }
        }

        if (! is_array($data)) {
            return $data;
        }

        // Check if this specific array node is an Uploadcare File object
        if ($this->isUploadcareValue($data)) {
            $newUlids = [];
            foreach ($data as $index => $item) {
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

            return $newUlids;
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->processValueRecursively($value, $mediaData);
        }

        return $data;
    }

    private function isUploadcareValue(array $data): bool
    {
        // Must be a list (integer keys)
        if (! array_is_list($data)) {
            return false;
        }

        // Check first item to see if it looks like an Uploadcare file structure
        if (empty($data)) {
            // Empty array could be an empty file list or empty repeater.
            // Ambiguous. But safe to return false and just return empty array.
            return false;
        }

        $first = $data[0];

        // Existing logic used: isset($value['uuid'])
        if (is_array($first) && isset($first['uuid'])) {
            return true;
        }

        // It might be a list of mixed things? Unlikely for a strictly typed field.
        return false;
    }

    private function parseItem(mixed $item): array
    {
        $uuid = null;
        $meta = [];

        if (is_string($item)) {
            if (filter_var($item, FILTER_VALIDATE_URL) && str_contains($item, 'ucarecdn.com')) {
                preg_match('/ucarecdn\.com\/([a-f0-9-]{36})(\/.*)?/i', $item, $matches);
                $uuid = $matches[1] ?? null;
                if ($uuid) {
                    $meta = [
                        'cdnUrl' => $item,
                        'cdnUrlModifiers' => $matches[2] ?? '',
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
            if (isset($item['cdnUrl']) && is_string($item['cdnUrl']) && str_contains($item['cdnUrl'], 'ucarecdn.com')) {
                preg_match('/ucarecdn\.com\/([a-f0-9-]{36})(\/.*)?/i', $item['cdnUrl'], $matches);
                if (isset($matches[2]) && ! empty($matches[2])) {
                    $meta['cdnUrlModifiers'] = $matches[2];
                    $meta['cdnUrl'] = $item['cdnUrl']; // Ensure url matches
                }
            }
        }

        return [$uuid, $meta];
    }

    private function resolveMediaUlid(string $uuid): ?string
    {
        if (strlen($uuid) === 26) {
            return $uuid; // Already a ULID?
        }

        // Check if it looks like a version 4 UUID (Uploadcare usually uses these)
        if (! Str::isUuid($uuid)) {
            // If strictly not a UUID, and not a ULID (checked via length/format), what is it?
            // Maybe it's a filename that is just a string?
            // Let's just try to find it.
        }

        $mediaModel = config('backstage.media.model', Media::class);
        $media = $mediaModel::where('filename', $uuid)->first();

        return $media?->ulid;
    }

    private function syncRelationships(ContentFieldValue $contentFieldValue, array $mediaData, mixed $modifiedValue): void
    {
        DB::transaction(function () use ($contentFieldValue, $mediaData, $modifiedValue) {
            $contentFieldValue->media()->detach();

            foreach ($mediaData as $data) {
                $contentFieldValue->media()->attach($data['media_ulid'], [
                    'position' => $data['position'],
                    'meta' => $data['meta'],
                ]);
            }

            // Important: We must save the modified value (with ULIDs) back to the field
            // But we must NOT double encode.
            // ContentFieldValue uses implicit casting or just stores string?
            // The model is "DecodesJsonStrings", but for saving we generally pass array if we want it cast,
            // or we manually json_encode if the model doesn't cast it automatically on set.
            // ContentFieldValue definition:
            // protected $guarded = [];
            // no specific casts defined for 'value' in the snippet viewed earlier (returns empty array).
            // But it has `use DecodesJsonStrings`.

            // In the original code:
            // $contentFieldValue->updateQuietly(['value' => json_encode($ulids)]);

            // So we should json_encode the result.
            $contentFieldValue->updateQuietly(['value' => json_encode($modifiedValue)]);
        });
    }
}
