<?php

namespace App\Console\Commands\User;

use App\Models\User;
use Webmozart\Assert\Assert;
use Illuminate\Console\Command;

class DisableTwoFactorCommand extends Command
{
    protected $description = 'Disable two-factor authentication for a specific user in the Panel.';

    protected $signature = 'p:user:disable2fa {--user=}';

    /**
     * Handle command execution process.
     *
     * @throws \App\Exceptions\Model\DataValidationException
     */
    public function handle(): int
    {
        if ($this->input->isInteractive()) {
            $this->output->warning(trans('command/messages.user.2fa_help_text'));
        }

        $search = $this->option('user') ?? $this->ask(trans('command/messages.user.search_users'));
        Assert::notEmpty($search, 'Search term should be an email address, got: %s.');
        $results = User::query()
            ->where('id', 'LIKE', "$search")
            ->orWhere('username', 'LIKE', "$search%")
            ->orWhere('email', 'LIKE', "$search%")
            ->get();

        if (count($results) < 1) {
            $this->error(trans('command/messages.user.no_users_found'));
            if ($this->input->isInteractive()) {
                return $this->handle();
            }
            return 1;
        }

        if ($this->input->isInteractive()) {
            $tableValues = [];
            foreach ($results as $user) {
                $tableValues[] = [$user->id, $user->email, $user->name, $user->use_totp ? 'enabled' : 'disabled'];
            }

            $this->table(['User ID', 'Email', 'Name', '2fa'], $tableValues);
            if (!$disable2faUser = $this->ask(trans('command/messages.user.select_search_user'))) {
                return $this->handle();
            }
        } else {
            if (count($results) > 1) {
                $this->error(trans('command/messages.user.multiple_found'));

                return 1;
            }

            $disable2faUser = $results->first();
        }

        if ($this->confirm(trans('command/messages.user.2fa_confirm_disable')) || !$this->input->isInteractive()) {
            $user = User::query()->findOrFail($disable2faUser->id);
            $user->use_totp = false;
            $user->totp_secret = null;
            $user->save();

            $this->info(trans('command/messages.user.2fa_disabled'));
            if ($this->input->isInteractive()) {
                $this->table(['User ID', 'Email', 'Name', '2fa'], [[$user->id, $user->email, $user->name, 'disabled']]);
            }
        }

        return 0;
    }
}
