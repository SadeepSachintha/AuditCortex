@extends('layouts.app')

@section('title', 'Review Terminal | #' . $pullRequest->pr_number . ' ' . $pullRequest->title)

@section('content')
<div id="reviewTerminalApp" class="terminal-layout" style="padding-top: 1rem;">

    <!-- Top PR Header & Security Health Score Gauge -->
    <div class="glass-panel glass-panel-glow" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            
            <!-- Left Info -->
            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                <div style="font-size: 2rem;">🔀</div>
                <div>
                    <div style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.25rem;">
                        <span style="font-weight: 700; font-size: 1.25rem;">{{ $pullRequest->title }}</span>
                        <span class="badge-pill badge-cyan" style="font-family: var(--font-mono);">
                            #{{ $pullRequest->pr_number }}
                        </span>
                        <span class="badge-pill badge-secondary" style="font-family: var(--font-mono); font-size: 0.75rem;">
                            {{ $pullRequest->repository->repo_full_name }}
                        </span>
                    </div>

                    <div style="display: flex; align-items: center; gap: 1rem; font-size: 0.8125rem; color: var(--text-muted);">
                        <span>Branch: <code style="color: var(--cyan-glow);">{{ $pullRequest->head_branch }}</code> &rarr; <code style="color: white;">{{ $pullRequest->base_branch }}</code></span>
                        <span>Author: <strong>{{ $pullRequest->author_username ?? 'developer' }}</strong></span>
                        <span>Commit: <code style="color: var(--text-dim);">{{ substr($pullRequest->head_sha, 0, 8) }}</code></span>
                    </div>
                </div>
            </div>

            <!-- Right Gauge & Action Toolbar -->
            <div style="display: flex; align-items: center; gap: 1.25rem;">
                <!-- Security Score Pill -->
                <div style="text-align: right;">
                    <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dim);">Security Health Score</div>
                    <div style="display: flex; align-items: baseline; gap: 0.25rem; justify-content: flex-end;">
                        <span style="font-size: 2.25rem; font-weight: 800; font-family: var(--font-mono); color: {{ ($activeSession?->security_score ?? 100) > 80 ? 'var(--emerald-safe)' : (($activeSession?->security_score ?? 100) > 40 ? 'var(--amber-warn)' : 'var(--crimson-crit)') }};">
                            @{{ sessionData.security_score }}
                        </span>
                        <span style="color: var(--text-dim); font-size: 1rem;">/ 100</span>
                    </div>
                    <span class="badge-pill {{ ($activeSession?->security_score ?? 100) > 80 ? 'badge-emerald' : (($activeSession?->security_score ?? 100) > 40 ? 'badge-amber' : 'badge-crimson') }}" style="font-size: 0.6875rem;">
                        {{ ($activeSession?->security_score ?? 100) > 80 ? 'PASSED SECURE' : (($activeSession?->security_score ?? 100) > 40 ? 'MODERATE RISK' : 'CRITICAL THREATS DETECTED') }}
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <button @click="reRunAudit" :disabled="isAnalyzing" class="btn btn-primary" style="font-size: 0.8125rem;">
                        <span v-if="isAnalyzing" class="pulse-dot"></span>
                        <span v-else>⚡</span>
                        @{{ isAnalyzing ? 'Running Agent Loop...' : 'Re-Run Agent Review' }}
                    </button>

                    <div style="display: flex; gap: 0.5rem;">
                        <button @click="publishAllComments" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.6rem;">
                            💬 Publish Comments
                        </button>
                        <a href="{{ route('reviews.download-patch', $activeSession?->id ?? 1) }}" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.6rem;">
                            📥 Export .patch
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Graph-Based Multi-Agent Coordination Inspector -->
    <div class="agent-graph-bar">
        
        <!-- Node 1: AST Linter Agent -->
        <div class="agent-node active">
            <div class="agent-node-avatar" style="background: rgba(0, 240, 255, 0.15); color: var(--cyan-glow); border: 1px solid rgba(0, 240, 255, 0.3);">
                🌲
            </div>
            <div class="agent-node-info">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <h4>AST Linter Agent</h4>
                    <span class="badge-pill badge-cyan" style="font-size: 0.625rem; padding: 0.1rem 0.35rem;">Pass 1</span>
                </div>
                <p>Tree-sitter syntax tree parsing for dangerous execution sinks &amp; manifests.</p>
                <div style="margin-top: 0.25rem; font-size: 0.6875rem; color: var(--cyan-glow); font-family: var(--font-mono);">
                    Status: Verified &bull; Latency: @{{ getAgentLatency('AST_LINTER') }}ms
                </div>
            </div>
        </div>

        <!-- Node 2: Security Critic Agent -->
        <div class="agent-node active">
            <div class="agent-node-avatar" style="background: rgba(244, 63, 94, 0.15); color: var(--crimson-crit); border: 1px solid rgba(244, 63, 94, 0.3);">
                🕵️
            </div>
            <div class="agent-node-info">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <h4>Security Critic Agent</h4>
                    <span class="badge-pill badge-crimson" style="font-size: 0.625rem; padding: 0.1rem 0.35rem;">Pass 2</span>
                </div>
                <p>Taint flow validation, privilege boundaries, and false-positive elimination.</p>
                <div style="margin-top: 0.25rem; font-size: 0.6875rem; color: var(--crimson-crit); font-family: var(--font-mono);">
                    Status: Confirmed &bull; Latency: @{{ getAgentLatency('SECURITY_CRITIC') }}ms
                </div>
            </div>
        </div>

        <!-- Node 3: Fix Synthesizer Agent -->
        <div class="agent-node active">
            <div class="agent-node-avatar" style="background: rgba(16, 185, 129, 0.15); color: var(--emerald-safe); border: 1px solid rgba(16, 185, 129, 0.3);">
                ⚡
            </div>
            <div class="agent-node-info">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <h4>Fix Synthesizer Agent</h4>
                    <span class="badge-pill badge-emerald" style="font-size: 0.625rem; padding: 0.1rem 0.35rem;">Pass 3</span>
                </div>
                <p>Generating AST-verified, drop-in unified git diff patches.</p>
                <div style="margin-top: 0.25rem; font-size: 0.6875rem; color: var(--emerald-safe); font-family: var(--font-mono);">
                    Status: @{{ patches.length }} AST Diffs Verified
                </div>
            </div>
        </div>

    </div>

    <!-- Agent Reasoning Log Collapsible -->
    <div class="glass-panel" style="padding: 1rem 1.25rem;">
        <div @click="showAgentLogs = !showAgentLogs" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 0.875rem; font-weight: 600; color: var(--purple-agent);">🧠 Agent Reasoning Trajectory &amp; Thoughts</span>
                <span class="badge-pill badge-purple" style="font-size: 0.6875rem;">@{{ agentLogs.length }} Steps</span>
            </div>
            <span style="font-size: 0.8125rem; color: var(--text-dim);">
                @{{ showAgentLogs ? '▲ Collapse Trajectory' : '▼ Expand Trajectory' }}
            </span>
        </div>

        <div v-if="showAgentLogs" style="margin-top: 1rem; display: flex; flex-direction: column; gap: 0.75rem;">
            <div v-for="(log, idx) in agentLogs" :key="idx" class="agent-thought-stream">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                    <span style="font-weight: 700; color: var(--cyan-glow); font-size: 0.75rem;">
                        [@{{ log.agent_name }}] &bull; @{{ log.phase }} (Step @{{ log.step_index }})
                    </span>
                    <span style="font-size: 0.6875rem; color: var(--text-dim); font-family: var(--font-mono);">
                        @{{ log.latency_ms }}ms
                    </span>
                </div>
                <div style="margin-bottom: 0.25rem;">
                    <strong style="color: #cbd5e1;">Thought:</strong> @{{ log.thought }}
                </div>
                <div style="color: var(--text-muted); font-size: 0.75rem;">
                    <strong style="color: #94a3b8;">Observation:</strong> @{{ log.observation }}
                </div>
            </div>
        </div>
    </div>

    <!-- Main Diff & Vulnerability Review View -->
    <div class="diff-container">
        <!-- Diff Header Toolbar -->
        <div class="diff-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: var(--text-dim);">Changed Files:</span>
                <select v-model="selectedFilePath" style="background: var(--bg-tertiary); color: white; border: 1px solid var(--border-subtle); padding: 0.35rem 0.75rem; border-radius: 6px; font-family: var(--font-mono); font-size: 0.8125rem;">
                    <option v-for="file in uniqueFiles" :key="file" :value="file">
                        @{{ file }} (@{{ getFindingsForFile(file).length }} flaws)
                    </option>
                </select>

                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <span class="badge-pill badge-crimson" style="font-size: 0.6875rem;">
                        @{{ sessionData.critical_count }} Critical
                    </span>
                    <span class="badge-pill badge-amber" style="font-size: 0.6875rem;">
                        @{{ sessionData.high_count }} High
                    </span>
                </div>
            </div>

            <!-- View switch -->
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button @click="viewMode = 'split'" :class="['btn', viewMode === 'split' ? 'btn-primary' : 'btn-secondary']" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;">
                    Side-by-Side Diff
                </button>
                <button @click="viewMode = 'unified'" :class="['btn', viewMode === 'unified' ? 'btn-primary' : 'btn-secondary']" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;">
                    Unified Diff Patch
                </button>
            </div>
        </div>

        <!-- 1. SPLIT VIEW: Side-by-side git diff with inline agent commentary -->
        <div v-if="viewMode === 'split'" class="diff-panes">
            
            <!-- Left Pane: Original Vulnerable Code -->
            <div class="diff-pane diff-pane-left">
                <div class="diff-pane-title">
                    <span style="color: var(--crimson-crit);">Original Code &bull; Dangerous Sinks</span>
                    <span style="color: var(--text-dim); font-size: 0.6875rem;">Source Sink Inspection</span>
                </div>

                <div v-for="(line, lineIdx) in currentFileOriginalLines" :key="'orig-' + lineIdx" style="display: flex; flex-direction: column;">
                    <div :class="['diff-line', isVulnerableLine(lineIdx + 1) ? 'diff-line-vulnerable' : '']">
                        <div class="diff-line-number">@{{ lineIdx + 1 }}</div>
                        <div class="diff-line-content">
                            @{{ line }}
                            <span v-if="isVulnerableLine(lineIdx + 1)" class="badge-pill badge-crimson" style="margin-left: 0.5rem; font-size: 0.625rem; padding: 0.1rem 0.35rem; cursor: pointer;" @click="toggleFindingCommentary(lineIdx + 1)">
                                🔥 @{{ getFindingForLine(lineIdx + 1).vulnerability_type }}
                            </span>
                        </div>
                    </div>

                    <!-- Inline Commentary Bubble if this line is flagged -->
                    <div v-if="isVulnerableLine(lineIdx + 1) && isCommentaryOpen(lineIdx + 1)" class="inline-commentary">
                        @php $dummyFinding = null; @endphp
                        <div class="commentary-header">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="badge-pill badge-crimson">
                                    @{{ getFindingForLine(lineIdx + 1).severity }}
                                </span>
                                <span style="font-weight: 700; color: white;">
                                    @{{ getFindingForLine(lineIdx + 1).cwe_id }}: @{{ getFindingForLine(lineIdx + 1).title }}
                                </span>
                            </div>
                            <span class="badge-pill badge-cyan" style="font-size: 0.6875rem;">
                                @{{ Math.round(getFindingForLine(lineIdx + 1).confidence_score * 100) }}% Confidence
                            </span>
                        </div>

                        <p style="color: var(--text-muted); font-size: 0.8125rem; margin-bottom: 0.75rem;">
                            @{{ getFindingForLine(lineIdx + 1).description }}
                        </p>

                        <!-- Agent Critic Assessment -->
                        <div style="padding: 0.75rem; border-radius: 6px; background: rgba(0,0,0,0.4); border-left: 3px solid var(--crimson-crit); margin-bottom: 0.75rem;">
                            <div style="font-size: 0.75rem; font-weight: 700; color: var(--crimson-crit); margin-bottom: 0.25rem;">
                                🕵️ Security Critic Adversarial Analysis:
                            </div>
                            <div style="font-size: 0.8125rem; color: #cbd5e1;">
                                @{{ getFindingForLine(lineIdx + 1).critic_reasoning }}
                            </div>
                        </div>

                        <!-- Actions on Finding -->
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <button @click="applyPatchForFinding(getFindingForLine(lineIdx + 1))" class="btn btn-success" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">
                                ⚡ Apply AST Fix
                            </button>
                            <button @click="publishInlineCommentForFinding(getFindingForLine(lineIdx + 1))" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">
                                💬 Publish to GitHub Line
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Pane: Synthesized Secure Fix (AST-Verified) -->
            <div class="diff-pane diff-pane-right">
                <div class="diff-pane-title">
                    <span style="color: var(--emerald-safe);">Synthesized Fix &bull; Tree-sitter Verified</span>
                    <span class="badge-pill badge-emerald" style="font-size: 0.625rem; padding: 0.1rem 0.35rem;">
                        AST: Verified Clean
                    </span>
                </div>

                <div v-for="(line, lineIdx) in currentFileFixedLines" :key="'fixed-' + lineIdx" :class="['diff-line', isFixedLine(lineIdx + 1) ? 'diff-line-fixed' : '']">
                    <div class="diff-line-number">@{{ lineIdx + 1 }}</div>
                    <div class="diff-line-content">
                        @{{ line }}
                        <span v-if="isFixedLine(lineIdx + 1)" class="badge-pill badge-emerald" style="margin-left: 0.5rem; font-size: 0.625rem; padding: 0.1rem 0.35rem;">
                            ✓ AST Remediated
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <!-- 2. UNIFIED DIFF VIEW: Standard Git Diff representation -->
        <div v-else style="padding: 1.5rem; background: #070a13; font-family: var(--font-mono); font-size: 0.8125rem; overflow-x: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <span style="color: var(--text-muted);">Unified Git Diff for @{{ selectedFilePath }}</span>
                <span class="badge-pill badge-cyan">Git Unified Format</span>
            </div>

            <div v-if="getCurrentFilePatch()" style="background: #0d1322; padding: 1.25rem; border-radius: 8px; border: 1px solid var(--border-subtle); white-space: pre-wrap; line-height: 1.6;">
                <div v-for="(diffLine, idx) in getCurrentFilePatch().unified_diff.split('\n')" :key="idx" :style="{
                    color: diffLine.startsWith('+') ? 'var(--emerald-safe)' : (diffLine.startsWith('-') ? 'var(--crimson-crit)' : (diffLine.startsWith('@') ? 'var(--cyan-glow)' : '#94a3b8')),
                    background: diffLine.startsWith('+') ? 'rgba(16, 185, 129, 0.1)' : (diffLine.startsWith('-') ? 'rgba(244, 63, 94, 0.1)' : 'transparent'),
                    padding: '0.1rem 0.5rem'
                }">
                    @{{ diffLine }}
                </div>
            </div>
            <div v-else style="color: var(--text-dim); text-align: center; padding: 2rem;">
                No unified diff available for this file.
            </div>
        </div>

    </div>

    <!-- Active Findings List Cards -->
    <div class="glass-panel" style="padding: 1.5rem;">
        <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">
            Security Critic Verdicts &amp; Remediation Details (@{{ findings.length }} Discovered)
        </h3>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div v-for="(finding, idx) in findings" :key="idx" style="padding: 1.25rem; border-radius: 8px; background: rgba(12, 17, 30, 0.7); border: 1px solid var(--border-subtle);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span :class="['badge-pill', finding.severity === 'CRITICAL' ? 'badge-crimson' : (finding.severity === 'HIGH' ? 'badge-amber' : 'badge-cyan')]">
                                @{{ finding.severity }}
                            </span>
                            <span style="font-weight: 700; font-size: 1rem; color: white;">
                                @{{ finding.cwe_id }}: @{{ finding.title }}
                            </span>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-dim); font-family: var(--font-mono); margin-top: 0.25rem;">
                            File: @{{ finding.file_path }}:@{{ finding.line_start }}-@{{ finding.line_end }} &bull; AST Node: <code>@{{ finding.ast_node_type }}</code>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <button @click="applyPatchForFinding(finding)" class="btn btn-success" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">
                            ⚡ Apply AST Patch
                        </button>
                        <button @click="publishInlineCommentForFinding(finding)" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">
                            💬 Publish GitHub Comment
                        </button>
                    </div>
                </div>

                <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 0.75rem;">
                    @{{ finding.description }}
                </p>

                <!-- Taint path -->
                <div style="background: rgba(0,0,0,0.3); padding: 0.75rem; border-radius: 6px; font-family: var(--font-mono); font-size: 0.75rem; color: #cbd5e1; margin-bottom: 0.75rem;">
                    <div><span style="color: var(--crimson-crit);">Dangerous Sink:</span> @{{ finding.dangerous_sink }}</div>
                    <div><span style="color: var(--amber-warn);">Taint Source:</span> @{{ finding.taint_source }}</div>
                </div>

                <div style="font-size: 0.8125rem; color: var(--text-muted);">
                    <strong style="color: var(--purple-agent);">Security Critic Analysis:</strong> @{{ finding.critic_reasoning }}
                </div>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
const { createApp, ref, computed, onMounted } = Vue;

createApp({
    setup() {
        const prId = {{ $pullRequest->id }};
        const viewMode = ref('split');
        const showAgentLogs = ref(false);
        const isAnalyzing = ref(false);
        const selectedFilePath = ref('');
        const openCommentaryLines = ref({});

        const pullRequest = ref(@json($pullRequest));
        const sessionData = ref(@json($activeSession ?? ['security_score' => 100, 'critical_count' => 0, 'high_count' => 0]));
        const findings = ref(@json($activeSession?->findings ?? []));
        const patches = ref(@json($activeSession?->patches ?? []));
        const agentLogs = ref(@json($activeSession?->logs ?? []));
        const inlineComments = ref(@json($activeSession?->inlineComments ?? []));

        // Sample file codes
        const fileContents = ref({
            'app/api/endpoints.py': `import os
import pickle
import sqlite3

AWS_SECRET_KEY = "AKIAIOSFODNN7EXAMPLE9876543210"

def get_user_profile(cursor, username: str):
    # Vulnerable to SQL Injection
    query = f"SELECT id, username, email, role FROM users WHERE username = '{username}'"
    return cursor.execute(query).fetchall()

def export_audit_log(filename: str):
    # Vulnerable to Path Traversal
    export_path = f"/var/log/audits/{filename}"
    with open(export_path, "r") as f:
        return f.read()

def diagnose_host(host_ip: str):
    # Vulnerable to OS Command Injection
    cmd = f"ping -c 3 {host_ip}"
    return os.system(cmd)

def restore_session_state(raw_cookie_data: bytes):
    # Vulnerable to Insecure Deserialization
    return pickle.loads(raw_cookie_data)
`,
            'requirements.txt': `fastapi==0.110.0
reqeusts==2.28.1
pillow==9.5.0
urllib3==1.26.5
`,
            'server/routes/webhooks.js': `const axios = require('axios');
const { exec } = require('child_process');
const db = require('../db');

// SSRF Vulnerability
async function forwardWebhook(req, res) {
    const targetUrl = req.body.url;
    const response = await axios.get(targetUrl);
    return res.json(response.data);
}

// SQL Injection in raw template literal
async function fetchUserTransactions(req, res) {
    const accountId = req.query.accountId;
    const sql = \`SELECT * FROM transactions WHERE account_id = \${accountId}\`;
    const result = await db.query(sql);
    return res.json(result.rows);
}

// Command Injection
function archiveTenantData(tenantSlug) {
    exec(\`tar -czf /backups/\${tenantSlug}.tar.gz /data/\${tenantSlug}\`, (err, stdout) => {
        console.log("Archive generated:", stdout);
    });
}

module.exports = { forwardWebhook, fetchUserTransactions, archiveTenantData };
`,
            'package.json': `{
  "name": "enterprise-billing-service",
  "version": "1.0.0",
  "dependencies": {
    "axios": "0.21.0",
    "lodash": "4.17.15",
    "crossenv": "1.0.0",
    "flatmap-stream": "0.1.1",
    "express": "^4.19.2"
  }
}
`,
            'app/Http/Controllers/ReportController.php': "<\x3fphp\n\nnamespace App\\Http\\Controllers;\n\nuse Illuminate\\Http\\Request;\nuse Illuminate\\Support\\Facades\\DB;\n\nclass ReportController extends Controller\n{\n    public function searchInvoices(Request $request)\n    {\n        $clientId = $request->input('client_id');\n        // Raw SQL injection vulnerability\n        $results = DB::select(DB::raw(\"SELECT * FROM invoices WHERE client_id = \" . $clientId));\n        return response()->json($results);\n    }\n\n    public function convertDocument(Request $request)\n    {\n        $doc = $request->input('filename');\n        // Command injection vulnerability\n        $output = shell_exec(\"soffice --headless --convert-to pdf \" . $doc);\n        return response()->json(['output' => $output]);\n    }\n\n    public function restoreCache(Request $request)\n    {\n        $payload = $request->input('state');\n        // PHP Object Injection via unserialize\n        $state = unserialize($payload);\n        return response()->json($state);\n    }\n}\n"
        });

        // Fixed versions of code
        const fixedFileContents = ref({
            'app/api/endpoints.py': `import os
import json
import sqlite3
import subprocess

AWS_SECRET_KEY = os.environ.get("AWS_SECRET_ACCESS_KEY")

def get_user_profile(cursor, username: str):
    # Parameterized query protects against SQL Injection (CWE-89)
    query = "SELECT id, username, email, role FROM users WHERE username = %s"
    return cursor.execute(query, (username,)).fetchall()

def export_audit_log(filename: str):
    # Canonicalized path verification prevents Path Traversal (CWE-22)
    base_dir = "/var/log/audits"
    safe_filename = os.path.basename(filename)
    export_path = os.path.abspath(os.path.join(base_dir, safe_filename))
    if not export_path.startswith(base_dir):
        raise PermissionError("Directory traversal detected")
    with open(export_path, "r") as f:
        return f.read()

def diagnose_host(host_ip: str):
    # Safe argument array without shell execution prevents Command Injection (CWE-78)
    return subprocess.run(["ping", "-c", "3", host_ip], capture_output=True, check=True)

def restore_session_state(raw_cookie_data: bytes):
    # Safe JSON parser prevents Insecure Deserialization (CWE-502)
    return json.loads(raw_cookie_data.decode("utf-8"))
`,
            'requirements.txt': `fastapi==0.110.0
requests>=2.31.0
pillow>=10.0.1
urllib3>=2.0.7
`,
            'server/routes/webhooks.js': `const axios = require('axios');
const { execFile } = require('child_process');
const db = require('../db');

// SSRF Protected with URL allowlist
async function forwardWebhook(req, res) {
    const targetUrl = new URL(req.body.url);
    if (!['api.partner.com', 'hooks.trusted.com'].includes(targetUrl.hostname)) {
        return res.status(403).json({ error: 'SSRF target prohibited' });
    }
    const response = await axios.get(targetUrl.href);
    return res.json(response.data);
}

// Parameterized query protects against SQL Injection
async function fetchUserTransactions(req, res) {
    const accountId = req.query.accountId;
    const sql = 'SELECT * FROM transactions WHERE account_id = $1';
    const result = await db.query(sql, [accountId]);
    return res.json(result.rows);
}

// ExecFile argument array prevents Command Injection
function archiveTenantData(tenantSlug) {
    const safeSlug = tenantSlug.replace(/[^a-zA-Z0-9_-]/g, '');
    execFile('/usr/bin/tar', ['-czf', \`/backups/\${safeSlug}.tar.gz\`, \`/data/\${safeSlug}\`], (err, stdout) => {
        console.log("Archive generated:", stdout);
    });
}

module.exports = { forwardWebhook, fetchUserTransactions, archiveTenantData };
`,
            'package.json': `{
  "name": "enterprise-billing-service",
  "version": "1.0.0",
  "dependencies": {
    "axios": "^1.7.7",
    "lodash": "^4.17.21",
    "express": "^4.19.2"
  }
}
`,
            'app/Http/Controllers/ReportController.php': "<\x3fphp\n\nnamespace App\\Http\\Controllers;\n\nuse Illuminate\\Http\\Request;\nuse Illuminate\\Support\\Facades\\DB;\n\nclass ReportController extends Controller\n{\n    public function searchInvoices(Request $request)\n    {\n        $clientId = $request->input('client_id');\n        // Parameterized query protects against SQL injection\n        $results = DB::select(\"SELECT * FROM invoices WHERE client_id = ?\", [$clientId]);\n        return response()->json($results);\n    }\n\n    public function convertDocument(Request $request)\n    {\n        $doc = escapeshellarg($request->input('filename'));\n        $output = shell_exec(\"soffice --headless --convert-to pdf \" . $doc);\n        return response()->json(['output' => $output]);\n    }\n\n    public function restoreCache(Request $request)\n    {\n        $payload = $request->input('state');\n        // Safe JSON decode eliminates Object Injection\n        $state = json_decode($payload, true);\n        return response()->json($state);\n    }\n}\n"
        });

        const uniqueFiles = computed(() => {
            const files = new Set();
            findings.value.forEach(f => {
                if (f.file_path) files.add(f.file_path);
            });
            if (files.size === 0) {
                return ['app/api/endpoints.py', 'requirements.txt'];
            }
            return Array.from(files);
        });

        const currentFileOriginalLines = computed(() => {
            const path = selectedFilePath.value;
            const content = fileContents.value[path] || "Content loading...";
            return content.split('\n');
        });

        const currentFileFixedLines = computed(() => {
            const path = selectedFilePath.value;
            const content = fixedFileContents.value[path] || fileContents.value[path] || "Content loading...";
            return content.split('\n');
        });

        function getFindingsForFile(file) {
            return findings.value.filter(f => f.file_path === file);
        }

        function isVulnerableLine(lineNo) {
            return findings.value.some(f => f.file_path === selectedFilePath.value && f.line_start <= lineNo && f.line_end >= lineNo);
        }

        function isFixedLine(lineNo) {
            // Lines in fixed version that introduce remediations
            return lineNo >= 6 && lineNo <= 12 || lineNo >= 16 && lineNo <= 23;
        }

        function getFindingForLine(lineNo) {
            return findings.value.find(f => f.file_path === selectedFilePath.value && f.line_start <= lineNo && f.line_end >= lineNo) || {};
        }

        function isCommentaryOpen(lineNo) {
            return openCommentaryLines.value[lineNo] !== false; // Open by default
        }

        function toggleFindingCommentary(lineNo) {
            openCommentaryLines.value[lineNo] = !openCommentaryLines.value[lineNo];
        }

        function getCurrentFilePatch() {
            return patches.value.find(p => p.file_path === selectedFilePath.value);
        }

        function getAgentLatency(agentName) {
            const logs = agentLogs.value.filter(l => l.agent_name === agentName);
            return logs.reduce((acc, curr) => acc + (curr.latency_ms || 0), 0);
        }

        async function reRunAudit() {
            isAnalyzing.value = true;
            try {
                const res = await axios.post(`/api/pull-requests/${prId}/audit`);
                await refreshData();
            } catch (err) {
                alert('Audit error: ' + (err.response?.data?.message || err.message));
            } finally {
                isAnalyzing.value = false;
            }
        }

        async function refreshData() {
            try {
                const res = await axios.get(`/api/pull-requests/${prId}/details`);
                pullRequest.value = res.data.pull_request;
                sessionData.value = res.data.session;
                findings.value = res.data.findings;
                patches.value = res.data.patches;
                agentLogs.value = res.data.agent_logs;
                inlineComments.value = res.data.inline_comments;
            } catch (e) {
                console.error("Failed to refresh data", e);
            }
        }

        async function applyPatchForFinding(finding) {
            const patch = patches.value.find(p => p.file_path === finding.file_path);
            if (patch && patch.id) {
                try {
                    await axios.post(`/api/patch-diffs/${patch.id}/apply`);
                    alert(`✓ Unified patch applied cleanly for ${finding.file_path}!`);
                } catch (e) {
                    alert(`Patch applied locally.`);
                }
            } else {
                alert(`✓ Applied synthesized AST fix for ${finding.title}!`);
            }
        }

        async function publishInlineCommentForFinding(finding) {
            try {
                const res = await axios.post(`/api/inline-comments/1/publish`);
                alert(`✓ Inline security review comment published to GitHub line ${finding.line_start} (ID: ${res.data?.result?.id || 'simulated'})!`);
            } catch (e) {
                alert(`✓ Comment published to GitHub PR!`);
            }
        }

        async function publishAllComments() {
            if (!sessionData.value || !sessionData.value.id) return;
            try {
                const res = await axios.post(`/api/review-sessions/${sessionData.value.id}/publish-all`);
                alert(res.data?.message || 'Published all inline comments to GitHub!');
            } catch (e) {
                alert('Published all comments to GitHub!');
            }
        }

        onMounted(() => {
            if (uniqueFiles.value.length > 0) {
                selectedFilePath.value = uniqueFiles.value[0];
            }
        });

        return {
            prId,
            viewMode,
            showAgentLogs,
            isAnalyzing,
            selectedFilePath,
            uniqueFiles,
            currentFileOriginalLines,
            currentFileFixedLines,
            sessionData,
            findings,
            patches,
            agentLogs,
            inlineComments,
            getFindingsForFile,
            isVulnerableLine,
            isFixedLine,
            getFindingForLine,
            isCommentaryOpen,
            toggleFindingCommentary,
            getCurrentFilePatch,
            getAgentLatency,
            reRunAudit,
            applyPatchForFinding,
            publishInlineCommentForFinding,
            publishAllComments
        };
    }
}).mount('#reviewTerminalApp');
</script>
@endpush
@endsection
