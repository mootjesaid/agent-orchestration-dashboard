<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('registered_repositories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('root');
            $table->char('root_key', 64)->unique();
            $table->text('common_directory');
            $table->text('workspace_root');
            $table->char('directory_identity', 64);
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registered_repositories');
    }
};
