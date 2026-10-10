<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Fiscal Environment
    |--------------------------------------------------------------------------
    |
    | Accepts test (3), homologation (2), or production (1), as a name in any
    | letter case, a code, or an Environment enum. test is the safe default.
    | Changing the environment selects the official repository code; it never
    | changes client URLs.
    |
    */
    'environment' => env('EFATURA_ENVIRONMENT', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Default Fiscal Emitter
    |--------------------------------------------------------------------------
    |
    | The emitter issues the fiscal document. All-null fields mean no default;
    | multi-emitter applications may set this section to null. Each document
    | can supply a complete replacement. Partial identities are accepted at
    | boot; required fiscal fields are checked when building the document.
    | Tax IDs are nine-digit strings; names, LED, address and contact values
    | are nullable strings. No field is copied from the transmitter.
    | A CV address needs country_code, address_detail and an official
    | address_code. Contacts need email and telephone or mobile. LED is a
    | decimal string (1..99999).
    |
    */
    'emitter' => [
        'tax_id'  => env('EFATURA_EMITTER_TAX_ID'),
        'name'    => env('EFATURA_EMITTER_NAME'),
        'led'     => env('EFATURA_EMITTER_LED'),
        'address' => [
            'country_code'    => env('EFATURA_EMITTER_COUNTRY_CODE'),
            'region'          => env('EFATURA_EMITTER_REGION'),
            'city'            => env('EFATURA_EMITTER_CITY'),
            'street'          => env('EFATURA_EMITTER_STREET'),
            'postal_code'     => env('EFATURA_EMITTER_POSTAL_CODE'),
            'address_detail'  => env('EFATURA_EMITTER_ADDRESS_DETAIL'),
            'address_code'    => env('EFATURA_EMITTER_ADDRESS_CODE'),
            'state'           => env('EFATURA_EMITTER_STATE'),
            'street_detail'   => env('EFATURA_EMITTER_STREET_DETAIL'),
            'building_name'   => env('EFATURA_EMITTER_BUILDING_NAME'),
            'building_number' => env('EFATURA_EMITTER_BUILDING_NUMBER'),
            'building_floor'  => env('EFATURA_EMITTER_BUILDING_FLOOR'),
        ],
        'contacts' => [
            'email'     => env('EFATURA_EMITTER_EMAIL'),
            'telephone' => env('EFATURA_EMITTER_TELEPHONE'),
            'mobile'    => env('EFATURA_EMITTER_MOBILE'),
            'telefax'   => env('EFATURA_EMITTER_TELEFAX'),
            'website'   => env('EFATURA_EMITTER_WEBSITE'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transmitter Identity And Credentials
    |--------------------------------------------------------------------------
    |
    | The transmitter authenticates and submits. Its nine-digit tax ID may
    | match the emitter's when the same taxpayer submits its own documents;
    | an authorized intermediary has its own identity. These roles stay
    | separate even when the tax IDs match. Identity fields are nullable.
    | The middleware key is sent as cv-ef-mw-core-transmitter-key. OAuth is
    | used only for direct platform authentication. Secrets default to null
    | and are required only by the operation using them. Never log these
    | values; protect cached configuration files as they contain secrets.
    |
    */
    'transmitter' => [
        'tax_id'         => env('EFATURA_TRANSMITTER_TAX_ID'),
        'name'           => env('EFATURA_TRANSMITTER_NAME'),
        'middleware_key' => env('EFATURA_TRANSMITTER_KEY'),
        'oauth'          => [
            'client_id'     => env('EFATURA_TRANSMITTER_CLIENT_ID'),
            'client_secret' => env('EFATURA_TRANSMITTER_CLIENT_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered Software
    |--------------------------------------------------------------------------
    |
    | Nullable strings identifying the software registered for transmission.
    | Supply all three when the selected operation requires software identity.
    |
    */
    'software' => [
        'code'    => env('EFATURA_SOFTWARE_CODE'),
        'name'    => env('EFATURA_SOFTWARE_NAME'),
        'version' => env('EFATURA_SOFTWARE_VERSION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signing Material
    |--------------------------------------------------------------------------
    |
    | A null disk inherits filesystems.default independently of document
    | storage. Paths are nullable disk-relative strings without traversal.
    | Keep private keys on a private disk and never serve or log their bytes.
    | The passphrase is a nullable secret string preserved exactly, including
    | whitespace. Signing operations validate missing material when needed.
    | An optional PEM CA bundle on the same disk enables chain verification;
    | production (repository 1) requires a certificate issued under ICP-CV.
    |
    */
    'certificates' => [
        'disk'             => env('EFATURA_CERTIFICATES_DISK'),
        'certificate_path' => env('EFATURA_CERTIFICATE_PATH'),
        'private_key_path' => env('EFATURA_PRIVATE_KEY_PATH'),
        'passphrase'       => env('EFATURA_PRIVATE_KEY_PASSPHRASE'),
        'ca_bundle_path'   => env('EFATURA_CA_BUNDLE_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Storage
    |--------------------------------------------------------------------------
    |
    | A null disk inherits filesystems.default. The path is a nonempty relative
    | directory without traversal, used for generated XML, ZIP and responses.
    | Fiscal documents contain personal data: configure a private host disk.
    |
    */
    'storage' => [
        'disk' => env('EFATURA_STORAGE_DISK'),
        'path' => env('EFATURA_STORAGE_PATH', 'efatura'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | A null store inherits cache.default; cache tags are not required. Prefix
    | is a nonempty string isolating package entries. The exchange-rate TTL
    | is a positive integer in seconds (3600 = one hour). Cache reusable
    | lookups only, never signing material or authentication secrets.
    |
    */
    'cache' => [
        'store'                      => env('EFATURA_CACHE_STORE'),
        'prefix'                     => env('EFATURA_CACHE_PREFIX', 'efatura'),
        'exchange_rates_ttl_seconds' => env('EFATURA_EXCHANGE_RATES_TTL_SECONDS', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sequence Database And Queue
    |--------------------------------------------------------------------------
    |
    | Null connections inherit database.default and queue.default respectively.
    | The sequence table is an SQL identifier using letters, digits and
    | underscores, starting with a letter or underscore. A null queue inherits
    | the selected connection's queue name, remaining null for drivers such as
    | sync that have no queue name. Explicit names must be nonempty strings.
    | The published sequence migration reads the connection and the table
    | when it runs, so set them before migrating. SQLite connections shared by
    | several workers need a busy_timeout in config/database.php.
    |
    */
    'database' => [
        'connection'      => env('EFATURA_DATABASE_CONNECTION'),
        'sequences_table' => env('EFATURA_SEQUENCES_TABLE', 'efatura_sequences'),
    ],
    'queue' => [
        'connection' => env('EFATURA_QUEUE_CONNECTION'),
        'queue'      => env('EFATURA_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Defaults And Client Profiles
    |--------------------------------------------------------------------------
    |
    | Timeouts are positive integer seconds. Retry delay is a nonnegative
    | integer in milliseconds; retries counts additional attempts, with zero
    | disabling retries. Concurrency is a positive integer. Automatic retries
    | apply only to requests with a verified idempotency guarantee.
    | TLS verification is a boolean, true by default. Disabling verification
    | exposes credentials and documents to interception; keep it on in production.
    | Each client's null tuning option inherits the global default. Base URLs
    | must use HTTPS without credentials, query strings or fragments. A null
    | base URL defers endpoint selection to its provider's later configuration.
    | Fiscal environment selection never replaces these endpoints.
    |
    */
    'http' => [
        'timeout_seconds'          => env('EFATURA_HTTP_TIMEOUT_SECONDS', 30),
        'connect_timeout_seconds'  => env('EFATURA_HTTP_CONNECT_TIMEOUT_SECONDS', 10),
        'retries'                  => env('EFATURA_HTTP_RETRIES', 2),
        'retry_delay_milliseconds' => env('EFATURA_HTTP_RETRY_DELAY_MILLISECONDS', 200),
        'concurrency'              => env('EFATURA_HTTP_CONCURRENCY', 5),
        'verify_tls'               => env('EFATURA_HTTP_VERIFY_TLS', true),
        'middleware'               => [
            'base_url'                 => env('EFATURA_MIDDLEWARE_BASE_URL', 'https://localhost:3443'),
            'timeout_seconds'          => null,
            'connect_timeout_seconds'  => null,
            'retries'                  => null,
            'retry_delay_milliseconds' => null,
            'concurrency'              => null,
            'verify_tls'               => null,
        ],
        'platform' => [
            'base_url'                 => env('EFATURA_PLATFORM_BASE_URL', 'https://services.efatura.cv/v1'),
            'timeout_seconds'          => null,
            'connect_timeout_seconds'  => null,
            'retries'                  => null,
            'retry_delay_milliseconds' => null,
            'concurrency'              => null,
            'verify_tls'               => null,
        ],
        'bcv' => [
            'base_url'                 => env('EFATURA_BCV_BASE_URL'),
            'timeout_seconds'          => null,
            'connect_timeout_seconds'  => null,
            'retries'                  => null,
            'retry_delay_milliseconds' => null,
            'concurrency'              => null,
            'verify_tls'               => null,
        ],
        'world_bank' => [
            'base_url'                 => env('EFATURA_WORLD_BANK_BASE_URL'),
            'timeout_seconds'          => null,
            'connect_timeout_seconds'  => null,
            'retries'                  => null,
            'retry_delay_milliseconds' => null,
            'concurrency'              => null,
            'verify_tls'               => null,
        ],
    ],
];
