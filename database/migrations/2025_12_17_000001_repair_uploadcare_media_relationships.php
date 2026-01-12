<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $targetFieldIds = DB::table('fields')
            ->whereIn('field_type', ['uploadcare', 'builder', 'repeater'])
            ->pluck('ulid');

        if ($targetFieldIds->isEmpty()) {
            return;
        }

        $firstSiteUlid = DB::table('sites')->orderBy('ulid')->value('ulid');

        $mediaModelClass = config('backstage.media.model', \Backstage\Media\Models\Media::class);
        if (! is_string($mediaModelClass) || ! class_exists($mediaModelClass)) {
            $mediaModelClass = \Backstage\Media\Models\Media::class;
        }

        $mediaTable = app($mediaModelClass)->getTable();

        $mediaHasSiteUlid = Schema::hasColumn($mediaTable, 'site_ulid');
        $mediaHasDisk = Schema::hasColumn($mediaTable, 'disk');
        $mediaHasPublic = Schema::hasColumn($mediaTable, 'public');
        $mediaHasMetadata = Schema::hasColumn($mediaTable, 'metadata');
        $mediaHasOriginalFilename = Schema::hasColumn($mediaTable, 'original_filename');
        $mediaHasMimeType = Schema::hasColumn($mediaTable, 'mime_type');
        $mediaHasExtension = Schema::hasColumn($mediaTable, 'extension');
        $mediaHasSize = Schema::hasColumn($mediaTable, 'size');
        $mediaHasWidth = Schema::hasColumn($mediaTable, 'width');
        $mediaHasHeight = Schema::hasColumn($mediaTable, 'height');
        $mediaHasChecksum = Schema::hasColumn($mediaTable, 'checksum');

        $isUlid = function (mixed $value): bool {
            return is_string($value) && (bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $value);
        };

        $extractUuidFromString = function (string $value): ?string {
            if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/i', $value, $matches)) {
                return $matches[1];
            }

            return null;
        };

        $buildUrlMeta = function (string $url, string $uuid) {
            $pos = stripos($url, $uuid);
            $modifiers = $pos === false ? '' : substr($url, $pos + strlen($uuid));

            return [
                'cdnUrl' => $url,
                'cdnUrlModifiers' => $modifiers,
                'uuid' => $uuid,
            ];
        };

        $shouldUpdateMeta = function (?string $existingMeta): bool {
            if (! $existingMeta) {
                return true;
            }

            $decoded = json_decode($existingMeta, true);
            if (! is_array($decoded) || empty($decoded)) {
                return true;
            }

            // If we already have identifying info, keep it.
            if (! empty($decoded['uuid']) || ! empty($decoded['cdnUrl']) || ! empty($decoded['fileInfo']['uuid'] ?? null) || ! empty($decoded['fileInfo']['cdnUrl'] ?? null)) {
                return false;
            }

            return true;
        };

        $ensureRelationship = function (string $contentFieldValueUlid, string $mediaUlid, int $position, ?array $meta) use ($shouldUpdateMeta) {
            $existing = DB::table('media_relationships')
                ->where('model_type', 'content_field_value')
                ->where('model_id', $contentFieldValueUlid)
                ->where('media_ulid', $mediaUlid)
                ->first();

            $payload = [
                'position' => $position,
                'updated_at' => now(),
            ];

            if ($meta !== null) {
                $metaJson = json_encode($meta);
                if (! $existing || $shouldUpdateMeta($existing->meta ?? null)) {
                    $payload['meta'] = $metaJson;
                }
            }

            if (! $existing) {
                DB::table('media_relationships')->insert([
                    'media_ulid' => $mediaUlid,
                    'model_type' => 'content_field_value',
                    'model_id' => $contentFieldValueUlid,
                    'position' => $position,
                    'meta' => $meta !== null ? json_encode($meta) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return;
            }

            DB::table('media_relationships')
                ->where('id', $existing->id)
                ->update($payload);
        };

        $findOrCreateMediaByUuid = function (string $uuid, ?array $fileData, ?string $siteUlid) use (
            $mediaModelClass,
            $mediaHasSiteUlid,
            $mediaHasDisk,
            $mediaHasPublic,
            $mediaHasMetadata,
            $mediaHasOriginalFilename,
            $mediaHasMimeType,
            $mediaHasExtension,
            $mediaHasSize,
            $mediaHasWidth,
            $mediaHasHeight,
            $mediaHasChecksum
        ) {
            $media = $mediaModelClass::where('filename', $uuid)->first();
            if ($media) {
                return $media;
            }

            if (! is_array($fileData) || empty($fileData)) {
                return null;
            }

            $info = $fileData['fileInfo'] ?? $fileData;
            $detailedInfo = $info['imageInfo'] ?? $info['videoInfo'] ?? $info['contentInfo'] ?? [];

            $media = new $mediaModelClass;

            // Some Media models auto-generate ULIDs, but setting explicitly is safe if field exists.
            if (Schema::hasColumn($media->getTable(), 'ulid')) {
                $media->ulid = (string) Str::ulid();
            }

            if ($mediaHasSiteUlid && $siteUlid) {
                $media->site_ulid = $siteUlid;
            }
            if ($mediaHasDisk) {
                $media->disk = 'uploadcare';
            }

            $media->filename = $uuid;

            if ($mediaHasOriginalFilename) {
                $media->original_filename = $info['originalFilename'] ?? $info['original_filename'] ?? $info['name'] ?? 'unknown';
            }
            if ($mediaHasMimeType) {
                $media->mime_type = $info['mimeType'] ?? $info['mime_type'] ?? 'application/octet-stream';
            }
            if ($mediaHasExtension) {
                $media->extension = $detailedInfo['format']
                    ?? pathinfo(($info['originalFilename'] ?? $info['name'] ?? ''), PATHINFO_EXTENSION);
            }
            if ($mediaHasSize) {
                $media->size = (int) ($info['size'] ?? 0);
            }
            if ($mediaHasWidth) {
                $media->width = $detailedInfo['width'] ?? null;
            }
            if ($mediaHasHeight) {
                $media->height = $detailedInfo['height'] ?? null;
            }
            if ($mediaHasPublic) {
                $media->public = true;
            }
            if ($mediaHasMetadata) {
                $media->metadata = $info;
            }
            if ($mediaHasChecksum) {
                $media->checksum = md5($uuid);
            }

            $media->save();

            return $media;
        };

        $processValue = function (&$data, string $siteUlid, string $rowUlid, int &$position) use (
            &$processValue,
            $isUlid,
            $extractUuidFromString,
            $buildUrlMeta,
            $ensureRelationship,
            $findOrCreateMediaByUuid,
            $mediaModelClass
        ): bool {
            $anyModified = false;

            if (is_string($data) && (str_starts_with($data, '[') || str_starts_with($data, '{'))) {
                $decoded = json_decode($data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                    $anyModified = true;
                }
            }

            if (! is_array($data)) {
                return $anyModified;
            }

            // Raw Uploadcare list: array of arrays with uuid
            $isRawUploadcareList = ! empty($data) && isset($data[0]) && is_array($data[0]) && isset($data[0]['uuid']);

            // List of strings (ULIDs, UUIDs, URLs)
            $isStringList = ! empty($data) && isset($data[0]) && is_string($data[0]) && array_is_list($data);

            if ($isRawUploadcareList) {
                $newUlids = [];
                foreach ($data as $fileData) {
                    if (! is_array($fileData)) {
                        continue;
                    }

                    $uuid = $fileData['uuid'] ?? ($fileData['fileInfo']['uuid'] ?? null);
                    if (! is_string($uuid) || ! Str::isUuid($uuid)) {
                        continue;
                    }

                    $media = $findOrCreateMediaByUuid($uuid, $fileData, $siteUlid);
                    if (! $media) {
                        // If media can't be created, skip relationship.
                        continue;
                    }

                    $position++;
                    $ensureRelationship($rowUlid, $media->ulid, $position, $fileData);
                    $newUlids[] = $media->ulid;
                }

                if (! empty($newUlids)) {
                    $data = $newUlids;
                    $anyModified = true;
                }

                return $anyModified;
            }

            if ($isStringList) {
                $newUlids = [];
                foreach ($data as $item) {
                    if (! is_string($item) || $item === '') {
                        continue;
                    }

                    // ULID list: only attach when a Media record exists.
                    if ($isUlid($item)) {
                        $media = $mediaModelClass::where('ulid', $item)->first();
                        if (! $media) {
                            continue;
                        }

                        $meta = is_array($media->metadata ?? null) ? $media->metadata : null;
                        $position++;
                        $ensureRelationship($rowUlid, $media->ulid, $position, $meta);
                        $newUlids[] = $media->ulid;

                        continue;
                    }

                    // UUID string or URL containing UUID: only attach when a Media record exists.
                    $uuid = $extractUuidFromString($item);
                    if (! $uuid || ! Str::isUuid($uuid)) {
                        continue;
                    }

                    $media = $mediaModelClass::where('filename', $uuid)->first();
                    if (! $media) {
                        // Don't create media here (too risky without fileData).
                        continue;
                    }

                    $meta = filter_var($item, FILTER_VALIDATE_URL) ? $buildUrlMeta($item, $uuid) : ['uuid' => $uuid];
                    $position++;
                    $ensureRelationship($rowUlid, $media->ulid, $position, $meta);
                    $newUlids[] = $media->ulid;
                }

                if (! empty($newUlids)) {
                    // Normalize to ULIDs
                    $data = $newUlids;
                    $anyModified = true;
                }

                return $anyModified;
            }

            foreach ($data as $key => &$value) {
                if (is_array($value) || is_string($value)) {
                    if ($processValue($value, $siteUlid, $rowUlid, $position)) {
                        $anyModified = true;
                    }
                }
            }
            unset($value);

            return $anyModified;
        };

        DB::table('content_field_values')
            ->whereIn('field_ulid', $targetFieldIds)
            ->chunkById(50, function ($rows) use ($processValue, $firstSiteUlid) {
                foreach ($rows as $row) {
                    $value = $row->value;

                    $decoded = json_decode($value, true);
                    if (is_string($decoded)) {
                        $decoded = json_decode($decoded, true);
                    }

                    if (! is_array($decoded)) {
                        continue;
                    }

                    $siteUlid = $row->site_ulid ?? $firstSiteUlid;
                    $position = 0;

                    if ($processValue($decoded, $siteUlid, $row->ulid, $position)) {
                        DB::table('content_field_values')
                            ->where('ulid', $row->ulid)
                            ->update(['value' => json_encode($decoded)]);
                    }
                }
            }, 'ulid');
    }

    public function down(): void
    {
        //
    }
};
