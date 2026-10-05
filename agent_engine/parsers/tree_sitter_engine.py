"""
Tree-sitter AST Parsing Engine for AuditCortex
Multi-language AST parser, dangerous sink analyzer, and syntax validator.
"""

import re
from typing import Dict, List, Optional, Tuple, Any
from tree_sitter import Language, Parser, Node, Tree

import tree_sitter_python as tspy
import tree_sitter_javascript as tsjs
import tree_sitter_typescript as tsts
import tree_sitter_php as tsphp
import tree_sitter_go as tsgo


class TreeSitterEngine:
    def __init__(self):
        self.languages: Dict[str, Language] = {}
        self.parsers: Dict[str, Parser] = {}
        self._initialize_parsers()

    def _initialize_parsers(self):
        try:
            self.languages["python"] = Language(tspy.language())
            self.parsers["python"] = Parser(self.languages["python"])
        except Exception as e:
            print(f"[TreeSitter] Warning: Failed to load python grammar: {e}")

        try:
            self.languages["javascript"] = Language(tsjs.language())
            self.parsers["javascript"] = Parser(self.languages["javascript"])
        except Exception as e:
            print(f"[TreeSitter] Warning: Failed to load javascript grammar: {e}")

        try:
            self.languages["typescript"] = Language(tsts.language_typescript())
            self.parsers["typescript"] = Parser(self.languages["typescript"])
            self.languages["tsx"] = Language(tsts.language_tsx())
            self.parsers["tsx"] = Parser(self.languages["tsx"])
        except Exception as e:
            print(f"[TreeSitter] Warning: Failed to load typescript grammar: {e}")

        try:
            self.languages["php"] = Language(tsphp.language_php())
            self.parsers["php"] = Parser(self.languages["php"])
        except Exception as e:
            print(f"[TreeSitter] Warning: Failed to load php grammar: {e}")

        try:
            self.languages["go"] = Language(tsgo.language())
            self.parsers["go"] = Parser(self.languages["go"])
        except Exception as e:
            print(f"[TreeSitter] Warning: Failed to load go grammar: {e}")

    def detect_language(self, filepath: str) -> Optional[str]:
        path = filepath.lower()
        if path.endswith(".py"):
            return "python"
        elif path.endswith((".js", ".jsx", ".mjs", ".cjs")):
            return "javascript"
        elif path.endswith(".ts"):
            return "typescript"
        elif path.endswith(".tsx"):
            return "tsx"
        elif path.endswith((".php", ".phtml")):
            return "php"
        elif path.endswith(".go"):
            return "go"
        return None

    def parse_code(self, code: str, language: str) -> Optional[Tree]:
        parser = self.parsers.get(language)
        if not parser:
            return None
        return parser.parse(code.encode("utf-8"))

    def validate_syntax(self, code: str, language: str) -> Tuple[bool, Optional[str]]:
        """
        Validates if the synthesized patch parses into a clean AST without ERROR nodes.
        Returns (is_valid, error_description).
        """
        parser = self.parsers.get(language)
        if not parser:
            # If no parser available for this language, default to True
            return True, None

        tree = parser.parse(code.encode("utf-8"))
        if not tree or not tree.root_node:
            return False, "Failed to parse syntax tree"

        if tree.root_node.has_error:
            # Locate first error node
            error_node = self._find_first_error(tree.root_node)
            if error_node:
                line = error_node.start_point[0] + 1
                col = error_node.start_point[1] + 1
                return False, f"Syntax error at line {line}:{col} (node: {error_node.type})"
            return False, "Syntax tree contains syntax error nodes"

        return True, None

    def _find_first_error(self, node: Node) -> Optional[Node]:
        if node.type == "ERROR" or node.is_missing:
            return node
        for child in node.children:
            if child.has_error:
                res = self._find_first_error(child)
                if res:
                    return res
        return None

    def scan_for_dangerous_sinks(self, filepath: str, code: str) -> List[Dict[str, Any]]:
        """
        Scans code using Tree-sitter AST traversal and pattern matching.
        Returns a list of candidate AST vulnerability findings.
        """
        lang = self.detect_language(filepath)
        findings = []

        # 1. Regex/Heuristic Secret Scanner (Language agnostic)
        secret_findings = self._scan_hardcoded_secrets(filepath, code)
        findings.extend(secret_findings)

        if not lang or lang not in self.parsers:
            return findings

        tree = self.parse_code(code, lang)
        if not tree or not tree.root_node:
            return findings

        lines = code.splitlines()

        # AST Traversal
        if lang == "python":
            findings.extend(self._scan_python_ast(tree.root_node, code, lines, filepath))
        elif lang in ("javascript", "typescript", "tsx"):
            findings.extend(self._scan_js_ast(tree.root_node, code, lines, filepath, lang))
        elif lang == "php":
            findings.extend(self._scan_php_ast(tree.root_node, code, lines, filepath))
        elif lang == "go":
            findings.extend(self._scan_go_ast(tree.root_node, code, lines, filepath))

        return findings

    def _scan_hardcoded_secrets(self, filepath: str, code: str) -> List[Dict[str, Any]]:
        findings = []
        patterns = [
            (
                r'(?i)(?:api_key|secret_key|app_secret|private_key|aws_access_key_id|aws_secret_access_key|jwt_secret)\s*[:=]\s*["\']([A-Za-z0-9_\-\.]{16,})["\']',
                "CWE-798",
                "HARDCODED_SECRET",
                "Hardcoded API / Secret Token in Source Code",
                "CRITICAL"
            ),
            (
                r'AKIA[0-9A-Z]{16}',
                "CWE-798",
                "HARDCODED_SECRET",
                "Hardcoded AWS Access Key Identifier",
                "CRITICAL"
            ),
            (
                r'ghp_[0-9a-zA-Z]{36}',
                "CWE-798",
                "HARDCODED_SECRET",
                "Hardcoded GitHub Personal Access Token",
                "CRITICAL"
            ),
            (
                r'-----BEGIN (?:RSA |EC )?PRIVATE KEY-----',
                "CWE-312",
                "HARDCODED_SECRET",
                "Exposed Cryptographic Private Key",
                "CRITICAL"
            )
        ]

        lines = code.splitlines()
        for idx, line in enumerate(lines):
            # Skip test files or comments if dummy
            if "example" in line.lower() or "mock" in line.lower() or "test" in line.lower() and "dummy" in line.lower():
                continue

            for pattern, cwe, vtype, title, severity in patterns:
                match = re.search(pattern, line)
                if match:
                    findings.append({
                        "file_path": filepath,
                        "line_start": idx + 1,
                        "line_end": idx + 1,
                        "severity": severity,
                        "cwe_id": cwe,
                        "vulnerability_type": vtype,
                        "title": title,
                        "description": f"Potential exposed credential or sensitive secret discovered in string literal on line {idx + 1}.",
                        "dangerous_sink": line.strip(),
                        "taint_source": "Source Code Literal",
                        "ast_node_type": "string_literal",
                        "confidence_score": 0.95
                    })
                    break
        return findings

    # --- PYTHON AST SCANNER ---
    def _scan_python_ast(self, root: Node, code: str, lines: List[str], filepath: str) -> List[Dict[str, Any]]:
        findings = []

        def visitor(node: Node):
            # Check call expressions
            if node.type == "call":
                func_node = node.child_by_field_name("function")
                args_node = node.child_by_field_name("arguments")

                if func_node and args_node:
                    func_text = self._get_node_text(func_node, code)
                    args = [c for c in args_node.children if c.type not in ("(", ")", ",")]

                    # 1. SQL Injection: cursor.execute / db.session.execute with f-string or % or +
                    if any(sink in func_text for sink in ["execute", "executemany", "raw_sql", "execute_query", "raw"]):
                        if args:
                            first_arg = args[0]
                            if first_arg.type in ("binary_operator", "formatted_string") or (
                                first_arg.type == "call" and ".format" in self._get_node_text(first_arg, code)
                            ):
                                line = node.start_point[0] + 1
                                findings.append({
                                    "file_path": filepath,
                                    "line_start": line,
                                    "line_end": node.end_point[0] + 1,
                                    "severity": "CRITICAL",
                                    "cwe_id": "CWE-89",
                                    "vulnerability_type": "SQL_INJECTION",
                                    "title": "SQL Injection via Unsanitized Dynamic Query Construction",
                                    "description": f"Database execution sink `{func_text}` called with formatted string or concatenated expression. Untrusted user inputs can alter query semantics.",
                                    "dangerous_sink": self._get_node_text(node, code),
                                    "taint_source": self._get_node_text(first_arg, code),
                                    "ast_node_type": node.type,
                                    "confidence_score": 0.96
                                })

                    # 2. Command Injection: os.system, subprocess.Popen(..., shell=True)
                    if func_text in ("os.system", "os.popen", "subprocess.call", "subprocess.run", "subprocess.Popen"):
                        is_shell_true = False
                        has_dynamic_command = False
                        first_arg = args[0] if args else None

                        if first_arg and first_arg.type in ("binary_operator", "formatted_string", "identifier"):
                            has_dynamic_command = True

                        for arg in args:
                            arg_text = self._get_node_text(arg, code)
                            if "shell=True" in arg_text or func_text in ("os.system", "os.popen"):
                                is_shell_true = True

                        if is_shell_true or has_dynamic_command:
                            line = node.start_point[0] + 1
                            findings.append({
                                "file_path": filepath,
                                "line_start": line,
                                "line_end": node.end_point[0] + 1,
                                "severity": "CRITICAL",
                                "cwe_id": "CWE-78",
                                "vulnerability_type": "COMMAND_INJECTION",
                                "title": "OS Command Injection via Shell Execution Sink",
                                "description": f"Dangerous command execution `{func_text}` invoked with shell=True or dynamic string interpolation. Arbitrary commands can be executed on host system.",
                                "dangerous_sink": self._get_node_text(node, code),
                                "taint_source": self._get_node_text(first_arg, code) if first_arg else "",
                                "ast_node_type": node.type,
                                "confidence_score": 0.94
                            })

                    # 3. SSRF: requests.get / httpx.get / urllib.request.urlopen with dynamic variable
                    if any(sink in func_text for sink in ["requests.get", "requests.post", "urllib.request.urlopen", "httpx.get", "httpx.post"]):
                        if args:
                            url_arg = args[0]
                            if url_arg.type in ("identifier", "formatted_string", "binary_operator"):
                                line = node.start_point[0] + 1
                                findings.append({
                                    "file_path": filepath,
                                    "line_start": line,
                                    "line_end": node.end_point[0] + 1,
                                    "severity": "HIGH",
                                    "cwe_id": "CWE-918",
                                    "vulnerability_type": "SSRF",
                                    "title": "Server-Side Request Forgery (SSRF) in HTTP Client Sink",
                                    "description": f"Outbound HTTP request sink `{func_text}` accepts arbitrary user-controlled URL without IP or schema allowlist validation.",
                                    "dangerous_sink": self._get_node_text(node, code),
                                    "taint_source": self._get_node_text(url_arg, code),
                                    "ast_node_type": node.type,
                                    "confidence_score": 0.88
                                })

                    # 4. Insecure Deserialization: pickle.loads, yaml.load
                    if func_text in ("pickle.loads", "pickle.load", "_pickle.loads"):
                        line = node.start_point[0] + 1
                        findings.append({
                            "file_path": filepath,
                            "line_start": line,
                            "line_end": node.end_point[0] + 1,
                            "severity": "CRITICAL",
                            "cwe_id": "CWE-502",
                            "vulnerability_type": "DESERIALIZATION",
                            "title": "Arbitrary Code Execution via Insecure Python Pickle Deserialization",
                            "description": f"`{func_text}` deserializes untrusted bytes into Python objects, leading to immediate Remote Code Execution (RCE) via `__reduce__` exploit gadgets.",
                            "dangerous_sink": self._get_node_text(node, code),
                            "taint_source": self._get_node_text(args[0], code) if args else "",
                            "ast_node_type": node.type,
                            "confidence_score": 0.98
                        })

                    # 5. Path Traversal: open(...) with dynamic path and no safe path validation
                    if func_text == "open" and args:
                        path_arg = args[0]
                        if path_arg.type in ("binary_operator", "formatted_string") or (
                            path_arg.type == "call" and "os.path.join" in self._get_node_text(path_arg, code)
                        ):
                            line = node.start_point[0] + 1
                            findings.append({
                                "file_path": filepath,
                                "line_start": line,
                                "line_end": node.end_point[0] + 1,
                                "severity": "HIGH",
                                "cwe_id": "CWE-22",
                                "vulnerability_type": "PATH_TRAVERSAL",
                                "title": "Arbitrary File Access via Path Traversal",
                                "description": f"File stream sink `{func_text}` opens dynamic path without `os.path.abspath` canonicalization or boundary checking.",
                                "dangerous_sink": self._get_node_text(node, code),
                                "taint_source": self._get_node_text(path_arg, code),
                                "ast_node_type": node.type,
                                "confidence_score": 0.90
                            })

            for child in node.children:
                visitor(child)

        visitor(root)
        return findings

    # --- JAVASCRIPT / TYPESCRIPT AST SCANNER ---
    def _scan_js_ast(self, root: Node, code: str, lines: List[str], filepath: str, lang: str) -> List[Dict[str, Any]]:
        findings = []

        def visitor(node: Node):
            # Check call expressions
            if node.type == "call_expression":
                func_node = node.child_by_field_name("function")
                args_node = node.child_by_field_name("arguments")

                if func_node and args_node:
                    func_text = self._get_node_text(func_node, code)
                    args = [c for c in args_node.children if c.type not in ("(", ")", ",")]

                    # 1. SQL Injection: db.query(`SELECT ... ${userInput}`)
                    if any(sink in func_text for sink in ["query", "raw", "$queryRawUnsafe", "execute"]):
                        if args:
                            first_arg = args[0]
                            if first_arg.type in ("template_string", "binary_expression"):
                                line = node.start_point[0] + 1
                                findings.append({
                                    "file_path": filepath,
                                    "line_start": line,
                                    "line_end": node.end_point[0] + 1,
                                    "severity": "CRITICAL",
                                    "cwe_id": "CWE-89",
                                    "vulnerability_type": "SQL_INJECTION",
                                    "title": "SQL Injection in Node.js Database Query",
                                    "description": f"Database sink `{func_text}` called with template literal or string concatenation instead of parameterized values.",
                                    "dangerous_sink": self._get_node_text(node, code),
                                    "taint_source": self._get_node_text(first_arg, code),
                                    "ast_node_type": node.type,
                                    "confidence_score": 0.95
                                })

                    # 2. Command Injection: child_process.exec(cmd)
                    if any(sink in func_text for sink in ["exec", "execSync"]) and "child_process" in code:
                        if args and args[0].type in ("template_string", "binary_expression", "identifier"):
                            line = node.start_point[0] + 1
                            findings.append({
                                "file_path": filepath,
                                "line_start": line,
                                "line_end": node.end_point[0] + 1,
                                "severity": "CRITICAL",
                                "cwe_id": "CWE-78",
                                "vulnerability_type": "COMMAND_INJECTION",
                                "title": "Command Injection via Child Process Execution",
                                "description": f"Process execution sink `{func_text}` executes command in system shell without input sanitization or argument separation.",
                                "dangerous_sink": self._get_node_text(node, code),
                                "taint_source": self._get_node_text(args[0], code),
                                "ast_node_type": node.type,
                                "confidence_score": 0.93
                            })

                    # 3. SSRF: axios.get(url) / fetch(url)
                    if func_text in ("axios.get", "axios.post", "fetch", "http.get", "https.get"):
                        if args and args[0].type in ("identifier", "template_string", "binary_expression"):
                            line = node.start_point[0] + 1
                            findings.append({
                                "file_path": filepath,
                                "line_start": line,
                                "line_end": node.end_point[0] + 1,
                                "severity": "HIGH",
                                "cwe_id": "CWE-918",
                                "vulnerability_type": "SSRF",
                                "title": "Server-Side Request Forgery via HTTP Request Client",
                                "description": f"Network client sink `{func_text}` requests unvalidated external resource specified by dynamic parameter.",
                                "dangerous_sink": self._get_node_text(node, code),
                                "taint_source": self._get_node_text(args[0], code),
                                "ast_node_type": node.type,
                                "confidence_score": 0.89
                            })

                    # 4. Path Traversal: fs.readFile / fs.readFileSync
                    if any(sink in func_text for sink in ["fs.readFile", "fs.readFileSync", "fs.createReadStream"]):
                        if args and args[0].type in ("template_string", "binary_expression"):
                            line = node.start_point[0] + 1
                            findings.append({
                                "file_path": filepath,
                                "line_start": line,
                                "line_end": node.end_point[0] + 1,
                                "severity": "HIGH",
                                "cwe_id": "CWE-22",
                                "vulnerability_type": "PATH_TRAVERSAL",
                                "title": "Arbitrary File Read via Path Traversal Sink",
                                "description": f"File system sink `{func_text}` accepts concatenated file paths, allowing `../` directory climbing.",
                                "dangerous_sink": self._get_node_text(node, code),
                                "taint_source": self._get_node_text(args[0], code),
                                "ast_node_type": node.type,
                                "confidence_score": 0.91
                            })

            for child in node.children:
                visitor(child)

        visitor(root)
        return findings

    # --- PHP AST SCANNER ---
    def _scan_php_ast(self, root: Node, code: str, lines: List[str], filepath: str) -> List[Dict[str, Any]]:
        findings = []

        def visitor(node: Node):
            # Check function / method calls
            if node.type in ("function_call_expression", "member_call_expression", "scoped_call_expression"):
                call_text = self._get_node_text(node, code)
                line = node.start_point[0] + 1

                # 1. SQL Injection: DB::raw("... $id"), $pdo->query("... " . $id)
                if re.search(r'\b(DB::raw|DB::select|->query|->selectRaw)\s*\(', call_text):
                    if ("$" in call_text or "." in call_text) and not (", [" in call_text or ", array(" in call_text or "?" in call_text):
                        findings.append({
                            "file_path": filepath,
                            "line_start": line,
                            "line_end": node.end_point[0] + 1,
                            "severity": "CRITICAL",
                            "cwe_id": "CWE-89",
                            "vulnerability_type": "SQL_INJECTION",
                            "title": "SQL Injection via Raw SQL Query Interpolation",
                            "description": f"Database execution sink `{call_text[:50]}...` uses unescaped string concatenation or variable interpolation in raw query.",
                            "dangerous_sink": call_text,
                            "taint_source": "Dynamic variable interpolation",
                            "ast_node_type": node.type,
                            "confidence_score": 0.96
                        })

                # 2. Command Injection: shell_exec, system, exec, passthru, popen, proc_open
                cmd_match = re.search(r'\b(shell_exec|system|exec|passthru|popen|proc_open)\s*\(', call_text)
                if cmd_match and "$" in call_text and "escapeshell" not in call_text:
                    sink = cmd_match.group(1)
                    findings.append({
                        "file_path": filepath,
                        "line_start": line,
                        "line_end": node.end_point[0] + 1,
                        "severity": "CRITICAL",
                        "cwe_id": "CWE-78",
                        "vulnerability_type": "COMMAND_INJECTION",
                        "title": "OS Command Injection in PHP Script",
                        "description": f"Dangerous execution sink `{sink}` called with user parameters without `escapeshellcmd` or `escapeshellarg`.",
                        "dangerous_sink": call_text,
                        "taint_source": "User input parameter",
                        "ast_node_type": node.type,
                        "confidence_score": 0.94
                    })

                # 3. Insecure Deserialization: unserialize
                if re.search(r'\bunserialize\s*\(', call_text) and "json_decode" not in call_text:
                    findings.append({
                        "file_path": filepath,
                        "line_start": line,
                        "line_end": node.end_point[0] + 1,
                        "severity": "CRITICAL",
                        "cwe_id": "CWE-502",
                        "vulnerability_type": "DESERIALIZATION",
                        "title": "PHP Object Injection via Insecure `unserialize()`",
                        "description": f"Calling `unserialize()` on untrusted data allows attacker to instantiate arbitrary objects and trigger destructors/magic methods for POP chain exploits.",
                        "dangerous_sink": call_text,
                        "taint_source": "Serialized input payload",
                        "ast_node_type": node.type,
                        "confidence_score": 0.97
                    })

            for child in node.children:
                visitor(child)

        visitor(root)
        return findings

    # --- GO AST SCANNER ---
    def _scan_go_ast(self, root: Node, code: str, lines: List[str], filepath: str) -> List[Dict[str, Any]]:
        findings = []

        def visitor(node: Node):
            if node.type == "call_expression":
                call_text = self._get_node_text(node, code)
                line = node.start_point[0] + 1

                # 1. SQL Injection: db.Query(fmt.Sprintf(...))
                if ("db.Query" in call_text or "db.Exec" in call_text) and (
                    "fmt.Sprintf" in call_text or "+" in call_text
                ):
                    findings.append({
                        "file_path": filepath,
                        "line_start": line,
                        "line_end": node.end_point[0] + 1,
                        "severity": "CRITICAL",
                        "cwe_id": "CWE-89",
                        "vulnerability_type": "SQL_INJECTION",
                        "title": "SQL Injection via Sprintf in Go Database Query",
                        "description": f"Database sink `{call_text[:50]}...` uses string formatting instead of parameterized placeholders ($1, ?).",
                        "dangerous_sink": call_text,
                        "taint_source": "fmt.Sprintf formatting",
                        "ast_node_type": node.type,
                        "confidence_score": 0.95
                    })

                # 2. Command Injection: exec.Command("sh", "-c", ...)
                if "exec.Command" in call_text and ("sh" in call_text or "bash" in call_text) and (
                    "fmt.Sprintf" in call_text or "+" in call_text
                ):
                    findings.append({
                        "file_path": filepath,
                        "line_start": line,
                        "line_end": node.end_point[0] + 1,
                        "severity": "CRITICAL",
                        "cwe_id": "CWE-78",
                        "vulnerability_type": "COMMAND_INJECTION",
                        "title": "Command Injection via Shell Invocation in Go",
                        "description": "Spawning system shell with formatted string arguments enables subshell command chaining.",
                        "dangerous_sink": call_text,
                        "taint_source": "Shell command arguments",
                        "ast_node_type": node.type,
                        "confidence_score": 0.93
                    })

            for child in node.children:
                visitor(child)

        visitor(root)
        return findings

    def _get_node_text(self, node: Node, code: str) -> str:
        return code[node.start_byte:node.end_byte]
