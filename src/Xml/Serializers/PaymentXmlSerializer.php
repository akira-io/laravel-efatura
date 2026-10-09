<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Serializers;

use Akira\Efatura\Data\PayeeFinancialAccountData;
use Akira\Efatura\Data\PaymentData;
use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\PaymentTermsData;
use Akira\Efatura\Xml\XmlValue;
use Akira\Efatura\Xml\XmlWriter;
use Carbon\CarbonImmutable;
use DOMElement;

final readonly class PaymentXmlSerializer
{
    public function append(XmlWriter $xml, DOMElement $parent, ?PaymentsData $payments, string $path): ?DOMElement
    {
        if (! $payments instanceof PaymentsData || $payments->payments === []) {
            return null;
        }

        $element = $xml->container($parent, 'Payments');

        foreach ($payments->payments as $index => $payment) {
            $this->payment($xml, $element, $payment, $path . '.payments.' . $index);
        }

        return $element;
    }

    public function appendInvoice(XmlWriter $xml, DOMElement $parent, ?PaymentsData $payments, string $path): ?DOMElement
    {
        if (! $payments instanceof PaymentsData || self::isEmptyInvoiceVariant($payments)) {
            return null;
        }

        $element = $xml->container($parent, 'Payments');
        $xml->element($element, 'PaymentDueDate', XmlValue::date($payments->paymentDueDate), $path . '.paymentDueDate');
        $this->terms($xml, $element, $payments->paymentTerms, $path . '.paymentTerms');

        foreach ($payments->payeeFinancialAccounts as $index => $account) {
            $this->account($xml, $element, $account, $path . '.payeeFinancialAccounts.' . $index);
        }

        return $element;
    }

    private function payment(XmlWriter $xml, DOMElement $parent, PaymentData $payment, string $path): void
    {
        $element = $xml->container($parent, 'Payment');

        $xml->elements($element, $path, [
            'PaymentMeansCode' => ['paymentMeansCode', $payment->paymentMeansCode],
            'PaymentReference' => ['paymentReference', $payment->paymentReference],
            'PaymentDate'      => ['paymentDate', XmlValue::date($payment->paymentDate)],
        ]);
        $xml->decimal($element, 'PaymentAmount', $payment->paymentAmount, $path . '.paymentAmount');
        $this->account($xml, $element, $payment->payeeFinancialAccount, $path . '.payeeFinancialAccount');
    }

    private function terms(XmlWriter $xml, DOMElement $parent, ?PaymentTermsData $terms, string $path): void
    {
        if ($terms instanceof PaymentTermsData) {
            $xml->requiredElement($xml->container($parent, 'PaymentTerms'), 'Note', $terms->note, $path . '.note');
        }
    }

    private function account(XmlWriter $xml, DOMElement $parent, ?PayeeFinancialAccountData $account, string $path): void
    {
        if (! $account instanceof PayeeFinancialAccountData) {
            return;
        }

        $element = $xml->container($parent, 'PayeeFinancialAccount');
        $xml->elements($element, $path, [
            'AccountNumber' => ['accountNumber', $account->accountNumber],
            'NIB'           => ['nib', $account->nib],
        ]);
        $xml->requiredElement($element, 'Name', $account->name, $path . '.name');
    }

    private static function isEmptyInvoiceVariant(PaymentsData $payments): bool
    {
        return ! $payments->paymentDueDate instanceof CarbonImmutable
            && ! $payments->paymentTerms instanceof PaymentTermsData
            && $payments->payeeFinancialAccounts === [];
    }
}
