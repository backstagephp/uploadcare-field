<?php

namespace Vormkracht10\UploadcareField\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Vormkracht10\UploadcareField\UploadcareField
 */
class UploadcareField extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Vormkracht10\UploadcareField\UploadcareField::class;
    }
}
