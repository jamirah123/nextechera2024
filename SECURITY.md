# Security Policy

## Supported versions

Only the current `main` branch of this private operations application is supported.

## Reporting a vulnerability

Do **not** open a public GitHub issue for security problems.

Email the Platinum Security Group technical owner (or your project administrator) with:

- Affected URL / module
- Steps to reproduce
- Impact assessment (data exposure, privilege escalation, etc.)
- Any suggested fix

You should receive an acknowledgement within a few business days.

## Hardening expectations

- Never commit `.env`, credentials, backup dumps, or private keys
- Keep `APP_DEBUG=false` in production
- Prefer Form Requests + policies for new endpoints
- Review [DEPLOY.md](./DEPLOY.md) security checklist before go-live
