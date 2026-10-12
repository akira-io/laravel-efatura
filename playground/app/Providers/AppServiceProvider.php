<?php

declare(strict_types=1);

namespace App\Providers;

use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Testing\FakeTransport;
use App\Enums\PlaygroundTransport;
use App\View\Composers\SampleInvoiceFormComposer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (PlaygroundTransport::configured() === PlaygroundTransport::Fake) {
            $this->app->singleton(Transport::class, fn (Application $app): Transport => new FakeTransport($app->make(ClockInterface::class)));
        }
    }

    public function boot(): void
    {
        View::composer('partials.invoice-form', SampleInvoiceFormComposer::class);
    }
}
