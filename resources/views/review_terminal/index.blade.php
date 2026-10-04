@extends('layouts.app')

@section('title', 'AuditCortex | Autonomous Agentic Code Reviewer & PR Security Auditor')

@section('content')
<div class="terminal-layout">

    <!-- Hero Banner -->
    <div class="glass-panel glass-panel-glow" style="padding: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                <span class="badge-pill badge-cyan">v1.0 Autonomous Agentic Loop</span>
                <span class="badge-pill badge-purple">Tree-sitter AST &bull; LangGraph Coordination</span>
            </div>
            <h2 style="font-size: 1.875rem; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 0.5rem;">
                Autonomous Agentic Code Reviewer &amp; PR Security Auditor
            </h2>
            <p style="color: var(--text-muted); max-width: 820px; font-size: 0.9375rem;">
                Catching boilerplate and cross-file security vulnerabilities before production. Orchestrating <strong>AST Linter</strong>, <strong>Security Critic</strong>, and <strong>Fix Synthesizer</strong> agents locally via Ollama / vLLM with zero code-leak risk.
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <button onclick="seedSamplePRs()" class="btn btn-primary" id="seedBtn">
                ⚡ Seed Real-World Attack Scenarios
            </button>
            <button onclick="triggerSimulateModal()" class="btn btn-secondary">
                Simulate Webhook
            </button>
        </div>
    </div>

    <!-- Live Metrics Overview -->
    <div class="metrics-grid">
        <div class="glass-panel metric-card">
            <div class="metric-header">
                <span class="metric-title">Monitored Repos</span>
                <span style="font-size: 1.25rem;">📦</span>
            </div>
            <div class="metric-value" style="color: var(--cyan-glow);">{{ $stats['total_repositories'] }}</div>
            <div class="metric-sub">Active GitHub / GitLab integrations</div>
        </div>

        <div class="glass-panel metric-card">
            <div class="metric-header">
                <span class="metric-title">Screened Pull Requests</span>
                <span style="font-size: 1.25rem;">🔀</span>
            </div>
            <div class="metric-value">{{ $stats['total_prs'] }}</div>
            <div class="metric-sub">Multi-pass autonomous audits</div>
        </div>

        <div class="glass-panel metric-card">
            <div class="metric-header">
                <span class="metric-title">Critical Flaws Mitigated</span>
                <span style="font-size: 1.25rem;">🔥</span>
            </div>
            <div class="metric-value" style="color: var(--crimson-crit);">{{ $stats['critical_vulnerabilities'] }}</div>
            <div class="metric-sub">SQLi, RCE, SSRF, Deserialization</div>
        </div>

        <div class="glass-panel metric-card">
            <div class="metric-header">
                <span class="metric-title">AST Patches Synthesized</span>
                <span style="font-size: 1.25rem;">⚡</span>
            </div>
            <div class="metric-value" style="color: var(--emerald-safe);">{{ $stats['patches_generated'] }}</div>
            <div class="metric-sub">Tree-sitter syntax-verified diffs</div>
        </div>

        <div class="glass-panel metric-card">
            <div class="metric-header">
                <span class="metric-title">Fleet Security Score</span>
                <span style="font-size: 1.25rem;">🛡️</span>
            </div>
            <div class="metric-value" style="color: {{ $stats['avg_security_score'] > 80 ? 'var(--emerald-safe)' : ($stats['avg_security_score'] > 50 ? 'var(--amber-warn)' : 'var(--crimson-crit)') }};">
                {{ $stats['avg_security_score'] }}<span style="font-size: 1rem; color: var(--text-dim);">/100</span>
            </div>
            <div class="metric-sub">Repository health baseline</div>
        </div>
    </div>

    <!-- 1-Click Interactive Attack Vector Demos -->
    <div class="simulator-box">
        <div class="simulator-header">
            <div>
                <h3 style="font-size: 1.125rem; font-weight: 700; color: white;">🎯 Instant Autonomous Audit Demos</h3>
                <p style="font-size: 0.8125rem; color: var(--text-muted);">
                    Select any real-world vulnerable pull request below to run the multi-agent graph immediately.
                </p>
            </div>
        </div>

        <div class="simulator-grid">
            <div class="sample-card">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <span class="sample-title" style="color: #fda4af;">Python Cloud Service</span>
                        <span class="badge-pill badge-crimson">CWE-89 &bull; CWE-78</span>
                    </div>
                    <p class="sample-desc">
                        Contains Raw SQL Injection, Command Injection in ping host, Insecure Pickle deserialization, and typosquatted PyPI dependency <code style="color: var(--cyan-glow);">reqeusts</code>.
                    </p>
                </div>
                <button onclick="executeSimulation('python_cloud_service')" class="btn btn-danger" style="width: 100%;">
                    ⚡ Audit Python PR &rarr;
                </button>
            </div>

            <div class="sample-card">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <span class="sample-title" style="color: #fde047;">Node.js Webhook Relay</span>
                        <span class="badge-pill badge-amber">CWE-918 &bull; Supply Chain</span>
                    </div>
                    <p class="sample-desc">
                        Contains Server-Side Request Forgery (SSRF) in Axios client, child_process execution, and toxic backdoored dependencies <code style="color: var(--cyan-glow);">flatmap-stream</code> &amp; <code style="color: var(--cyan-glow);">crossenv</code>.
                    </p>
                </div>
                <button onclick="executeSimulation('node_webhook_relay')" class="btn btn-primary" style="width: 100%;">
                    ⚡ Audit Node.js PR &rarr;
                </button>
            </div>

            <div class="sample-card">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <span class="sample-title" style="color: #d8b4fe;">PHP Laravel Portal</span>
                        <span class="badge-pill badge-purple">CWE-89 &bull; CWE-502</span>
                    </div>
                    <p class="sample-desc">
                        Contains Raw SQL Injection in <code style="color: var(--cyan-glow);">DB::raw</code>, shell execution in LibreOffice converter, and PHP Object Injection via <code style="color: var(--cyan-glow);">unserialize()</code>.
                    </p>
                </div>
                <button onclick="executeSimulation('php_laravel_reports')" class="btn btn-secondary" style="width: 100%; border-color: rgba(168, 85, 247, 0.4);">
                    ⚡ Audit PHP PR &rarr;
                </button>
            </div>
        </div>
    </div>

    <!-- Recent Pull Requests Table -->
    <div class="glass-panel" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h3 style="font-size: 1.125rem; font-weight: 700;">Active Pull Requests &amp; Security Audits</h3>
                <p style="font-size: 0.8125rem; color: var(--text-muted);">
                    Historical inspection sessions screened across connected repositories.
                </p>
            </div>
        </div>

        @if($recentPullRequests->isEmpty())
            <div style="text-align: center; padding: 3rem 1rem;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🛡️</div>
                <h4 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">No Pull Requests Audited Yet</h4>
                <p style="color: var(--text-muted); font-size: 0.875rem; max-width: 480px; margin: 0 auto 1.5rem auto;">
                    Click "Seed Real-World Attack Scenarios" or select any of the demo PR cards above to launch the autonomous agent review loop.
                </p>
                <button onclick="seedSamplePRs()" class="btn btn-primary">
                    ⚡ Seed Real-World Attack Scenarios
                </button>
            </div>
        @else
            <div style="overflow-x: auto;">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Repository &amp; PR</th>
                            <th>Author</th>
                            <th>Branch</th>
                            <th>Security Score</th>
                            <th>Vulnerabilities</th>
                            <th>Status</th>
                            <th>Review Session</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentPullRequests as $pr)
                            @php
                                $session = $pr->latestReviewSession;
                                $score = $session?->security_score ?? 100;
                            @endphp
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: white;">
                                        #{{ $pr->pr_number }} {{ $pr->title }}
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-dim); font-family: var(--font-mono);">
                                        {{ $pr->repository->repo_full_name }}
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        @if($pr->author_avatar_url)
                                            <img src="{{ $pr->author_avatar_url }}" style="width: 22px; height: 22px; border-radius: 50%;">
                                        @endif
                                        <span>{{ $pr->author_username ?? 'contributor' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-pill badge-cyan" style="font-family: var(--font-mono); font-size: 0.6875rem;">
                                        {{ $pr->head_branch }} &rarr; {{ $pr->base_branch }}
                                    </span>
                                </td>
                                <td>
                                    @if($session)
                                        <span class="badge-pill {{ $score > 80 ? 'badge-emerald' : ($score > 40 ? 'badge-amber' : 'badge-crimson') }}">
                                            {{ $score }} / 100
                                        </span>
                                    @else
                                        <span class="badge-pill badge-secondary">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @if($session)
                                        <div style="display: flex; gap: 0.375rem; align-items: center;">
                                            @if($session->critical_count > 0)
                                                <span class="badge-pill badge-crimson" style="padding: 0.15rem 0.4rem; font-size: 0.6875rem;">
                                                    {{ $session->critical_count }} Crit
                                                </span>
                                            @endif
                                            @if($session->high_count > 0)
                                                <span class="badge-pill badge-amber" style="padding: 0.15rem 0.4rem; font-size: 0.6875rem;">
                                                    {{ $session->high_count }} High
                                                </span>
                                            @endif
                                            @if($session->total_findings == 0)
                                                <span class="badge-pill badge-emerald" style="padding: 0.15rem 0.4rem; font-size: 0.6875rem;">
                                                    Clean
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span style="color: var(--text-dim);">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($session && $session->status === 'completed')
                                        <span class="badge-pill badge-emerald">Audited</span>
                                    @elseif($session && $session->status === 'analyzing')
                                        <span class="badge-pill badge-amber">
                                            <span class="pulse-dot"></span> Analyzing
                                        </span>
                                    @else
                                        <span class="badge-pill badge-cyan">Ready</span>
                                    @endif
                                </td>
                                <td>
                                    <span style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-dim);">
                                        {{ $session ? substr($session->session_uuid, 0, 8) . '...' : '-' }}
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="{{ route('terminal.show', $pr->id) }}" class="btn btn-primary" style="padding: 0.35rem 0.85rem; font-size: 0.8125rem;">
                                        Open Review Terminal &rarr;
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

<script>
async function seedSamplePRs() {
    const btn = document.getElementById('seedBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '⚡ Seeding &amp; Auditing Scenarios...';
    btn.disabled = true;

    try {
        const res = await axios.post('/api/engine/seed-samples');
        if (res.data && res.data.pull_requests && res.data.pull_requests.length > 0) {
            window.location.href = '/terminal/' + res.data.pull_requests[0].pr_id;
        } else {
            window.location.reload();
        }
    } catch (e) {
        alert('Seeding failed: ' + (e.response?.data?.message || e.message));
        btn.innerHTML = orig;
        btn.disabled = false;
    }
}
</script>
@endsection
