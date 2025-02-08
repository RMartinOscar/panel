<?php

namespace App\Filament\Admin\Resources\EggResource\Pages;

use AbdelhamidErrahmouni\FilamentMonacoEditor\MonacoEditor;
use App\Filament\Admin\Resources\EggResource;
use App\Models\EggVariable;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class CreateEgg extends CreateRecord
{
    protected static string $resource = EggResource::class;

    protected static bool $canCreateAnother = false;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()->formId('form'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make()->tabs([
                    Tab::make('Configuration')
                        ->columns(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 4])
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 2])
                                ->helperText('A simple, human-readable name to use as an identifier for this Egg.'),
                            TextInput::make('author')
                                ->maxLength(255)
                                ->required()
                                ->email()
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 2])
                                ->helperText('The author of this version of the Egg.'),
                            Textarea::make('description')
                                ->rows(3)
                                ->columnSpanFull()
                                ->helperText('A description of this Egg that will be displayed throughout the Panel as needed.'),
                            Textarea::make('startup')
                                ->rows(3)
                                ->columnSpanFull()
                                ->required()
                                ->placeholder(implode("\n", [
                                    'java -Xms128M -XX:MaxRAMPercentage=95.0 -jar {{SERVER_JARFILE}}',
                                ]))
                                ->helperText('The default startup command that should be used for new servers using this Egg.'),
                            TagsInput::make('file_denylist')
                                ->placeholder('denied-file.txt')
                                ->helperText('A list of files that the end user is not allowed to edit.')
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 2]),
                            TagsInput::make('features')
                                ->placeholder('Add Feature')
                                ->helperText('')
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 2]),
                            Toggle::make('force_outgoing_ip')
                                ->hintIcon('tabler-question-mark')
                                ->hintIconTooltip("Forces all outgoing network traffic to have its Source IP NATed to the IP of the server's primary allocation IP.
                                    Required for certain games to work properly when the Node has multiple public IP addresses.
                                    Enabling this option will disable internal networking for any servers using this egg, causing them to be unable to internally access other servers on the same node."),
                            Hidden::make('script_is_privileged')
                                ->default(1),
                            TagsInput::make('tags')
                                ->placeholder('Add Tags')
                                ->helperText('')
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 2]),
                            TextInput::make('update_url')
                                ->hintIcon('tabler-question-mark')
                                ->hintIconTooltip('URLs must point directly to the raw .json file.')
                                ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 2])
                                ->url(),
                            KeyValue::make('docker_images')
                                ->live()
                                ->columnSpanFull()
                                ->required()
                                ->addActionLabel('Add Image')
                                ->keyLabel('Name')
                                ->keyPlaceholder('Java 21')
                                ->valueLabel('Image URI')
                                ->valuePlaceholder('ghcr.io/parkervcp/yolks:java_21')
                                ->helperText('The docker images available to servers using this egg.'),
                        ]),

                    Tab::make('Process Management')
                        ->columns()
                        ->schema([
                            Hidden::make('config_from')
                                ->default(null)
                                ->label('Copy Settings From')
                                // ->placeholder('None')
                                // ->relationship('configFrom', 'name', ignoreRecord: true)
                                ->helperText('If you would like to default to settings from another Egg select it from the menu above.'),
                            TextInput::make('config_stop')
                                ->required()
                                ->maxLength(255)
                                ->label('Stop Command')
                                ->helperText('The command that should be sent to server processes to stop them gracefully. If you need to send a SIGINT you should enter ^C here.'),
                            Textarea::make('config_startup')->rows(10)->json()
                                ->label('Start Configuration')
                                ->default('{}')
                                ->helperText('List of values the daemon should be looking for when booting a server to determine completion.'),
                            Textarea::make('config_files')->rows(10)->json()
                                ->label('Configuration Files')
                                ->default('{}')
                                ->helperText('This should be a JSON representation of configuration files to modify and what parts should be changed.'),
                            Textarea::make('config_logs')->rows(10)->json()
                                ->label('Log Configuration')
                                ->default('{}')
                                ->helperText('This should be a JSON representation of where log files are stored, and whether or not the daemon should be creating custom logs.'),
                        ]),
                    Tab::make('Egg Variables')
                        ->columnSpanFull()
                        ->schema([
                            Repeater::make('variables')
                                ->label('')
                                ->addActionLabel('Add New Egg Variable')
                                ->grid()
                                ->relationship('variables')
                                ->name('name')
                                ->reorderable()->orderColumn()
                                ->collapsible()->collapsed()
                                ->columnSpan(2)
                                ->defaultItems(0)
                                ->itemLabel(fn (array $state) => $state['name'])
                                ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                    $data['default_value'] ??= '';
                                    $data['description'] ??= '';
                                    $data['rules'] ??= [];
                                    $data['user_viewable'] ??= '';
                                    $data['user_editable'] ??= '';

                                    return $data;
                                })
                                ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                    $data['default_value'] ??= '';
                                    $data['description'] ??= '';
                                    $data['rules'] ??= [];
                                    $data['user_viewable'] ??= '';
                                    $data['user_editable'] ??= '';

                                    return $data;
                                })
                                ->schema([
                                    TextInput::make('name')
                                        ->live()
                                        ->debounce(750)
                                        ->maxLength(255)
                                        ->columnSpanFull()
                                        ->afterStateUpdated(fn (Set $set, $state) => $set('env_variable', str($state)->trim()->snake()->upper()->toString()))
                                        ->unique(modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('egg_id', $get('../../id')), ignoreRecord: true)
                                        ->validationMessages([
                                            'unique' => 'A variable with this name already exists.',
                                        ])
                                        ->required(),
                                    Textarea::make('description')->columnSpanFull(),
                                    TextInput::make('env_variable')
                                        ->label('Environment Variable')
                                        ->maxLength(255)
                                        ->prefix('{{')
                                        ->suffix('}}')
                                        ->hintIcon('tabler-code')
                                        ->hintIconTooltip(fn ($state) => "{{{$state}}}")
                                        ->unique(modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('egg_id', $get('../../id')), ignoreRecord: true)
                                        ->rules(EggVariable::$validationRules['env_variable'])
                                        ->validationMessages([
                                            'unique' => 'A variable with this name already exists.',
                                            'required' => ' The environment variable field is required.',
                                            '*' => 'This environment variable is reserved and cannot be used.',
                                        ])
                                        ->required(),
                                    TextInput::make('default_value')->maxLength(255),
                                    Fieldset::make('User Permissions')
                                        ->schema([
                                            Checkbox::make('user_viewable')->label('Viewable'),
                                            Checkbox::make('user_editable')->label('Editable'),
                                        ]),
                                    TagsInput::make('rules')
                                        ->columnSpanFull()
                                        ->placeholder('Add Rule')
                                        ->reorderable()
                                        ->suggestions([
                                            'required',
                                            'nullable',
                                            'string',
                                            'integer',
                                            'numeric',
                                            'boolean',
                                            'alpha',
                                            'alpha_dash',
                                            'alpha_num',
                                            'url',
                                            'email',
                                            'regex:',
                                            'min:',
                                            'max:',
                                            'between:',
                                            'between:1024,65535',
                                            'in:',
                                            'in:true,false',
                                        ]),
                                ]),
                        ]),
                    Tab::make('Install Script')
                        ->columns(3)
                        ->schema([

                            Hidden::make('copy_script_from'),
                            //->placeholder('None')
                            //->relationship('scriptFrom', 'name', ignoreRecord: true),

                            TextInput::make('script_container')
                                ->required()
                                ->maxLength(255)
                                ->default('ghcr.io/pelican-eggs/installers:debian'),

                            Select::make('script_entry')
                                ->selectablePlaceholder(false)
                                ->default('bash')
                                ->options(['bash', 'ash', '/bin/bash'])
                                ->required(),

                            MonacoEditor::make('script_install')
                                ->columnSpanFull()
                                ->fontSize('16px')
                                ->language('shell')
                                ->lazy()
                                ->view('filament.plugins.monaco-editor'),
                        ]),

                ])->columnSpanFull()->persistTabInQueryString(),
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['uuid'] ??= Str::uuid()->toString();

        if (is_array($data['config_startup'])) {
            $data['config_startup'] = json_encode($data['config_startup']);
        }

        if (is_array($data['config_logs'])) {
            $data['config_logs'] = json_encode($data['config_logs']);
        }

        logger()->info('new egg', $data);

        return parent::handleRecordCreation($data);
    }
}
