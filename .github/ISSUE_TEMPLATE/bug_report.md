---
name: Bug report
about: Report a problem with ingest, delivery, replay, or the dashboard
title: ''
labels: bug
assignees: ''
---

<!--
Do NOT report security vulnerabilities here. Follow SECURITY.md and use GitHub's
private vulnerability reporting instead.
-->

## Summary

A clear and concise description of what the bug is.

## Steps to reproduce

1.
2.
3.

## Expected behavior

What you expected to happen.

## Actual behavior

What actually happened. Include the JSON error envelope for an ingest issue, or the
flash/field error for a dashboard issue.

## Relevant area

- [ ] Ingest / signature verification
- [ ] Delivery / retries / dead-lettering
- [ ] Replay or requeue
- [ ] Dashboard (browse, filter, detail)
- [ ] Pruning / retention
- [ ] Other

Which provider is involved, if any (Stripe / GitHub / Shopify / generic):

## Logs

Paste any relevant structured log lines (for example `ingest.signature_failed`,
`delivery.dead`). **Redact secrets, signatures, and full payloads.**

```
```

## Environment

- hook-relay version or commit:
- PHP version (`php -v`):
- Database (SQLite / MySQL and version):
- Queue driver:

## Additional context

Anything else that helps explain the problem.
