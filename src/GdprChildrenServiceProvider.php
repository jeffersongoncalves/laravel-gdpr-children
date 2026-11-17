<?php

namespace JeffersonGoncalves\GdprChildren;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GdprChildrenServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-gdpr-children')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations();
    }
}
