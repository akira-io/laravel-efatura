<?php

declare(strict_types=1);

use Akira\Efatura\Http\Controllers\ReadinessController;
use Akira\Efatura\Http\Controllers\ValidationController;

$router->get('readiness', ReadinessController::class)->name('readiness');
$router->post('validate', ValidationController::class)->name('validate');
