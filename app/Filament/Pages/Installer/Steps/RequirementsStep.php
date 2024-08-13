<?php

namespace App\Filament\Pages\Installer\Steps;

use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Symfony\Component\Process\Process;

class RequirementsStep
{
    public static function make(): Step
    {
        $correctPhpVersion = version_compare(PHP_VERSION, '8.2.0') >= 0;

        $fields = [
            Section::make('PHP Version')
                ->description('8.2 or newer')
                ->icon($correctPhpVersion ? 'tabler-check' : 'tabler-x')
                ->iconColor($correctPhpVersion ? 'success' : 'danger')
                ->schema([
                    Placeholder::make('')
                        ->content('Your PHP Version ' . ($correctPhpVersion ? 'is' : 'needs to be') .' 8.2 or newer.'),
                ]),
        ];

        $phpExtensions = [
            'BCMath' => extension_loaded('bcmath'),
            'cURL' => extension_loaded('curl'),
            'GD' => extension_loaded('gd'),
            'intl' => extension_loaded('intl'),
            'mbstring' => extension_loaded('mbstring'),
            'MySQL' => extension_loaded('pdo_mysql') ?: (extension_loaded('pdo_sqlite') ? 2 : 0),
            'SQLite3' => extension_loaded('pdo_sqlite') ?: (extension_loaded('pdo_mysql') ? 2 : 0),
            'XML' => extension_loaded('xml'),
            'Zip' => extension_loaded('zip'),
        ];
        $allExtensionsInstalled = !in_array(0, $phpExtensions);

        $fields[] = Section::make('PHP Extensions')
            ->description(implode(', ', array_keys($phpExtensions)))
            ->icon($allExtensionsInstalled ? 'tabler-check' : 'tabler-x')
            ->iconColor(in_array(2, $phpExtensions) ? 'primary' : ($allExtensionsInstalled ? 'success' : 'danger'))
            ->schema([
                Placeholder::make('')
                    ->content('All needed PHP Extensions are installed.')
                    ->helperText(implode(', ', [$phpExtensions['MySQL'] == 1 ? 'Mysql' : '', $phpExtensions['SQLite3'] == 1 ? 'SQLite3' : '']))
                    ->visible($allExtensionsInstalled),
                Placeholder::make('')
                    ->content('The following PHP Extensions are missing: ' . implode(', ', array_keys($phpExtensions, 0)))
                    ->visible(!$allExtensionsInstalled),
            ]);

        $folderPermissions = [
            'Ownership' => function_exists('posix_getpwuid') ? self::checkOwner() : 2,
            'Storage' => substr(sprintf('%o', fileperms(base_path('storage/'))), -4) == 755,
            'Cache' => substr(sprintf('%o', fileperms(base_path('bootstrap/cache/'))), -4) == 755,
        ];
        $correctFolderPermissions = !in_array(0, $folderPermissions);

        $fields[] = Section::make('Folder Permissions')
            ->description(implode(', ', array_keys($folderPermissions)))
            ->icon($correctFolderPermissions ? 'tabler-check' : 'tabler-x')
            ->iconColor($correctFolderPermissions && $folderPermissions['Ownership'] == 2 ? 'primary' : ($correctFolderPermissions ? 'success' : 'danger'))
            ->schema([
                Placeholder::make('')
                    ->content('All Folders have the correct permissions.')
                    ->helperText($folderPermissions['Ownership'] == 2 ? "(Couldn't check ownership)" : null)
                    ->visible($correctFolderPermissions),
                Placeholder::make('')
                    ->key('wrong_chmod')
                    ->content('The following Folders have wrong permissions: ' . implode(', ', array_keys($folderPermissions, 0)))
                    ->visible(!$correctFolderPermissions)
                    ->hintAction(
                        FormAction::make('chmod')
                            ->label('Fix that')
                            ->icon('tabler-refresh')
                            ->hidden($correctFolderPermissions)
                            ->action(function () {
                                $process = new Process(['chmod', '-R', '755', 'storage', 'bootstrap/cache']);
                                $process->run(function ($type, $buffer) {
                                    if ($type === Process::ERR) {
                                        Notification::make()
                                            ->title("Couldn't chmod !")
                                            ->body($buffer)
                                            ->danger()
                                            ->send();
                                    } else {
                                        Notification::make()
                                            ->title($buffer)
                                            ->info()
                                            ->send();
                                    }
                                });
                            })
                    ),
            ]);

        return Step::make('requirements')
            ->label('Server Requirements')
            ->schema($fields)
            ->afterValidation(function () use ($correctPhpVersion, $allExtensionsInstalled, $correctFolderPermissions) {
                if (!$correctPhpVersion || !$allExtensionsInstalled || !$correctFolderPermissions) {
                    Notification::make()
                        ->title('Some requirements are missing!')
                        ->danger()
                        ->send();

                    throw new Halt();
                }
            });
    }

    public static function checkOwner(): bool
    {
        $rightUser = posix_getpwuid(fileowner(base_path('public')));
        $folders = ['public', 'database', 'bootstrap', 'storage'];
        $result = [];
        foreach ($folders as $folder) {
            $result[] = posix_getpwuid(fileowner(base_path($folder)))['name'] == $rightUser['name'];
        }

        return !in_array(0, $result);
    }
}
