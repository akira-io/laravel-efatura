<?php

declare(strict_types=1);

namespace Akira\Efatura\Commands;

use Akira\Efatura\Actions\CheckReadinessAction;
use Akira\Efatura\Readiness\ReadinessReport;
use Akira\Efatura\Readiness\ReadinessResult;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

#[Signature('efatura:doctor {--remote : Also reach the endpoint of the configured driver} {--json : Print the report as a JSON document}')]
#[Description('Check whether this installation can issue e-Fatura documents')]
final class DoctorCommand extends Command
{
    public function handle(CheckReadinessAction $readiness): int
    {
        $remote = (bool) $this->option('remote');
        $report = $readiness->handle($remote);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $this->exitCode($report);
        }

        $this->table(
            [
                __('efatura::efatura.console.doctor.check'),
                __('efatura::efatura.console.doctor.status'),
                __('efatura::efatura.console.doctor.code'),
                __('efatura::efatura.console.doctor.field'),
            ],
            array_map(static fn (ReadinessResult $result): array => [
                $result->check->value,
                $result->status->value,
                $result->code,
                $result->field ?? '',
            ], $report->results),
        );

        if (! $remote) {
            note(__('efatura::efatura.console.doctor.remote_skipped'));
        }

        $this->summarize($report);

        return $this->exitCode($report);
    }

    private function summarize(ReadinessReport $report): void
    {
        if (! $report->ready()) {
            error(__('efatura::efatura.console.doctor.not_ready'));

            return;
        }

        $warnings = \count($report->warnings());

        $warnings === 0
            ? info(__('efatura::efatura.console.doctor.ready'))
            : warning(__('efatura::efatura.console.doctor.ready_with_warnings', ['count' => $warnings]));
    }

    private function exitCode(ReadinessReport $report): int
    {
        return $report->ready() ? self::SUCCESS : self::FAILURE;
    }
}
