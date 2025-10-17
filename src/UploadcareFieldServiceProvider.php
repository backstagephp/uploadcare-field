<?php

namespace Backstage\UploadcareField;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
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
            ])
            ->hasAssets()
            ->hasViews();
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('uploadcare-field', __DIR__.'/../resources/dist/uploadcare-field.css'),
            Js::make('media-picker', __DIR__.'/../resources/js/media-picker.js'),
        ], 'backstage/uploadcare-field');
    }

    public function bootingPackage(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'backstage-uploadcare-field');

        // Register Livewire components
        $this->app->make('livewire')->component('backstage-uploadcare-field::media-grid-picker', \Backstage\UploadcareField\Livewire\MediaGridPicker::class);
    }
}
