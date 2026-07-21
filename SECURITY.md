# Security Policy

## Supported versions

hook-relay is distributed as source and security fixes land on `main`. Run the latest `main`, keep
`composer.lock` current, and pull framework patch releases as they ship.

| Version | Supported |
|---------|-----------|
| `main`  | Yes       |

## Reporting a vulnerability

Please report suspected vulnerabilities privately — do not open a public issue or pull request.

Use GitHub's private vulnerability reporting for this repository:
<https://github.com/thealirazadev/hook-relay/security/advisories/new>

Include the affected version or commit, a description of the impact, and a minimal reproduction if
you have one. You can expect an acknowledgement within a few days and a fix or mitigation plan once
the report is confirmed. Please give us a reasonable window to release a fix before any public
disclosure.

## Handling notes

A few relevant properties of the codebase when assessing a report:

- Inbound webhook signatures are verified per provider with `hash_hmac` + `hash_equals`
  (constant-time), and Stripe timestamps are checked against a 300-second tolerance.
- Source signing secrets are encrypted at rest via Laravel's `encrypted` cast (`APP_KEY`); they are
  never logged or rendered back in the dashboard.
- The ingest endpoint is stateless (no session, no CSRF) and is authenticated solely by the request
  signature; denylisted headers (authorization, cookie, proxy-auth) are stripped before storage.
- Dashboard access is session-authenticated, login is rate-limited, and all rendered user input is
  output-escaped.
