<?php

namespace Tests\Feature\Actions\Workflows;

use App\Actions\Workflows\ManageRunnerCommands;
use App\Actions\Workflows\ManageWorkflowState;
use App\Enums\WorkflowCommand;
use App\Jobs\SweepRunnerOutbox;
use DomainException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use Tests\TestCase;

class ManageRunnerCommandsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_outbox_failure_rolls_back_the_planning_transition_and_command(): void
    {
        $state = new ManageWorkflowState;
        $id = (string) Str::uuid();
        $state->create($id, 'Update README.', 'user:1', 'Submitted');
        DB::unprepared("CREATE TEMP TRIGGER reject_runner_outbox BEFORE INSERT ON runner_outbox BEGIN SELECT RAISE(ABORT, 'outbox failure'); END");

        try {
            $state->apply($id, 1, WorkflowCommand::BeginPlanning, 'orchestrator', 'Context ready');
            $this->fail('Expected the outbox insert to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('outbox failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER reject_runner_outbox');
        }

        $this->assertDatabaseHas('workflows', ['id' => $id, 'version' => 1, 'status' => 'inspecting']);
        $this->assertDatabaseCount('workflow_events', 1);
        $this->assertDatabaseCount('runner_commands', 0);
        $this->assertDatabaseCount('runner_outbox', 0);
    }

    public function test_repeated_outbox_sweeps_and_worker_deliveries_claim_once(): void
    {
        $this->freezeTime();
        $id = $this->startPlanning();
        $journal = new ManageRunnerCommands;
        $generation = $journal->acquireLease('process-a');
        $this->assertIsInt($generation);
        $this->assertNull($journal->acquireLease('process-b'));
        $this->assertFalse($journal->claim($id, $generation, 'process-a'));

        SweepRunnerOutbox::dispatchSync();
        $this->travel(1)->seconds();
        SweepRunnerOutbox::dispatchSync();

        $this->assertFalse($journal->claim($id, $generation, 'process-b'));
        $this->assertTrue($journal->claim($id, $generation, 'process-a'));
        $this->assertFalse((new ManageRunnerCommands)->claim($id, $generation, 'process-a'));
        $this->assertTrue($journal->acknowledge($id, $generation, 'process-a'));
        $this->assertFalse($journal->acknowledge($id, $generation, 'process-a'));

        $this->assertDatabaseHas('runner_outbox', ['command_id' => $id, 'published_at' => now()->timestamp - 1]);
        $this->assertDatabaseHas('runner_commands', ['id' => $id, 'status' => 'acknowledged', 'lease_generation' => $generation]);
        $this->assertDatabaseCount('runner_commands', 1);
    }

    public function test_expired_lease_cannot_be_replaced_until_its_writer_is_confirmed_stopped(): void
    {
        $this->freezeTime();
        $id = $this->startPlanning();
        $journal = new ManageRunnerCommands;
        $generation = $journal->acquireLease('process-a');
        $this->assertIsInt($generation);
        SweepRunnerOutbox::dispatchSync();
        $this->assertTrue($journal->claim($id, $generation, 'process-a'));

        $this->travel(20)->seconds();
        $this->assertTrue($journal->renewLease($generation, 'process-a'));
        $this->travel(30)->seconds();
        $this->assertNull($journal->acquireLease('process-b'));
        $this->assertFalse($journal->renewLease($generation, 'process-a'));
        $this->assertFalse($journal->acknowledge($id, $generation, 'process-a'));

        SweepRunnerOutbox::dispatchSync();
        $this->assertDatabaseHas('runner_commands', ['id' => $id, 'status' => 'reconciliation_required']);
        $this->assertTrue($journal->confirmStopped($generation, 'Supervisor verified process tree exit'));
        $replacement = $journal->acquireLease('process-b');
        $this->assertIsInt($replacement);
        $this->assertGreaterThan($generation, $replacement);
        $this->assertFalse($journal->confirmStopped($generation, 'Duplicate exit notification'));
        $this->assertFalse($journal->claim($id, $replacement, 'process-b'));
        $this->assertDatabaseHas('runner_leases', ['generation' => $generation, 'active_slot' => null, 'stop_evidence' => 'Supervisor verified process tree exit']);
    }

    public function test_late_sweep_reconciles_missed_publication_and_ack_without_resending(): void
    {
        $this->freezeTime();
        $claimed = $this->startPlanning();
        $journal = new ManageRunnerCommands;
        $generation = $journal->acquireLease('process-a');
        $this->assertIsInt($generation);
        SweepRunnerOutbox::dispatchSync();
        $this->assertTrue($journal->claim($claimed, $generation, 'process-a'));
        $unpublished = $this->startPlanning();

        $this->travel(120)->seconds();
        SweepRunnerOutbox::dispatchSync();
        SweepRunnerOutbox::dispatchSync();

        foreach ([$claimed, $unpublished] as $id) {
            $this->assertDatabaseHas('runner_commands', ['id' => $id, 'status' => 'reconciliation_required']);
        }
        $this->assertDatabaseHas('runner_outbox', ['command_id' => $unpublished, 'published_at' => null]);
    }

    public function test_claim_samples_lease_time_after_the_write_transaction_begins(): void
    {
        $this->freezeTime();
        $id = $this->startPlanning();
        $journal = new ManageRunnerCommands;
        $generation = $journal->acquireLease('process-a');
        $this->assertIsInt($generation);
        SweepRunnerOutbox::dispatchSync();

        Event::listen(TransactionBeginning::class, function (): void {
            $this->travel(30)->seconds();
        });

        $this->assertFalse($journal->claim($id, $generation, 'process-a'));
        $this->assertDatabaseHas('runner_commands', ['id' => $id, 'status' => 'pending']);
    }

    public function test_sweep_with_an_elapsed_budget_leaves_publication_for_the_next_run(): void
    {
        $this->freezeTime();
        $id = $this->startPlanning();

        (new ManageRunnerCommands)->sweep(hrtime(true));
        $this->assertDatabaseHas('runner_outbox', ['command_id' => $id, 'published_at' => null]);
        $this->assertDatabaseHas('runner_commands', ['id' => $id, 'status' => 'unpublished']);

        SweepRunnerOutbox::dispatchSync();
        $this->assertDatabaseHas('runner_outbox', ['command_id' => $id, 'published_at' => now()->timestamp]);
    }

    public function test_an_expired_backlog_does_not_delay_live_publication(): void
    {
        $this->freezeTime();

        for ($index = 0; $index < 101; $index++) {
            $this->startPlanning(sprintf('00000000-0000-4000-8000-%012d', $index));
        }

        $this->travel(120)->seconds();
        $live = $this->startPlanning('ffffffff-ffff-4fff-8fff-ffffffffffff');
        SweepRunnerOutbox::dispatchSync();

        $this->assertSame(100, DB::table('runner_commands')->where('status', 'reconciliation_required')->count());
        $this->assertDatabaseHas('runner_outbox', ['command_id' => $live, 'published_at' => now()->timestamp]);
    }

    public function test_upgrade_records_existing_planning_work_for_reconciliation(): void
    {
        $id = $this->startPlanning();
        $migration = require database_path('migrations/2026_11_18_092821_create_runner_command_journal_tables.php');
        $migration->down();
        $migration->up();

        SweepRunnerOutbox::dispatchSync();

        $this->assertDatabaseHas('runner_commands', [
            'id' => $id,
            'status' => 'reconciliation_required',
            'reconciliation_reason' => 'planning_predates_journal',
        ]);
        $this->assertDatabaseHas('runner_outbox', ['command_id' => $id, 'published_at' => null]);
    }

    public function test_failed_upgrade_rolls_back_journal_tables_and_can_retry(): void
    {
        $id = $this->startPlanning();
        $migration = require database_path('migrations/2026_11_18_092821_create_runner_command_journal_tables.php');
        $migration->down();
        $interrupt = true;
        DB::listen(function (QueryExecuted $query) use (&$interrupt): void {
            if ($interrupt && str_starts_with($query->sql, 'insert into "runner_outbox"')) {
                throw new DomainException('Interrupted backfill');
            }
        });

        try {
            $migration->up();
            $this->fail('Expected the backfill to be interrupted.');
        } catch (DomainException $exception) {
            $this->assertSame('Interrupted backfill', $exception->getMessage());
        } finally {
            $interrupt = false;
        }

        foreach (['runner_leases', 'runner_commands', 'runner_outbox'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }

        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('runner_outbox', ['command_id' => $id, 'published_at' => null]);
    }

    public function test_upgrade_and_sweep_can_retry_after_a_reader_blocks_sqlite_commit(): void
    {
        $path = tempnam(storage_path('framework/testing'), 'outbox-');
        $this->assertIsString($path);
        $connection = array_replace(config('database.connections.sqlite'), [
            'url' => null,
            'database' => $path,
            'busy_timeout' => 30,
            'journal_mode' => 'delete',
        ]);
        config(['database.connections.outbox_test' => $connection]);

        try {
            DB::usingConnection('outbox_test', function () use ($path): void {
                Schema::clearResolvedInstance('db.schema');
                (require database_path('migrations/2026_11_18_084503_create_workflow_state_tables.php'))->up();
                $migration = require database_path('migrations/2026_11_18_092821_create_runner_command_journal_tables.php');
                $reader = new PDO('sqlite:'.$path);
                $reader->beginTransaction();
                $reader->query('select id from workflows')->fetchColumn();

                try {
                    $migration->up();
                    $this->fail('Expected the read transaction to block the migration commit.');
                } catch (PDOException $exception) {
                    $this->assertStringContainsString('database is locked', $exception->getMessage());
                    $this->assertFalse(DB::connection()->getPdo()->inTransaction());
                } finally {
                    $reader->rollBack();
                }

                $this->assertFalse(Schema::hasTable('runner_outbox'));
                $migration->up();
                $id = $this->startPlanning();

                $reader->beginTransaction();
                $reader->query('select command_id from runner_outbox')->fetchColumn();

                try {
                    SweepRunnerOutbox::dispatchSync();
                    $this->fail('Expected the read transaction to block the sweep commit.');
                } catch (PDOException $exception) {
                    $this->assertStringContainsString('database is locked', $exception->getMessage());
                    $this->assertFalse(DB::connection()->getPdo()->inTransaction());
                } finally {
                    $reader->rollBack();
                }

                $this->assertDatabaseHas('runner_outbox', ['command_id' => $id, 'published_at' => null]);
                SweepRunnerOutbox::dispatchSync();
                $this->assertDatabaseHas('runner_commands', ['id' => $id, 'status' => 'pending']);
            });
        } finally {
            Schema::clearResolvedInstance('db.schema');
            DB::purge('outbox_test');
            unlink($path);
        }
    }

    public function test_amended_workflow_invalidates_previously_published_planning_command(): void
    {
        $id = $this->startPlanning();
        [$workflowId] = explode(':', $id);
        $journal = new ManageRunnerCommands;
        $generation = $journal->acquireLease('process-a');
        $this->assertIsInt($generation);
        SweepRunnerOutbox::dispatchSync();
        (new ManageWorkflowState)->apply($workflowId, 2, WorkflowCommand::AmendRequest, 'user:1', 'Scope amended', 'Update docs.');

        $this->assertFalse($journal->claim($id, $generation, 'process-a'));
        SweepRunnerOutbox::dispatchSync();
        $this->assertDatabaseHas('runner_commands', ['id' => $id, 'status' => 'reconciliation_required']);
    }

    public function test_amendment_waits_until_an_unresolved_sender_is_confirmed_stopped(): void
    {
        $this->freezeTime();
        $id = $this->startPlanning();
        [$workflowId] = explode(':', $id);
        $journal = new ManageRunnerCommands;
        $generation = $journal->acquireLease('process-a');
        $this->assertIsInt($generation);
        SweepRunnerOutbox::dispatchSync();
        $this->assertTrue($journal->claim($id, $generation, 'process-a'));

        $state = new ManageWorkflowState;
        foreach ([0, 30] as $seconds) {
            $this->travel($seconds)->seconds();
            SweepRunnerOutbox::dispatchSync();

            try {
                $state->apply($workflowId, 2, WorkflowCommand::AmendRequest, 'user:1', 'Scope amended', 'Update docs.');
                $this->fail('Expected the unresolved send to block the amendment.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('unresolved send', $exception->getMessage());
            }

            $this->assertDatabaseHas('workflows', ['id' => $workflowId, 'version' => 2]);
            $this->assertDatabaseCount('workflow_events', 2);
        }

        $this->assertTrue($journal->confirmStopped($generation, 'Supervisor verified process tree exit'));
        $this->assertSame(3, $state->apply($workflowId, 2, WorkflowCommand::AmendRequest, 'user:1', 'Scope amended', 'Update docs.'));
    }

    private function startPlanning(?string $workflowId = null): string
    {
        $state = new ManageWorkflowState;
        $workflowId ??= (string) Str::uuid();
        $state->create($workflowId, 'Update README.', 'user:1', 'Submitted');
        $state->apply($workflowId, 1, WorkflowCommand::BeginPlanning, 'orchestrator', 'Context ready');

        return "$workflowId:2:begin_planning";
    }
}
