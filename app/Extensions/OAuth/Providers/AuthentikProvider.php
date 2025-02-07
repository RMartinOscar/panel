<?php

namespace App\Extensions\OAuth\Providers;

use Filament\Forms\Components\TextInput;
use SocialiteProviders\Authentik\Provider;

final class AuthentikProvider extends OAuthProvider
{
    public function getId(): string
    {
        return 'authentik';
    }

    public function getProviderClass(): string
    {
        return Provider::class;
    }

    public function getServiceConfig(): array
    {
        return [
            'client_id' => null,
            'client_secret' => env('OAUTH_STEAM_CLIENT_SECRET'),
            'allowed_hosts' => [
                str_replace(['http://', 'https://'], '', env('APP_URL')),
            ],
        ];
    }

    public function getSettingsForm(): array
    {
        return array_merge(parent::getSettingsForm(), [
            TextInput::make('OAUTH_AUTHENTIK_BASE_URL')
                ->label('Base URL')
                ->placeholder('Base URL')
                ->columnSpan(2)
                ->required()
                ->url()
                ->autocomplete(false)
                ->default(env('OAUTH_AUTHENTIK_BASE_URL')),
            TextInput::make('OAUTH_AUTHENTIK_DISPLAY_NAME')
                ->label('Display Name')
                ->placeholder('Display Name')
                ->columnSpan(2)
                ->autocomplete(false)
                ->default(env('OAUTH_AUTHENTIK_DISPLAY_NAME', 'Authentik')),
        ]);
    }

    public function getName(): string
    {
        return env('OAUTH_AUTHENTIK_DISPLAY_NAME') ?? 'Authentik';
    }

    public function getHexColor(): string
    {
        return '#fd4b2d';
    }

    public static function register(): self
    {
        return new self();
    }
}
