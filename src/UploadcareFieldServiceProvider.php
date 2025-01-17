<?php

namespace Vormkracht10\Uploadcare;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class UploadcareServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('backstage-uploadcare-field');
    }
}
