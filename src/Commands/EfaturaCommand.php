<?php

namespace Akira\Efatura\Commands;

use Illuminate\Console\Command;

class EfaturaCommand extends Command
{
    public $signature = 'efatura';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
