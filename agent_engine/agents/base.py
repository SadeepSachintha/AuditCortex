"""
Base Agent Module for AuditCortex Collaborative Multi-Agent Graph
"""

import time
from typing import Dict, Any, List


class BaseAgent:
    def __init__(self, name: str, role: str):
        self.name = name
        self.role = role

    def create_log_entry(
        self,
        phase: str,
        step_index: int,
        thought: str,
        action: str,
        observation: str,
        latency_ms: int = 0
    ) -> Dict[str, Any]:
        return {
            "agent_name": self.name,
            "phase": phase,
            "step_index": step_index,
            "thought": thought,
            "action": action,
            "observation": observation,
            "latency_ms": latency_ms,
            "timestamp": time.time()
        }
