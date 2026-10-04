"""
Semantic Dependency Manifest Analyzer for AuditCortex
Parses package.json, composer.json, requirements.txt to detect toxic dependencies,
typosquats, known compromised packages, and severe CVEs.
"""

import json
import re
from typing import List, Dict, Any


class ManifestAnalyzer:
    # Known toxic or compromised packages (real-world supply chain attacks)
    TOXIC_PACKAGES = {
        "npm": {
            "flatmap-stream": "Compromised malicious dependency injected into event-stream to steal Bitcoin wallets.",
            "crossenv": "Typosquatted malware package mimicking popular 'cross-env' to exfiltrate env vars.",
            "fallguys": "Malicious package stealing Discord tokens and browser cookies.",
            "coa-compromised": "Compromised supply-chain version containing obfuscated password stealers.",
            "rc-compromised": "Backdoored version stealing local credentials and SSH keys.",
            "ua-parser-js-compromised": "Trojanized package deploying crypto-miners and password dumpers."
        },
        "python": {
            "reqeusts": "Typosquat of requests library that sends environment tokens to remote C2.",
            "colourama": "Typosquat of colorama distributing cryptocurrency clipbankers.",
            "python-dotenvs": "Typosquat harvesting .env secrets upon module import.",
            "py-cord-token": "Malicious credential harvester disguised as Discord wrapper."
        },
        "php": {
            "vulnerable/phpunit-eval": "Exposed eval-stdin RCE vulnerability in test runner endpoint.",
            "crypto-payment-miner": "Trojanized PHP package injecting mining scripts."
        }
    }

    # Known CVE vulnerability database for common dependencies
    KNOWN_CVES = {
        "npm": [
            {
                "package": "lodash",
                "vulnerable_version_regex": r"^<[=]?\s*4\.17\.20|^[~^]?4\.(?:[0-9]|1[0-6])\.",
                "cwe": "CWE-1321",
                "severity": "HIGH",
                "cve": "CVE-2020-8203",
                "title": "Prototype Pollution in Lodash",
                "description": "Versions of lodash prior to 4.17.21 are vulnerable to prototype pollution via zipObjectDeep and merge, leading to RCE or DoS."
            },
            {
                "package": "axios",
                "vulnerable_version_regex": r"^<[=]?\s*0\.21\.1|^[~^]?0\.(?:[0-9]|1[0-9]|20)\.",
                "cwe": "CWE-918",
                "severity": "HIGH",
                "cve": "CVE-2020-28168",
                "title": "Axios Server-Side Request Forgery via Insecure Follow Redirects",
                "description": "Axios prior to 0.21.1 permits SSRF when following 302 redirects with sensitive authorization headers forwarded to third-party domains."
            },
            {
                "package": "jsonwebtoken",
                "vulnerable_version_regex": r"^<[=]?\s*9\.0\.0|^[~^]?[0-8]\.",
                "cwe": "CWE-347",
                "severity": "CRITICAL",
                "cve": "CVE-2022-23529",
                "title": "Arbitrary Code Execution in jsonwebtoken verify()",
                "description": "Passing malicious secretOrPublicKey to jsonwebtoken.verify allows arbitrary file writing or code execution."
            }
        ],
        "python": [
            {
                "package": "urllib3",
                "vulnerable_version_regex": r"^<[=]?\s*2\.0\.7|^[~^]?1\.",
                "cwe": "CWE-200",
                "severity": "MEDIUM",
                "cve": "CVE-2023-45803",
                "title": "Proxy Authorization Header Leakage in urllib3",
                "description": "urllib3 leaks sensitive Proxy-Authorization headers to destination servers when redirecting across origins."
            },
            {
                "package": "pillow",
                "vulnerable_version_regex": r"^<[=]?\s*10\.0\.1|^[~^]?[0-9]\.",
                "cwe": "CWE-125",
                "severity": "HIGH",
                "cve": "CVE-2023-44271",
                "title": "Out-of-bounds Read in Pillow Image Processing",
                "description": "Uncontrolled memory read in Pillow font/image rasterizers allowing denial of service or arbitrary memory disclosure."
            }
        ],
        "php": [
            {
                "package": "guzzlehttp/guzzle",
                "vulnerable_version_regex": r"^<[=]?\s*7\.4\.5|^[~^]?[0-6]\.",
                "cwe": "CWE-918",
                "severity": "HIGH",
                "cve": "CVE-2022-31042",
                "title": "Authorization Header Leak on Cross-Origin Redirects in Guzzle",
                "description": "Guzzle client does not strip the Authorization header upon following cross-origin redirects."
            }
        ]
    }

    def analyze_manifest(self, filepath: str, content: str) -> List[Dict[str, Any]]:
        findings = []
        filename = filepath.replace("\\", "/").split("/")[-1].lower()

        if filename == "package.json":
            findings.extend(self._analyze_package_json(filepath, content))
        elif filename == "composer.json":
            findings.extend(self._analyze_composer_json(filepath, content))
        elif filename in ("requirements.txt", "requirements-dev.txt"):
            findings.extend(self._analyze_requirements_txt(filepath, content))

        return findings

    def _analyze_package_json(self, filepath: str, content: str) -> List[Dict[str, Any]]:
        findings = []
        try:
            data = json.loads(content)
        except Exception:
            return findings

        lines = content.splitlines()
        deps = {}
        deps.update(data.get("dependencies", {}))
        deps.update(data.get("devDependencies", {}))

        for pkg, version in deps.items():
            line_no = self._find_line(lines, pkg)

            # Check toxic package
            if pkg in self.TOXIC_PACKAGES["npm"]:
                findings.append({
                    "file_path": filepath,
                    "line_start": line_no,
                    "line_end": line_no,
                    "severity": "CRITICAL",
                    "cwe_id": "CWE-1395",
                    "vulnerability_type": "TOXIC_DEPENDENCY",
                    "title": f"Malicious / Toxic Package Detected: `{pkg}`",
                    "description": self.TOXIC_PACKAGES["npm"][pkg],
                    "dangerous_sink": f'"{pkg}": "{version}"',
                    "taint_source": "Dependency Manifest Manifest Injection",
                    "ast_node_type": "package_dependency",
                    "confidence_score": 0.99
                })

            # Check known CVEs
            for cve in self.KNOWN_CVES["npm"]:
                if cve["package"] == pkg:
                    if re.search(cve["vulnerable_version_regex"], version):
                        findings.append({
                            "file_path": filepath,
                            "line_start": line_no,
                            "line_end": line_no,
                            "severity": cve["severity"],
                            "cwe_id": cve["cwe"],
                            "vulnerability_type": "TOXIC_DEPENDENCY",
                            "title": f"Known CVE Vulnerability in `{pkg}`: {cve['cve']}",
                            "description": cve["description"],
                            "dangerous_sink": f'"{pkg}": "{version}"',
                            "taint_source": f"{cve['cve']} Advisory",
                            "ast_node_type": "package_dependency",
                            "confidence_score": 0.95
                        })
        return findings

    def _analyze_composer_json(self, filepath: str, content: str) -> List[Dict[str, Any]]:
        findings = []
        try:
            data = json.loads(content)
        except Exception:
            return findings

        lines = content.splitlines()
        reqs = {}
        reqs.update(data.get("require", {}))
        reqs.update(data.get("require-dev", {}))

        for pkg, version in reqs.items():
            line_no = self._find_line(lines, pkg)
            for cve in self.KNOWN_CVES["php"]:
                if cve["package"] == pkg:
                    if re.search(cve["vulnerable_version_regex"], version):
                        findings.append({
                            "file_path": filepath,
                            "line_start": line_no,
                            "line_end": line_no,
                            "severity": cve["severity"],
                            "cwe_id": cve["cwe"],
                            "vulnerability_type": "TOXIC_DEPENDENCY",
                            "title": f"Known Security Vulnerability in PHP Package `{pkg}`",
                            "description": cve["description"],
                            "dangerous_sink": f'"{pkg}": "{version}"',
                            "taint_source": f"{cve['cve']} Advisory",
                            "ast_node_type": "composer_requirement",
                            "confidence_score": 0.95
                        })
        return findings

    def _analyze_requirements_txt(self, filepath: str, content: str) -> List[Dict[str, Any]]:
        findings = []
        lines = content.splitlines()

        for idx, line in enumerate(lines):
            clean_line = line.strip().split("#")[0]
            if not clean_line:
                continue

            # e.g., requests==2.25.1 or reqeusts
            parts = re.split(r'[=><~!]+', clean_line)
            pkg = parts[0].strip()
            version = clean_line[len(pkg):].strip() if len(parts) > 1 else ""

            # Check toxic
            if pkg in self.TOXIC_PACKAGES["python"]:
                findings.append({
                    "file_path": filepath,
                    "line_start": idx + 1,
                    "line_end": idx + 1,
                    "severity": "CRITICAL",
                    "cwe_id": "CWE-1395",
                    "vulnerability_type": "TOXIC_DEPENDENCY",
                    "title": f"Malicious / Typosquatted Python Package: `{pkg}`",
                    "description": self.TOXIC_PACKAGES["python"][pkg],
                    "dangerous_sink": line.strip(),
                    "taint_source": "PyPI Supply Chain Poisoning",
                    "ast_node_type": "pip_requirement",
                    "confidence_score": 0.99
                })

            # Check CVE
            for cve in self.KNOWN_CVES["python"]:
                if cve["package"] == pkg and version:
                    clean_v = version.lstrip("=<>~")
                    if re.search(cve["vulnerable_version_regex"], clean_v):
                        findings.append({
                            "file_path": filepath,
                            "line_start": idx + 1,
                            "line_end": idx + 1,
                            "severity": cve["severity"],
                            "cwe_id": cve["cwe"],
                            "vulnerability_type": "TOXIC_DEPENDENCY",
                            "title": f"Vulnerable Dependency in `{pkg}`: {cve['cve']}",
                            "description": cve["description"],
                            "dangerous_sink": line.strip(),
                            "taint_source": f"{cve['cve']} Advisory",
                            "ast_node_type": "pip_requirement",
                            "confidence_score": 0.94
                        })
        return findings

    def _find_line(self, lines: List[str], target: str) -> int:
        for idx, line in enumerate(lines):
            if f'"{target}"' in line or f"'{target}'" in line or target in line:
                return idx + 1
        return 1
