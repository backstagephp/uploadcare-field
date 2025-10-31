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

    public ?string $selectedMediaId = null;

    public function mount(string $fieldName, int $perPage = 12): void
    {
        $this->fieldName = $fieldName;
        $this->perPage = $perPage;
    }

    #[Computed]
    public function mediaItems(): LengthAwarePaginator
    {
        $mediaModel = config('backstage.media.model', 'Backstage\\Models\\Media');

        $query = $mediaModel::query();

        return $query->paginate($this->perPage)
            ->through(function ($media) {
                // Decode metadata if it's a JSON string
                $metadata = is_string($media->metadata) ? json_decode($media->metadata, true) : $media->metadata;

                return [
                    'id' => $media->ulid,
                    'filename' => $media->original_filename,
                    'mime_type' => $media->mime_type,
                    'is_image' => $media->mime_type && str_starts_with($media->mime_type, 'image/'),
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

    public function selectMedia(array $media): void
    {
        \Log::info('selectMedia called', ['media' => $media]);
        $this->selectedMediaId = $media['id'];
        \Log::info('selectedMediaId set to', ['selectedMediaId' => $this->selectedMediaId]);
    }

    public function confirmSelection(?string $selectedMediaId = null): void
    {
        if ($selectedMediaId) {
            $this->selectedMediaId = $selectedMediaId;
        }

        \Log::info('confirmSelection called', ['selectedMediaId' => $this->selectedMediaId]);

        if (! $this->selectedMediaId) {
            \Log::info('No selectedMediaId, returning');

            return;
        }

        // Find the selected media from the current page
        $selectedMedia = $this->mediaItems->firstWhere('id', $this->selectedMediaId);

        \Log::info('Selected media found', ['selectedMedia' => $selectedMedia]);

        if (! $selectedMedia) {
            \Log::info('No selectedMedia found, returning');

            return;
        }

        // Extract UUID from CDN URL
        $cdnUrl = $selectedMedia['cdn_url'];
        $uuid = $cdnUrl;

        if (str_contains($cdnUrl, 'ucarecdn.com/')) {
            if (preg_match('/ucarecdn\.com\/([^\/\?]+)/', $cdnUrl, $matches)) {
                $uuid = $matches[1];
            }
        }

        // Set the hidden field value directly
        $this->dispatch('set-hidden-field',
            fieldName: 'selected_media_uuid',
            value: $uuid
        );

        // Dispatch browser event with the UUID
        $this->dispatch('media-selected',
            fieldName: $this->fieldName,
            uuid: $uuid
        );
    }

    public function render()
    {
        return view('backstage-uploadcare-field::livewire.media-grid-picker');
    }
}
