<?php

namespace Vormkracht10\Uploadcare\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Vormkracht10\Uploadcare\Uploadcare
 */
class Uploadcare extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Vormkracht10\Uploadcare\Uploadcare::class;
    }
}
