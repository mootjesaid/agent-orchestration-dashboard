<?php

namespace App\Jobs;

use App\Actions\Workflows\ManageRunnerCommands;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SweepRunnerOutbox implements ShouldQueue
{
    use Queueable;

    private const BUDGET_NANOSECONDS = 5_000_000_000;

    public function handle(ManageRunnerCommands $commands): void
    {
        $commands->sweep(hrtime(true) + self::BUDGET_NANOSECONDS);
    }
}
