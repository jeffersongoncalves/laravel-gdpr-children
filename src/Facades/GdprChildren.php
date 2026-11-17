<?php

namespace JeffersonGoncalves\GdprChildren\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JeffersonGoncalves\GdprChildren\GdprChildren
 */
class GdprChildren extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-gdpr-children';
    }
}
