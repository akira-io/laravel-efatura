<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Support\SampleDefaults;
use Illuminate\Http\Request;
use Illuminate\View\View;

final readonly class SampleInvoiceFormComposer
{
    private const int MAX_LINES = 20;

    public function __construct(private Request $request) {}

    public function compose(View $view): void
    {
        $submitted = $this->request->old('lines', $this->request->input('lines'));
        $lines = collect(is_array($submitted) ? $submitted : SampleDefaults::lines())
            ->filter(fn (mixed $line): bool => is_array($line))
            ->values();

        $view->with('formLines', $lines->push(['description' => '', 'quantity' => '', 'unit_price' => ''])->take(self::MAX_LINES)->all())
            ->with('formTax', (string) $this->request->old('tax_percentage', $this->request->input('tax_percentage', '15')))
            ->with('formPayableOverride', (string) $this->request->old('payable_override', $this->request->input('payable_override', '')));
    }
}
