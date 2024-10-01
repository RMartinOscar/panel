<?php

namespace App\Providers;

use App\Extensions\Themes\Theme;
use App\Models;
use App\Models\ApiKey;
use App\Models\Node;
use App\Models\User;
use App\Services\Helpers\SoftwareVersionService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $versionData = app(SoftwareVersionService::class)->versionData();
        View::share('appVersion', $versionData['version'] ?? 'undefined');
        View::share('appIsGit', $versionData['is_git'] ?? false);

        Paginator::useBootstrap();

        // If the APP_URL value is set with https:// make sure we force it here. Theoretically
        // this should just work with the proxy logic, but there are a lot of cases where it
        // doesn't, and it triggers a lot of support requests, so lets just head it off here.
        if (Str::startsWith(config('app.url') ?? '', 'https://')) {
            URL::forceScheme('https');
        }

        Relation::enforceMorphMap([
            'allocation' => Models\Allocation::class,
            'api_key' => Models\ApiKey::class,
            'backup' => Models\Backup::class,
            'database' => Models\Database::class,
            'egg' => Models\Egg::class,
            'egg_variable' => Models\EggVariable::class,
            'schedule' => Models\Schedule::class,
            'server' => Models\Server::class,
            'ssh_key' => Models\UserSSHKey::class,
            'task' => Models\Task::class,
            'user' => Models\User::class,
        ]);

        Http::macro(
            'daemon',
            fn (Node $node, array $headers = []) => Http::acceptJson()
                ->asJson()
                ->withToken($node->daemon_token)
                ->withHeaders($headers)
                ->withOptions(['verify' => (bool) app()->environment('production')])
                ->timeout(config('panel.guzzle.timeout'))
                ->connectTimeout(config('panel.guzzle.connect_timeout'))
                ->baseUrl($node->getConnectionAddress())
        );

        $this->bootAuth();
        $this->bootBroadcast();

        $bearerTokens = fn (OpenApi $openApi) => $openApi->secure(SecurityScheme::http('bearer'));
        Gate::define('viewApiDocs', fn () => true);
        $apiConfig = [
            'info' => ['version' => '1.0'],
            'ui' => ['theme' => 'system'],
        ];
        Scramble::registerApi('application', ['api_path' => 'api/application', ...$apiConfig]);
        Scramble::registerApi('client', ['api_path' => 'api/client', ...$apiConfig])->afterOpenApiGenerated($bearerTokens);
        Scramble::registerApi('remote', ['api_path' => 'api/remote', ...$apiConfig])->afterOpenApiGenerated($bearerTokens);

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('discord', \SocialiteProviders\Discord\Provider::class);
        });

        FilamentColor::register([
            'danger' => Color::Red,
            'gray' => Color::Zinc,
            'info' => Color::Sky,
            'primary' => Color::Blue,
            'success' => Color::Green,
            'warning' => Color::Amber,
        ]);

        Gate::before(function (User $user, $ability) {
            return $user->isRootAdmin() ? true : null;
        });
    }

    /**
     * Register application service providers.
     */
    public function register(): void
    {
        $this->app->singleton('extensions.themes', function () {
            return new Theme();
        });

        Scramble::extendOpenApi(fn (OpenApi $openApi) => $openApi->secure(SecurityScheme::http('bearer')));
        Scramble::ignoreDefaultRoutes();
    }

    public function bootAuth(): void
    {
        Sanctum::usePersonalAccessTokenModel(ApiKey::class);
    }

    public function bootBroadcast(): void
    {
        Broadcast::routes();

        /*
         * Authenticate the user's personal channel...
         */
        Broadcast::channel('App.User.*', function ($user, $userId) {
            return (int) $user->id === (int) $userId;
        });
    }
}
