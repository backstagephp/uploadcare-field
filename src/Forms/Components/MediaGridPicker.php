<?php

namespace Backstage\UploadcareField\Forms\Components;

use Filament\Forms\Components\Field;

class MediaGridPicker extends Field
{
    protected string $view = 'backstage-uploadcare-field::forms.components.media-grid-picker';

    protected string $fieldName;

    protected int $perPage = 12;

    protected bool $multiple = false;

    protected ?array $acceptedFileTypes = null;

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

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function getMultiple(): bool
    {
        return $this->multiple;
    }

    public function acceptedFileTypes(?array $acceptedFileTypes): static
    {
        $this->acceptedFileTypes = $acceptedFileTypes;

        return $this;
    }

    public function getAcceptedFileTypes(): ?array
    {
        return $this->acceptedFileTypes;
    }
}
