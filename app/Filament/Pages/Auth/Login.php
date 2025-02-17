<?php

namespace App\Filament\Pages\Auth;

use App\Extensions\OAuth\Providers\OAuthProvider;
use Coderflex\FilamentTurnstile\Forms\Components\Turnstile;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Support\Colors\Color;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getLoginFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getRememberFormComponent(),
                        $this->getOAuthFormComponent(),
                        Turnstile::make('captcha')
                            ->hidden(!config('turnstile.turnstile_enabled'))
                            ->validationMessages([
                                'required' => config('turnstile.error_messages.turnstile_check_message'),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function throwFailureValidationException(): never
    {
        $this->dispatch('reset-captcha');

        throw ValidationException::withMessages([
            'data.login' => trans('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }

    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('Login')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getOAuthFormComponent(): Component
    {
        $actions = [];

        $oauthProviders = collect(OAuthProvider::get())->filter(fn (OAuthProvider $provider) => $provider->isEnabled())->all();

        foreach ($oauthProviders as $oauthProvider) {

            $id = $oauthProvider->getId();

            $actions[] = Action::make("oauth_$id")
                ->label($oauthProvider->getName())
                ->icon($oauthProvider->getIcon())
                ->color(Color::hex($oauthProvider->getHexColor()))
                ->url(route('auth.oauth.redirect', ['driver' => $id], false));
        }

        return Actions::make($actions);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $loginType = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $loginType => mb_strtolower($data['login']),
            'password' => $data['password'],
        ];
    }
}
