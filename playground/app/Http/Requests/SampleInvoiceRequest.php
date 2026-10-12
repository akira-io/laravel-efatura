<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\SampleInvoice;
use App\Data\SampleLine;
use App\Enums\DfaModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SampleInvoiceRequest extends FormRequest
{
    private const string DECIMAL = 'regex:/\A\d{1,9}(\.\d{1,5})?\z/';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.description' => ['required', 'string', 'max:200'],
            'lines.*.quantity' => ['required', 'string', self::DECIMAL, 'not_regex:/\A0+(\.0+)?\z/'],
            'lines.*.unit_price' => ['required', 'string', self::DECIMAL],
            'tax_percentage' => ['required', Rule::in(['0', '8', '15'])],
            'payable_override' => ['nullable', 'string', 'regex:/\A\d{1,9}(\.\d{1,2})?\z/'],
            'model' => ['nullable', Rule::enum(DfaModel::class)],
            'download' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['lines' => collect($this->input('lines', []))
            ->filter(fn (mixed $line): bool => is_array($line) && collect($line)->filter(fn (mixed $value): bool => filled($value))->isNotEmpty())
            ->values()
            ->all()]);
    }

    public function sampleInvoice(): SampleInvoice
    {
        $lines = collect($this->array('lines'))
            ->map(fn (array $line): SampleLine => new SampleLine($line['description'], $line['quantity'], $line['unit_price']))
            ->values()
            ->all();

        return new SampleInvoice(
            $lines,
            $this->string('tax_percentage')->toString(),
            $this->filled('payable_override') ? $this->string('payable_override')->toString() : null,
            $this->dfaModel()->emissionMode(),
        );
    }

    public function dfaModel(): DfaModel
    {
        return $this->enum('model', DfaModel::class) ?? DfaModel::Online;
    }
}
