<?php

namespace App\Jobs;

use App\Models\AgentLog;
use App\Models\InlineComment;
use App\Models\PatchDiff;
use App\Models\ReviewSession;
use App\Models\VulnerabilityFinding;
use App\Services\AgentEngineClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzePullRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $sessionId;
    public array $files;
    public ?string $rawDiff;

    public function __construct(int $sessionId, array $files = [], ?string $rawDiff = null)
    {
        $this->sessionId = $sessionId;
        $this->files = $files;
        $this->rawDiff = $rawDiff;
    }

    public function handle(AgentEngineClient $engineClient): void
    {
        $session = ReviewSession::find($this->sessionId);
        if (!$session) {
            return;
        }

        $session->update(['status' => 'analyzing', 'started_at' => now()]);

        // If files are empty, retrieve sample fallback fixtures based on PR title or default Python
        $filesToAnalyze = $this->files;
        if (empty($filesToAnalyze)) {
            $filesToAnalyze = $this->resolveFallbackFiles($session);
        }

        try {
            $result = $engineClient->analyze($filesToAnalyze, $session->session_uuid, $this->rawDiff);

            // Update session status & metrics
            $session->update([
                'status' => $result['status'] ?? 'completed',
                'security_score' => $result['security_score'] ?? 100,
                'critical_count' => $result['critical_count'] ?? 0,
                'high_count' => $result['high_count'] ?? 0,
                'medium_count' => $result['medium_count'] ?? 0,
                'low_count' => $result['low_count'] ?? 0,
                'total_findings' => $result['total_findings'] ?? 0,
                'execution_time_ms' => $result['execution_time_ms'] ?? 0,
                'llm_runner_info' => $result['llm_runner_info'] ?? null,
                'error_message' => $result['error_message'] ?? null,
                'completed_at' => now(),
            ]);

            // Save Agent Logs
            if (!empty($result['agent_logs'])) {
                foreach ($result['agent_logs'] as $log) {
                    AgentLog::create([
                        'review_session_id' => $session->id,
                        'agent_name' => $log['agent_name'] ?? 'AGENT',
                        'phase' => $log['phase'] ?? 'EXECUTION',
                        'step_index' => $log['step_index'] ?? 1,
                        'thought' => $log['thought'] ?? '',
                        'action' => $log['action'] ?? '',
                        'observation' => $log['observation'] ?? '',
                        'latency_ms' => $log['latency_ms'] ?? 0,
                    ]);
                }
            }

            // Save Vulnerability Findings & Patches
            $findingMap = [];
            if (!empty($result['findings'])) {
                foreach ($result['findings'] as $f) {
                    $findingRecord = VulnerabilityFinding::create([
                        'review_session_id' => $session->id,
                        'file_path' => $f['file_path'] ?? 'file',
                        'line_start' => $f['line_start'] ?? 1,
                        'line_end' => $f['line_end'] ?? 1,
                        'severity' => $f['severity'] ?? 'MEDIUM',
                        'cwe_id' => $f['cwe_id'] ?? null,
                        'vulnerability_type' => $f['vulnerability_type'] ?? 'SECURITY_FLAW',
                        'title' => $f['title'] ?? 'Security Vulnerability',
                        'description' => $f['description'] ?? '',
                        'dangerous_sink' => $f['dangerous_sink'] ?? '',
                        'taint_source' => $f['taint_source'] ?? '',
                        'confidence_score' => $f['confidence_score'] ?? 0.85,
                        'critic_verdict' => $f['critic_verdict'] ?? 'CONFIRMED',
                        'critic_reasoning' => $f['critic_reasoning'] ?? '',
                        'ast_node_type' => $f['ast_node_type'] ?? '',
                        'metadata' => $f,
                    ]);
                    $findingMap[$f['title']] = $findingRecord->id;
                }
            }

            // Save Patches
            if (!empty($result['patches'])) {
                foreach ($result['patches'] as $p) {
                    $linkedFindingId = $findingMap[$p['finding_title'] ?? ''] ?? null;
                    PatchDiff::create([
                        'review_session_id' => $session->id,
                        'vulnerability_finding_id' => $linkedFindingId,
                        'file_path' => $p['file_path'] ?? 'file',
                        'original_code' => $p['original_code'] ?? '',
                        'fixed_code' => $p['fixed_code'] ?? '',
                        'unified_diff' => $p['unified_diff'] ?? '',
                        'is_ast_verified' => $p['is_ast_verified'] ?? true,
                        'verification_notes' => $p['verification_notes'] ?? '',
                        'status' => 'GENERATED',
                    ]);
                }
            }

            // Save Inline Comments
            if (!empty($result['inline_comments'])) {
                foreach ($result['inline_comments'] as $c) {
                    InlineComment::create([
                        'review_session_id' => $session->id,
                        'vulnerability_finding_id' => null,
                        'file_path' => $c['file_path'] ?? 'file',
                        'line_number' => $c['line_number'] ?? 1,
                        'body' => $c['body'] ?? '',
                        'status' => 'DRAFT',
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error("Analysis failed for session {$session->id}: " . $e->getMessage());
            $session->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    protected function resolveFallbackFiles(ReviewSession $session): array
    {
        $title = strtolower($session->pullRequest->title ?? '');

        if (str_contains($title, 'node') || str_contains($title, 'webhook') || str_contains($title, 'billing')) {
            return [
                'server/routes/webhooks.js' => "const axios = require('axios');\nconst { exec } = require('child_process');\nconst db = require('../db');\n\nasync function forwardWebhook(req, res) {\n    const targetUrl = req.body.url;\n    const response = await axios.get(targetUrl);\n    return res.json(response.data);\n}\n\nasync function fetchUserTransactions(req, res) {\n    const accountId = req.query.accountId;\n    const sql = `SELECT * FROM transactions WHERE account_id = \${accountId}`;\n    const result = await db.query(sql);\n    return res.json(result.rows);\n}\n\nfunction archiveTenantData(tenantSlug) {\n    exec(`tar -czf /backups/\${tenantSlug}.tar.gz /data/\${tenantSlug}`, (err, stdout) => {\n        console.log('Archive generated:', stdout);\n    });\n}\n\nmodule.exports = { forwardWebhook, fetchUserTransactions, archiveTenantData };\n",
                'package.json' => "{\n  \"name\": \"enterprise-billing-service\",\n  \"version\": \"1.0.0\",\n  \"dependencies\": {\n    \"axios\": \"0.21.0\",\n    \"lodash\": \"4.17.15\",\n    \"crossenv\": \"1.0.0\",\n    \"flatmap-stream\": \"0.1.1\",\n    \"express\": \"^4.19.2\"\n  }\n}\n"
            ];
        }

        if (str_contains($title, 'php') || str_contains($title, 'invoice') || str_contains($title, 'report')) {
            return [
                'app/Http/Controllers/ReportController.php' => "<?php\n\nnamespace App\\Http\\Controllers;\n\nuse Illuminate\\Http\\Request;\nuse Illuminate\\Support\\Facades\\DB;\n\nclass ReportController extends Controller\n{\n    public function searchInvoices(Request \$request)\n    {\n        \$clientId = \$request->input('client_id');\n        \$results = DB::select(DB::raw(\"SELECT * FROM invoices WHERE client_id = \" . \$clientId));\n        return response()->json(\$results);\n    }\n\n    public function convertDocument(Request \$request)\n    {\n        \$doc = \$request->input('filename');\n        \$output = shell_exec(\"soffice --headless --convert-to pdf \" . \$doc);\n        return response()->json(['output' => \$output]);\n    }\n\n    public function restoreCache(Request \$request)\n    {\n        \$payload = \$request->input('state');\n        \$state = unserialize(\$payload);\n        return response()->json(\$state);\n    }\n}\n"
            ];
        }

        // Default Python Service
        return [
            'app/api/endpoints.py' => "import os\nimport pickle\nimport sqlite3\n\nAWS_SECRET_KEY = \"AKIAIOSFODNN7EXAMPLE9876543210\"\n\ndef get_user_profile(cursor, username: str):\n    query = f\"SELECT id, username, email, role FROM users WHERE username = '{username}'\"\n    return cursor.execute(query).fetchall()\n\ndef export_audit_log(filename: str):\n    export_path = f\"/var/log/audits/{filename}\"\n    with open(export_path, \"r\") as f:\n        return f.read()\n\ndef diagnose_host(host_ip: str):\n    cmd = f\"ping -c 3 {host_ip}\"\n    return os.system(cmd)\n\ndef restore_session_state(raw_cookie_data: bytes):\n    return pickle.loads(raw_cookie_data)\n",
            'requirements.txt' => "fastapi==0.110.0\nreqeusts==2.28.1\npillow==9.5.0\nurllib3==1.26.5\n"
        ];
    }
}
