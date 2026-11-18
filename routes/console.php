<?php

use App\Jobs\SweepRunnerOutbox;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => SweepRunnerOutbox::dispatchSync())
    ->name('sweep-runner-outbox')
    ->everyTenSeconds();
