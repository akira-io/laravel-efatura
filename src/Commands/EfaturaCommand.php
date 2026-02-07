<?php

declare(strict_types=1);

namespace Akira\Efatura\Commands;

use Illuminate\Console\Command;

final class EfaturaCommand extends Command
{
    public $signature = 'efatura';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
