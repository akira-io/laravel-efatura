<?php

declare(strict_types=1);

return [
    'validation' => [
        'invoice_type_mismatch'     => 'Invoice type does not match the expected document type.',
        'emitter_nif_required'      => 'Emitter NIF is required.',
        'emitter_name_required'     => 'Emitter name is required.',
        'party_nif_required'        => 'Party NIF is required.',
        'party_name_required'       => 'Party name is required.',
        'receiver_nif_required'     => 'Receiver NIF is required.',
        'receiver_name_required'    => 'Receiver name is required.',
        'receiver_required'         => 'Receiver is required.',
        'emitter_required'          => 'Emitter is required.',
        'totals_required'           => 'Totals are required.',
        'invoice_required'          => 'Invoice is required.',
        'receiver_required'         => 'Receiver is required.',
        'emitter_required'          => 'Emitter is required.',
        'totals_required'           => 'Totals are required.',
        'invoice_required'          => 'Invoice is required.',
        'lines_required'            => 'At least one line item is required.',
        'totals_negative'           => 'Totals cannot be negative.',
        'na_tax_exemption_required' => 'NA tax requires an exemption reason.',
    ],
    'invoice' => [
        'issue_date_required'         => 'Issue date is required.',
        'receiver_required_for_type'  => 'Receiver is required for this document type.',
        'original_iud_required'       => 'Original IUD is required for credit notes.',
        'credit_note_reason_required' => 'Credit note reason is required.',
    ],
    'config' => [
        'transmitter_nif_required'     => 'Transmitter NIF is required.',
        'transmitter_led_required'     => 'Transmitter LED code is required.',
        'software_code_required'       => 'Software code is required.',
        'software_name_required'       => 'Software name is required.',
        'software_version_required'    => 'Software version is required.',
        'middleware_base_url_required' => 'Middleware base URL is required.',
        'environment_invalid'          => 'Repository environment is invalid.',
    ],
    'install' => [
        'command_description'      => 'Install akira/efatura configuration',
        'completed'                => 'akira/efatura installation complete.',
        'config_exists'            => 'Config file already exists. Skipped publishing.',
        'config_published'         => 'Config file published.',
        'env_missing'              => '.env file not found. Skipped environment updates.',
        'env_add_confirm'          => 'Add :key to .env?',
        'env_skipped'              => 'Skipped :key.',
        'env_added'                => 'Added :key to .env.',
        'optional_packages_notice' => 'Optional PDF/QR packages not detected: :packages. PDF and QR generation are optional. The recommended packages work out of the box when installed with akira/efatura.',
    ],
    'general' => [
        'package' => 'akira/efatura',
    ],
];
