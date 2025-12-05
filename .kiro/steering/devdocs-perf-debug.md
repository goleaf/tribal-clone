# DevDocs performance & debugging

- Consult DevDocs for perf-sensitive APIs (streams, buffers, generators); prefer lazy iteration over materializing large arrays.
- Measure before optimizing; add lightweight logging/metrics and remove noisy debug output from production paths.
- Handle errors explicitly; use typed exceptions; avoid broad catch-all without rethrowing critical failures.
- Keep I/O bounded: batch DB and network calls, reuse prepared statements, and cache config where safe.
- Document profiling steps and tools used; include reproducible commands and expected baselines.
