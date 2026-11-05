<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateNotifierKey extends Command
{
    protected $signature = 'notifier:generate-key';

    protected $description = 'Print a new random signing key for NOTIFIER_SIGNING_KEY (paste it into .env yourself)';

    public function handle(): int
    {
        $this->line(bin2hex(random_bytes(32)));
        $this->comment('Paste this into backend/.env as NOTIFIER_SIGNING_KEY=... . Do not commit it or share a screenshot of it.');

        return self::SUCCESS;
    }
}
