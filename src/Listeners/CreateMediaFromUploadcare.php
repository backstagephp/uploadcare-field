<?php

namespace Backstage\UploadcareField\Listeners;

use Backstage\Media\Events\MediaUploading;
use Backstage\Media\Models\Media;
use Exception;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

class CreateMediaFromUploadcare
{
    public function handle(MediaUploading $event): ?Media
    {
        return $this->createMediaFromUploadcare($event->file);
    }

    private function createMediaFromUploadcare(mixed $file): ?Media
    {
        try {
            $normalizedFile = $this->normalizeUploadcareFile($file);
            if (! $normalizedFile) {
                return null;
            }

            $fileInfo = $this->extractUploadcareFileInfo($normalizedFile);
            if (! $fileInfo) {
                return null;
            }

            $disk = 'uploadcare';
            $searchCriteria = $this->buildUploadcareSearchCriteria($fileInfo, $disk);
            $values = $this->buildUploadcareValues($fileInfo, $disk);

            $searchCriteria = $this->addTenantToSearchCriteria($searchCriteria);
            $values = $this->addTenantToMediaData($values);

            return Media::updateOrCreate($searchCriteria, $values);
        } catch (Exception $e) {
            return null;
        }
    }

    private function normalizeUploadcareFile(mixed $file): ?array
    {
        if (is_string($file)) {
            if (filter_var($file, FILTER_VALIDATE_URL) && str_contains($file, 'ucarecdn.com')) {
                return ['cdnUrl' => $file, 'name' => basename(parse_url($file, PHP_URL_PATH))];
            }

            $decoded = json_decode($file, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return null;
        }

        return is_array($file) ? $file : null;
    }

    private function extractUploadcareFileInfo(array $file): ?array
    {
        $info = $file['fileInfo'] ?? $file;
        $cdnUrl = $info['cdnUrl'] ?? null;

        if (! $cdnUrl || (! str_contains($cdnUrl, 'ucarecdn.com') && ! str_contains($cdnUrl, 'ucarecd.net'))) {
            return null;
        }

        $detailedInfo = $info['imageInfo'] ?? $info['videoInfo'] ?? $info['contentInfo'] ?? [];

        // Extract UUID from info or URL
        $uuid = $info['uuid'] ?? $this->extractUuidFromUrl($cdnUrl);

        // Use UUID as filename, fallback to original name if UUID not found (unlikely)
        $filename = $uuid ?? $info['name'] ?? basename(parse_url($cdnUrl, PHP_URL_PATH));
        $originalFilename = $info['originalFilename'] ?? $info['name'] ?? basename(parse_url($cdnUrl, PHP_URL_PATH));

        return [
            'info' => $info,
            'detailedInfo' => $detailedInfo,
            'cdnUrl' => $cdnUrl,
            'filename' => $filename,
            'originalFilename' => $originalFilename,
            'checksum' => md5($uuid),
        ];
    }

    private function extractUuidFromUrl(string $url): ?string
    {
        if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function buildUploadcareSearchCriteria(array $fileInfo, string $disk): array
    {
        return [
            'disk' => $disk,
            'filename' => $fileInfo['filename'],
        ];
    }

    private function buildUploadcareValues(array $fileInfo, string $disk): array
    {
        $info = $fileInfo['info'];
        $detailedInfo = $fileInfo['detailedInfo'];

        return [
            'disk' => $disk,
            'uploaded_by' => Auth::id(),
            'original_filename' => $fileInfo['originalFilename'],
            'filename' => $fileInfo['filename'],
            'extension' => $detailedInfo['format'] ?? pathinfo($fileInfo['originalFilename'], PATHINFO_EXTENSION),
            'mime_type' => $info['mimeType'] ?? null,
            'size' => $info['size'] ?? null,
            'width' => $detailedInfo['width'] ?? null,
            'height' => $detailedInfo['height'] ?? null,
            'alt' => null,
            'public' => config('backstage.media.visibility') === 'public',
            'metadata' => $info,
            'checksum' => md5($fileInfo['cdnUrl']),
        ];
    }

    private function addTenantToMediaData(array $mediaData): array
    {
        if (! config('backstage.media.is_tenant_aware', false) || ! Filament::hasTenancy()) {
            return $mediaData;
        }

        $tenant = Filament::getTenant();
        if (! $tenant) {
            return $mediaData;
        }

        $tenantRelationship = config('backstage.media.tenant_relationship', 'site');
        $tenantField = $tenantRelationship . '_ulid';
        $tenantUlid = $tenant->ulid ?? (method_exists($tenant, 'getKey') ? $tenant->getKey() : ($tenant->id ?? null));

        if ($tenantUlid) {
            $mediaData[$tenantField] = $tenantUlid;
        }

        return $mediaData;
    }

    private function addTenantToSearchCriteria(array $searchCriteria): array
    {
        if (! config('backstage.media.is_tenant_aware', false) || ! Filament::hasTenancy()) {
            return $searchCriteria;
        }

        $tenant = Filament::getTenant();
        if (! $tenant) {
            return $searchCriteria;
        }

        $tenantRelationship = config('backstage.media.tenant_relationship', 'site');
        $tenantField = $tenantRelationship . '_ulid';
        $tenantUlid = $tenant->ulid ?? (method_exists($tenant, 'getKey') ? $tenant->getKey() : ($tenant->id ?? null));

        if ($tenantUlid) {
            $searchCriteria[$tenantField] = $tenantUlid;
        }

        return $searchCriteria;
    }
}
