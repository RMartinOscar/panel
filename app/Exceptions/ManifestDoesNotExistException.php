<?php

namespace App\Exceptions;

use Spatie\Ignition\Contracts\ProvidesSolution;
use Spatie\Ignition\Contracts\Solution;

class ManifestDoesNotExistException extends \Exception implements ProvidesSolution
{
    public function getSolution(): Solution
    {
        return new Solutions\ManifestDoesNotExistSolution();
    }
}
