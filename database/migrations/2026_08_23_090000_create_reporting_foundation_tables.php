<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category');
            $table->text('description')->nullable();
            $table->json('supported_filters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['category', 'is_active'], 'report_def_category_active_idx');
        });

        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('queued');
            $table->dateTime('period_start')->nullable();
            $table->dateTime('period_end')->nullable();
            $table->json('filters')->nullable();
            $table->json('result_metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['report_definition_id', 'status'], 'report_runs_definition_status_idx');
            $table->index(['facility_id', 'created_at'], 'report_runs_facility_created_idx');
            $table->index(['requested_by_id', 'created_at'], 'report_runs_requester_created_idx');
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('context')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id'], 'audit_auditable_idx');
            $table->index(['facility_id', 'occurred_at'], 'audit_facility_occurred_idx');
            $table->index(['actor_id', 'occurred_at'], 'audit_actor_occurred_idx');
            $table->index(['event_type', 'occurred_at'], 'audit_event_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('report_definitions');
    }
};
