<?php

namespace Backstage\UploadcareField\Forms\Components;

use Filament\Forms\Components\Field;

class MediaGridPicker extends Field
{
    protected string $view = 'backstage-uploadcare-field::forms.components.media-grid-picker';

    protected string $fieldName;

    protected int $perPage = 12;

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
}
