<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class AgentEngineClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.agent_engine.url', env('AGENT_ENGINE_URL', 'http://127.0.0.1:8080'));
    }

    public function getHealth(): array
    {
        try {
            $response = Http::timeout(2)->get("{$this->baseUrl}/api/health");
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning("AgentEngine HTTP health check failed: " . $e->getMessage());
        }

        return [
            'status' => 'offline',
            'engine' => 'AuditCortex Agentic Core',
            'active_parsers' => ['python', 'javascript', 'typescript', 'tsx', 'php', 'go'],
            'llm_runner' => [
                'active_provider' => 'local_fallback',
                'zero_leak_guarantee' => 'Localhost strictly enforced.'
            ],
            'zero_code_leak_guarantee' => true
        ];
    }

    public function analyze(array $files, ?string $sessionId = null, ?string $rawDiff = null): array
    {
        // 1. Try FastAPI HTTP endpoint
        try {
            $response = Http::timeout(60)->post("{$this->baseUrl}/api/analyze", [
                'session_id' => $sessionId,
                'files' => $files,
                'raw_diff' => $rawDiff,
            ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning("FastAPI endpoint unreachable ({$e->getMessage()}), attempting local CLI execution fallback.");
        }

        // 2. Direct Python CLI execution fallback
        return $this->executeDirectPythonAnalysis($files, $sessionId, $rawDiff);
    }

    protected function executeDirectPythonAnalysis(array $files, ?string $sessionId, ?string $rawDiff): array
    {
        $payload = json_encode(['files' => $files, 'session_id' => $sessionId, 'raw_diff' => $rawDiff]);
        $tempInput = str_replace('\\', '/', tempnam(sys_get_temp_dir(), 'ac_pr_'));
        file_put_contents($tempInput, $payload);

        $pythonCmd = "python -c \"import sys, json, asyncio; from agent_engine.graph.workflow import ReviewGraphWorkflow; data = json.load(open('{$tempInput}')); wf = ReviewGraphWorkflow(); res = asyncio.run(wf.run(data['files'], data.get('session_id'), data.get('raw_diff'))); print(json.dumps(res))\"";

        $result = Process::path(base_path())->run($pythonCmd);
        @unlink($tempInput);

        if ($result->successful()) {
            $decoded = json_decode($result->output(), true);
            if ($decoded && isset($decoded['findings'])) {
                return $decoded;
            }
        }

        throw new \RuntimeException("Failed to execute agent review: " . ($result->errorOutput() ?: "Unknown error"));
    }

    public function getSamplePRs(): array
    {
        try {
            $response = Http::timeout(2)->get("{$this->baseUrl}/api/sample-prs");
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            // Fallback list
        }

        return [
            'samples' => [
                [
                    'id' => 'python_cloud_service',
                    'title' => 'Add User Profile & Diagnostic Endpoint',
                    'repo' => 'enterprise/auth-service',
                    'pr_number' => 42,
                    'author' => 'dev-engineer',
                    'branch' => 'feature/user-profiles',
                    'files_count' => 2,
                    'description' => 'Introduces user management, audit log file exporting, and ping diagnostic routines.'
                ],
                [
                    'id' => 'node_webhook_relay',
                    'title' => 'Billing Webhook Relay & Backup Worker',
                    'repo' => 'enterprise/billing-service',
                    'pr_number' => 88,
                    'author' => 'backend-lead',
                    'branch' => 'feat/webhook-forwarder',
                    'files_count' => 2,
                    'description' => 'Adds outward webhook forwarding, direct database transactions, and tenant data archiving.'
                ],
                [
                    'id' => 'php_laravel_reports',
                    'title' => 'Financial Invoices & PDF Document Converter',
                    'repo' => 'enterprise/finance-portal',
                    'pr_number' => 104,
                    'author' => 'contractor-dev',
                    'branch' => 'fix/invoice-search',
                    'files_count' => 1,
                    'description' => 'Invoice search query builder, server document converter, and session cache restoration.'
                ]
            ]
        ];
    }
}
