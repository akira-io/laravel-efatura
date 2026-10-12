<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildSampleInvoiceAction;
use App\Actions\PreviewDocumentXmlAction;
use App\Http\Requests\SampleInvoiceRequest;
use Illuminate\Contracts\View\View;

final readonly class PreviewDocumentController
{
    public function __invoke(SampleInvoiceRequest $request, BuildSampleInvoiceAction $buildInvoice, PreviewDocumentXmlAction $preview): View
    {
        return view('document', ['preview' => $preview->handle($buildInvoice->handle($request->sampleInvoice()))]);
    }
}
