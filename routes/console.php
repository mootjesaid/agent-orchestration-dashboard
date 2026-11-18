<?php

use App\Actions\Repositories\GitBroker;
use App\Jobs\SweepRunnerOutbox;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Output\OutputInterface;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => SweepRunnerOutbox::dispatchSync())
    ->name('sweep-runner-outbox')
    ->everyTenSeconds();

Artisan::command('git-broker:register {repository} {workspaceRoot}', function (GitBroker $broker, string $repository, string $workspaceRoot): int {
    $this->line($broker->register($repository, $workspaceRoot));

    return 0;
})->purpose('Register a repository and its dedicated workspace root');

Artisan::command('git-broker:prepare-registered {registration} {base} {branch}', function (GitBroker $broker, string $registration, string $base, string $branch): int {
    $this->line($broker->prepareRegistered($registration, $base, $branch));

    return 0;
})->purpose('Prepare a task clone using a registered workspace root');

Artisan::command('git-broker:prepare {repository} {workspaceRoot} {base} {branch}', function (
    GitBroker $broker,
    string $repository,
    string $workspaceRoot,
    string $base,
    string $branch,
): int {
    $this->line($broker->prepare($repository, $workspaceRoot, $base, $branch));

    return 0;
})->purpose('Prepare a standalone task clone from an exact local commit');

Artisan::command('git-broker:inspect {workspaceRoot} {workspace} {base} {branch}', function (
    GitBroker $broker,
    string $workspaceRoot,
    string $workspace,
    string $base,
    string $branch,
): int {
    $snapshot = $broker->inspect($workspaceRoot, $workspace, $base, $branch);
    if (! mb_check_encoding($snapshot['diff'], 'UTF-8') || ! mb_check_encoding($snapshot['untracked'], 'UTF-8')) {
        $snapshot = [
            'encoding' => 'base64',
            'diff' => base64_encode($snapshot['diff']),
            'untracked' => array_map(base64_encode(...), $snapshot['untracked']),
        ];
    }

    $this->output->writeln(json_encode($snapshot, JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

    return 0;
})->purpose('Read the tracked diff and untracked paths of a prepared task clone');
