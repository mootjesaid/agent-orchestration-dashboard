<?php

namespace Tests\Feature\Actions\Workflows;

use App\Actions\Workflows\ManageWorkflowState;
use App\Enums\WorkflowCommand;
use DomainException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ManageWorkflowStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_keeps_immutable_request_history_and_ordered_audit_events(): void
    {
        [$state, $id] = $this->createWorkflow();

        $this->assertSame(2, $state->apply(strtoupper($id), 1, WorkflowCommand::RequestClarification, 'orchestrator', 'Missing detail'));
        $this->assertSame(3, $state->apply($id, 2, WorkflowCommand::AmendRequest, 'user:1', 'Answer supplied', 'Update the README only.'));
        $this->assertSame(4, $state->apply($id, 3, WorkflowCommand::BeginPlanning, 'orchestrator', 'Context is sufficient'));

        $this->assertDatabaseHas('workflows', ['id' => $id, 'status' => 'planning', 'version' => 4, 'request_revision' => 2]);
        $this->assertSame(
            ['Update the README.', 'Update the README only.'],
            DB::table('workflow_request_revisions')->where('workflow_id', $id)->orderBy('revision')->pluck('request')->all(),
        );
        $this->assertSame(
            [[1, null, 'inspecting'], [2, 'inspecting', 'clarifying'], [3, 'clarifying', 'inspecting'], [4, 'inspecting', 'planning']],
            DB::table('workflow_events')->where('workflow_id', $id)->orderBy('sequence')
                ->get()->map(fn (object $event): array => [(int) $event->sequence, $event->previous_status, $event->status])->all(),
        );
        $this->assertDatabaseHas('workflow_events', [
            'workflow_id' => $id, 'sequence' => 3, 'request_revision' => 2,
            'command' => 'amend_request', 'actor' => 'user:1', 'cause' => 'Answer supplied',
        ]);
    }

    public function test_invalid_and_replayed_commands_leave_state_and_audit_unchanged(): void
    {
        [$state, $id] = $this->createWorkflow();
        $state->apply($id, 1, WorkflowCommand::RequestClarification, 'orchestrator', 'Missing detail');

        foreach ([1, 2] as $version) {
            try {
                $state->apply($id, $version, WorkflowCommand::BeginPlanning, 'orchestrator', 'Premature plan');
                $this->fail('Expected the command to be rejected.');
            } catch (DomainException $exception) {
                $this->assertSame($version === 1
                    ? 'The expected workflow version is unavailable.'
                    : 'The workflow command is invalid in the current state.', $exception->getMessage());
            }
        }

        $this->assertDatabaseHas('workflows', ['id' => $id, 'status' => 'clarifying', 'version' => 2]);
        $this->assertDatabaseCount('workflow_events', 2);
    }

    public function test_duplicate_creation_cannot_replace_the_original_request(): void
    {
        [$state, $id] = $this->createWorkflow();

        try {
            $state->create(strtoupper($id), 'Replacement request', 'user:1', 'Retry');
            $this->fail('Expected the duplicate workflow ID to be rejected.');
        } catch (UniqueConstraintViolationException) {
            $this->assertDatabaseHas('workflow_request_revisions', ['workflow_id' => $id, 'revision' => 1, 'request' => 'Update the README.']);
            $this->assertDatabaseCount('workflow_request_revisions', 1);
            $this->assertDatabaseCount('workflow_events', 1);
        }
    }

    public function test_a_command_that_loses_the_version_race_cannot_append_an_event(): void
    {
        $database = tempnam(storage_path('framework/testing'), 'workflow-');
        $this->assertIsString($database);
        $connection = array_replace(config('database.connections.sqlite'), ['url' => null, 'database' => $database]);
        config(['database.connections.workflow_left' => $connection, 'database.connections.workflow_right' => $connection]);

        try {
            DB::usingConnection('workflow_left', function (): void {
                Schema::clearResolvedInstance('db.schema');
                $migration = require database_path('migrations/2026_11_18_084503_create_workflow_state_tables.php');
                $migration->up();
                [$state, $id] = $this->createWorkflow();

                DB::listen(function (QueryExecuted $query) use ($state, $id): void {
                    if ($query->connectionName === 'workflow_left' && str_starts_with($query->sql, 'select * from "workflows"')) {
                        DB::usingConnection('workflow_right', fn () => $state->apply(
                            $id, 1, WorkflowCommand::AmendRequest, 'user:1', 'Competing command', 'Revised scope.',
                        ));
                    }
                });

                try {
                    $state->apply($id, 1, WorkflowCommand::AmendRequest, 'user:1', 'Original command', 'Stale scope.');
                    $this->fail('Expected the command with a superseded read to be rejected.');
                } catch (DomainException $exception) {
                    $this->assertSame('The expected workflow version is unavailable.', $exception->getMessage());
                }

                $this->assertDatabaseHas('workflows', ['id' => $id, 'version' => 2, 'request_revision' => 2]);
                $this->assertDatabaseHas('workflow_request_revisions', ['workflow_id' => $id, 'revision' => 2, 'request' => 'Revised scope.']);
                $this->assertDatabaseHas('workflow_events', ['workflow_id' => $id, 'sequence' => 2, 'cause' => 'Competing command']);
                $this->assertDatabaseCount('workflow_request_revisions', 2);
                $this->assertDatabaseCount('workflow_events', 2);
            });
        } finally {
            Schema::clearResolvedInstance('db.schema');
            DB::purge('workflow_left');
            DB::purge('workflow_right');
            unlink($database);
        }
    }

    public function test_sqlite_rejects_rewriting_a_request_revision(): void
    {
        [, $id] = $this->createWorkflow();

        try {
            DB::table('workflow_request_revisions')->where('workflow_id', $id)->update(['request' => 'Rewritten text']);
            $this->fail('Expected SQLite to reject a historical revision update.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Workflow request revisions are immutable', $exception->getMessage());
        }

        $this->assertDatabaseHas('workflow_request_revisions', ['workflow_id' => $id, 'revision' => 1, 'request' => 'Update the README.']);
    }

    public function test_event_write_failure_rolls_back_the_state_update_and_request_revision(): void
    {
        [$state, $id] = $this->createWorkflow();
        DB::unprepared("CREATE TEMP TRIGGER reject_workflow_event BEFORE INSERT ON workflow_events BEGIN SELECT RAISE(ABORT, 'audit failure'); END");

        try {
            $state->apply($id, 1, WorkflowCommand::AmendRequest, 'user:1', 'Revised scope', 'Replacement request');
            $this->fail('Expected the audit write to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('audit failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER reject_workflow_event');
        }

        $this->assertDatabaseHas('workflows', ['id' => $id, 'status' => 'inspecting', 'version' => 1, 'request_revision' => 1]);
        $this->assertDatabaseCount('workflow_request_revisions', 1);
        $this->assertDatabaseCount('workflow_events', 1);
    }

    public function test_blank_amendments_leave_the_request_revision_unchanged(): void
    {
        [$state, $id] = $this->createWorkflow();

        try {
            $state->apply($id, 1, WorkflowCommand::AmendRequest, 'user:1', 'Amendment', ' ');
            $this->fail('Expected the empty amendment to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Request text is required.', $exception->getMessage());
        }

        $this->assertDatabaseHas('workflows', ['id' => $id, 'version' => 1, 'request_revision' => 1]);
        $this->assertDatabaseCount('workflow_request_revisions', 1);
    }

    public function test_approval_is_unavailable_as_a_workflow_command(): void
    {
        $this->assertNull(WorkflowCommand::tryFrom('approve_plan'));
        $this->assertNull(WorkflowCommand::tryFrom('ready'));
    }

    /**
     * @return array{ManageWorkflowState, string}
     */
    private function createWorkflow(): array
    {
        $state = new ManageWorkflowState;
        $id = (string) Str::uuid();
        $state->create($id, 'Update the README.', 'user:1', 'Submitted request');

        return [$state, $id];
    }
}
