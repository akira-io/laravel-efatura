<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Akira\Efatura\Actions\IssueDocumentAction;
use App\Actions\BuildSampleInvoiceAction;
use App\Http\Requests\SampleInvoiceRequest;
use Illuminate\Contracts\View\View;

final readonly class IssueDocumentController
{
    public function __invoke(SampleInvoiceRequest $request, BuildSampleInvoiceAction $buildInvoice, IssueDocumentAction $issue): View
    {
        return view('issue', ['issued' => $issue->handle($buildInvoice->handle($request->sampleInvoice()))]);
    }
}
