<?php

namespace App\Filament\Resources\EggResource\Pages;

use App\Filament\Resources\EggResource;
use App\Models\Egg;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use AbdelhamidErrahmouni\FilamentMonacoEditor\MonacoEditor;
use App\Services\Eggs\Sharing\EggExporterService;
use Filament\Forms;
use Filament\Forms\Form;

class EditEgg extends EditRecord
{
    protected static string $resource = EggResource::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make()->tabs([
                    Forms\Components\Tabs\Tab::make('Configuration')
                        ->columns(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 4])
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 1])
                                ->helperText('A simple, human-readable name to use as an identifier for this Egg.'),
                            Forms\Components\TextInput::make('uuid')
                                ->label('Egg UUID')
                                ->disabled()
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 1, 'lg' => 2])
                                ->helperText('This is the globally unique identifier for this Egg which Wings uses as an identifier.'),
                            Forms\Components\TextInput::make('id')
                                ->label('Egg ID')
                                ->disabled(),
                            Forms\Components\Textarea::make('description')
                                ->rows(3)
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 2])
                                ->helperText('A description of this Egg that will be displayed throughout the Panel as needed.'),
                            Forms\Components\TextInput::make('author')
                                ->required()
                                ->maxLength(255)
                                ->email()
                                ->disabled()
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 2])
                                ->helperText('The author of this version of the Egg. Uploading a new Egg configuration from a different author will change this.'),
                            Forms\Components\Textarea::make('startup')
                                ->rows(2)
                                ->columnSpanFull()
                                ->required()
                                ->helperText('The default startup command that should be used for new servers using this Egg.'),
                            Forms\Components\TagsInput::make('file_denylist')
                                ->hidden() // latest wings breaks it.
                                ->placeholder('denied-file.txt')
                                ->helperText('A list of files that the end user is not allowed to edit.')
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 2]),
                            Forms\Components\TagsInput::make('features')
                                ->placeholder('Add Feature')
                                ->helperText('')
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 2]),
                            Forms\Components\Toggle::make('force_outgoing_ip')
                                ->hintIcon('tabler-question-mark')
                                ->hintIconTooltip("Forces all outgoing network traffic to have its Source IP NATed to the IP of the server's primary allocation IP.
                                    Required for certain games to work properly when the Node has multiple public IP addresses.
                                    Enabling this option will disable internal networking for any servers using this egg, causing them to be unable to internally access other servers on the same node."),
                            Forms\Components\Hidden::make('script_is_privileged')
                                ->helperText('The docker images available to servers using this egg.'),
                            Forms\Components\TagsInput::make('tags')
                                ->placeholder('Add Tags')
                                ->helperText('')
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 2]),
                            Forms\Components\TextInput::make('update_url')
                                ->disabled()
                                ->helperText('Not implemented.')
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 2]),
                            Forms\Components\KeyValue::make('docker_images')
                                ->live()
                                ->columnSpanFull()
                                ->required()
                                ->addActionLabel('Add Image')
                                ->keyLabel('Name')
                                ->valueLabel('Image URI')
                                ->helperText('The docker images available to servers using this egg.'),
                        ]),

                    Forms\Components\Tabs\Tab::make('Process Management')
                        ->columns()
                        ->schema([
                            Forms\Components\Select::make('config_from')
                                ->label('Copy Settings From')
                                ->placeholder('None')
                                ->relationship('configFrom', 'name', ignoreRecord: true)
                                ->helperText('If you would like to default to settings from another Egg select it from the menu above.'),
                            Forms\Components\TextInput::make('config_stop')
                                ->maxLength(255)
                                ->label('Stop Command')
                                ->helperText('The command that should be sent to server processes to stop them gracefully. If you need to send a SIGINT you should enter ^C here.'),
                            Forms\Components\Textarea::make('config_startup')->rows(10)->json()
                                ->label('Start Configuration')
                                ->helperText('List of values the daemon should be looking for when booting a server to determine completion.'),
                            Forms\Components\Textarea::make('config_files')->rows(10)->json()
                                ->label('Configuration Files')
                                ->helperText('This should be a JSON representation of configuration files to modify and what parts should be changed.'),
                            Forms\Components\Textarea::make('config_logs')->rows(10)->json()
                                ->label('Log Configuration')
                                ->helperText('This should be a JSON representation of where log files are stored, and whether or not the daemon should be creating custom logs.'),
                        ]),
                    Forms\Components\Tabs\Tab::make('Egg Variables')
                        ->columnSpanFull()
                        ->schema([
                            Forms\Components\Repeater::make('variables')
                                ->label('')
                                ->grid()
                                ->relationship('variables')
                                ->name('name')
                                ->reorderable()
                                ->collapsible()->collapsed()
                                ->orderColumn()
                                ->addActionLabel('New Variable')
                                ->itemLabel(fn (array $state) => $state['name'])
                                ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                    $data['default_value'] ??= '';
                                    $data['description'] ??= '';
                                    $data['rules'] ??= '';
                                    $data['user_viewable'] ??= '';
                                    $data['user_editable'] ??= '';

                                    return $data;
                                })
                                ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                    $data['default_value'] ??= '';
                                    $data['description'] ??= '';
                                    $data['rules'] ??= '';
                                    $data['user_viewable'] ??= '';
                                    $data['user_editable'] ??= '';

                                    return $data;
                                })
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->live()
                                        ->debounce(750)
                                        ->maxLength(255)
                                        ->columnSpanFull()
                                        ->afterStateUpdated(fn (Forms\Set $set, $state) => $set('env_variable', str($state)->trim()->snake()->upper()->toString())
                                        )
                                        ->required(),
                                    Forms\Components\Textarea::make('description')->columnSpanFull(),
                                    Forms\Components\TextInput::make('env_variable')
                                        ->label('Environment Variable')
                                        ->maxLength(255)
                                        ->prefix('{{')
                                        ->suffix('}}')
                                        ->hintIcon('tabler-code')
                                        ->hintIconTooltip(fn ($state) => "{{{$state}}}")
                                        ->required(),
                                    Forms\Components\TextInput::make('default_value')->maxLength(255),
                                    Forms\Components\Fieldset::make('User Permissions')
                                        ->schema([
                                            Forms\Components\Checkbox::make('user_viewable')->label('Viewable'),
                                            Forms\Components\Checkbox::make('user_editable')->label('Editable'),
                                        ]),
                                    Forms\Components\TextInput::make('rules')->columnSpanFull(),
                                ]),
                        ]),
                    Forms\Components\Tabs\Tab::make('Install Script')
                        ->columns(3)
                        ->schema([

                            Forms\Components\Select::make('copy_script_from')
                                ->placeholder('None')
                                ->relationship('scriptFrom', 'name', ignoreRecord: true),

                            Forms\Components\TextInput::make('script_container')
                                ->required()
                                ->maxLength(255)
                                ->default('alpine:3.4'),

                            Forms\Components\TextInput::make('script_entry')
                                ->required()
                                ->maxLength(255)
                                ->default('ash'),

                            MonacoEditor::make('script_install')
                                ->label('Install Script')
                                ->columnSpanFull()
                                ->fontSize('16px')
                                ->language('shell')
                                ->view('filament.plugins.monaco-editor'),
                        ]),

                ])->columnSpanFull()->persistTabInQueryString(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->disabled(fn (Egg $egg): bool => $egg->servers()->count() > 0)
                ->label(fn (Egg $egg): string => $egg->servers()->count() <= 0 ? 'Delete Egg' : 'Egg In Use'),
            Actions\Action::make('export')
                ->icon('tabler-download')
                ->label('Export Egg')
                ->color('primary')
                ->action(fn (EggExporterService $service, Egg $egg) => response()->streamDownload(function () use ($service, $egg) {
                    echo $service->handle($egg->id);
                }, 'egg-' . $egg->getKebabName() . '.json')),
            $this->getSaveFormAction()->formId('form'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getRelationManagers(): array
    {
        return [
            EggResource\RelationManagers\ServersRelationManager::class,
        ];
    }
}
