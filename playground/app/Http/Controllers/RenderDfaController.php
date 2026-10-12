<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildSampleInvoiceAction;
use App\Actions\RenderSampleDfaAction;
use App\Http\Requests\SampleInvoiceRequest;
use Illuminate\Http\Response;

final readonly class RenderDfaController
{
    public function __invoke(SampleInvoiceRequest $request, BuildSampleInvoiceAction $buildInvoice, RenderSampleDfaAction $renderDfa): Response
    {
        $pdf = $renderDfa->handle($buildInvoice->handle($request->sampleInvoice()));
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->bytes, 200, [
            'Content-Type' => $pdf->contentType,
            'Content-Disposition' => $disposition.'; filename="'.$pdf->filename.'"',
        ]);
    }
}
