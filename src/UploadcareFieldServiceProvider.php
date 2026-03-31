<?php

namespace Backstage\UploadcareField;

use Backstage\Fields\Fields;
use Backstage\Media\Events\MediaUploading;
use Backstage\Media\Models\Media;
use Backstage\Models\ContentFieldValue;
use Backstage\UploadcareField\Listeners\CreateMediaFromUploadcare;
use Backstage\UploadcareField\Livewire\MediaGridPicker;
use Backstage\UploadcareField\Observers\ContentFieldValueObserver;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Event;
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

        Event::listen(
            MediaUploading::class,
            CreateMediaFromUploadcare::class,
        );

        ContentFieldValue::observe(ContentFieldValueObserver::class);

        Fields::registerField(Uploadcare::class);
    }

    public function bootingPackage(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'backstage-uploadcare-field');

        // Register Media src resolver
        Media::resolveSrcUsing(function ($media) {
            if ($media->metadata && isset($media->metadata['cdnUrl'])) {
                $cdnUrl = $media->metadata['cdnUrl'];
                if (filter_var($cdnUrl, FILTER_VALIDATE_URL)) {
                    return $cdnUrl;
                }
            }

            return null;
        });

        // Register Livewire components
        $this->app->make('livewire')->component('backstage-uploadcare-field::media-grid-picker', MediaGridPicker::class);
    }
}
