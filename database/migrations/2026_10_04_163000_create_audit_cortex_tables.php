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
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('github'); // github, gitlab
            $table->string('name');
            $table->string('owner');
            $table->string('repo_full_name')->unique();
            $table->string('default_branch')->default('main');
            $table->string('webhook_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->integer('pr_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('head_branch');
            $table->string('base_branch');
            $table->string('head_sha');
            $table->string('base_sha')->nullable();
            $table->string('author_username')->nullable();
            $table->string('author_avatar_url')->nullable();
            $table->string('status')->default('open'); // open, closed, merged
            $table->text('diff_url')->nullable();
            $table->longText('raw_diff')->nullable();
            $table->timestamps();

            $table->unique(['repository_id', 'pr_number']);
        });

        Schema::create('review_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->uuid('session_uuid')->unique();
            $table->string('status')->default('queued'); // queued, analyzing, critic_review, synthesizing, completed, failed
            $table->integer('security_score')->default(100); // 0-100
            $table->integer('critical_count')->default(0);
            $table->integer('high_count')->default(0);
            $table->integer('medium_count')->default(0);
            $table->integer('low_count')->default(0);
            $table->integer('total_findings')->default(0);
            $table->integer('execution_time_ms')->default(0);
            $table->string('llm_runner_mode')->default('local_qwen_coder');
            $table->json('llm_runner_info')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vulnerability_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_session_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->integer('line_start');
            $table->integer('line_end');
            $table->string('severity')->default('MEDIUM'); // CRITICAL, HIGH, MEDIUM, LOW, INFO
            $table->string('cwe_id')->nullable(); // CWE-89, CWE-78, CWE-918, CWE-22, CWE-502, CWE-798, etc.
            $table->string('vulnerability_type');
            $table->string('title');
            $table->text('description');
            $table->text('dangerous_sink')->nullable();
            $table->text('taint_source')->nullable();
            $table->float('confidence_score')->default(0.85);
            $table->string('critic_verdict')->default('CONFIRMED'); // CONFIRMED, DOWNGRADED, REJECTED_FALSE_POSITIVE
            $table->text('critic_reasoning')->nullable();
            $table->string('ast_node_type')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('patch_diffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vulnerability_finding_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->longText('original_code');
            $table->longText('fixed_code');
            $table->longText('unified_diff');
            $table->boolean('is_ast_verified')->default(true);
            $table->text('verification_notes')->nullable();
            $table->string('status')->default('GENERATED'); // GENERATED, APPLIED, COMMITTED
            $table->timestamps();
        });

        Schema::create('agent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_session_id')->constrained()->cascadeOnDelete();
            $table->string('agent_name'); // AST_LINTER, SECURITY_CRITIC, FIX_SYNTHESIZER, ORCHESTRATOR
            $table->string('phase');
            $table->integer('step_index')->default(1);
            $table->text('thought')->nullable();
            $table->text('action')->nullable();
            $table->longText('observation')->nullable();
            $table->integer('latency_ms')->default(0);
            $table->timestamps();
        });

        Schema::create('inline_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vulnerability_finding_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->integer('line_number');
            $table->string('github_comment_id')->nullable();
            $table->string('status')->default('DRAFT'); // DRAFT, PUBLISHED, DISMISSED
            $table->longText('body');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inline_comments');
        Schema::dropIfExists('agent_logs');
        Schema::dropIfExists('patch_diffs');
        Schema::dropIfExists('vulnerability_findings');
        Schema::dropIfExists('review_sessions');
        Schema::dropIfExists('pull_requests');
        Schema::dropIfExists('repositories');
    }
};
