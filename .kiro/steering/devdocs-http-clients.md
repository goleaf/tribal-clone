# DevDocs HTTP client practices

- Align HTTP verbs/status codes with DevDocs HTTP spec references; use 2xx for success, 4xx for client errors, 5xx for server errors.
- Validate headers and caching semantics (`ETag`, `If-None-Match`, `Cache-Control`) per DevDocs; avoid custom headers when standard ones fit.
- Encode payloads with correct `Content-Type` and charset; prefer JSON with explicit `application/json; charset=utf-8`.
- Implement robust timeout/retry/backoff strategies; surface actionable error messages without leaking internals.
- Log request ids and correlation tokens; redact secrets; comply with rate limits advertised by upstream APIs.
