<?php

declare(strict_types=1);

use Akira\Efatura\Actions\BuildDfaDataAction;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Dfa\DfaView;
use Akira\Efatura\Issuance\IssuedDocument;
use Akira\Efatura\Packaging\PreparedDocument;
use Akira\Efatura\Tests\Support\DfaFixtures as D;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;

function dfaHtml(IssuedDocument|PreparedDocument $source, ?string $iuc = null): string
{
    return resolve(DfaView::class)->render(resolve(BuildDfaDataAction::class)->handle($source, $iuc));
}

it('prints the ONLINE model with the emitter, the totals, the QR code and the IUD', function (): void {
    $html = dfaHtml(D::issued());

    expect($html)->toContain('NIF:</strong> 100200300', 'NOME:</strong> Emitter', 'Endereço:</strong> Praia office', 'Contato:</strong> 1234567')
        ->toContain('Nº de Itens:', 'Total Líquido:', 'Encargos:', 'Descontos:', 'Imposto:', 'Total a Pagar:', '115$00')
        ->toContain('Consulta DFE em <strong>efatura.cv</strong>', 'src="data:image/svg+xml;base64,', D::iud())
        ->not->toContain('Pendente de Autorização', 'EMITIDO EM CONTINGÊNCIA', 'Cliente', 'Valor Unitário');
});

it('prints the OFFLINE model with the contingency notices, the items and the customer', function (): void {
    $html = dfaHtml(D::prepared(D::offline()));

    expect($html)->toContain('Série:</strong> A', 'Tipo Documento:</strong> FTE', 'Nº Documento:</strong> 7', 'Data:</strong> 02/10/2026 12:00:00')
        ->toContain('EMITIDO EM CONTINGÊNCIA OFFLINE', 'Pendente de Autorização')
        ->toContain('Código', 'Descrição', 'Quantidade', 'Unidade', 'Valor Unitário', 'Taxa Imposto', 'Valor Total', '>SKU<', '>C62<', '>15%<')
        ->toContain('Cliente', 'NIF: 900800700', 'Nome: Receiver', 'src="data:image/svg+xml;base64,')
        ->toContain('Este documento não confere direito a dedução de imposto, enquanto o documento associado não for transmitido e autorizado pela Administração Fiscal.')
        ->not->toContain('IUC');
});

it('prints the OFF model with the IUC, blank customer lines and neither QR code nor item codes', function (): void {
    $html = dfaHtml(D::prepared(D::off()));

    expect($html)->toContain('EMITIDO EM CONTINGÊNCIA OFF<', 'IUC 2026/15', 'Pendente de Autorização', 'Consulta DFE em <strong>efatura.cv</strong>')
        ->toContain('Descrição', 'Valor Total', 'NIF: ____', 'Nome: ____', 'Endereço: ____', 'Contacto: ____')
        ->toContain('Este documento não confere direito a dedução de imposto')
        ->not->toContain('Código', 'data:image/svg+xml', '<img class="qr"', 'qr-cell', '>SKU<', 'Receiver');
});

it('prints the conversion, the withholding and the rounding only when the document has them', function (): void {
    $document = new ElectronicInvoiceData(...[...get_object_vars(D::offline()), 'totals' => F::totals([
        'withholdingTaxTotalAmount' => '10', 'payableRoundingAmount' => '-0.01', 'payableAmount' => '104.99',
        'payableAlternativeAmounts' => [['value' => '0.95', 'currencyCode' => 'EUR', 'exchangeRate' => '110.265']],
    ])]);

    expect(dfaHtml(D::prepared($document)))->toContain('Retenção na Fonte:', '10$00', 'Arredondamento:', '-0$01', '104$99')
        ->toContain('Conversão cambial', 'EUR', '0,95 EUR', '1 EUR = 110,26500 CVE')
        ->and(dfaHtml(D::prepared(D::offline())))->not->toContain('Retenção na Fonte', 'Arredondamento', 'Conversão cambial');
});

it('escapes every document value it prints', function (): void {
    $document = new ElectronicInvoiceData(...[...get_object_vars(D::offline()), 'receiver' => PartyData::from([...F::payload()['receiver'], 'name' => '<script>alert(1)</script>'])]);

    expect(dfaHtml(D::prepared($document)))->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>');
});

it('renders from the package views without publishing them', function (): void {
    expect(view()->exists(DfaView::VIEW))->toBeTrue()
        ->and(resource_path('views/vendor/efatura'))->not->toBeDirectory();
});
