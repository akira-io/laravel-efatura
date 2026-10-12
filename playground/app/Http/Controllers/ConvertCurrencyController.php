<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildSampleInvoiceAction;
use App\Actions\ConvertSampleCurrencyAction;
use App\Http\Requests\SampleInvoiceRequest;
use Illuminate\Contracts\View\View;

final readonly class ConvertCurrencyController
{
    public function __invoke(SampleInvoiceRequest $request, BuildSampleInvoiceAction $buildInvoice, ConvertSampleCurrencyAction $convert): View
    {
        return view('currency', ['conversion' => $convert->handle($buildInvoice->handle($request->sampleInvoice()))]);
    }
}
