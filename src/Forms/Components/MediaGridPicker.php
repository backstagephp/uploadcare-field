<?php

namespace Backstage\UploadcareField\Forms\Components;

use Filament\Forms\Components\Field;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class MediaGridPicker extends Field
{
    protected string $view = 'backstage-uploadcare-field::forms.components.media-grid-picker';

    protected string $fieldName;

    protected int $perPage = 12;

    protected int $currentPage = 1;

    public function mount(): void
    {
        // Read pagination parameters from URL
        $this->currentPage = (int) request()->get('media_page', 1);
        $this->perPage = (int) request()->get('per_page', 12);
    }

    public function fieldName(string $fieldName): static
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function perPage(int $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getMediaItems(): LengthAwarePaginator
    {
        $mediaModel = config('backstage.media.model', 'Backstage\\Models\\Media');

        $query = $mediaModel::query();

        // Get total count
        $total = $query->count();

        // Get items for current page
        $items = $query
            ->skip(($this->currentPage - 1) * $this->perPage)
            ->take($this->perPage)
            ->get()
            ->map(function ($media) {
                return [
                    'id' => $media->ulid,
                    'filename' => $media->original_filename,
                    'mime_type' => $media->mime_type,
                    'is_image' => $media->mime_type && str_starts_with($media->mime_type, 'image/'),
                    'cdn_url' => $media->metadata['cdnUrl'] ?? null,
                    'width' => $media->width,
                    'height' => $media->height,
                ];
            });

        return new Paginator(
            $items,
            $total,
            $this->perPage,
            $this->currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'media_page',
            ]
        );
    }

    public function setCurrentPage(int $page): static
    {
        $this->currentPage = $page;

        return $this;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getPaginationData(): array
    {
        $mediaItems = $this->getMediaItems();

        return [
            'items' => $mediaItems->items(),
            'current_page' => $mediaItems->currentPage(),
            'last_page' => $mediaItems->lastPage(),
            'per_page' => $mediaItems->perPage(),
            'total' => $mediaItems->total(),
            'from' => $mediaItems->firstItem(),
            'to' => $mediaItems->lastItem(),
            'has_pages' => $mediaItems->hasPages(),
            'on_first_page' => $mediaItems->onFirstPage(),
            'has_more_pages' => $mediaItems->hasMorePages(),
        ];
    }

    public function getMediaItemsForPage(int $page, ?int $perPage = null): array
    {
        $this->currentPage = $page;
        if ($perPage) {
            $this->perPage = $perPage;
        }

        $mediaItems = $this->getMediaItems();

        return $mediaItems->items();
    }
}
