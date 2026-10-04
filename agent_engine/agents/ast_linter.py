"""
Agent 1: AST Linter Agent
Parses syntax trees using Tree-sitter grammars to identify dangerous sinks,
tainted source paths, and toxic dependency manifestations.
"""

import time
from typing import Dict, List, Any, Tuple
from agent_engine.agents.base import BaseAgent
from agent_engine.parsers.tree_sitter_engine import TreeSitterEngine
from agent_engine.parsers.manifest_analyzer import ManifestAnalyzer


class ASTLinterAgent(BaseAgent):
    def __init__(self, ts_engine: TreeSitterEngine, manifest_analyzer: ManifestAnalyzer):
        super().__init__(name="AST_LINTER", role="Syntax Tree & Dangerous Sink Auditor")
        self.ts_engine = ts_engine
        self.manifest_analyzer = manifest_analyzer

    async def execute(self, files: Dict[str, str]) -> Tuple[List[Dict[str, Any]], List[Dict[str, Any]]]:
        """
        Parses all changed PR files, detects languages, generates AST trees,
        and queries for dangerous sinks.
        """
        start_time = time.time()
        findings: List[Dict[str, Any]] = []
        logs: List[Dict[str, Any]] = []
        step = 1

        # Step 1: Language detection & AST initialization
        lang_map = {}
        for path in files.keys():
            lang = self.ts_engine.detect_language(path)
            if lang:
                lang_map[path] = lang

        logs.append(self.create_log_entry(
            phase="AST_INITIALIZATION",
            step_index=step,
            thought=f"Received {len(files)} file(s) for review. Initializing Tree-sitter AST parsers across languages: {set(lang_map.values()) if lang_map else 'manifests only'}.",
            action="detect_languages_and_build_ast",
            observation=f"Mapped {len(lang_map)} source file(s) to Tree-sitter grammars.",
            latency_ms=int((time.time() - start_time) * 1000)
        ))
        step += 1

        # Step 2: Traverse files for AST sinks and dependencies
        for filepath, content in files.items():
            file_start = time.time()
            lang = lang_map.get(filepath)

            # Check manifest dependencies
            manifest_findings = self.manifest_analyzer.analyze_manifest(filepath, content)
            if manifest_findings:
                findings.extend(manifest_findings)
                logs.append(self.create_log_entry(
                    phase="MANIFEST_AUDIT",
                    step_index=step,
                    thought=f"Audited dependency manifest `{filepath}`. Discovered {len(manifest_findings)} suspicious/toxic dependencies.",
                    action="scan_manifest_dependencies",
                    observation=f"Flagged dependencies: {', '.join([f['title'] for f in manifest_findings])}",
                    latency_ms=int((time.time() - file_start) * 1000)
                ))
                step += 1

            # Check Tree-sitter AST syntax sinks
            if lang:
                ast_findings = self.ts_engine.scan_for_dangerous_sinks(filepath, content)
                if ast_findings:
                    findings.extend(ast_findings)
                    logs.append(self.create_log_entry(
                        phase="AST_SINK_SCAN",
                        step_index=step,
                        thought=f"Constructed AST for `{filepath}` ({lang}). Found {len(ast_findings)} dangerous sink calls (SQLi, Command Injection, SSRF, Deserialization, Path Traversal, or Hardcoded Secrets).",
                        action="query_ast_dangerous_sinks",
                        observation=f"Matched {len(ast_findings)} AST node(s). Handing over candidate nodes to Security Critic Agent for boundary validation.",
                        latency_ms=int((time.time() - file_start) * 1000)
                    ))
                    step += 1

        total_latency = int((time.time() - start_time) * 1000)
        logs.append(self.create_log_entry(
            phase="AST_SCAN_COMPLETE",
            step_index=step,
            thought=f"Completed AST parsing and sink detection pass. Total candidate findings detected: {len(findings)} across {len(files)} file(s).",
            action="finalize_ast_pass",
            observation=f"Extracted {len(findings)} structured candidate finding(s). Dispatched to Security Critic.",
            latency_ms=total_latency
        ))

        return findings, logs
