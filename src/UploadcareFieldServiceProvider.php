<?php

namespace Backstage\UploadcareField;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class UploadcareFieldServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('backstage-uploadcare-field')
            ->hasMigrations([
                '2025_08_08_000000_fix_uploadcare_double_encoded_json',
            ]);
    }

    public function boot(): void
    {
        parent::boot();

        // Auto-run migration if it hasn't been run yet
        if ($this->app->runningInConsole()) {
            $this->runMigrationIfNeeded();
        }
    }

    private function runMigrationIfNeeded(): void
    {
        try {
            $migrationName = '2025_08_08_000000_fix_uploadcare_double_encoded_json';

            // Check if migration has been run
            $migrationExists = \Illuminate\Support\Facades\Schema::hasTable('migrations') &&
                \Illuminate\Support\Facades\DB::table('migrations')
                    ->where('migration', 'like', '%'.$migrationName.'%')
                    ->exists();

            if (! $migrationExists) {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Exception $e) {
            // Silently fail if migration can't be run
            // This prevents issues in non-Laravel contexts
        }
    }
}
// Test comment
