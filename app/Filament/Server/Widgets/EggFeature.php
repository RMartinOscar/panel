<?php

namespace App\Filament\Server\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use App\Features;
use App\Models\Server;

class EggFeature extends Widget
{
    protected static string $view = 'filament.components.egg-feature';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public ?Server $server = null;

    public static array $features = [
        Features\MinecraftEula::class,
        Features\JavaVersion::class,
    ];

    public function getActiveFeatures(): array
    {
        return collect(self::$features)
            ->filter(fn ($feature) => in_array($feature::featureName(), $this->server->egg->features))
            ->map(fn ($feature) => new $feature())
            ->all();
    }

    #[On('line-to-check')]
    public function lineToCheck(string $line): void
    {
        foreach ($this->getActiveFeatures() as $feature) {
            if ($feature->matchesListeners($line)) {
                Log::info('Feature listens for this', compact(['feature', 'line']));
            }
        }
    }
}
