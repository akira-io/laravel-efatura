<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildSampleInvoiceAction;
use App\Actions\SignSampleDocumentAction;
use App\Http\Requests\SampleInvoiceRequest;
use Illuminate\Contracts\View\View;

final readonly class SignDocumentController
{
    public function __invoke(SampleInvoiceRequest $request, BuildSampleInvoiceAction $buildInvoice, SignSampleDocumentAction $sign): View
    {
        return view('sign', ['signed' => $sign->handle($buildInvoice->handle($request->sampleInvoice()))]);
    }
}
