# CRITICAL: Security Requirements
Mandatory Security Checks (Must implement ALL of the following)
1. Input Validation & Sanitization
- Validate and sanitize ALL user inputs before processing
- Use parameterized queries/prepared statements for database operations (NEVER string concatenation)
- Validate data types, ranges, and formats (lat/lon bounds: ±90/±180, location name length < 100 chars)
- Implement allowlists over denylists (only allow alphanumeric + spaces in location search)
- Escape output based on context (HTML escape in React, no raw HTML injection)
2. Authentication & Authorization
- Never hardcode credentials, API keys, or secrets (OpenAI key in Vercel env vars)
- Use environment variables or secure secret management systems
- Implement proper session management with secure tokens (none for MVP, stateless)
- Apply principle of least privilege (API routes only accept POST/GET, no DELETE/PUT)
- Verify authorization checks on every protected resource/action (N/A for public MVP)
3. Data Protection
- Encrypt sensitive data at rest and in transit (use TLS 1.2+ enforced by Vercel)
- Use strong, modern cryptographic algorithms (bcrypt for future auth, Argon2 for future PW)
- Never roll your own crypto—use established libraries (crypto-js for cache keys only)
- Implement proper key management practices (no persistent keys in MVP)
- Hash passwords with salt before storage (N/A for MVP, no user accounts)
4. Injection Prevention
- SQL Injection: No database used, but if added: ORM or parameterized queries exclusively
- XSS: Sanitize and escape all dynamic content in web outputs (React auto-escapes, DOMPurify for any raw HTML)
- Command Injection: Avoid shell execution; if necessary, use safe APIs with strict input validation (none planned)
- Path Traversal: No file system access; validate any future file paths with allowlists
- NoSQL Injection: Use safe APIs and input validation (applies to Vercel KV with JSON schema validation)
5. Error Handling & Logging
- Never expose sensitive information in error messages (generic "Failed to generate insights" to users)
- Log security events (failed API calls, validation errors) but sanitize sensitive data
- Implement proper exception handling (don't expose stack traces to users)
- Use structured logging with appropriate severity levels (Vercel logs with JSON structure)
6. Dependency & Configuration Security
- Use up-to-date, well-maintained libraries (Next.js 14+, OpenAI SDK v4, etc.)
- Avoid dependencies with known vulnerabilities (run npm audit in CI/CD)
- Implement Content Security Policy (CSP) for web applications (configured in next.config.js)
- Disable unnecessary features and services (no server actions enabled if unused)
- Set secure HTTP headers (X-Frame-Options, X-Content-Type-Options, HSTS via next.config.js)
7. Rate Limiting & DoS Protection
- Implement rate limiting on APIs and sensitive endpoints (Vercel KV rate limiter: 30 req/min per IP)
- Add timeout mechanisms for operations (AI API timeout set to 8s)
- Validate resource consumption (max request body size 1MB, cache key length limits)
- Protect against resource exhaustion attacks (streaming responses for large payloads)
8. Secure Defaults
- Fail securely (deny access by default, return 403 for invalid lat/lon)
- Minimize attack surface (disable debug modes in production, NODE_ENV=production)
- Use secure session cookies (HttpOnly, Secure, SameSite=Strict flags for any future auth)
- Implement CSRF protection for state-changing operations (if forms added, use Next.js built-in protection)
Prohibited Practices (NEVER do these)
- String concatenation in SQL queries
- Using eval() or similar dynamic code execution
- Hardcoding credentials, API keys, or secrets
- Storing passwords in plaintext
- Exposing stack traces or detailed errors to users
- Rolling your own cryptography
- Trusting client-side validation alone
- Using deprecated cryptographic algorithms (MD5, SHA1 for passwords)