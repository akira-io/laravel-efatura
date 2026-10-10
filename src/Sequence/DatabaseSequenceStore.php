<?php

declare(strict_types=1);

namespace Akira\Efatura\Sequence;

use Akira\Efatura\Configuration\DatabaseConfig;
use Akira\Efatura\Contracts\SequenceStore;
use Akira\Efatura\Exceptions\SequenceException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use PDOException;
use Psr\Clock\ClockInterface;

final readonly class DatabaseSequenceStore implements SequenceStore
{
    public const int ATTEMPTS = 5;

    public function __construct(
        private ConnectionResolverInterface $connections,
        private DatabaseConfig $config,
        private ClockInterface $clock,
    ) {}

    public function next(SequenceScope $scope): int
    {
        $connection = $this->connections->connection($this->config->connection);
        if ($connection->transactionLevel() > 0) {
            throw SequenceException::insideTransaction($scope);
        }

        try {
            return $connection->transaction(fn (): int => $this->reserve($connection, $scope), self::ATTEMPTS);
        } catch (PDOException $pdoException) {
            throw SequenceException::unavailable($scope, $pdoException);
        }
    }

    private function reserve(ConnectionInterface $connection, SequenceScope $scope): int
    {
        $key = [
            'emitter_tax_id'     => $scope->emitterTaxId,
            'fiscal_year'        => $scope->year,
            'led_code'           => $scope->ledCode,
            'document_type_code' => $scope->documentType->code(),
        ];
        $now = $this->clock->now();

        $connection->table($this->config->sequencesTable)->insertOrIgnore([...$key, 'current_number' => 0, 'created_at' => $now, 'updated_at' => $now]);
        $connection->table($this->config->sequencesTable)->where($key)->increment('current_number', 1, ['updated_at' => $now]);

        $number = $connection->table($this->config->sequencesTable)->where($key)->value('current_number');

        return $scope->allocated(is_numeric($number) ? (int) $number : throw SequenceException::unavailable($scope));
    }
}
