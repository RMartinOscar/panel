<?php

namespace App\Features;

use App\Repositories\Daemon\DaemonFileRepository;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;

class MinecraftEula extends Feature
{
    public static function listeners(): array
    {
        return [
            'you need to agree to the eula in order to run the server',
        ];
    }

    public static function featureName(): string
    {
        return 'eula';
    }

    public static function action(): Action
    {
        return Action::make(self::featureName())
            ->form([
                Placeholder::make(self::featureName())
                    ->label('By pressing I Accept below you are indicating your agreement to the Minecraft® EULA.'),
            ])
            ->action(function (DaemonFileRepository $fileRepository) {
                try {
                    $fileRepository->putContent('eula.txt', 'eula=true');
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Error')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            }
            );
    }
}
