"""
Sample Pull Request Fixtures for Testing & Demonstration
"""

SAMPLE_PYTHON_PR = {
    "app/api/endpoints.py": '''"""
User Management and Export Controller
"""
import os
import pickle
import sqlite3

AWS_SECRET_KEY = "AKIAIOSFODNN7EXAMPLE9876543210"

def get_user_profile(cursor, username: str):
    # Vulnerable to SQL Injection
    query = f"SELECT id, username, email, role FROM users WHERE username = '{username}'"
    return cursor.execute(query).fetchall()

def export_audit_log(filename: str):
    # Vulnerable to Path Traversal
    export_path = f"/var/log/audits/{filename}"
    with open(export_path, "r") as f:
        return f.read()

def diagnose_host(host_ip: str):
    # Vulnerable to OS Command Injection
    cmd = f"ping -c 3 {host_ip}"
    return os.system(cmd)

def restore_session_state(raw_cookie_data: bytes):
    # Vulnerable to Insecure Deserialization
    return pickle.loads(raw_cookie_data)
''',
    "requirements.txt": '''fastapi==0.110.0
reqeusts==2.28.1
pillow==9.5.0
urllib3==1.26.5
'''
}

SAMPLE_NODE_PR = {
    "server/routes/webhooks.js": '''const axios = require('axios');
const { exec } = require('child_process');
const db = require('../db');

// SSRF Vulnerability
async function forwardWebhook(req, res) {
    const targetUrl = req.body.url;
    const response = await axios.get(targetUrl);
    return res.json(response.data);
}

// SQL Injection in raw template literal
async function fetchUserTransactions(req, res) {
    const accountId = req.query.accountId;
    const sql = `SELECT * FROM transactions WHERE account_id = ${accountId}`;
    const result = await db.query(sql);
    return res.json(result.rows);
}

// Command Injection
function archiveTenantData(tenantSlug) {
    exec(`tar -czf /backups/${tenantSlug}.tar.gz /data/${tenantSlug}`, (err, stdout) => {
        console.log("Archive generated:", stdout);
    });
}

module.exports = { forwardWebhook, fetchUserTransactions, archiveTenantData };
''',
    "package.json": '''{
  "name": "enterprise-billing-service",
  "version": "1.0.0",
  "dependencies": {
    "axios": "0.21.0",
    "lodash": "4.17.15",
    "crossenv": "1.0.0",
    "flatmap-stream": "0.1.1",
    "express": "^4.19.2"
  }
}
'''
}

SAMPLE_PHP_PR = {
    "app/Http/Controllers/ReportController.php": r'''<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function searchInvoices(Request $request)
    {
        $clientId = $request->input('client_id');
        // Raw SQL injection vulnerability
        $results = DB::select(DB::raw("SELECT * FROM invoices WHERE client_id = " . $clientId));
        return response()->json($results);
    }

    public function convertDocument(Request $request)
    {
        $doc = $request->input('filename');
        // Command injection vulnerability
        $output = shell_exec("soffice --headless --convert-to pdf " . $doc);
        return response()->json(['output' => $output]);
    }

    public function restoreCache(Request $request)
    {
        $payload = $request->input('state');
        // PHP Object Injection via unserialize
        $state = unserialize($payload);
        return response()->json($state);
    }
}
'''
}
