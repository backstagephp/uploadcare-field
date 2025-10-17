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
        $this->dispatch('media-selected', 
            fieldName: $this->fieldName,
            media: $media
        );
    }

    public function render()
    {
        return view('backstage-uploadcare-field::livewire.media-grid-picker');
    }
}
