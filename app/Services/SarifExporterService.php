<?php

namespace App\Services;

use App\Models\ReviewSession;

class SarifExporterService
{
    public function export(ReviewSession $session): array
    {
        $session->loadMissing(['findings.patch', 'pullRequest.repository']);
        $findings = $session->findings;

        $rules = [];
        $results = [];
        $ruleIdsSeen = [];

        foreach ($findings as $finding) {
            $ruleId = $finding->cwe_id ?: $finding->vulnerability_type;
            
            // Register rule definition if not yet registered
            if (!isset($ruleIdsSeen[$ruleId])) {
                $ruleIdsSeen[$ruleId] = true;
                $rules[] = [
                    'id' => $ruleId,
                    'name' => $finding->vulnerability_type,
                    'shortDescription' => [
                        'text' => $finding->title,
                    ],
                    'fullDescription' => [
                        'text' => $finding->description,
                    ],
                    'helpUri' => $finding->cwe_id ? "https://cwe.mitre.org/data/definitions/" . preg_replace('/[^0-9]/', '', $finding->cwe_id) . ".html" : "https://auditcortex.dev",
                    'defaultConfiguration' => [
                        'level' => in_array($finding->severity, ['CRITICAL', 'HIGH']) ? 'error' : ($finding->severity === 'MEDIUM' ? 'warning' : 'note'),
                    ],
                    'properties' => [
                        'tags' => ['security', 'auditcortex', strtolower($finding->vulnerability_type)],
                        'precision' => 'high',
                        'confidence' => $finding->confidence_score,
                    ],
                ];
            }

            // Map level
            $level = in_array($finding->severity, ['CRITICAL', 'HIGH']) ? 'error' : ($finding->severity === 'MEDIUM' ? 'warning' : 'note');

            $resultItem = [
                'ruleId' => $ruleId,
                'level' => $level,
                'message' => [
                    'text' => "{$finding->title}: {$finding->critic_reasoning}",
                ],
                'locations' => [
                    [
                        'physicalLocation' => [
                            'artifactLocation' => [
                                'uri' => $finding->file_path,
                                'uriBaseId' => '%SRCROOT%',
                            ],
                            'region' => [
                                'startLine' => (int) $finding->line_start,
                                'endLine' => (int) $finding->line_end,
                            ],
                        ],
                    ],
                ],
                'properties' => [
                    'criticVerdict' => $finding->critic_verdict,
                    'dangerousSink' => $finding->dangerous_sink,
                    'taintSource' => $finding->taint_source,
                ],
            ];

            // If an AST patch exists, attach fixes to SARIF
            if ($finding->patch) {
                $resultItem['fixes'] = [
                    [
                        'description' => [
                            'text' => "Apply AuditCortex AST-Verified Remediation",
                        ],
                        'fileChanges' => [
                            [
                                'artifactLocation' => [
                                    'uri' => $finding->file_path,
                                ],
                                'replacements' => [
                                    [
                                        'deletedRegion' => [
                                            'startLine' => (int) $finding->line_start,
                                            'endLine' => (int) $finding->line_end,
                                        ],
                                        'insertedContent' => [
                                            'text' => $finding->patch->fixed_code,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }

            $results[] = $resultItem;
        }

        return [
            '$schema' => 'https://raw.githubusercontent.com/oasis-tcs/sarif-spec/master/Schemata/sarif-schema-2.1.0.json',
            'version' => '2.1.0',
            'runs' => [
                [
                    'tool' => [
                        'driver' => [
                            'name' => 'AuditCortex Autonomous Security Gate',
                            'semanticVersion' => '1.0.0',
                            'informationUri' => 'https://github.com/SadeepSachintha/AuditCortex',
                            'rules' => $rules,
                        ],
                    ],
                    'results' => $results,
                ],
            ],
        ];
    }
}
