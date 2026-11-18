<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status');
            $table->unsignedBigInteger('version');
            $table->unsignedBigInteger('request_revision');
            $table->timestamps();
        });

        Schema::create('workflow_request_revisions', function (Blueprint $table) {
            $table->foreignUuid('workflow_id')->constrained();
            $table->unsignedBigInteger('revision');
            $table->text('request');
            $table->primary(['workflow_id', 'revision']);
        });

        Schema::create('workflow_events', function (Blueprint $table) {
            $table->foreignUuid('workflow_id')->constrained();
            $table->unsignedBigInteger('sequence');
            $table->unsignedBigInteger('request_revision');
            $table->string('command');
            $table->string('previous_status')->nullable();
            $table->string('status');
            $table->text('actor');
            $table->text('cause');
            $table->timestamp('created_at');
            $table->primary(['workflow_id', 'sequence']);
            $table->foreign(['workflow_id', 'request_revision'], 'workflow_event_revision_fk')
                ->references(['workflow_id', 'revision'])->on('workflow_request_revisions');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER workflow_request_revisions_reject_update
                BEFORE UPDATE ON workflow_request_revisions
                BEGIN SELECT RAISE(ABORT, 'Workflow request revisions are immutable'); END
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER workflow_request_revisions_reject_delete
                BEFORE DELETE ON workflow_request_revisions
                BEGIN SELECT RAISE(ABORT, 'Workflow request revisions are immutable'); END
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER workflow_request_revisions_reject_replace
                BEFORE INSERT ON workflow_request_revisions
                WHEN EXISTS (
                    SELECT 1 FROM workflow_request_revisions
                    WHERE workflow_id = NEW.workflow_id AND revision = NEW.revision
                )
                BEGIN SELECT RAISE(ABORT, 'Workflow request revisions are immutable'); END
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_events');
        Schema::dropIfExists('workflow_request_revisions');
        Schema::dropIfExists('workflows');
    }
};
