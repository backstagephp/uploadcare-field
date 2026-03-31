<?php

use Backstage\Media\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
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

        $processValue = function (&$data, $siteUlid, $rowUlid) use (&$processValue) {
            $anyModified = false;

            if (! is_array($data)) {
                return false;
            }

            $isRawUploadcareList = false;
            if (! empty($data) && isset($data[0]) && is_array($data[0]) && isset($data[0]['uuid'])) {
                $isRawUploadcareList = true;
            }

            $isAlreadyUlidList = false;
            if (! empty($data) && isset($data[0]) && is_string($data[0]) && strlen($data[0]) === 26) {
                $isAlreadyUlidList = true;
                foreach ($data as $item) {
                    if (! is_string($item) || strlen($item) !== 26) {
                        $isAlreadyUlidList = false;

                        break;
                    }
                }
            }

            if ($isRawUploadcareList) {
                $newUlids = [];
                foreach ($data as $fileData) {
                    $uuid = $fileData['uuid'];

                    $media = Media::where('filename', $uuid)->first();

                    if (! $media) {
                        $media = new Media;
                        $media->ulid = (string) Str::ulid();
                        $media->site_ulid = $siteUlid;
                        $media->disk = 'uploadcare';
                        $media->filename = $uuid;
                        $info = $fileData['fileInfo'] ?? $fileData;
                        $detailedInfo = $info['imageInfo'] ?? $info['videoInfo'] ?? $info['contentInfo'] ?? [];

                        $media->extension = $detailedInfo['format']
                            ?? pathinfo($info['originalFilename'] ?? $info['name'] ?? '', PATHINFO_EXTENSION);

                        $media->original_filename = $info['originalFilename'] ?? $info['original_filename'] ?? $info['name'] ?? 'unknown';
                        $media->mime_type = $info['mimeType'] ?? $info['mime_type'] ?? 'application/octet-stream';
                        $media->size = $info['size'] ?? 0;
                        $media->public = true;
                        $media->metadata = $info;

                        $media->checksum = md5($uuid);
                        $media->save();
                    }
                    $newUlids[] = $media->ulid;

                    DB::table('media_relationships')->insertOrIgnore([
                        'media_ulid' => $media->ulid,
                        'model_type' => 'content_field_value',
                        'model_id' => $rowUlid,
                        'meta' => json_encode($fileData),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $data = $newUlids;

                return true;
            }

            if ($isAlreadyUlidList) {
                foreach ($data as $mediaUlid) {
                    $media = Media::where('ulid', $mediaUlid)->first();
                    if ($media) {
                        DB::table('media_relationships')->insertOrIgnore([
                            'media_ulid' => $media->ulid,
                            'model_type' => 'content_field_value',
                            'model_id' => $rowUlid,
                            'meta' => json_encode($media->metadata),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                return false;
            }

            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                    if ($processValue($value, $siteUlid, $rowUlid)) {
                        $anyModified = true;
                    }
                } elseif (is_string($value)) {
                    if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            if ($processValue($decoded, $siteUlid, $rowUlid)) {
                                $value = $decoded;
                                $anyModified = true;
                            }
                        }
                    }
                }
            }

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

                    // Use row's site_ulid if available, otherwise fallback to first site
                    $siteUlid = $row->site_ulid ?? $firstSiteUlid;

                    if ($processValue($decoded, $siteUlid, $row->ulid)) {
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
