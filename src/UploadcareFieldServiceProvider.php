<?php

namespace Backstage\UploadcareField;

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
            ]);
    }
}
