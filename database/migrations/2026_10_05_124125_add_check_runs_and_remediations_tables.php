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
        Schema::create('check_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->default('AuditCortex Autonomous Security Gate');
            $table->string('head_sha');
            $table->string('status')->default('completed'); // queued, in_progress, completed
            $table->string('conclusion')->default('failure'); // success, failure, neutral, action_required
            $table->string('title');
            $table->text('summary');
            $table->longText('text')->nullable();
            $table->string('github_check_run_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('remediation_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('branch_name');
            $table->string('base_branch');
            $table->string('commit_sha')->nullable();
            $table->string('status')->default('CREATED'); // CREATED, COMMITTED, PR_OPENED
            $table->string('pr_url')->nullable();
            $table->integer('patches_applied_count')->default(0);
            $table->text('diff_stat')->nullable();
            $table->timestamps();
        });

        Schema::create('inline_discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vulnerability_finding_id')->constrained()->cascadeOnDelete();
            $table->string('sender')->default('developer'); // developer, security_critic
            $table->longText('message');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inline_discussions');
        Schema::dropIfExists('remediation_branches');
        Schema::dropIfExists('check_runs');
    }
};
