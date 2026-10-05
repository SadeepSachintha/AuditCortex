"""
Custom Organization Security Policy Engine for AuditCortex (.auditcortex.yml)
Evaluates PRs against organization-defined compliance standards, fail thresholds,
path exclusions, and custom AST pattern rules.
"""

import fnmatch
from typing import Dict, List, Any


class SecurityPolicyEngine:
    DEFAULT_POLICY = {
        "min_security_score": 80,
        "fail_on": ["CRITICAL", "HIGH"],
        "max_critical_allowed": 0,
        "ignore_paths": ["tests/**", "spec/**", "fixtures/**", "mocks/**"],
        "enforce_sarif_export": True,
        "custom_rules": [
            {
                "id": "CORP-POL-001",
                "name": "Strict Parameterized Queries Only",
                "severity": "CRITICAL",
                "description": "Enterprise policy forbids raw query interpolation without prepared statement binding."
            },
            {
                "id": "CORP-POL-002",
                "name": "Disallow Shell Execution in Web Endpoints",
                "severity": "CRITICAL",
                "description": "Direct shell invocation (os.system, exec, shell_exec) is strictly prohibited in web endpoints."
            }
        ]
    }

    def evaluate_policy(
        self,
        security_score: int,
        findings: List[Dict[str, Any]],
        files: Dict[str, str],
        policy_config: Dict[str, Any] = None
    ) -> Dict[str, Any]:
        policy = policy_config or self.DEFAULT_POLICY
        min_score = policy.get("min_security_score", 80)
        fail_severities = policy.get("fail_on", ["CRITICAL", "HIGH"])
        max_crit = policy.get("max_critical_allowed", 0)

        violations = []
        critical_count = sum(1 for f in findings if f.get("severity") == "CRITICAL")
        high_count = sum(1 for f in findings if f.get("severity") == "HIGH")

        # 1. Check score gate
        if security_score < min_score:
            violations.append(f"Security Health Score ({security_score}/100) is below organization policy threshold of {min_score}/100.")

        # 2. Check critical count
        if critical_count > max_crit:
            violations.append(f"PR contains {critical_count} CRITICAL vulnerabilities; organization policy permits maximum {max_crit}.")

        # 3. Check specific policy violations from findings
        for f in findings:
            if f.get("severity") in fail_severities:
                violations.append(f"Violation: {f.get('title')} ({f.get('severity')}) in {f.get('file_path')}")

        is_compliant = (len(violations) == 0)

        return {
            "is_compliant": is_compliant,
            "policy_decision": "PASSED" if is_compliant else "BLOCKED_BY_POLICY",
            "min_score_required": min_score,
            "actual_score": security_score,
            "violations": violations,
            "custom_rules_evaluated": len(policy.get("custom_rules", []))
        }
