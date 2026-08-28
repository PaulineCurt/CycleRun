<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->guardDestructiveMigrations();
    }

    /**
     * Garde-fou : les commandes qui vident la base (`migrate:fresh` & co.) sont
     * refusées en environnement `local` — c'est la base de dev, sans backup.
     *
     * Pour les rejouer volontairement sur la base de dev :
     *   ALLOW_FRESH=1 php artisan migrate:fresh
     *
     * En `testing` (via phpunit.xml) le garde-fou est inactif : RefreshDatabase
     * appelle `migrate:fresh` sur `cyclerun_testing`, ce qui est le comportement
     * voulu.
     */
    private function guardDestructiveMigrations(): void
    {
        $destructive = ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'];

        Event::listen(function (CommandStarting $event) use ($destructive) {
            if (! in_array($event->command, $destructive, true)) {
                return;
            }

            if (! $this->app->environment('local')) {
                return;
            }

            if (env('ALLOW_FRESH') === '1') {
                return;
            }

            throw new \RuntimeException(
                "`{$event->command}` est bloqué en environnement local (base de dev, aucun backup). ".
                'Cible la base de test, ou relance avec ALLOW_FRESH=1 si tu veux vraiment vider la base de dev.'
            );
        });
    }
}
