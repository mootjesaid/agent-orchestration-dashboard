<?php

namespace App\Actions\Workflows;

use App\Enums\WorkflowCommand;
use App\Enums\WorkflowStatus;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ManageWorkflowState
{
    /**
     * Actor and cause are supplied by trusted Laravel callers for attribution.
     */
    public function create(string $workflowId, string $request, string $actor, string $cause): void
    {
        $this->validateRequest($request);

        if (! Str::isUuid($workflowId)) {
            throw new InvalidArgumentException('The workflow ID must be a UUID.');
        }

        $workflowId = strtolower($workflowId);

        DB::transaction(function () use ($workflowId, $request, $actor, $cause): void {
            $now = now();
            DB::table('workflows')->insert([
                'id' => $workflowId,
                'status' => WorkflowStatus::Inspecting->value,
                'version' => 1,
                'request_revision' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->appendRequest($workflowId, 1, $request);
            $this->appendEvent($workflowId, 1, 1, 'create', null, WorkflowStatus::Inspecting, $actor, $cause);
        });
    }

    public function apply(
        string $workflowId,
        int $expectedVersion,
        WorkflowCommand $command,
        string $actor,
        string $cause,
        ?string $request = null,
    ): int {
        if ($command === WorkflowCommand::AmendRequest) {
            $this->validateRequest($request);
        } elseif ($request !== null) {
            throw new InvalidArgumentException('Only request amendments may supply request text.');
        }

        $workflowId = strtolower($workflowId);
        $workflow = DB::table('workflows')->where('id', $workflowId)->first();

        if ($workflow === null || (int) $workflow->version !== $expectedVersion) {
            throw new DomainException('The expected workflow version is unavailable.');
        }

        $previous = WorkflowStatus::from($workflow->status);
        $next = match (true) {
            $command === WorkflowCommand::RequestClarification && $previous === WorkflowStatus::Inspecting => WorkflowStatus::Clarifying,
            $command === WorkflowCommand::AmendRequest => WorkflowStatus::Inspecting,
            $command === WorkflowCommand::BeginPlanning && $previous === WorkflowStatus::Inspecting => WorkflowStatus::Planning,
            default => throw new DomainException('The workflow command is invalid in the current state.'),
        };
        $version = $expectedVersion + 1;
        $revision = (int) $workflow->request_revision + ($request === null ? 0 : 1);

        DB::transaction(function () use ($workflowId, $expectedVersion, $command, $actor, $cause, $request, $previous, $next, $version, $revision): void {
            $updated = DB::table('workflows')
                ->where('id', $workflowId)
                ->where('version', $expectedVersion)
                ->where('status', $previous->value)
                ->update([
                    'status' => $next->value,
                    'version' => $version,
                    'request_revision' => $revision,
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                throw new DomainException('The expected workflow version is unavailable.');
            }

            if ($request !== null) {
                $this->appendRequest($workflowId, $revision, $request);
            }

            $this->appendEvent($workflowId, $version, $revision, $command->value, $previous, $next, $actor, $cause);
        });

        return $version;
    }

    private function validateRequest(?string $request): void
    {
        if (blank($request)) {
            throw new InvalidArgumentException('Request text is required.');
        }
    }

    private function appendRequest(string $workflowId, int $revision, string $request): void
    {
        DB::table('workflow_request_revisions')->insert([
            'workflow_id' => $workflowId,
            'revision' => $revision,
            'request' => $request,
        ]);
    }

    private function appendEvent(
        string $workflowId,
        int $sequence,
        int $revision,
        string $command,
        ?WorkflowStatus $previous,
        WorkflowStatus $status,
        string $actor,
        string $cause,
    ): void {
        DB::table('workflow_events')->insert([
            'workflow_id' => $workflowId,
            'sequence' => $sequence,
            'request_revision' => $revision,
            'command' => $command,
            'previous_status' => $previous?->value,
            'status' => $status->value,
            'actor' => $actor,
            'cause' => $cause,
            'created_at' => now(),
        ]);
    }
}
