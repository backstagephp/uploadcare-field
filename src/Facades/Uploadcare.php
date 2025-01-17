<?php

namespace Vormkracht10\UploadcareField\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Vormkracht10\UploadcareField\Uploadcare
 */
class Uploadcare extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Vormkracht10\UploadcareField\Uploadcare::class;
    }
}
