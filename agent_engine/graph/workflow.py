"""
AuditCortex Collaborative Multi-Agent Graph Workflow
Orchestrates AST Linter -> Security Critic -> Fix Synthesizer in a stateful loop.
Computes comprehensive security scores and generates GitHub-ready inline comment payloads.
"""

import time
import uuid
from typing import Dict, List, Any, Optional

from agent_engine.parsers.tree_sitter_engine import TreeSitterEngine
from agent_engine.parsers.manifest_analyzer import ManifestAnalyzer
from agent_engine.llm.runner import LocalLLMRunner
from agent_engine.agents.ast_linter import ASTLinterAgent
from agent_engine.agents.security_critic import SecurityCriticAgent
from agent_engine.agents.fix_synthesizer import FixSynthesizerAgent


class ReviewGraphWorkflow:
    def __init__(self, llm_runner: Optional[LocalLLMRunner] = None):
        self.ts_engine = TreeSitterEngine()
        self.manifest_analyzer = ManifestAnalyzer()
        self.llm_runner = llm_runner or LocalLLMRunner()

        # Instantiate collaborative agents
        self.linter_agent = ASTLinterAgent(self.ts_engine, self.manifest_analyzer)
        self.critic_agent = SecurityCriticAgent(self.llm_runner)
        self.synthesizer_agent = FixSynthesizerAgent(self.ts_engine, self.llm_runner)

    async def run(
        self,
        files: Dict[str, str],
        session_id: Optional[str] = None,
        raw_diff: Optional[str] = None
    ) -> Dict[str, Any]:
        """
        Executes the autonomous agent review loop.
        """
        start_time = time.time()
        session_uuid = session_id or str(uuid.uuid4())
        all_logs: List[Dict[str, Any]] = []

        # Graph State
        state = {
            "session_uuid": session_uuid,
            "status": "analyzing",
            "files": files,
            "candidates": [],
            "verified_findings": [],
            "patches": [],
            "inline_comments": [],
            "security_score": 100,
            "metrics": {},
            "error": None
        }

        try:
            # -------------------------------------------------------------
            # PASS 1: AST Linter Agent (Syntax trees & Dangerous Sinks)
            # -------------------------------------------------------------
            candidates, linter_logs = await self.linter_agent.execute(files)
            all_logs.extend(linter_logs)
            state["candidates"] = candidates

            # -------------------------------------------------------------
            # PASS 2: Security Critic Agent (Taint flow & Boundary Analysis)
            # -------------------------------------------------------------
            state["status"] = "critic_review"
            verified_findings, critic_logs = await self.critic_agent.execute(candidates, files)
            all_logs.extend(critic_logs)
            state["verified_findings"] = verified_findings

            # -------------------------------------------------------------
            # PASS 3: Fix Synthesizer Agent (AST-Verified Git Unified Diff)
            # -------------------------------------------------------------
            state["status"] = "synthesizing"
            patches, synth_logs = await self.synthesizer_agent.execute(verified_findings, files)
            all_logs.extend(synth_logs)
            state["patches"] = patches

            # -------------------------------------------------------------
            # PASS 4: Synthesis & GitHub Inline Comment Generation
            # -------------------------------------------------------------
            inline_comments = self._generate_github_inline_comments(verified_findings, patches)
            state["inline_comments"] = inline_comments

            # Calculate metrics & score
            score_metrics = self._calculate_security_score(verified_findings)
            state["security_score"] = score_metrics["score"]
            state["metrics"] = score_metrics
            state["status"] = "completed"

        except Exception as e:
            state["status"] = "failed"
            state["error"] = str(e)
            all_logs.append({
                "agent_name": "ORCHESTRATOR",
                "phase": "ERROR",
                "step_index": 999,
                "thought": f"Agent workflow encountered runtime error: {str(e)}",
                "action": "handle_error",
                "observation": str(e),
                "latency_ms": int((time.time() - start_time) * 1000),
                "timestamp": time.time()
            })

        execution_time_ms = int((time.time() - start_time) * 1000)

        return {
            "session_uuid": session_uuid,
            "status": state["status"],
            "security_score": state["security_score"],
            "critical_count": state["metrics"].get("critical_count", 0),
            "high_count": state["metrics"].get("high_count", 0),
            "medium_count": state["metrics"].get("medium_count", 0),
            "low_count": state["metrics"].get("low_count", 0),
            "total_findings": len(state["verified_findings"]),
            "execution_time_ms": execution_time_ms,
            "llm_runner_info": self.llm_runner.get_status(),
            "findings": state["verified_findings"],
            "patches": state["patches"],
            "agent_logs": all_logs,
            "inline_comments": state["inline_comments"],
            "error_message": state.get("error")
        }

    def _calculate_security_score(self, findings: List[Dict[str, Any]]) -> Dict[str, Any]:
        score = 100
        critical = 0
        high = 0
        medium = 0
        low = 0

        for f in findings:
            sev = f.get("severity", "MEDIUM").upper()
            if sev == "CRITICAL":
                critical += 1
                score -= 25
            elif sev == "HIGH":
                high += 1
                score -= 15
            elif sev == "MEDIUM":
                medium += 1
                score -= 5
            elif sev == "LOW":
                low += 1
                score -= 2

        score = max(0, min(100, score))
        return {
            "score": score,
            "critical_count": critical,
            "high_count": high,
            "medium_count": medium,
            "low_count": low,
            "total": len(findings)
        }

    def _generate_github_inline_comments(
        self,
        findings: List[Dict[str, Any]],
        patches: List[Dict[str, Any]]
    ) -> List[Dict[str, Any]]:
        comments = []
        # Index patches by finding title or file
        patch_map = {p.get("finding_title"): p for p in patches}

        for idx, finding in enumerate(findings):
            patch = patch_map.get(finding.get("title"))
            suggested_code = patch.get("fixed_code") if patch else None

            comment_md = (
                f"### 🛡️ AuditCortex Security Advisory\n\n"
                f"**Severity**: `{finding.get('severity')}` | **CWE**: `{finding.get('cwe_id')}` | **Confidence**: `{int(finding.get('confidence_score', 0.9) * 100)}%`\n\n"
                f"**Vulnerability**: **{finding.get('title')}**\n\n"
                f"{finding.get('description')}\n\n"
                f"> **Security Critic Analysis**: {finding.get('critic_reasoning')}\n\n"
            )

            if suggested_code:
                comment_md += (
                    f"#### ⚡ Suggested Fix (AST-Verified):\n"
                    f"```suggestion\n{suggested_code}\n```\n"
                )

            comments.append({
                "file_path": finding.get("file_path"),
                "line_number": finding.get("line_start"),
                "body": comment_md,
                "status": "DRAFT"
            })

        return comments
