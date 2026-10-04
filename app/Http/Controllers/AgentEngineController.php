<?php

namespace App\Http\Controllers;

use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\ReviewSession;
use App\Services\AgentEngineClient;
use App\Jobs\AnalyzePullRequestJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentEngineController extends Controller
{
    protected AgentEngineClient $client;

    public function __construct(AgentEngineClient $client)
    {
        $this->client = $client;
    }

    public function status(): JsonResponse
    {
        $health = $this->client->getHealth();
        return response()->json($health);
    }

    public function seedSamplePRs(): JsonResponse
    {
        // Setup 3 pre-configured demo PRs representing real-world attack vectors
        $samples = [
            [
                'repo_name' => 'auth-service',
                'owner' => 'enterprise',
                'repo_full_name' => 'enterprise/auth-service',
                'pr_number' => 42,
                'title' => 'feat(auth): User profile retrieval and diagnostic exporter',
                'description' => 'Adds new endpoints for user profile search, log exporting, host ping diagnostics, and session state caching.',
                'branch' => 'feature/user-profiles',
                'author' => 'sre-engineer',
                'files' => [
                    'app/api/endpoints.py' => "import os\nimport pickle\nimport sqlite3\n\nAWS_SECRET_KEY = \"AKIAIOSFODNN7EXAMPLE9876543210\"\n\ndef get_user_profile(cursor, username: str):\n    # Vulnerable to SQL Injection\n    query = f\"SELECT id, username, email, role FROM users WHERE username = '{username}'\"\n    return cursor.execute(query).fetchall()\n\ndef export_audit_log(filename: str):\n    # Vulnerable to Path Traversal\n    export_path = f\"/var/log/audits/{filename}\"\n    with open(export_path, \"r\") as f:\n        return f.read()\n\ndef diagnose_host(host_ip: str):\n    # Vulnerable to OS Command Injection\n    cmd = f\"ping -c 3 {host_ip}\"\n    return os.system(cmd)\n\ndef restore_session_state(raw_cookie_data: bytes):\n    # Vulnerable to Insecure Deserialization\n    return pickle.loads(raw_cookie_data)\n",
                    'requirements.txt' => "fastapi==0.110.0\nreqeusts==2.28.1\npillow==9.5.0\nurllib3==1.26.5\n"
                ]
            ],
            [
                'repo_name' => 'billing-service',
                'owner' => 'enterprise',
                'repo_full_name' => 'enterprise/billing-service',
                'pr_number' => 88,
                'title' => 'feat(billing): Outbound webhook forwarder & tenant archiver',
                'description' => 'Implements outbound webhook relay, raw query transaction retrieval, and automated tenant backup compression.',
                'branch' => 'feat/webhook-forwarder',
                'author' => 'backend-lead',
                'files' => [
                    'server/routes/webhooks.js' => "const axios = require('axios');\nconst { exec } = require('child_process');\nconst db = require('../db');\n\nasync function forwardWebhook(req, res) {\n    const targetUrl = req.body.url;\n    const response = await axios.get(targetUrl);\n    return res.json(response.data);\n}\n\nasync function fetchUserTransactions(req, res) {\n    const accountId = req.query.accountId;\n    const sql = `SELECT * FROM transactions WHERE account_id = \${accountId}`;\n    const result = await db.query(sql);\n    return res.json(result.rows);\n}\n\nfunction archiveTenantData(tenantSlug) {\n    exec(`tar -czf /backups/\${tenantSlug}.tar.gz /data/\${tenantSlug}`, (err, stdout) => {\n        console.log('Archive generated:', stdout);\n    });\n}\n\nmodule.exports = { forwardWebhook, fetchUserTransactions, archiveTenantData };\n",
                    'package.json' => "{\n  \"name\": \"enterprise-billing-service\",\n  \"version\": \"1.0.0\",\n  \"dependencies\": {\n    \"axios\": \"0.21.0\",\n    \"lodash\": \"4.17.15\",\n    \"crossenv\": \"1.0.0\",\n    \"flatmap-stream\": \"0.1.1\",\n    \"express\": \"^4.19.2\"\n  }\n}\n"
                ]
            ],
            [
                'repo_name' => 'finance-portal',
                'owner' => 'enterprise',
                'repo_full_name' => 'enterprise/finance-portal',
                'pr_number' => 104,
                'title' => 'fix(reports): Invoice search query & document PDF converter',
                'description' => 'Custom raw query for client invoice lookup and LibreOffice headless PDF conversion wrapper.',
                'branch' => 'fix/invoice-search',
                'author' => 'contractor-dev',
                'files' => [
                    'app/Http/Controllers/ReportController.php' => "<?php\n\nnamespace App\\Http\\Controllers;\n\nuse Illuminate\\Http\\Request;\nuse Illuminate\\Support\\Facades\\DB;\n\nclass ReportController extends Controller\n{\n    public function searchInvoices(Request \$request)\n    {\n        \$clientId = \$request->input('client_id');\n        \$results = DB::select(DB::raw(\"SELECT * FROM invoices WHERE client_id = \" . \$clientId));\n        return response()->json(\$results);\n    }\n\n    public function convertDocument(Request \$request)\n    {\n        \$doc = \$request->input('filename');\n        \$output = shell_exec(\"soffice --headless --convert-to pdf \" . \$doc);\n        return response()->json(['output' => \$output]);\n    }\n\n    public function restoreCache(Request \$request)\n    {\n        \$payload = \$request->input('state');\n        \$state = unserialize(\$payload);\n        return response()->json(\$state);\n    }\n}\n"
                ]
            ]
        ];

        $createdPrs = [];

        foreach ($samples as $sample) {
            $repo = Repository::firstOrCreate(
                ['repo_full_name' => $sample['repo_full_name']],
                [
                    'provider' => 'github',
                    'name' => $sample['repo_name'],
                    'owner' => $sample['owner'],
                    'default_branch' => 'main',
                    'is_active' => true,
                ]
            );

            $pr = PullRequest::updateOrCreate(
                [
                    'repository_id' => $repo->id,
                    'pr_number' => $sample['pr_number'],
                ],
                [
                    'title' => $sample['title'],
                    'description' => $sample['description'],
                    'head_branch' => $sample['branch'],
                    'base_branch' => 'main',
                    'head_sha' => 'c8e41a99f12' . $sample['pr_number'],
                    'author_username' => $sample['author'],
                    'author_avatar_url' => "https://avatars.githubusercontent.com/u/" . (4000 + $sample['pr_number']),
                    'status' => 'open',
                ]
            );

            // Create and execute immediate review session
            $session = ReviewSession::create([
                'pull_request_id' => $pr->id,
                'session_uuid' => (string) Str::uuid(),
                'status' => 'queued',
                'started_at' => now(),
            ]);

            AnalyzePullRequestJob::dispatchSync($session->id, $sample['files']);
            $createdPrs[] = [
                'pr_id' => $pr->id,
                'title' => $pr->title,
                'score' => $session->fresh()->security_score,
                'findings' => $session->fresh()->total_findings,
            ];
        }

        return response()->json([
            'message' => 'Successfully seeded and analyzed 3 real-world attack vector sample PRs.',
            'pull_requests' => $createdPrs,
        ]);
    }
}
