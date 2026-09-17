---
description: Review current changes for security vulnerabilities
---

Review the current git diff for security issues only.

Focus on:
- authentication
- authorization and access control
- session, cookie and JWT handling
- input validation and output encoding
- SQL, NoSQL, template and command injection
- XSS and CSRF
- cryptography, hashing and encryption
- secrets, credentials and API keys
- sensitive data and PII exposure
- file upload and path traversal
- SSRF and unsafe outbound requests
- database permissions and RLS
- unsafe external input
- security misconfiguration
- insecure defaults
- dependency and software supply chain risks
- untrusted packages, scripts and build processes
- software and data integrity
- insecure deserialization
- missing or unsafe security logging
- information leakage through logs or errors
- unsafe error and exception handling
- fail-open behavior
- insecure design or missing security controls

Do not modify files.

Report only actionable security findings.

Output findings as a Markdown table with these columns:

| Severity | File / Location | Risk | Recommended Fix |
|---|---|---|---|

Severity must be one of:
- Critical
- High
- Medium
- Low

Keep each finding concise and actionable.

If no meaningful security issue is found, say clearly:

No meaningful security issues found in the current diff.