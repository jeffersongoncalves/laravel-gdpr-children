<?php

namespace JeffersonGoncalves\GdprChildren;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use JeffersonGoncalves\GdprChildren\Commands\ChildrenCommand;
use JeffersonGoncalves\GdprChildren\Http\Middleware\EnsureVerifiedAdult;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GdprChildrenServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('gdpr-children')
            ->hasConfigFile()
            ->hasMigrations(['create_gdpr_children_tables'])
            ->hasCommand(ChildrenCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ChildrenManager::class);
    }

    public function packageBooted(): void
    {
        $this->app->make(Router::class)->aliasMiddleware('gdpr.adult', EnsureVerifiedAdult::class);

        Blade::if('gdprProfiling', function (): bool {
            $user = auth()->user();

            return $this->app->make(ChildrenManager::class)->allowsProfiling($user instanceof Model ? $user : null);
        });
    }
}
