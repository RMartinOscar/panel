<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Console\Kernel;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Helper\ProgressBar;

class UpgradeCommand extends Command
{
    protected const DEFAULT_URL = 'https://github.com/pelican-dev/panel/%s';
    protected $isPatch = false;

    protected $signature = 'p:upgrade
        {--user= : The user that PHP runs under. All files will be owned by this user.}
        {--group= : The group that PHP runs under. All files will be owned by this group.}
        {--url= : The specific archive to download.}
        {--release= : A specific version to download from GitHub. Leave blank to use latest.}
        {--skip-download : If set no archive will be downloaded.}
        {--patch= : The patch file to use}';
    // patch can either be commit/d7316c4 or pull/385
    protected $description = 'Downloads a new archive/patch from GitHub and then executes the normal upgrade commands.';

    /**
     * Executes an upgrade command which will run through all of our standard
     * Panel commands and enable users to basically just download
     * the archive/patch and execute this and be done.
     *
     * This places the application in maintenance mode as well while the commands
     * are being executed.
     *
     * @throws \Exception
     */
    public function handle(): void
    {
        $this->isPatch = $this->option('patch');
        $skipDownload = $this->isPatch ? false : $this->option('skip-download');
        if (!$skipDownload) {
            $this->output->warning(__('commands.upgrade.integrity'));
            $this->output->comment(__('commands.upgrade.source_url'), ['type' => $this->isPatch ? 'patch' : 'archive']);
            $this->line($this->getUrl());
        }

        if (version_compare(PHP_VERSION, '7.4.0') < 0) {
            $this->error(__('commands.upgrade.php_version') . ' [' . PHP_VERSION . '].');
        }

        $user = 'www-data';
        $group = 'www-data';
        if ($this->input->isInteractive()) {
            if (!$skipDownload) {
                $skipDownload = !$this->confirm(__('commands.upgrade.skipDownload'), true);
            }

            if (is_null($this->option('user'))) {
                $userDetails = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner('public')) : [];
                $user = $userDetails['name'] ?? 'www-data';

                $message = __('commands.upgrade.webserver_user', ['user' => $user]);
                if (!$this->confirm($message, true)) {
                    $user = $this->anticipate(
                        __('commands.upgrade.name_webserver'),
                        [
                            'www-data',
                            'nginx',
                            'apache',
                        ]
                    );
                }
            }

            if (is_null($this->option('group'))) {
                $groupDetails = function_exists('posix_getgrgid') ? posix_getgrgid(filegroup('public')) : [];
                $group = $groupDetails['name'] ?? 'www-data';

                $message = __('commands.upgrade.group_webserver', ['group' => $user]);
                if (!$this->confirm($message, true)) {
                    $group = $this->anticipate(
                        __('commands.upgrade.group_webserver_question'),
                        [
                            'www-data',
                            'nginx',
                            'apache',
                        ]
                    );
                }
            }

            if (!$this->confirm(__('commands.upgrade.are_your_sure'), ['type' => $this->isPatch ? 'Patch' : 'Upgrade'])) {
                $this->warn(__('commands.upgrade.terminated'), ['type' => $this->isPatch ? 'Patch' : 'Upgrade']);

                return;
            }
        }

        ini_set('output_buffering', '0');
        $bar = $this->output->createProgressBar($skipDownload ? 9 : 10);
        $bar->start();

        $this->withProgress($bar, function () {
            $this->line('$upgrader> php artisan down');
            $this->call('down');
        });

        if (!$skipDownload) {
            $this->withProgress($bar, function () {
                $command = "curl \"{$this->getUrl()}\" | ";
                $command .= $isPatch ? "patch -p1;find -type f -name '*.rej' -exec mv -t patchs {} +" : "tar -xzv";
                $this->line("\$upgrader> $command");
                $process = Process::fromShellCommandline($command);
                $process->run(function ($type, $buffer) {
                    $this->{$type === Process::ERR ? 'error' : 'line'}($buffer);
                });
            });
        }

        $this->withProgress($bar, function () {
            $this->line('$upgrader> chmod -R 755 storage bootstrap/cache');
            $process = new Process(['chmod', '-R', '755', 'storage', 'bootstrap/cache']);
            $process->run(function ($type, $buffer) {
                $this->{$type === Process::ERR ? 'error' : 'line'}($buffer);
            });
        });

        $this->withProgress($bar, function () {
            $this->line('$upgrader> chmod 644 {bootstrap/cache,storage/framework/testing}/.gitignore');
            $process = new Process(['chmod', '644', '{bootstrap/cache,storage/framework/testing}/.gitignore']);
            $process->run(function ($type, $buffer) {
                $this->{$type === Process::ERR ? 'error' : 'line'}($buffer);
            });
        });

        $this->withProgress($bar, function () {
            $command = ['composer', 'install', '--no-ansi'];
            if (config('app.env') === 'production' && !config('app.debug')) {
                $command[] = '--optimize-autoloader';
                $command[] = '--no-dev';
            }

            $this->line('$upgrader> ' . implode(' ', $command));
            $process = new Process($command);
            $process->setTimeout(10 * 60);
            $process->run(function ($type, $buffer) {
                $this->line($buffer);
            });
        });

        /** @var \Illuminate\Foundation\Application $app */
        $app = require __DIR__ . '/../../../bootstrap/app.php';
        /** @var \App\Console\Kernel $kernel */
        $kernel = $app->make(Kernel::class);
        $kernel->bootstrap();
        $this->setLaravel($app);

        $this->withProgress($bar, function () {
            $this->line('$upgrader> php artisan view:clear');
            $this->call('view:clear');
        });

        $this->withProgress($bar, function () {
            $this->line('$upgrader> php artisan config:clear');
            $this->call('config:clear');
        });

        $this->withProgress($bar, function () {
            $this->line('$upgrader> php artisan migrate --force --seed');
            $this->call('migrate', ['--force' => true, '--seed' => true]);
        });

        $this->withProgress($bar, function () use ($user, $group) {
            $this->line("\$upgrader> chown -R {$user}:{$group} *");
            $process = Process::fromShellCommandline("chown -R {$user}:{$group} *", $this->getLaravel()->basePath());
            $process->setTimeout(10 * 60);
            $process->run(function ($type, $buffer) {
                $this->{$type === Process::ERR ? 'error' : 'line'}($buffer);
            });
        });

        $this->withProgress($bar, function () {
            $this->line('$upgrader> php artisan queue:restart');
            $this->call('queue:restart');
        });

        $this->withProgress($bar, function () {
            $this->line('$upgrader> php artisan up');
            $this->call('up');
        });

        $this->newLine(2);
        $this->info(__('commands.upgrade.success'), ['type' => $this->isPatch ? 'patched' : 'upgraded']);
    }

    protected function withProgress(ProgressBar $bar, \Closure $callback)
    {
        $bar->clear();
        $callback();
        $bar->advance();
        $bar->display();
    }

    protected function getUrl(): string
    {
        if ($this->option('url')) {
            return $this->option('url');
        }

        if ($this->isPatch) {
            return sprintf(self::DEFAULT_URL, $this->option('patch'));
        } else {
            return sprintf(self::DEFAULT_URL, sprintf('releases/%s/panel.tar.gz', $this->option('release') ? 'download/v' . $this->option('release') : 'latest/download'));
        }
    }
}
