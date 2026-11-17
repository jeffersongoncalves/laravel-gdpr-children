<?php

namespace JeffersonGoncalves\GdprChildren\Tests;

use JeffersonGoncalves\GdprChildren\GdprChildrenServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            GdprChildrenServiceProvider::class,
        ];
    }
}
