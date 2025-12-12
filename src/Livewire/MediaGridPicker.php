<?php

namespace Backstage\UploadcareField\Livewire;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class MediaGridPicker extends Component
{
    use WithPagination;

    public string $fieldName;

    public int $perPage = 12;

    public bool $multiple = false;

    public ?array $acceptedFileTypes = null;

    public ?string $selectedMediaId = null;

    public ?string $selectedMediaUuid = null;

    public array $selectedMediaIds = [];

    public array $selectedMediaUuids = [];

    public string $search = '';

    public function mount(string $fieldName, int $perPage = 12, bool $multiple = false, ?array $acceptedFileTypes = null): void
    {
        $this->fieldName = $fieldName;
        $this->perPage = $perPage;
        $this->multiple = $multiple;
        $this->acceptedFileTypes = $acceptedFileTypes;
    }

    #[Computed]
    public function mediaItems(): LengthAwarePaginator
    {
        $mediaModel = config('backstage.media.model', 'Backstage\\Models\\Media');

        $query = $mediaModel::query();

        // Apply search filter
        if (! empty($this->search)) {
            $query->where('original_filename', 'like', '%' . $this->search . '%');
        }

        // Apply accepted file types filter at query level
        if (! empty($this->acceptedFileTypes)) {
            $query->where(function ($q) {
                foreach ($this->acceptedFileTypes as $acceptedType) {
                    // Handle wildcard patterns like "image/*"
                    if (str_ends_with($acceptedType, '/*')) {
                        $baseType = substr($acceptedType, 0, -2);
                        $q->orWhere('mime_type', 'like', $baseType . '/%');
                    }
                    // Handle exact matches
                    else {
                        $q->orWhere('mime_type', $acceptedType);
                    }
                }
            });
        }

        return $query->paginate($this->perPage)
            ->through(function ($media) {
                // Decode metadata if it's a JSON string
                $metadata = is_string($media->metadata) ? json_decode($media->metadata, true) : $media->metadata;

                $mimeType = $media->mime_type;

                return [
                    'id' => $media->ulid,
                    'filename' => $media->original_filename,
                    'mime_type' => $mimeType,
                    'is_image' => $mimeType && str_starts_with($mimeType, 'image/'),
                    'cdn_url' => $metadata['cdnUrl'] ?? null,
                    'width' => $media->width,
                    'height' => $media->height,
                ];
            });
    }

    public function updatePerPage(int $newPerPage): void
    {
        $this->perPage = $newPerPage;
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function selectMedia(array $media): void
    {
        $mediaId = $media['id'];

        // Extract UUID from CDN URL
        $cdnUrl = $media['cdn_url'] ?? null;
        $uuid = $cdnUrl;

        if ($cdnUrl && str_contains($cdnUrl, 'ucarecdn.com/')) {
            if (preg_match('/ucarecdn\.com\/([^\/\?]+)/', $cdnUrl, $matches)) {
                $uuid = $matches[1];
            }
        }

        if ($this->multiple) {
            // Toggle selection in arrays
            $index = array_search($mediaId, $this->selectedMediaIds);
            if ($index !== false) {
                // Remove from selection
                unset($this->selectedMediaIds[$index]);
                unset($this->selectedMediaUuids[$index]);
                $this->selectedMediaIds = array_values($this->selectedMediaIds);
                $this->selectedMediaUuids = array_values($this->selectedMediaUuids);
            } else {
                // Add to selection
                $this->selectedMediaIds[] = $mediaId;
                $this->selectedMediaUuids[] = $uuid;
            }

            // Dispatch event to update hidden field in modal with array
            $this->dispatch(
                'set-hidden-field',
                fieldName: 'selected_media_uuid',
                value: $this->selectedMediaUuids
            );
        } else {
            // Single selection mode
            $this->selectedMediaId = $mediaId;
            $this->selectedMediaUuid = $uuid;

            // Dispatch event to update hidden field in modal
            $this->dispatch(
                'set-hidden-field',
                fieldName: 'selected_media_uuid',
                value: $uuid
            );
        }
    }

    private function matchesAcceptedFileTypes(?string $mimeType): bool
    {
        if (empty($this->acceptedFileTypes) || empty($mimeType)) {
            return true;
        }

        foreach ($this->acceptedFileTypes as $acceptedType) {
            // Handle wildcard patterns like "image/*"
            if (str_ends_with($acceptedType, '/*')) {
                $baseType = substr($acceptedType, 0, -2);
                if (str_starts_with($mimeType, $baseType . '/')) {
                    return true;
                }
            }
            // Handle exact matches
            elseif ($mimeType === $acceptedType) {
                return true;
            }
        }

        return false;
    }

    public function render()
    {
        return view('backstage-uploadcare-field::livewire.media-grid-picker');
    }
}
