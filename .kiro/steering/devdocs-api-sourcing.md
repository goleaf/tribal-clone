# DevDocs API sourcing

- Treat devdocs.io as the canonical API reference; link directly to sections for language/framework APIs you cite.
- Verify parameter defaults, return types, and exceptions against DevDocs before coding; avoid relying on memory.
- Prefer stable APIs over experimental ones; call out browser/node/PHP version support explicitly.
- When code touches multiple runtimes (PHP + JS), check both stacks on DevDocs and mention compatibility notes.
- Capture breaking changes or deprecations from DevDocs in the change summary or migration notes.
