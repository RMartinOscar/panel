<?php

namespace App\Features;

use App\Models\Permission;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Illuminate\Support\HtmlString;

class JavaVersion extends Feature
{
    public static function listeners(): array
    {
        return [
            'minecraft 1.17 requires running the server with java 16 or above',
            'minecraft 1.18 requires running the server with java 17 or above',
            'java.lang.unsupportedclassversionerror',
            'unsupported major.minor version',
            'has been compiled by a more recent version of the java runtime',
        ];
    }

    public static function featureName(): string
    {
        return 'java_version';
    }

    public static function action(): Action
    {
        return Action::make(self::featureName())
            ->form(fn (Server $server) => [
                Placeholder::make(self::featureName())
                    ->label(new HtmlString('This server is currently running an unsupported version of Java and cannot be started.\nPlease select a supported version from the list below to continue starting the server.')),
                Select::make('docker_image')
                    ->label('Java Version')
                    ->options($server->egg->docker_images)
                    ->required(),
            ])
            ->action(function ($data, Server $server) {
                $server->update(['docker_image' => $data['docker_image']]);
            })
            ->authorize(fn (Server $server) => auth()->user()->can(Permission::ACTION_STARTUP_DOCKER_IMAGE, $server));
    }
}
