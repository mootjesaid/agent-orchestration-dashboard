<?php

namespace App\Actions\Workflows;

use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class ManageRunnerCommands
{
    private const COMMAND_WINDOW_SECONDS = 120;

    private const LEASE_WINDOW_SECONDS = 30;

    /**
     * Called inside the transaction that records the planning event.
     */
    public function recordPlanning(string $workflowId, int $version): void
    {
        $id = "$workflowId:$version:begin_planning";

        DB::table('runner_commands')->insert([
            'id' => $id,
            'workflow_id' => $workflowId,
            'workflow_version' => $version,
            'kind' => 'begin_planning',
            'status' => 'unpublished',
            'deadline' => now()->getTimestamp() + self::COMMAND_WINDOW_SECONDS,
        ]);
        DB::table('runner_outbox')->insert(['command_id' => $id]);
    }

    public function acquireLease(string $owner): ?int
    {
        if (blank($owner)) {
            throw new InvalidArgumentException('A runner process identity is required.');
        }

        return $this->transaction(function () use ($owner): ?int {
            $inserted = DB::table('runner_leases')->insertOrIgnore([
                'active_slot' => 1,
                'owner' => $owner,
                'expires_at' => now()->getTimestamp() + self::LEASE_WINDOW_SECONDS,
            ]);

            return $inserted === 1
                ? (int) DB::table('runner_leases')->where('active_slot', 1)->value('generation')
                : null;
        });
    }

    public function renewLease(int $generation, string $owner): bool
    {
        return $this->transaction(function () use ($generation, $owner): bool {
            $now = now()->getTimestamp();

            return $this->activeLease($generation, $owner, $now)
                ->update(['expires_at' => $now + self::LEASE_WINDOW_SECONDS]) === 1;
        });
    }

    /**
     * Only a trusted supervisor may call this after verifying the process
     * and its children have stopped. Expiry alone cannot satisfy this contract.
     */
    public function confirmStopped(int $generation, string $evidence): bool
    {
        if (blank($evidence)) {
            throw new InvalidArgumentException('Process-stop evidence is required.');
        }

        return $this->transaction(function () use ($generation, $evidence): bool {
            $released = DB::table('runner_leases')
                ->where('generation', $generation)
                ->where('active_slot', 1)
                ->update(['active_slot' => null, 'stopped_at' => now()->getTimestamp(), 'stop_evidence' => $evidence]);

            if ($released !== 1) {
                return false;
            }

            DB::table('runner_commands')->where('lease_generation', $generation)->where('status', 'claimed')
                ->update(['status' => 'reconciliation_required', 'reconciliation_reason' => 'writer_stopped']);

            return true;
        });
    }

    /**
     * Reserve a single send attempt. The runner must commit the claim and
     * enforce the lease and command deadlines at its wire boundary.
     */
    public function claim(string $id, int $generation, string $owner): bool
    {
        return $this->transaction(fn (): bool => $this->currentCommand($id, $generation, $owner, now()->getTimestamp())
            ->where('status', 'pending')
            ->whereExists(DB::table('runner_outbox')->whereColumn('command_id', 'runner_commands.id')->whereNotNull('published_at'))
            ->update(['status' => 'claimed', 'lease_generation' => $generation]) === 1);
    }

    public function acknowledge(string $id, int $generation, string $owner): bool
    {
        return $this->transaction(function () use ($id, $generation, $owner): bool {
            $now = now()->getTimestamp();

            return $this->currentCommand($id, $generation, $owner, $now)
                ->where('status', 'claimed')
                ->where('lease_generation', $generation)
                ->update(['status' => 'acknowledged', 'acknowledged_at' => $now]) === 1;
        });
    }

    public function supersedePlanning(string $workflowId, int $version): void
    {
        $command = DB::table('runner_commands')->where('id', "$workflowId:$version:begin_planning");

        if ((clone $command)->whereIn('status', ['claimed', 'reconciliation_required'])
            ->whereExists(DB::table('runner_leases')
                ->whereColumn('generation', 'runner_commands.lease_generation')->where('active_slot', 1))
            ->exists()) {
            throw new DomainException('The planning command has an unresolved send. Wait for acknowledgement or verified runner shutdown.');
        }

        $command
            ->whereIn('status', ['unpublished', 'pending', 'claimed'])
            ->update(['status' => 'reconciliation_required', 'reconciliation_reason' => 'superseded']);
    }

    public function sweep(int $deadlineNanoseconds): void
    {
        $this->transaction(function () use ($deadlineNanoseconds): void {
            if (hrtime(true) >= $deadlineNanoseconds) {
                return;
            }

            $now = now()->getTimestamp();
            $expired = DB::table('runner_commands')->whereIn('status', ['claimed', 'pending', 'unpublished'])
                ->where('deadline', '<=', $now)
                ->orderBy('status')->orderBy('deadline')->orderBy('id')->limit(100)->pluck('id');

            DB::table('runner_commands')->whereIn('id', $expired)
                ->update(['status' => 'reconciliation_required', 'reconciliation_reason' => 'command_expired']);

            if (hrtime(true) >= $deadlineNanoseconds) {
                return;
            }

            $generation = DB::table('runner_leases')->where('active_slot', 1)->where('expires_at', '<=', $now)->value('generation');

            if ($generation !== null) {
                $abandoned = DB::table('runner_commands')->where('lease_generation', $generation)->where('status', 'claimed')
                    ->orderBy('id')->limit(100)->pluck('id');

                DB::table('runner_commands')->whereIn('id', $abandoned)
                    ->update(['status' => 'reconciliation_required', 'reconciliation_reason' => 'lease_expired']);
            }

            if (hrtime(true) >= $deadlineNanoseconds) {
                return;
            }

            $publishedAt = now()->getTimestamp();
            $batch = DB::table('runner_commands')->where('status', 'unpublished')
                ->where('deadline', '>', $publishedAt)
                ->orderBy('deadline')->orderBy('id')->limit(100)->pluck('id');

            DB::table('runner_outbox')->whereIn('command_id', $batch)->update(['published_at' => $publishedAt]);
            DB::table('runner_commands')->whereIn('id', $batch)->update(['status' => 'pending']);
        });
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private function transaction(callable $callback): mixed
    {
        $connection = DB::connection();

        try {
            return $connection->transaction(fn () => $callback());
        } catch (Throwable $exception) {
            // SQLite can leave a failed COMMIT open after Laravel resets its transaction level.
            if ($connection->transactionLevel() === 0 && $connection->getPdo()->inTransaction()) {
                $connection->getPdo()->rollBack();
            }

            throw $exception;
        }
    }

    private function activeLease(int $generation, string $owner, int $now): Builder
    {
        return DB::table('runner_leases')->where('generation', $generation)
            ->where('owner', $owner)->where('active_slot', 1)->where('expires_at', '>', $now);
    }

    private function currentCommand(string $id, int $generation, string $owner, int $now): Builder
    {
        return DB::table('runner_commands')->where('id', $id)->where('deadline', '>', $now)
            ->whereExists($this->activeLease($generation, $owner, $now))
            ->whereExists($this->currentWorkflow());
    }

    private function currentWorkflow(): Builder
    {
        return DB::table('workflows')->whereColumn('workflows.id', 'runner_commands.workflow_id')
            ->whereColumn('workflows.version', 'runner_commands.workflow_version');
    }
}
