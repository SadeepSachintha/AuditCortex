<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use App\Services\GitHubWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected GitHubWebhookService $webhookService;

    public function __construct(GitHubWebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    public function handleGitHub(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('X-Hub-Signature-256');
        $event = $request->header('X-GitHub-Event', 'pull_request');

        // Look up repo secret if exists
        $repoFullName = $payload['repository']['full_name'] ?? null;
        $repo = $repoFullName ? Repository::where('repo_full_name', $repoFullName)->first() : null;
        $secret = $repo?->webhook_secret ?: config('services.github.webhook_secret');

        if (!$this->webhookService->verifyGitHubSignature($request->getContent(), $signature, $secret)) {
            Log::warning("GitHub webhook signature verification failed for {$repoFullName}");
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        if ($event === 'ping') {
            return response()->json(['message' => 'AuditCortex GitHub webhook ping acknowledged. Ready for PR events.']);
        }

        if ($event === 'pull_request') {
            $session = $this->webhookService->handlePullRequestEvent($payload);
            return response()->json([
                'message' => 'Pull request webhook processed successfully.',
                'review_session_id' => $session?->id,
                'session_uuid' => $session?->session_uuid,
            ]);
        }

        return response()->json(['message' => "Event '{$event}' ignored."]);
    }

    public function handleGitLab(Request $request): JsonResponse
    {
        $token = $request->header('X-Gitlab-Token');
        $event = $request->header('X-Gitlab-Event', 'Merge Request Hook');

        // In GitLab, merge request object has similar structure
        return response()->json([
            'message' => 'AuditCortex GitLab webhook received.',
            'event' => $event,
        ]);
    }

    public function simulate(Request $request): JsonResponse
    {
        $fixtureType = $request->input('fixture', 'python_cloud_service');

        $fixtures = [
            'python_cloud_service' => [
                'repo_full_name' => 'enterprise/auth-service',
                'title' => 'Security Audit: User Profile & Diagnostic Endpoint',
                'pr_number' => rand(100, 999),
                'branch' => 'feature/user-profiles',
                'author' => 'sre-engineer',
                'files' => [
                    'app/api/endpoints.py' => "import os\nimport pickle\nimport sqlite3\n\nAWS_SECRET_KEY = \"AKIAIOSFODNN7EXAMPLE9876543210\"\n\ndef get_user_profile(cursor, username: str):\n    # Vulnerable to SQL Injection\n    query = f\"SELECT id, username, email, role FROM users WHERE username = '{username}'\"\n    return cursor.execute(query).fetchall()\n\ndef export_audit_log(filename: str):\n    # Vulnerable to Path Traversal\n    export_path = f\"/var/log/audits/{filename}\"\n    with open(export_path, \"r\") as f:\n        return f.read()\n\ndef diagnose_host(host_ip: str):\n    # Vulnerable to OS Command Injection\n    cmd = f\"ping -c 3 {host_ip}\"\n    return os.system(cmd)\n\ndef restore_session_state(raw_cookie_data: bytes):\n    # Vulnerable to Insecure Deserialization\n    return pickle.loads(raw_cookie_data)\n",
                    'requirements.txt' => "fastapi==0.110.0\nreqeusts==2.28.1\npillow==9.5.0\nurllib3==1.26.5\n"
                ]
            ],
            'node_webhook_relay' => [
                'repo_full_name' => 'enterprise/billing-service',
                'title' => 'Security Audit: Billing Webhook Relay & Backup Worker',
                'pr_number' => rand(100, 999),
                'branch' => 'feat/webhook-forwarder',
                'author' => 'backend-lead',
                'files' => [
                    'server/routes/webhooks.js' => "const axios = require('axios');\nconst { exec } = require('child_process');\nconst db = require('../db');\n\nasync function forwardWebhook(req, res) {\n    const targetUrl = req.body.url;\n    const response = await axios.get(targetUrl);\n    return res.json(response.data);\n}\n\nasync function fetchUserTransactions(req, res) {\n    const accountId = req.query.accountId;\n    const sql = `SELECT * FROM transactions WHERE account_id = \${accountId}`;\n    const result = await db.query(sql);\n    return res.json(result.rows);\n}\n\nfunction archiveTenantData(tenantSlug) {\n    exec(`tar -czf /backups/\${tenantSlug}.tar.gz /data/\${tenantSlug}`, (err, stdout) => {\n        console.log('Archive generated:', stdout);\n    });\n}\n\nmodule.exports = { forwardWebhook, fetchUserTransactions, archiveTenantData };\n",
                    'package.json' => "{\n  \"name\": \"enterprise-billing-service\",\n  \"version\": \"1.0.0\",\n  \"dependencies\": {\n    \"axios\": \"0.21.0\",\n    \"lodash\": \"4.17.15\",\n    \"crossenv\": \"1.0.0\",\n    \"flatmap-stream\": \"0.1.1\",\n    \"express\": \"^4.19.2\"\n  }\n}\n"
                ]
            ],
            'php_laravel_reports' => [
                'repo_full_name' => 'enterprise/finance-portal',
                'title' => 'Security Audit: Financial Invoices & PDF Document Converter',
                'pr_number' => rand(100, 999),
                'branch' => 'fix/invoice-search',
                'author' => 'contractor-dev',
                'files' => [
                    'app/Http/Controllers/ReportController.php' => "<?php\n\nnamespace App\\Http\\Controllers;\n\nuse Illuminate\\Http\\Request;\nuse Illuminate\\Support\\Facades\\DB;\n\nclass ReportController extends Controller\n{\n    public function searchInvoices(Request \$request)\n    {\n        \$clientId = \$request->input('client_id');\n        \$results = DB::select(DB::raw(\"SELECT * FROM invoices WHERE client_id = \" . \$clientId));\n        return response()->json(\$results);\n    }\n\n    public function convertDocument(Request \$request)\n    {\n        \$doc = \$request->input('filename');\n        \$output = shell_exec(\"soffice --headless --convert-to pdf \" . \$doc);\n        return response()->json(['output' => \$output]);\n    }\n\n    public function restoreCache(Request \$request)\n    {\n        \$payload = \$request->input('state');\n        \$state = unserialize(\$payload);\n        return response()->json(\$state);\n    }\n}\n"
                ]
            ]
        ];

        $choice = $fixtures[$fixtureType] ?? $fixtures['python_cloud_service'];

        $simulatedPayload = [
            'action' => 'opened',
            'repository' => [
                'full_name' => $choice['repo_full_name'],
                'default_branch' => 'main',
            ],
            'pull_request' => [
                'number' => $choice['pr_number'],
                'title' => $choice['title'],
                'body' => 'Autonomous Security Audit PR triggered via AuditCortex Webhook Simulator.',
                'state' => 'open',
                'head' => ['ref' => $choice['branch'], 'sha' => substr(md5(microtime()), 0, 12)],
                'base' => ['ref' => 'main', 'sha' => 'main-sha-01'],
                'user' => ['login' => $choice['author'], 'avatar_url' => 'https://avatars.githubusercontent.com/u/' . rand(1000, 9999)],
            ],
            '_custom_files' => $choice['files'],
        ];

        $session = $this->webhookService->handlePullRequestEvent($simulatedPayload);

        return response()->json([
            'message' => 'Simulated GitHub webhook dispatched and review session started.',
            'pull_request_id' => $session?->pull_request_id,
            'review_session_id' => $session?->id,
            'session_uuid' => $session?->session_uuid,
        ]);
    }
}
