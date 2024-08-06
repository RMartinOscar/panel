<?php

namespace App\Filament\Pages;

use App\Models\Backup;
use App\Notifications\MailTested;
use App\Traits\Commands\EnvironmentWriterTrait;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Concerns\InteractsWithHeaderActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification as MailNotification;

/**
 * @property Form $form
 */
class Settings extends Page implements HasForms
{
    use EnvironmentWriterTrait;
    use HasUnsavedDataChangesAlert;
    use InteractsWithForms;
    use InteractsWithHeaderActions;

    protected static ?string $navigationIcon = 'tabler-settings';
    protected static ?string $navigationGroup = 'Advanced';

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Tabs::make('Tabs')
                ->columns()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('general')
                        ->label('General')
                        ->icon('tabler-home')
                        ->schema($this->generalSettings()),
                    Tab::make('recaptcha')
                        ->label('reCAPTCHA')
                        ->icon('tabler-shield')
                        ->schema($this->recaptchaSettings()),
                    Tab::make('mail')
                        ->label('Mail')
                        ->icon('tabler-mail')
                        ->schema($this->mailSettings()),
                    Tab::make('backup')
                        ->label('Backup')
                        ->icon('tabler-box')
                        ->schema($this->backupSettings()),
                    Tab::make('misc')
                        ->label('Misc')
                        ->icon('tabler-tool')
                        ->schema($this->miscSettings()),
                ]),
        ];
    }

    private function generalSettings(): array
    {
        return [
            TextInput::make('APP_NAME')
                ->label('App Name')
                ->required()
                ->default(env('APP_NAME', 'Pelican')),
            TextInput::make('APP_FAVICON')
                ->label('App Favicon')
                ->hintIcon('tabler-question-mark')
                ->hintIconTooltip('Favicons should be placed in the public folder, located in the root panel directory.')
                ->required()
                ->default(env('APP_FAVICON', '/pelican.ico')),
            Toggle::make('APP_DEBUG')
                ->label('Enable Debug Mode?')
                ->inline(false)
                ->onIcon('tabler-check')
                ->offIcon('tabler-x')
                ->onColor('success')
                ->offColor('danger')
                ->formatStateUsing(fn ($state): bool => (bool) $state)
                ->afterStateUpdated(fn ($state, Set $set) => $set('APP_DEBUG', (bool) $state))
                ->default(env('APP_DEBUG', config('app.debug'))),
            ToggleButtons::make('FILAMENT_TOP_NAVIGATION')
                ->label('Navigation')
                ->inline()
                ->options([
                    false => 'Sidebar',
                    true => 'Topbar',
                ])
                ->formatStateUsing(fn ($state): bool => (bool) $state)
                ->afterStateUpdated(fn ($state, Set $set) => $set('FILAMENT_TOP_NAVIGATION', (bool) $state))
                ->default(env('FILAMENT_TOP_NAVIGATION', config('panel.filament.top-navigation'))),
            ToggleButtons::make('PANEL_USE_BINARY_PREFIX')
                ->label('Unit prefix')
                ->inline()
                ->options([
                    false => 'Decimal Prefix (MB/ GB)',
                    true => 'Binary Prefix (MiB/ GiB)',
                ])
                ->formatStateUsing(fn ($state): bool => (bool) $state)
                ->afterStateUpdated(fn ($state, Set $set) => $set('PANEL_USE_BINARY_PREFIX', (bool) $state))
                ->default(env('PANEL_USE_BINARY_PREFIX', config('panel.use_binary_prefix'))),
            ToggleButtons::make('APP_2FA_REQUIRED')
                ->label('2FA Requirement')
                ->inline()
                ->options([
                    0 => 'Not required',
                    1 => 'Required for only Admins',
                    2 => 'Required for all Users',
                ])
                ->formatStateUsing(fn ($state): int => (int) $state)
                ->afterStateUpdated(fn ($state, Set $set) => $set('APP_2FA_REQUIRED', (int) $state))
                ->default(env('APP_2FA_REQUIRED', config('panel.auth.2fa_required'))),
            TagsInput::make('TRUSTED_PROXIES')
                ->label('Trusted Proxies')
                ->separator()
                ->splitKeys(['Tab', ' '])
                ->placeholder('New IP or IP Range')
                ->default(env('TRUSTED_PROXIES', config('trustedproxy.proxies')))
                ->hintActions([
                    FormAction::make('clear')
                        ->label('Clear')
                        ->color('danger')
                        ->icon('tabler-trash')
                        ->requiresConfirmation()
                        ->action(fn (Set $set) => $set('TRUSTED_PROXIES', [])),
                    FormAction::make('cloudflare')
                        ->label('Set to Cloudflare IPs')
                        ->icon('tabler-brand-cloudflare')
                        ->action(fn (Set $set) => $set('TRUSTED_PROXIES', [
                            '173.245.48.0/20',
                            '103.21.244.0/22',
                            '103.22.200.0/22',
                            '103.31.4.0/22',
                            '141.101.64.0/18',
                            '108.162.192.0/18',
                            '190.93.240.0/20',
                            '188.114.96.0/20',
                            '197.234.240.0/22',
                            '198.41.128.0/17',
                            '162.158.0.0/15',
                            '104.16.0.0/13',
                            '104.24.0.0/14',
                            '172.64.0.0/13',
                            '131.0.72.0/22',
                        ])),
                ]),
        ];
    }

    private function recaptchaSettings(): array
    {
        return [
            Toggle::make('RECAPTCHA_ENABLED')
                ->label('Enable reCAPTCHA?')
                ->inline(false)
                ->onIcon('tabler-check')
                ->offIcon('tabler-x')
                ->onColor('success')
                ->offColor('danger')
                ->live()
                ->formatStateUsing(fn ($state): bool => (bool) $state)
                ->afterStateUpdated(fn ($state, Set $set) => $set('RECAPTCHA_ENABLED', (bool) $state))
                ->default(env('RECAPTCHA_ENABLED', config('recaptcha.enabled'))),
            TextInput::make('RECAPTCHA_DOMAIN')
                ->label('Domain')
                ->required()
                ->visible(fn (Get $get) => $get('RECAPTCHA_ENABLED'))
                ->default(env('RECAPTCHA_DOMAIN', config('recaptcha.domain'))),
            TextInput::make('RECAPTCHA_WEBSITE_KEY')
                ->label('Website Key')
                ->required()
                ->visible(fn (Get $get) => $get('RECAPTCHA_ENABLED'))
                ->default(env('RECAPTCHA_WEBSITE_KEY', config('recaptcha.website_key'))),
            TextInput::make('RECAPTCHA_SECRET_KEY')
                ->label('Secret Key')
                ->required()
                ->visible(fn (Get $get) => $get('RECAPTCHA_ENABLED'))
                ->default(env('RECAPTCHA_SECRET_KEY', config('recaptcha.secret_key'))),
        ];
    }

    private function mailSettings(): array
    {
        return [
            ToggleButtons::make('MAIL_MAILER')
                ->label('Mail Driver')
                ->columnSpanFull()
                ->inline()
                ->options([
                    'log' => 'Print mails to Log',
                    'smtp' => 'SMTP Server',
                    'sendmail' => 'sendmail Binary',
                    'mailgun' => 'Mailgun',
                    'mandrill' => 'Mandrill',
                    'postmark' => 'Postmark',
                ])
                ->live()
                ->default(env('MAIL_MAILER', config('mail.default')))
                ->hintAction(
                    FormAction::make('test')
                        ->label('Send Test Mail')
                        ->icon('tabler-send')
                        ->hidden(fn (Get $get) => $get('MAIL_MAILER') === 'log')
                        ->action(function () {
                            try {
                                MailNotification::route('mail', auth()->user()->email)
                                    ->notify(new MailTested(auth()->user()));

                                Notification::make()
                                    ->title('Test Mail sent')
                                    ->success()
                                    ->send();
                            } catch (Exception $exception) {
                                Notification::make()
                                    ->title('Test Mail failed')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                ),
            Section::make('"From" Settings')
                ->description('Set the Address and Name used as "From" in mails.')
                ->columns()
                ->schema([
                    TextInput::make('MAIL_FROM_ADDRESS')
                        ->label('From Address')
                        ->required()
                        ->email()
                        ->default(env('MAIL_FROM_ADDRESS', config('mail.from.address'))),
                    TextInput::make('MAIL_FROM_NAME')
                        ->label('From Name')
                        ->required()
                        ->default(env('MAIL_FROM_NAME', config('mail.from.name'))),
                ]),
            Section::make('SMTP Configuration')
                ->columns()
                ->visible(fn (Get $get) => $get('MAIL_MAILER') === 'smtp')
                ->schema([
                    TextInput::make('MAIL_HOST')
                        ->label('SMTP Host')
                        ->required()
                        ->default(env('MAIL_HOST', config('mail.mailers.smtp.host'))),
                    TextInput::make('MAIL_PORT')
                        ->label('SMTP Port')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(65535)
                        ->default(env('MAIL_PORT', config('mail.mailers.smtp.port'))),
                    TextInput::make('MAIL_USERNAME')
                        ->label('SMTP Username')
                        ->required()
                        ->default(env('MAIL_USERNAME', config('mail.mailers.smtp.username'))),
                    TextInput::make('MAIL_PASSWORD')
                        ->label('SMTP Password')
                        ->password()
                        ->revealable()
                        ->default(env('MAIL_PASSWORD')),
                    ToggleButtons::make('MAIL_ENCRYPTION')
                        ->label('SMTP encryption')
                        ->required()
                        ->inline()
                        ->options(['tls' => 'TLS', 'ssl' => 'SSL', '' => 'None'])
                        ->default(env('MAIL_ENCRYPTION', config('mail.mailers.smtp.encryption', 'tls'))),
                ]),
            Section::make('Mailgun Configuration')
                ->columns()
                ->visible(fn (Get $get) => $get('MAIL_MAILER') === 'mailgun')
                ->schema([
                    TextInput::make('MAILGUN_DOMAIN')
                        ->label('Mailgun Domain')
                        ->required()
                        ->default(env('MAILGUN_DOMAIN', config('services.mailgun.domain'))),
                    TextInput::make('MAILGUN_SECRET')
                        ->label('Mailgun Secret')
                        ->required()
                        ->default(env('MAIL_USERNAME', config('services.mailgun.secret'))),
                    TextInput::make('MAILGUN_ENDPOINT')
                        ->label('Mailgun Endpoint')
                        ->required()
                        ->default(env('MAILGUN_ENDPOINT', config('services.mailgun.endpoint'))),
                ]),
        ];
    }

    private function backupSettings(): array
    {
        return [
            ToggleButtons::make('APP_BACKUP_DRIVER')
                ->label('Backup Driver')
                ->columnSpanFull()
                ->inline()
                ->options([
                    Backup::ADAPTER_DAEMON => 'Wings',
                    Backup::ADAPTER_AWS_S3 => 'S3',
                ])
                ->live()
                ->default(env('APP_BACKUP_DRIVER', config('backups.default'))),
            Section::make('Throttles')
                ->description('Configure how many backups can be created in a period. Set period to 0 to disable this throttle.')
                ->columns()
                ->schema([
                    TextInput::make('BACKUP_THROTTLE_LIMIT')
                        ->label('Limit')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->default(config('backups.throttles.limit')),
                    TextInput::make('BACKUP_THROTTLE_PERIOD')
                        ->label('Period')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->suffix('Seconds')
                        ->default(config('backups.throttles.period')),
                ]),
            Section::make('S3 Configuration')
                ->columns()
                ->visible(fn (Get $get) => $get('APP_BACKUP_DRIVER') === Backup::ADAPTER_AWS_S3)
                ->schema([
                    TextInput::make('AWS_DEFAULT_REGION')
                        ->label('Default Region')
                        ->required()
                        ->default(config('backups.disks.s3.region')),
                    TextInput::make('AWS_ACCESS_KEY_ID')
                        ->label('Access Key ID')
                        ->required()
                        ->default(config('backups.disks.s3.key')),
                    TextInput::make('AWS_SECRET_ACCESS_KEY')
                        ->label('Secret Access Key')
                        ->required()
                        ->default(config('backups.disks.s3.secret')),
                    TextInput::make('AWS_BACKUPS_BUCKET')
                        ->label('Bucket')
                        ->required()
                        ->default(config('backups.disks.s3.bucket')),
                    TextInput::make('AWS_ENDPOINT')
                        ->label('Endpoint')
                        ->required()
                        ->default(config('backups.disks.s3.endpoint')),
                    Toggle::make('AWS_USE_PATH_STYLE_ENDPOINT')
                        ->label('Use path style endpoint?')
                        ->inline(false)
                        ->onIcon('tabler-check')
                        ->offIcon('tabler-x')
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->formatStateUsing(fn ($state): bool => (bool) $state)
                        ->afterStateUpdated(fn ($state, Set $set) => $set('AWS_USE_PATH_STYLE_ENDPOINT', (bool) $state))
                        ->default(env('AWS_USE_PATH_STYLE_ENDPOINT', config('backups.disks.s3.use_path_style_endpoint'))),
                ]),
        ];
    }

    private function miscSettings(): array
    {
        return [
            Section::make('Automatic Allocation Creation')
                ->description('Toggle if Users can create allocations via the client area.')
                ->columns()
                ->collapsible()
                ->collapsed()
                ->schema([
                    Toggle::make('PANEL_CLIENT_ALLOCATIONS_ENABLED')
                        ->label('Allow Users to create allocations?')
                        ->onIcon('tabler-check')
                        ->offIcon('tabler-x')
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->columnSpanFull()
                        ->formatStateUsing(fn ($state): bool => (bool) $state)
                        ->afterStateUpdated(fn ($state, Set $set) => $set('PANEL_CLIENT_ALLOCATIONS_ENABLED', (bool) $state))
                        ->default(env('PANEL_CLIENT_ALLOCATIONS_ENABLED', config('panel.client_features.allocations.enabled'))),
                    TextInput::make('PANEL_CLIENT_ALLOCATIONS_RANGE_START')
                        ->label('Starting Port')
                        ->required()
                        ->numeric()
                        ->minValue(1024)
                        ->maxValue(65535)
                        ->visible(fn (Get $get) => $get('PANEL_CLIENT_ALLOCATIONS_ENABLED'))
                        ->default(env('PANEL_CLIENT_ALLOCATIONS_RANGE_START')),
                    TextInput::make('PANEL_CLIENT_ALLOCATIONS_RANGE_END')
                        ->label('Ending Port')
                        ->required()
                        ->numeric()
                        ->minValue(1024)
                        ->maxValue(65535)
                        ->visible(fn (Get $get) => $get('PANEL_CLIENT_ALLOCATIONS_ENABLED'))
                        ->default(env('PANEL_CLIENT_ALLOCATIONS_RANGE_END')),
                ]),
            Section::make('Mail Notifications')
                ->description('Toggle which mail notifications should be sent to Users.')
                ->columns()
                ->collapsible()
                ->collapsed()
                ->schema([
                    Toggle::make('PANEL_SEND_INSTALL_NOTIFICATION')
                        ->label('Server Installed')
                        ->onIcon('tabler-check')
                        ->offIcon('tabler-x')
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->columnSpanFull()
                        ->formatStateUsing(fn ($state): bool => (bool) $state)
                        ->afterStateUpdated(fn ($state, Set $set) => $set('PANEL_SEND_INSTALL_NOTIFICATION', (bool) $state))
                        ->default(env('PANEL_SEND_INSTALL_NOTIFICATION', config('panel.email.send_install_notification'))),
                    Toggle::make('PANEL_SEND_REINSTALL_NOTIFICATION')
                        ->label('Server Reinstalled')
                        ->onIcon('tabler-check')
                        ->offIcon('tabler-x')
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->columnSpanFull()
                        ->formatStateUsing(fn ($state): bool => (bool) $state)
                        ->afterStateUpdated(fn ($state, Set $set) => $set('PANEL_SEND_REINSTALL_NOTIFICATION', (bool) $state))
                        ->default(env('PANEL_SEND_REINSTALL_NOTIFICATION', config('panel.email.send_reinstall_notification'))),
                ]),
            Section::make('Connections')
                ->description('Timeouts used when making requests.')
                ->columns()
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('GUZZLE_TIMEOUT')
                        ->label('Request Timeout')
                        ->required()
                        ->numeric()
                        ->minValue(15)
                        ->maxValue(60)
                        ->suffix('Seconds')
                        ->default(env('GUZZLE_TIMEOUT', config('panel.guzzle.timeout'))),
                    TextInput::make('GUZZLE_CONNECT_TIMEOUT')
                        ->label('Connect Timeout')
                        ->required()
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(60)
                        ->suffix('Seconds')
                        ->default(env('GUZZLE_CONNECT_TIMEOUT', config('panel.guzzle.connect_timeout'))),
                ]),
            Section::make('Activity Logs')
                ->description('Configure how often old activity logs should be pruned and whether admin activities should be logged.')
                ->columns()
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('APP_ACTIVITY_PRUNE_DAYS')
                        ->label('Prune age')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(365)
                        ->suffix('Days')
                        ->default(env('APP_ACTIVITY_PRUNE_DAYS', config('activity.prune_days'))),
                    Toggle::make('APP_ACTIVITY_HIDE_ADMIN')
                        ->label('Hide admin activities?')
                        ->inline(false)
                        ->onIcon('tabler-check')
                        ->offIcon('tabler-x')
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->formatStateUsing(fn ($state): bool => (bool) $state)
                        ->afterStateUpdated(fn ($state, Set $set) => $set('APP_ACTIVITY_HIDE_ADMIN', (bool) $state))
                        ->default(env('APP_ACTIVITY_HIDE_ADMIN', config('activity.hide_admin_activity'))),
                ]),
            Section::make('API')
                ->description('Defines the rate limit for the number of requests per minute that can be executed.')
                ->columns()
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('APP_API_CLIENT_RATELIMIT')
                        ->label('Client API Rate Limit')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->suffix('Requests Per Minute')
                        ->default(env('APP_API_CLIENT_RATELIMIT', config('http.rate_limit.client'))),
                    TextInput::make('APP_API_APPLICATION_RATELIMIT')
                        ->label('Application API Rate Limit')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->suffix('Requests Per Minute')
                        ->default(env('APP_API_APPLICATION_RATELIMIT', config('http.rate_limit.application'))),
                ]),
        ];
    }

    protected function getFormStatePath(): ?string
    {
        return 'data';
    }

    protected function hasUnsavedDataChangesAlert(): bool
    {
        return true;
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            // Convert bools to a string, so they are correctly written to the .env file
            $data = array_map(fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : $value, $data);

            $this->writeToEnvironment($data);

            Artisan::call('config:clear');
            Artisan::call('queue:restart');

            $this->rememberData();

            $this->redirect($this->getUrl());

            Notification::make()
                ->title('Settings saved')
                ->success()
                ->send();
        } catch (Exception $exception) {
            Notification::make()
                ->title('Save failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->action('save')
                ->keyBindings(['mod+s']),
        ];

    }
    protected function getFormActions(): array
    {
        return [];
    }
}
