# DevDocs JS guardrails

- Reference DevDocs for modern JS/DOM APIs; prefer standards over library polyfills unless required for compatibility.
- Validate fetch/XMLHttpRequest usage against DevDocs: CORS, headers, credentials, and error handling.
- Keep DOM writes minimal; batch mutations; prefer `textContent` to avoid XSS; validate query selectors per DevDocs definitions.
- Use `Intl` and `URL` APIs from DevDocs for parsing/formatting instead of ad-hoc regex/string splits.
- Note browser support and fallbacks when using newer APIs; link to DevDocs compatibility tables if available.
