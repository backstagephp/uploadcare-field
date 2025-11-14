<?php

namespace Backstage\UploadcareField\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Backstage\UploadcareField\Uploadcare
 */
class Uploadcare extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Backstage\UploadcareField\Uploadcare::class;
    }
}
