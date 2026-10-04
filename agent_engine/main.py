"""
FastAPI Server for AuditCortex Agent Execution Engine
Orchestrates multi-pass static analysis, AST security vulnerability scanning,
and semantic dependency validation using autonomous agent loops.
"""

import os
from typing import Dict, Any, Optional
from fastapi import FastAPI, HTTPException, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from agent_engine.graph.workflow import ReviewGraphWorkflow
from agent_engine.llm.runner import LocalLLMRunner
from agent_engine.sample_prs.fixtures import SAMPLE_PYTHON_PR, SAMPLE_NODE_PR, SAMPLE_PHP_PR

app = FastAPI(
    title="AuditCortex Agent Execution Engine",
    description="Autonomous Agentic Code Reviewer & PR Security Auditor API",
    version="1.0.0"
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

llm_runner = LocalLLMRunner()
workflow = ReviewGraphWorkflow(llm_runner=llm_runner)


class AnalyzeRequest(BaseModel):
    session_id: Optional[str] = None
    files: Dict[str, str] = Field(..., description="Mapping of relative file path to file string content")
    raw_diff: Optional[str] = Field(default=None, description="Unified git diff string if available")
    repo_name: Optional[str] = Field(default="unknown/repository")
    pr_number: Optional[int] = Field(default=1)


class SynthesizeRequest(BaseModel):
    file_path: str
    code: str
    vulnerability_type: str
    line_start: int
    dangerous_sink: str


@app.get("/health")
@app.get("/api/health")
async def health_check():
    return {
        "status": "online",
        "engine": "AuditCortex Agentic Core v1.0",
        "active_parsers": ["python", "javascript", "typescript", "tsx", "php", "go"],
        "llm_runner": llm_runner.get_status(),
        "zero_code_leak_guarantee": True
    }


@app.post("/api/analyze")
async def analyze_pull_request(payload: AnalyzeRequest):
    try:
        result = await workflow.run(
            files=payload.files,
            session_id=payload.session_id,
            raw_diff=payload.raw_diff
        )
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/api/sample-prs")
async def get_sample_prs():
    return {
        "samples": [
            {
                "id": "python_cloud_service",
                "title": "Add User Profile & Diagnostic Endpoint",
                "repo": "enterprise/auth-service",
                "pr_number": 42,
                "author": "dev-engineer",
                "branch": "feature/user-profiles",
                "files_count": len(SAMPLE_PYTHON_PR),
                "description": "Introduces user management, audit log file exporting, and ping diagnostic routines."
            },
            {
                "id": "node_webhook_relay",
                "title": "Billing Webhook Relay & Backup Worker",
                "repo": "enterprise/billing-service",
                "pr_number": 88,
                "author": "backend-lead",
                "branch": "feat/webhook-forwarder",
                "files_count": len(SAMPLE_NODE_PR),
                "description": "Adds outward webhook forwarding, direct database transactions, and tenant data archiving."
            },
            {
                "id": "php_laravel_reports",
                "title": "Financial Invoices & PDF Document Converter",
                "repo": "enterprise/finance-portal",
                "pr_number": 104,
                "author": "contractor-dev",
                "branch": "fix/invoice-search",
                "files_count": len(SAMPLE_PHP_PR),
                "description": "Invoice search query builder, server document converter, and session cache restoration."
            }
        ]
    }


@app.post("/api/sample-prs/{sample_id}/analyze")
async def analyze_sample(sample_id: str):
    fixtures = {
        "python_cloud_service": SAMPLE_PYTHON_PR,
        "node_webhook_relay": SAMPLE_NODE_PR,
        "php_laravel_reports": SAMPLE_PHP_PR
    }
    selected_fixture = fixtures.get(sample_id)
    if not selected_fixture:
        raise HTTPException(status_code=404, detail=f"Sample fixture '{sample_id}' not found.")

    result = await workflow.run(files=selected_fixture)
    return result


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8080)
