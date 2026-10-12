<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Akira\Efatura\Actions\CheckReadinessAction;
use Illuminate\Contracts\View\View;

final readonly class ReadinessController
{
    public function __invoke(CheckReadinessAction $checkReadiness, bool $remote = false): View
    {
        return view('doctor', ['report' => $checkReadiness->handle($remote), 'remote' => $remote]);
    }
}
