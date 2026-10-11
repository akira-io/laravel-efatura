<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Enums\ReadinessCheck;
use Akira\Efatura\Readiness\LocalChecks;
use Akira\Efatura\Readiness\ReadinessReport;
use Akira\Efatura\Readiness\ReadinessResult;
use Akira\Efatura\Readiness\RemoteChecks;
use Closure;
use Illuminate\Contracts\Container\Container;
use Throwable;

final readonly class CheckReadinessAction
{
    public function __construct(private Container $container, private LocalChecks $local, private RemoteChecks $remote) {}

    public function handle(bool $remote = false): ReadinessReport
    {
        try {
            $config = $this->container->make(EfaturaConfig::class);
        } catch (Throwable $throwable) {
            return new ReadinessReport([
                ReadinessResult::failure(ReadinessCheck::Configuration, $throwable),
                ...array_map(
                    static fn (ReadinessCheck $check): ReadinessResult => ReadinessResult::skipped($check, $remote || ! $check->isRemote()),
                    array_values(array_filter(ReadinessCheck::cases(), static fn (ReadinessCheck $check): bool => $check !== ReadinessCheck::Configuration)),
                ),
            ]);
        }

        return new ReadinessReport([
            ...$this->attempt(ReadinessCheck::Configuration, fn (): array => $this->local->configuration($config)),
            ...$this->attempt(ReadinessCheck::Certificate, fn (): array => $this->local->certificate($config)),
            ...$this->attempt(ReadinessCheck::Sequences, fn (): array => $this->local->sequences($config)),
            ...$this->attempt(ReadinessCheck::Environment, fn (): array => $this->local->environment($config)),
            ...($remote ? $this->attempt(ReadinessCheck::Endpoint, fn (): array => $this->remote->run($config)) : $this->remoteSkipped()),
        ]);
    }

    /**
     * @param  Closure(): list<ReadinessResult> $check
     * @return list<ReadinessResult>
     */
    private function attempt(ReadinessCheck $name, Closure $check): array
    {
        try {
            return $check();
        } catch (Throwable $throwable) {
            return [ReadinessResult::failure($name, $throwable)];
        }
    }

    /**
     * @return list<ReadinessResult>
     */
    private function remoteSkipped(): array
    {
        return array_values(array_map(
            static fn (ReadinessCheck $check): ReadinessResult => ReadinessResult::skipped($check, false),
            array_filter(ReadinessCheck::cases(), static fn (ReadinessCheck $check): bool => $check->isRemote()),
        ));
    }
}
