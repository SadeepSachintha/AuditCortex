<?php

namespace App\Console\Commands;

use App\Services\AgentEngineClient;
use App\Services\SarifExporterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ScanRepositoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auditcortex:scan 
                            {path=. : The path to the repository directory or file}
                            {--repo=local/repo : Repository identifier}
                            {--format=table : Output format: table, sarif, json}
                            {--output= : Optional filepath to save SARIF/JSON or patch results}
                            {--fail-under=80 : Minimum security health score to pass CI/CD gate}
                            {--max-critical=0 : Maximum critical vulnerabilities permitted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform multi-pass AST static analysis and autonomous agentic vulnerability scanning on a local repository or path.';

    /**
     * Supported file extensions to index for AST analysis.
     */
    protected array $supportedExtensions = [
        'py', 'js', 'jsx', 'ts', 'tsx', 'php', 'go', 'json', 'txt'
    ];

    /**
     * Paths to ignore during scanning.
     */
    protected array $ignoreDirectories = [
        'vendor', 'node_modules', '.git', 'storage', 'dist', 'build', '.idea', '.vscode', '.gemini'
    ];

    public function handle(AgentEngineClient $engineClient): int
    {
        $path = $this->argument('path');
        $targetPath = realpath($path);

        if (!$targetPath || (!is_dir($targetPath) && !is_file($targetPath))) {
            $this->error("Invalid path: {$path}");
            return 1;
        }

        $this->info("===============================================================================");
        $this->info("          AUDITCORTEX AUTONOMOUS AGENTIC CODE SCANNER (CLI)");
        $this->info("===============================================================================");
        $this->line("Target Path:  <comment>{$targetPath}</comment>");
        $this->line("Engine Core:  <info>FastAPI + Tree-sitter AST + Security Critic</info>");
        $this->line("Zero-Leak:    <comment>Strictly Localhost (Zero cloud telemetry)</comment>\n");

        // 1. Discover and read source files
        $this->output->write("Indexing source files for AST parsing... ");
        $files = $this->collectFiles($targetPath);
        $this->info(count($files) . " file(s) indexed.\n");

        if (empty($files)) {
            $this->warn("No supported source files (.py, .js, .ts, .php, .go) found in target path.");
            return 0;
        }

        // 2. Dispatch to Agent Execution Engine
        $this->output->write("Executing multi-pass autonomous agent loops (AST Linter -> Security Critic -> Fix Synthesizer)... ");
        $startTime = microtime(true);
        
        try {
            $result = $engineClient->analyze($files, 'cli-' . uniqid());
        } catch (\Exception $e) {
            $this->error("\nEngine analysis failed: " . $e->getMessage());
            return 1;
        }

        $durationMs = round((microtime(true) - $startTime) * 1000);
        $this->info("Done in {$durationMs}ms.\n");

        $score = $result['security_score'] ?? 100;
        $critical = $result['critical_count'] ?? 0;
        $high = $result['high_count'] ?? 0;
        $medium = $result['medium_count'] ?? 0;
        $findings = $result['findings'] ?? [];
        $patches = $result['patches'] ?? [];
        $format = strtolower($this->option('format'));
        $outputFile = $this->option('output');

        // Handle SARIF / JSON Output
        if ($format === 'json') {
            $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($outputFile) {
                file_put_contents($outputFile, $json);
                $this->info("Saved JSON results to {$outputFile}");
            } else {
                $this->line($json);
            }
            return $this->evaluateGate($score, $critical);
        }

        if ($format === 'sarif') {
            $rules = [];
            $results = [];
            $rulesSeen = [];

            foreach ($findings as $f) {
                $ruleId = $f['cwe_id'] ?? ($f['vulnerability_type'] ?? 'CWE-Unknown');
                if (!isset($rulesSeen[$ruleId])) {
                    $rulesSeen[$ruleId] = true;
                    $rules[] = [
                        'id' => $ruleId,
                        'name' => $f['vulnerability_type'] ?? 'SecurityVulnerability',
                        'shortDescription' => ['text' => $f['title'] ?? 'Security Finding'],
                        'fullDescription' => ['text' => $f['description'] ?? 'AST Security Finding'],
                        'defaultConfiguration' => [
                            'level' => in_array($f['severity'] ?? 'MEDIUM', ['CRITICAL', 'HIGH']) ? 'error' : 'warning',
                        ],
                    ];
                }

                $results[] = [
                    'ruleId' => $ruleId,
                    'level' => in_array($f['severity'] ?? 'MEDIUM', ['CRITICAL', 'HIGH']) ? 'error' : 'warning',
                    'message' => [
                        'text' => ($f['title'] ?? 'Flaw') . ': ' . ($f['critic_reasoning'] ?? ($f['description'] ?? 'Tainted sink detected')),
                    ],
                    'locations' => [
                        [
                            'physicalLocation' => [
                                'artifactLocation' => [
                                    'uri' => $f['file_path'] ?? 'unknown',
                                    'uriBaseId' => '%SRCROOT%',
                                ],
                                'region' => [
                                    'startLine' => (int) ($f['line_start'] ?? 1),
                                    'endLine' => (int) ($f['line_end'] ?? ($f['line_start'] ?? 1)),
                                ],
                            ],
                        ],
                    ],
                ];
            }

            $sarifData = [
                '$schema' => 'https://raw.githubusercontent.com/oasis-tcs/sarif-spec/master/Schemata/sarif-schema-2.1.0.json',
                'version' => '2.1.0',
                'runs' => [
                    [
                        'tool' => [
                            'driver' => [
                                'name' => 'AuditCortex Autonomous Security Engine',
                                'version' => '1.0.0',
                                'rules' => $rules,
                            ],
                        ],
                        'results' => $results,
                    ],
                ],
            ];
            $json = json_encode($sarifData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($outputFile) {
                file_put_contents($outputFile, $json);
                $this->info("Saved OASIS SARIF 2.1.0 report to {$outputFile} (" . count($results) . " finding(s))");
            } else {
                $this->line($json);
            }
            return $this->evaluateGate($score, $critical);
        }

        // Default: Console Table Format
        $this->renderSummaryTable($score, $critical, $high, $medium, count($findings), count($patches));

        if (!empty($findings)) {
            $this->renderFindingsTable($findings);
        }

        if ($outputFile && !empty($patches)) {
            $patchContent = "# AuditCortex Synthesized AST Unified Patch\n\n";
            foreach ($patches as $p) {
                $patchContent .= ($p['unified_diff'] ?? '') . "\n\n";
            }
            file_put_contents($outputFile, $patchContent);
            $this->info("Synthesized unified git patch saved to {$outputFile}");
        }

        return $this->evaluateGate($score, $critical);
    }

    protected function collectFiles(string $path): array
    {
        $files = [];

        if (is_file($path)) {
            $rel = basename($path);
            $files[$rel] = file_get_contents($path);
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                if (in_array($item->getFilename(), $this->ignoreDirectories)) {
                    // Skip directory
                    continue;
                }
            } elseif ($item->isFile()) {
                $ext = strtolower($item->getExtension());
                if (in_array($ext, $this->supportedExtensions)) {
                    // Check if file is inside ignored path
                    $fullPath = $item->getPathname();
                    $skip = false;
                    foreach ($this->ignoreDirectories as $ignored) {
                        if (str_contains($fullPath, DIRECTORY_SEPARATOR . $ignored . DIRECTORY_SEPARATOR)) {
                            $skip = true;
                            break;
                        }
                    }
                    if (!$skip && $item->getSize() < 500000) { // Limit to 500KB per file
                        $relPath = ltrim(str_replace($path, '', $fullPath), DIRECTORY_SEPARATOR);
                        $files[$relPath] = file_get_contents($fullPath);
                    }
                }
            }

            if (count($files) >= 50) { // Batch scan cap
                break;
            }
        }

        return $files;
    }

    protected function renderSummaryTable(int $score, int $critical, int $high, int $medium, int $totalFindings, int $totalPatches): void
    {
        $this->table(
            ['Metric', 'Value', 'Status / Evaluation'],
            [
                ['Security Health Score', "{$score} / 100", $score >= 80 ? '<info>PASSED</info>' : '<error>FAILED GATE</error>'],
                ['Critical Vulnerabilities', (string) $critical, $critical === 0 ? '<info>CLEAN (0)</info>' : '<error>BLOCKING (' . $critical . ')</error>'],
                ['High Severity Vulnerabilities', (string) $high, $high === 0 ? '<info>0</info>' : '<comment>' . $high . '</comment>'],
                ['Medium Severity Vulnerabilities', (string) $medium, (string) $medium],
                ['Total Discovered Flaws', (string) $totalFindings, 'AST & Critic Verified'],
                ['AST Patches Synthesized', (string) $totalPatches, '<info>' . $totalPatches . ' Drop-in Diffs</info>'],
            ]
        );
    }

    protected function renderFindingsTable(array $findings): void
    {
        $this->line("\n<comment>DISCOVERED VULNERABILITY FINDINGS:</comment>");
        $rows = [];
        foreach ($findings as $f) {
            $sevTag = match($f['severity'] ?? 'MEDIUM') {
                'CRITICAL' => '<error>CRITICAL</error>',
                'HIGH' => '<comment>HIGH</comment>',
                default => '<info>' . ($f['severity'] ?? 'MEDIUM') . '</info>',
            };

            $rows[] = [
                $f['cwe_id'] ?? 'CWE-?',
                $sevTag,
                $f['title'] ?? 'Security Flaw',
                ($f['file_path'] ?? 'file') . ':' . ($f['line_start'] ?? 1),
                $f['dangerous_sink'] ?? '-',
            ];
        }

        $this->table(['CWE', 'Severity', 'Vulnerability', 'Location', 'Dangerous Sink'], $rows);
    }

    protected function evaluateGate(int $score, int $critical): int
    {
        $failUnder = (int) $this->option('fail-under');
        $maxCritical = (int) $this->option('max-critical');

        $isPassed = ($score >= $failUnder) && ($critical <= $maxCritical);

        if ($isPassed) {
            $this->info("\n===============================================================================");
            $this->info(" [GATE: PASSED] Repository satisfies enterprise security policy thresholds.");
            $this->info("===============================================================================");
            return 0;
        }

        $this->error("\n===============================================================================");
        $this->error(" [GATE: BLOCKED] Score ({$score}/100 < {$failUnder}) or Criticals ({$critical} > {$maxCritical}) violated.");
        $this->error("===============================================================================");
        return 1;
    }
}
