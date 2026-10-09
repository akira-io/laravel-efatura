<?php

declare(strict_types=1);

return [
    'validation' => [
        'document_field_forbidden' => 'This field does not belong to this fiscal document type.',
        'issue_date_window'        => 'The issue date and time are outside the permitted emission window.',
        'reconciliation'           => 'The supplied amount cannot be reconciled with the fiscal evidence.',
        'tax_point_after_issue'    => 'The tax point date cannot be later than the issue date.',
        'payment_not_on_issue_day' => 'The payment date must be the issue date.',
        'reserved_field'           => 'The :attribute is reserved for an official fiscal field.',
        'field_name'               => 'The :attribute must be a text field name.',
        'fiscal_date'              => 'The :attribute must use a valid fiscal date or time.',
        'official_code'            => 'The :attribute must be a code in the official catalog.',
        'tax_id'                   => 'The :attribute must be a valid tax identifier for its country.',
        'iud_invalid'              => 'The :attribute must be an official IUD with a valid check digit.',
        'iud_mismatch'             => 'The :attribute does not identify this document.',
        'event_id_invalid'         => 'The :attribute must be an official event identifier.',
        'event_id_mismatch'        => 'The :attribute does not identify this event.',
        'xml_text_invalid'         => 'The :attribute contains characters that XML 1.0 does not allow.',
        'xml_required'             => 'The :attribute is required to write the XML document.',
        'number_bounds'            => 'The :attribute is outside its permitted numeric bounds.',
        'invalid_decimal'          => 'Value must be a plain decimal number.',
        'decimal_scale_exceeded'   => 'Value exceeds the allowed decimal precision.',
        'integer_digits_exceeded'  => 'Value exceeds the allowed 15 integer digits.',
        'invalid_currency'         => 'Currency must be an uppercase code of the official currency catalog.',
        'currency_mismatch'        => 'Money currency does not match the requested currency.',
        'invalid_money'            => 'Money amount or currency is invalid.',
        'invalid_money_input'      => 'Money must be an integer, decimal string, or Money value.',
    ],
    'install' => [
        'completed'                => 'akira/efatura installation complete.',
        'config_exists'            => 'Config file already exists. Skipped publishing.',
        'config_published'         => 'Config file published.',
        'env_missing'              => '.env file not found. Skipped environment updates.',
        'env_add_confirm'          => 'Add :key to .env?',
        'env_skipped'              => 'Skipped :key.',
        'env_added'                => 'Added :key to .env.',
        'optional_packages_notice' => 'Optional PDF/QR packages not detected: :packages. PDF and QR generation are optional. The recommended packages work out of the box when installed with akira/efatura.',
    ],
];
