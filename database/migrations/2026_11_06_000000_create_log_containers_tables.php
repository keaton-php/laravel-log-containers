<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('container_key')->index();
            $table->foreignId('parent_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('status')->default('open');
            $table->json('context')->nullable();
            $table->unsignedInteger('entry_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('log_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('container_key')->index();
            $table->string('level')->index();
            $table->text('message');
            $table->string('type')->nullable()->index();
            $table->string('doing')->nullable();
            $table->string('during')->nullable();
            $table->foreignId('session_id')->nullable()->index();
            $table->string('execution_id')->nullable()->index();
            $table->json('context')->nullable();
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();
            $table->string('exception_file')->nullable();
            $table->unsignedInteger('exception_line')->nullable();
            $table->longText('exception_trace')->nullable();
            // A string id, so integer, UUID and ULID authenticatable keys all fit.
            $table->string('user_id')->nullable();
            $table->string('environment')->nullable();
            $table->json('triggered_watches')->nullable();
            $table->timestamps();

            $table->index(['container_key', 'level']);
            $table->index(['container_key', 'created_at']);
        });

        Schema::create('log_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('log_entry_id')->index();
            // String ids so any Eloquent key type fits.
            $table->string('taggable_type')->nullable();
            $table->string('taggable_id')->nullable();
            $table->string('label');
            $table->timestamps();

            $table->index(['taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_tags');
        Schema::dropIfExists('log_entries');
        Schema::dropIfExists('log_sessions');
    }
};
