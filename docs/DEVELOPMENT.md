# Psst — Development Flow & Engineering Standards

## Repo layout (monorepo)
```
psst/
  server/    Laravel 13 API + Reverb + queues
  client/    Quasar app (web/PWA, Capacitor mobile, desktop wrapper)
  admin/     Vue 3 admin SPA
  deploy/    docker-compose, Caddy, env templates, backup scripts
  docs/      PLAN, DATABASE, DESIGN, DEVELOPMENT
```

## Workflow
- Trunk-based: short-lived branches → PR → CI green → squash merge to `main`. Conventional Commits.
- CI (GitHub Actions) on every PR: Pint + PHPStan (level 8 / Larastan), Pest tests, `composer audit`, ESLint + vue-tsc + Vitest, `pnpm audit`, Docker build, Trivy image scan.
- Migrations are forward-only, zero-downtime-safe (expand → migrate → contract).
- Deploy: build images in CI → push to registry → `docker compose pull && up -d` on VPS; healthchecks + automatic rollback to previous tag.
- Environments: local (docker compose), production. Secrets via env files outside git (SOPS/age or Docker secrets).

## Milestones (each ends shippable + tested)
0. **Foundations:** monorepo, docker compose (caddy, app, reverb, queue, scheduler, postgres, redis), CI, lint/static analysis, health endpoint.
1. **Auth:** Google/Apple SSO (Socialite web flow), Sanctum SPA cookie auth for web + tokens for mobile, mobile sign-in spike.
2. **Domain:** users/threads/messages/blocks/reports, policies, API Resources, feature tests incl. privacy leak tests.
3. **Realtime:** Reverb private channels + authorization, optimistic UI.
4. **Client web:** design tokens, component gallery, link page, inbox, chat, settings.
5. **Notifications:** Web Push + FCM via queue jobs.
6. **Moderation + Admin SPA:** block/report, admin API, audit log, dashboard.
7. **Mobile + desktop builds:** Capacitor, Electron/Tauri.
8. **Hardening + launch:** load test, backups/restore drill, privacy/ToS pages, monitoring.

## Code principles
- **SOLID, thin controllers.** Controller → FormRequest (validation) → Action class (one use-case, single `handle()`) → Eloquent/Repository only when it earns its keep. Policies for authorization. API Resources for output shape. No business logic in controllers or models' magic.
- **DRY but not clever.** Prefer explicit over magic; small pure functions; strict types (`declare(strict_types=1)`), typed properties/returns, enums, readonly DTOs.
- **Frontend:** Composition API + TypeScript strict, feature-folder structure, composables for logic, Pinia only for shared state, API layer generated from OpenAPI (Scramble) so types never drift.
- **Testing pyramid:** Pest unit (Actions) + feature (HTTP, policies) + a few E2E (Playwright). Mandatory tests: authorization boundaries, "receiver never sees sender identity", block enforcement, rate limits.
- **Observability:** structured JSON logs, request IDs, Sentry (or GlitchTip self-hosted), Prometheus metrics/Uptime Kuma.

## Security checklist (built in, not bolted on)
- SSO only; verify ID tokens server-side (iss/aud/exp/nonce); no passwords stored.
- Sanctum cookies: HttpOnly, Secure, SameSite=Lax; CSRF on stateful routes; short-lived tokens + rotation for mobile.
- Authorization on every route via Policies; deny by default; UUIDs prevent IDOR enumeration; scoped queries (`where receiver_id = auth`).
- Input: FormRequest validation, length caps, output escaped (Vue default), CSP + HSTS + X-Content-Type-Options + Referrer-Policy via Caddy; strict CORS allow-list.
- Rate limiting: per-user + per-IP on send/login/report; Turnstile on the send form; queue-based abuse throttles.
- Reverb channel auth checks thread membership; no client-trusted identity.
- Secrets never in git; dependency audit in CI; Dependabot/Renovate; non-root containers, read-only FS where possible, minimal images, only Caddy exposes ports; UFW + fail2ban + SSH keys on VPS.
- Admin: role + mandatory 2FA, every identity reveal written to the audit log, separate subdomain and stricter CSP.
- Privacy: transparent ToS/Privacy Policy stating admins can identify senders; data export/delete flow; retention schedule.

## Performance checklist
- Laravel: Octane (FrankenPHP) or tuned PHP-FPM + OPcache/JIT, `config:cache route:cache event:cache`, queues for push/email/reports, Redis cache/sessions, eager loading + lazy-loading prevention, keyset pagination, DB indexes reviewed with `EXPLAIN`.
- Frontend: route-level code splitting, Vite tree-shaking, image optimization (AVIF/WebP), font subsetting + `font-display: swap`, PWA caching, optimistic UI; budget: link page <100KB JS gz, LCP <1.5s on 4G.
- Edge: Caddy with HTTP/2-3 + brotli/zstd; static assets immutable-cached; optional Cloudflare in front (free).
- Realtime: Reverb behind Caddy, Redis pub/sub for horizontal scale, heartbeat tuned.
- Load-test with k6 before launch; track p95 latency and DB slow-query log.
