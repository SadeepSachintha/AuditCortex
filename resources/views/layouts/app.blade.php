<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AuditCortex | Autonomous Agentic Code Reviewer & PR Security Auditor')</title>

    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- AuditCortex Core CSS -->
    <link rel="stylesheet" href="{{ asset('css/auditcortex.css') }}">

    <!-- Vue 3 and Axios via ESM / CDN for immediate, zero-build-friction reactivity -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>

    @stack('styles')
</head>
<body>
    <header class="navbar">
        <a href="{{ route('terminal.index') }}" class="brand-container">
            <div class="brand-icon">🛡️</div>
            <div class="brand-text">
                <h1>AuditCortex</h1>
                <span>Autonomous Agentic Security Engine</span>
            </div>
        </a>

        <div class="nav-actions">
            <div class="badge-pill badge-emerald">
                <span class="pulse-dot"></span>
                <span>Agent Graph Active</span>
            </div>

            <div class="badge-pill badge-cyan">
                <span>🔒 Zero Code-Leak Risk (Local Qwen2.5-Coder)</span>
            </div>

            <button onclick="triggerSimulateModal()" class="btn btn-secondary" style="font-size: 0.8125rem;">
                ⚡ Webhook Simulator
            </button>

            <a href="https://github.com" target="_blank" class="btn btn-primary" style="font-size: 0.8125rem;">
                GitHub App
            </a>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <!-- Webhook Simulator Modal -->
    <div id="simulatorModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); z-index: 100; align-items: center; justify-content: center;">
        <div class="glass-panel" style="max-width: 650px; width: 90%; padding: 2rem; border-color: rgba(0, 240, 255, 0.4); box-shadow: 0 0 35px rgba(0, 240, 255, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--cyan-glow);">⚡ Simulate GitHub Webhook Event</h3>
                <button onclick="closeSimulateModal()" style="background: none; border: none; color: var(--text-dim); font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>

            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">
                Trigger an instantaneous pull request webhook payload with real-world vulnerabilities to witness the 3-agent autonomous loop (AST Linter &rarr; Security Critic &rarr; Fix Synthesizer) in real time.
            </p>

            <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                <div class="sample-card" style="cursor: pointer;" onclick="executeSimulation('python_cloud_service')">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="sample-title" style="color: var(--crimson-crit);">1. Python Cloud Service (Critical SQLi + RCE + Toxic Typosquat)</span>
                        <span class="badge-pill badge-crimson">5 Vulnerabilities</span>
                    </div>
                    <p class="sample-desc">CWE-89 SQL Injection, CWE-78 Shell Command Injection, CWE-502 Pickle Deserialization, CWE-22 Path Traversal, and malicious typosquat 'reqeusts'.</p>
                    <button class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">Simulate & Review &rarr;</button>
                </div>

                <div class="sample-card" style="cursor: pointer;" onclick="executeSimulation('node_webhook_relay')">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="sample-title" style="color: var(--amber-warn);">2. Node.js Webhook Relay (SSRF + Toxic flatmap-stream)</span>
                        <span class="badge-pill badge-amber">4 Vulnerabilities</span>
                    </div>
                    <p class="sample-desc">CWE-918 Server-Side Request Forgery, CWE-78 Child Process RCE, and supply chain malware 'flatmap-stream' & 'crossenv'.</p>
                    <button class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">Simulate & Review &rarr;</button>
                </div>

                <div class="sample-card" style="cursor: pointer;" onclick="executeSimulation('php_laravel_reports')">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="sample-title" style="color: var(--purple-agent);">3. PHP Laravel Reports (Raw Query SQLi + Object Injection)</span>
                        <span class="badge-pill badge-purple">4 Vulnerabilities</span>
                    </div>
                    <p class="sample-desc">CWE-89 Raw SQL Query Interpolation, CWE-78 shell_exec LibreOffice converter, and CWE-502 unserialize Object Injection.</p>
                    <button class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">Simulate & Review &rarr;</button>
                </div>
            </div>

            <div style="text-align: right;">
                <button onclick="closeSimulateModal()" class="btn btn-secondary">Close</button>
            </div>
        </div>
    </div>

    <script>
        function triggerSimulateModal() {
            const modal = document.getElementById('simulatorModal');
            modal.style.display = 'flex';
        }

        function closeSimulateModal() {
            const modal = document.getElementById('simulatorModal');
            modal.style.display = 'none';
        }

        async function executeSimulation(fixtureType) {
            try {
                const btn = event.currentTarget.querySelector('button') || event.currentTarget;
                const originalText = btn.innerText;
                btn.innerText = '⚡ Triggering Agent Loop...';
                btn.disabled = true;

                const response = await axios.post('/api/webhooks/simulate', {
                    fixture: fixtureType
                });

                if (response.data && response.data.pull_request_id) {
                    window.location.href = '/terminal/' + response.data.pull_request_id;
                } else {
                    alert('Simulation started! Refreshing dashboard.');
                    window.location.reload();
                }
            } catch (err) {
                alert('Simulation error: ' + (err.response?.data?.message || err.message));
            }
        }
    </script>

    @stack('scripts')
</body>
</html>
