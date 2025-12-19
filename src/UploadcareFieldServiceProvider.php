<?php

namespace Backstage\UploadcareField;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class UploadcareFieldServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('backstage/uploadcare-field')
            ->hasMigrations([
                '2025_08_08_000000_fix_uploadcare_double_encoded_json',
                '2025_12_08_163311_normalize_uploadcare_values_to_ulids',
                '2025_12_17_000001_repair_uploadcare_media_relationships',
            ])
            ->hasAssets()
            ->hasViews();
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('uploadcare-field', __DIR__.'/../resources/dist/uploadcare-field.css'),
        ], 'backstage/uploadcare-field');

        \Illuminate\Support\Facades\Event::listen(
            \Backstage\Media\Events\MediaUploading::class,
            \Backstage\UploadcareField\Listeners\CreateMediaFromUploadcare::class,
        );

        \Backstage\Models\ContentFieldValue::observe(\Backstage\UploadcareField\Observers\ContentFieldValueObserver::class);

        \Backstage\Fields\Fields::registerField(\Backstage\UploadcareField\Uploadcare::class);
    }

    public function bootingPackage(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'backstage-uploadcare-field');

        // Register Media src resolver
        \Backstage\Media\Models\Media::resolveSrcUsing(function ($media) {
            if ($media->metadata && isset($media->metadata['cdnUrl'])) {
                $cdnUrl = $media->metadata['cdnUrl'];
                if (filter_var($cdnUrl, FILTER_VALIDATE_URL)) {
                    return $cdnUrl;
                }
            }

            return null;
        });

        // Register Livewire components
        $this->app->make('livewire')->component('backstage-uploadcare-field::media-grid-picker', \Backstage\UploadcareField\Livewire\MediaGridPicker::class);
    }
}
