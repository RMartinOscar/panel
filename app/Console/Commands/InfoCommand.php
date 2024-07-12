<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Services\Helpers\SoftwareVersionService;

class InfoCommand extends Command
{
    protected $signature = 'p:info
        {--secret : Prints every info.}';

    protected $description = 'Displays the application, database, email and backup configurations along with the panel version.';

    /**
     * InfoCommand constructor.
     */
    public function __construct(private SoftwareVersionService $versionService)
    {
        parent::__construct();
    }

    /**
     * Handle execution of command.
     */
    public function handle(): void
    {
        $this->output->title('Version Information');
        $this->table([], [
            ['Panel Version', $this->versionService->versionData()['version']],
            ['Latest Version', $this->versionService->getPanel()],
            ['Up-to-Date', $this->versionService->isLatestPanel() ? 'Yes' : $this->formatText('No', 'bg=red')],
        ], 'compact');

        $this->output->title('Application Configuration');
        $this->table([], [
            ['Environment', config('app.env') === 'production' ? config('app.env') : $this->formatText(config('app.env'), 'bg=red')],
            ['Debug Mode', config('app.debug') ? $this->formatText('Yes', 'bg=red') : 'No'],
            ['Secure', request()->isSecure() ? 'Yes' : $this->formatText('No', 'bg=red')],
            ['Installation Directory', base_path()],
            [' '],
            ['Key', env('APP_KEY') === '' ? $this->formatText('Not generated yet', 'bg=red') : $this->sanitize()],
            ['TimeZone', config('app.timezone')],
            ['Locale', config('app.locale')],
            // ['Theme', config('app.theme')],
            ['Name', $this->sanitize(config('app.name'))],
            ['URL', $this->sanitize(config('app.url'))],
            [' '],
            ['Cache Driver', config('cache.default')],
            ['Queue Driver', config('queue.default') === 'sync' ? $this->formatText(config('queue.default'), 'bg=red') : config('queue.default')],
            ['Session Driver', config('session.driver')],
            ['Filesystem Driver', config('filesystems.default')],

        ], 'compact');

        $this->output->title('Database Configuration');
        $driver = config('database.default');
        $sqlConfig = [
            ['Driver', $driver],
            ['Database', config("database.connections.$driver.database")],
        ];

        if ($driver !== 'sqlite') {
            array_push($sqlConfig,
                ['Host', $this->sanitize(config("database.connections.$driver.host"))],
                ['Port', config("database.connections.$driver.port")],
                ['Username', config("database.connections.$driver.username")],
            );
        }

        $this->table([], $sqlConfig, 'compact');

        $this->output->title('Redis Configuration');

        $this->table([], [
            ['Enabled', env('REDIS_HOST') === '' ? 'No' : 'Yes'],
            ['Host', $this->sanitize(env('REDIS_HOST'))],
            ['Port', env('REDIS_PORT')],
            ['User', env('REDIS_USER')],
        ], 'compact');

        $this->output->title('Email Configuration');
        $driver = config('mail.default');
        $mailConfig = [
            ['Driver', $driver],
            ['From Address', $this->sanitize(config('mail.from.address'))],
            ['From Name', $this->sanitize(config('mail.from.name'))],
            [$this->formatText('Notifications', 'bg=yellow')],
            ['Send Install', config('mail.send_install_notification') ? 'Yes' : 'No'],
            ['Send Reinstall', config('mail.send_reinstall_notification') ? 'Yes' : 'No'],
        ];

        if ($driver === 'smtp') {
            array_splice($mailConfig, 1, 0, [
                ['Driver', $driver],
                ['Host', $this->sanitize(config("mail.mailers.$driver.host"))],
                ['Port', config("mail.mailers.$driver.port")],
                ['Username', $this->sanitize(config("mail.mailers.$driver.username"))],
                ['Encryption', config("mail.mailers.$driver.encryption")],
                ['From Address', $this->sanitize(config('mail.from.address'))],
                ['From Name', $this->sanitize(config('mail.from.name'))],
            ]);
        }

        $this->table([], $mailConfig, 'compact');

        $this->output->title('Backup Configuration');
        $driver = config('backups.default');
        $backupConfig = [
            ['Driver', $driver],
        ];

        if ($driver === 's3') {
            array_push($backupConfig,
                ['Region', config("backups.disks.$driver.region")],
                ['Bucket', config("backups.disks.$driver.bucket")],
                ['Endpoint', $this->sanitize(config("backups.disks.$driver.endpoint"))],
                ['Use path style endpoint', config("backups.disks.$driver.use_path_style_endpoint") ? 'Yes' : 'No'],
                ['Use accelerate endpoint', config("backups.disks.$driver.use_accelerate_endpoint") ? 'Yes' : 'No'],
                ['Storage class', config("backups.disks.$driver.storage_class")],
            );
        }
        $this->table([], $backupConfig, 'compact');

        $this->output->title('Recaptcha Configuration');
        $this->table([], [
            ['Enabled', config('recaptcha.enabled') ? 'Yes' : $this->formatText('No', 'bg=red')],
            ['Domain', config('recaptcha.domain')],
            ['Secret Key', config('recaptcha.secret_key') == config('recaptcha._shipped_secret_key') ? $this->formatText('Default', 'bg=red') : 'Custom'],
            ['Website Key', config('recaptcha.website_key') == config('recaptcha._shipped_website_key') ? $this->formatText('Default', 'bg=red') : 'Custom'],
        ], 'compact');

        $this->output->title('Session Configuration');
        $this->table([], [
            ['Domain', $this->sanitize(env('SESSION_DOMAIN'))],
            ['Path', env('SESSION_PATH')],
            ['Encrypt', env('SESSION_ENCRYPT') ? 'Yes' : $this->formatText('No', 'bg=red')],
            ['Secure', env('SESSION_SECURE_COOKIE') ? 'Yes' : $this->formatText('No', 'bg=red')],
        ], 'compact');

        $this->output->title('Features Configuration');
        $this->table([], [
            [$this->formatText('Databases', 'bg=yellow')],
            ['Enabled', config('client_features.databases.enabled') ? 'Yes' : $this->formatText('No', 'bg=red')],
            ['Allow Random', config('client_features.databases.allow_random') ? 'Yes' : 'No'],
            [$this->formatText('Schedules', 'bg=yellow')],
            ['Per Schedule task limit', config('client_features.schedules.per_schedule_task_limit')],
            [$this->formatText('Auto Allocations', 'bg=yellow')],
            ['Enabled', config('allocations.enabled') ? 'Yes' : $this->formatText('No', 'bg=red')],
            ['Range Start', config('allocations.range_start')],
            ['Range End', config('allocations.range_end')],
            [$this->formatText('Others', 'bg=yellow')],
            ['Top Navigation', config('filament.top-navigation') ? 'Yes' : 'No'],
            ['Use binary prefix', config('use_binary_prefix') ? 'MiB' : 'MB'],
        ], 'compact');

        $this->output->title('Logs Configuration');
        $this->table([], [
            ['Level', env('LOG_LEVEL')],
            ['Channel', env('LOG_CHANNEL')],
            ['Stack', env('LOG_STACK')],
            ['Deprecations Channel', env('LOG_DEPRECATIONS_CHANNEL')],
        ], 'compact');

    }

    /**
     * Format output in a Name: Value manner.
     */
    private function formatText(string $value, string $opts = ''): string
    {
        return sprintf('<%s>%s</>', $opts, $value);
    }

    /**
     * Format output to anonymize informations.
     */
    private function sanitize(?string $value = ''): string
    {
        if (!$this->option('secret')) {
            return $value ?? '';
        }

        if (Str::startsWith($value, 'http')) {
            $value = 'http' . (Str::startsWith($value, 'https://') ? 's' : '') . '://REDACTED';
        } else {
            $value = 'REDACTED';
        }

        return sprintf('%s', $value);
    }
}
