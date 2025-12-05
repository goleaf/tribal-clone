# DevDocs DOM + accessibility

- Follow DevDocs DOM specs for element creation, events, and focus management; avoid deprecated APIs.
- Provide keyboard support for interactive elements; ensure focus states and ARIA labels where semantics need clarification.
- Use semantic HTML; prefer `<button>` over clickable `<div>`; ensure form controls are labeled.
- Keep markup light; avoid inline event handlers; attach listeners unobtrusively and clean up to prevent leaks.
- Validate behavior against DevDocs accessibility references where available; test with screen readers and without a mouse.
