# Security Hardening Tasks

## Goals
- Reduce exploit surface for auth, session handling, and resource transfer endpoints.
- Add prevention and monitoring for account takeover and resource-pushing abuse.

## Tasks
- Enforce HTTPS-only cookies, SameSite=Lax, HttpOnly for session cookies; add per-request CSRF token validation on all POST/PUT/DELETE endpoints.
- Rate-limit login and password reset by IP + account (sliding window); add exponential backoff after 5 failures; emit audit log entries.
- Implement signed nonces for AJAX endpoints that mutate state; expire after 10 minutes or one use.
- Add HMAC on resource transfer/trade actions to prevent tampering; verify server-side with replay protection (nonce + timestamp).
- Validate request origins (Origin/Referer) for sensitive endpoints; reject cross-origin posts when not matching allowed host list.
- Add IP/device fingerprint to session table; alert and require re-auth on sudden changes in high-risk actions (trades over 50k, tribe role changes).
- Implement server-side caps for push-risk flows: max transfer per hour per pair, diminishing returns for repeated gifts, and tribe-aid quotas.
- Create admin audit views for privilege changes, large trades, role escalations, and mass kicks; include actor, target, IP hash, and timestamp.
- Run dependency scan (npm/audit, composer audit if available); document critical CVEs and patch plan.

## Acceptance Criteria
- All mutating requests require CSRF token + session cookie; cookies are secure and properly scoped.
- Login/reset endpoints are rate-limited and logged; brute-force attempts are visible in audit logs.
- Resource transfer/trade actions are tamper-resistant (HMAC + nonce) with replay prevention.
- Push/abuse caps enforced server-side with clear error codes surfaced to UI.
- Admin audit view shows recent privileged actions with search/filter.
