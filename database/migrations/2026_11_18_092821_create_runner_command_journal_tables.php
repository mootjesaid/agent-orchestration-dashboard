<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();

        try {
            $connection->transaction(function (): void {
                if (Schema::hasTable('runner_leases') && Schema::hasTable('runner_commands') && Schema::hasTable('runner_outbox')) {
                    // The schema and backfill may have committed before the migrator recorded completion.
                    return;
                }

                $this->createJournal();
            });
        } catch (Throwable $exception) {
            // A blocked SQLite COMMIT can leave PDO open after Laravel resets its transaction level.
            if ($connection->transactionLevel() === 0 && $connection->getPdo()->inTransaction()) {
                $connection->getPdo()->rollBack();
            }

            throw $exception;
        }
    }

    private function createJournal(): void
    {
        Schema::create('runner_leases', function (Blueprint $table): void {
            $table->id('generation');
            $table->unsignedTinyInteger('active_slot')->nullable()->unique();
            $table->string('owner');
            $table->unsignedBigInteger('expires_at');
            $table->unsignedBigInteger('stopped_at')->nullable();
            $table->text('stop_evidence')->nullable();
        });

        Schema::create('runner_commands', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignUuid('workflow_id');
            $table->unsignedBigInteger('workflow_version');
            $table->string('kind');
            $table->string('status');
            $table->unsignedBigInteger('deadline');
            $table->foreignId('lease_generation')->nullable()->references('generation')->on('runner_leases');
            $table->string('reconciliation_reason')->nullable();
            $table->unsignedBigInteger('acknowledged_at')->nullable();
            $table->foreign(['workflow_id', 'workflow_version'])->references(['workflow_id', 'sequence'])->on('workflow_events');
            $table->index(['status', 'deadline', 'id']);
            $table->index(['lease_generation', 'status', 'id']);
        });

        Schema::create('runner_outbox', function (Blueprint $table): void {
            $table->string('command_id')->primary();
            $table->unsignedBigInteger('published_at')->nullable();
            $table->foreign('command_id')->references('id')->on('runner_commands');
        });

        DB::table('workflows')->select('id', 'version')->where('status', 'planning')
            ->chunkById(100, function ($workflows): void {
                $deadline = now()->getTimestamp();
                $commands = $workflows->map(fn (object $workflow): array => [
                    'id' => "$workflow->id:$workflow->version:begin_planning",
                    'workflow_id' => $workflow->id,
                    'workflow_version' => $workflow->version,
                    'kind' => 'begin_planning',
                    'status' => 'reconciliation_required',
                    'deadline' => $deadline,
                    'reconciliation_reason' => 'planning_predates_journal',
                ]);

                DB::table('runner_commands')->insert($commands->all());
                DB::table('runner_outbox')->insert($commands->map(fn (array $command): array => [
                    'command_id' => $command['id'],
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('runner_outbox');
        Schema::dropIfExists('runner_commands');
        Schema::dropIfExists('runner_leases');
    }
};
