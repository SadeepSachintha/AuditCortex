# 🛡️ AuditCortex
### Autonomous Agentic Code Reviewer & PR Security Auditor

> A high-performance developer tools SaaS that hooks into GitHub/GitLab webhooks to perform multi-pass static analysis, AST security vulnerability scanning, and semantic dependency validation using autonomous agent loops.

---

## ⚡ The Problem

Modern engineering teams waste countless hours in code reviews manually catching boilerplate security flaws (SQL injections, exposed credentials, SSRF vulnerabilities, toxic supply-chain packages) that traditional linters miss because standard regex/linters lack **cross-file contextual reasoning** and **semantic syntax-tree awareness**.

**AuditCortex** solves this through **Graph-Based Multi-Agent Coordination** combining **Tree-sitter AST parsing**, **Adversarial Security Critic validation**, and **AST-verified unified Git patch synthesis** powered by local LLMs (Qwen2.5-Coder / DeepSeek-Coder) with **zero code-leak risk** to third parties.

---

## 🏗️ System Architecture

```mermaid
graph TD
    GH[GitHub / GitLab Webhook] -->|HMAC SHA-256 Validated| ORCH[Laravel 12 Webhook Orchestrator]
    ORCH -->|Task Dispatch| QUEUE[Queue & Review Session Manager]
    QUEUE -->|Async REST Payload| ENGINE[FastAPI Python Multi-Agent Core]

    subgraph "Autonomous Agent Loop"
        LINTER[Agent 1: AST Linter<br/>Tree-sitter Grammars]
        CRITIC[Agent 2: Security Critic<br/>Taint & Boundary Validator]
        SYNTH[Agent 3: Fix Synthesizer<br/>AST Verified Patch Engine]

        LINTER -->|Candidate Sinks| CRITIC
        CRITIC -->|Adversarially Confirmed Sinks| SYNTH
        SYNTH -->|AST Syntax Re-Validation Loop| LINTER
    end

    ENGINE --> LINTER
    SYNTH -->|Unified Diffs + Findings| ORCH

    subgraph "Local LLM Runner (100% On-Premise)"
        LLM[Local vLLM / Ollama<br/>Qwen2.5-Coder / DeepSeek-Coder]
    end

    CRITIC <--> LLM
    SYNTH <--> LLM

    ORCH --> DB[(SQLite / MySQL / Redis)]
    ORCH --> UI[Review Terminal<br/>Vue 3 Reactive Split Diff Viewer]
    UI -->|1-Click Publish| GH_COMMENTS[GitHub Inline Comments API]
    UI -->|1-Click Apply| GIT_PATCH[Ready-to-Merge Unified .patch]
```

---

## 🤖 The 3 Collaborative Agents

### 1. 🌲 AST Linter Agent (`AST_LINTER`)
- **Technology**: Tree-sitter multi-language syntax tree parsers (`tree-sitter-python`, `tree-sitter-javascript`, `tree-sitter-typescript`, `tree-sitter-php`, `tree-sitter-go`).
- **Sink Analysis**: Queries AST nodes directly for dangerous execution sinks:
  - **SQL Injection (CWE-89)**: Unparameterized string interpolation in `execute`, `raw_sql`, `DB::raw`, `db.query`.
  - **Command Injection / RCE (CWE-78)**: Subprocess execution with `shell=True`, `os.system`, `child_process.exec`, `shell_exec`.
  - **Server-Side Request Forgery (CWE-918)**: Outbound requests (`requests.get`, `axios.get`) accepting unvalidated dynamic URLs.
  - **Insecure Deserialization (CWE-502)**: Python `pickle.loads`, PHP `unserialize`, PyYAML un-safe loaders.
  - **Path Traversal (CWE-22)**: File stream calls (`open`, `fs.readFile`) vulnerable to `../` directory climbing.
  - **Hardcoded Secrets (CWE-798 / CWE-312)**: Exposed AWS keys (`AKIA...`), GitHub PATs, JWT secrets, private keys.
  - **Toxic Dependency Manifests (CWE-1395)**: Semantic audit of `package.json`, `composer.json`, `requirements.txt` detecting malware/typosquats (e.g. `reqeusts`, `flatmap-stream`, `crossenv`, `coa-compromised`).

### 2. 🕵️ Security Critic Agent (`SECURITY_CRITIC`)
- **Contextual Adversarial Validation**: Traverses preceding AST statements to detect existing sanitizers, type-casts (`int()`, `parseInt`), regex allowlists, or prepared statement parameter markers (`?`, `%s`, `$1`).
- **Taint Flow & Privilege Boundaries**: Evaluates whether untrusted user inputs actually cross security perimeters into the target sink.
- **Confidence Scoring & False-Positive Elimination**: Assigns a confidence score (0.0 to 1.0) and emits verdicts (`CONFIRMED`, `DOWNGRADED`, or `REJECTED_FALSE_POSITIVE`).

### 3. ⚡ Fix Synthesizer Agent (`FIX_SYNTHESIZER`)
- **Idiomatic Remediation**: Converts raw queries to parameterized placeholders, introduces path canonicalization guards, and replaces dangerous deserialization with safe parsers.
- **AST Verification Loop**: Re-parses the patched code using Tree-sitter. If any `ERROR` or missing syntax node is detected, self-corrects until the AST is 100% syntactically valid!
- **Unified Git Patch Diff**: Emits standard unified diff format (`--- a/... +++ b/... @@ ... @@`) ready for Git apply or direct review.

---

## 🔒 Zero Code-Leak Risk Guarantee

AuditCortex runs **100% locally on localhost**:
- **Local LLM Runner**: Compatible with **Ollama** (`http://localhost:11434`) and **vLLM** (`http://localhost:8001/v1`) running **Qwen2.5-Coder** or **DeepSeek-Coder**.
- **No Third-Party Telemetry**: Zero source code or proprietary IP is transmitted to external cloud APIs or public model providers.
- **Local Fallback**: Includes a high-performance local deterministic neural-symbolic engine ensuring full offline testability and resilience.

---

## 💻 Tech Stack

| Layer | Technologies |
| :--- | :--- |
| **Webhook & Orchestrator** | Laravel 12 (PHP 8.2+), Eloquent ORM, Database/Redis Queues, HMAC SHA-256 Webhook Signatures |
| **Agent Execution Engine** | Python 3.14+, FastAPI, Tree-sitter Multi-Language Grammars, Uvicorn, Asynchronous Graph Coordination |
| **Local LLM Runner** | Ollama / vLLM serving Qwen2.5-Coder / DeepSeek-Coder |
| **Review Terminal UI** | Vue 3 Reactive Components, Vanilla Cyber-Defense Glassmorphism CSS, Split Git Diff Viewer, GitHub Inline Commentary Publisher |

---

## 🚀 Quickstart Guide

### 1. Requirements
- PHP 8.2+ with PDO SQLite / MySQL
- Python 3.10+ (Python 3.14 supported)
- Node.js & npm

### 2. One-Click Launcher (Windows)
Double-click [`start.bat`](file:///d:/xampp/htdocs/AuditCortex/start.bat) (or run `start.bat` / `run.bat` in terminal). This automatically:
1. Verifies PHP and Python runtimes.
2. Initializes and seeds the SQLite database with real-world sample PR fixtures.
3. Launches the **FastAPI Agent Execution Engine** on `http://127.0.0.1:8080`.
4. Launches the **Laravel Orchestrator & Review Terminal** on `http://127.0.0.1:8000`.
5. Opens the Review Terminal in your default web browser!

To gracefully stop both services, double-click [`stop.bat`](file:///d:/xampp/htdocs/AuditCortex/stop.bat).

### 3. Manual Startup Alternative
If running manually across separate terminal windows:
```bash
# Terminal 1: Python FastAPI Agent Engine
python -m uvicorn agent_engine.main:app --host 127.0.0.1 --port 8080

# Terminal 2: Laravel Webhook Orchestrator & Review Terminal
php artisan serve --port 8000
```

### 4. Seed Demo Attack Vectors (1-Click)
In the dashboard UI, click **"⚡ Seed Real-World Attack Scenarios"** or run:
```bash
php artisan db:seed
```
This pre-loads 3 real-world pull requests:
1. **Python Cloud Service**: CWE-89 SQLi, CWE-78 RCE, CWE-502 Pickle, CWE-22 Path Traversal, and typosquatted `reqeusts`.
2. **Node.js Webhook Relay**: CWE-918 SSRF, CWE-78 exec RCE, and toxic `flatmap-stream` & `crossenv`.
3. **PHP Laravel Reports**: CWE-89 Raw Query SQLi, CWE-78 shell_exec, and CWE-502 Object Injection.

### 5. Webhook Simulator
To simulate an incoming GitHub Pull Request webhook event:
```bash
curl -X POST http://127.0.0.1:8000/api/webhooks/simulate \
  -H "Content-Type: application/json" \
  -d '{"fixture": "python_cloud_service"}'
```

---

## 📸 Review Terminal Features

- **Side-by-Side Git Diff Viewer**: Left pane highlights dangerous sinks and tainted source paths; right pane displays Tree-sitter AST-verified synthesized patches.
- **Inline Severity Badges**: Pulsating crimson/amber tags anchored directly on the vulnerable lines of code.
- **Agent Reasoning Trajectory**: Expandable audit logs showing the exact step-by-step thoughts, actions, observations, and latencies of all 3 agents.
- **One-Click Actions**:
  - Apply AST Remediation Patch locally
  - Publish Inline Markdown Review Comments to GitHub PR lines
  - Download Combined `.diff` / `.patch` file
  - Re-Run Autonomous Multi-Pass Audit on demand

---

## 🛡️ License

MIT License. Built with ❤️ for autonomous application security and engineering velocity.
