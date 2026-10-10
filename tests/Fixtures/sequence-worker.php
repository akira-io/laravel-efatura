<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\DatabaseConfig;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Sequence\DatabaseSequenceStore;
use Akira\Efatura\Sequence\SequenceScope;
use Carbon\FactoryImmutable;
use Illuminate\Database\Capsule\Manager;

require __DIR__ . '/../../vendor/autoload.php';

[, $database, $reservations] = $argv;

$capsule = new Manager;
$capsule->addConnection(['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'busy_timeout' => 5000]);

$store = new DatabaseSequenceStore($capsule->getDatabaseManager(), new DatabaseConfig('default', 'efatura_sequences'), new FactoryImmutable);
$scope = new SequenceScope('100200300', 2026, 1, DocumentType::Invoice);

foreach (range(1, (int) $reservations) as $reservation) {
    echo $store->next($scope), PHP_EOL;
}
