# ZK Gallery — best-practices audit (2026-07-09)

Pilot 2.0 T1 brainstorm-time audit (laravel-best-practices). Stack verified from composer.json + package.json: Laravel 13.8, PHP 8.4, Postgres + pgvector, predis 3 (Valkey), intervention/image v4, libsodium-wrappers-sumo 0.8.4, Alpine 3.15. **No** Reverb / Horizon / Echo / Livewire.

## Executive summary / load-bearing findings

1. **True ZK and server-side ML/EXIF/transcode are mutually exclusive.** The transient-plaintext-during-processing window *is* the architecture — a strictly weaker guarantee than the opaque-manifest store (notes/files/etc). This MUST be written into the Gallery threat model as an explicit, accepted downgrade, not glossed over.

2. **Two hard impossibilities — surface as design deletions, not TODOs:**
   - Server-side pgvector similarity search on *encrypted* embeddings is impossible. Duplicate detection + face clustering must run **in the transient plaintext window** (worker holds decrypted vectors briefly) OR **client-side**. You cannot keep a persistent server-side pgvector index under ZK.
   - Any raw per-user **count** leaks structure — the same metadata-hiding you enforce with 4 KiB manifest padding is violated by a plain `COUNT(*)`. Decide whether gallery counts are in-scope for hiding.

3. **Resumability** = DB per-stage flags + `ShouldBeUnique` idempotent jobs + an "outstanding work" query. **NOT** `job_batches` state — batch state is orphaned when `docker compose up -d` cycles the workers (this deploy does exactly that).

4. **Skip Reverb.** No WebSocket/Echo infra exists and a backlog progress bar doesn't need sub-second latency. Poll a Valkey counter (or a cheap status endpoint).

## Anti-patterns to avoid
- Persisting plaintext renditions/embeddings "just for performance" — defeats ZK at rest.
- Relying on `Bus::batch()` progress across a worker restart.
- Server-side similarity search on encrypted vectors (impossible — see #2).
- Leaking structure via unpadded counts / per-stage row counts returned to the client.

## Open questions (for the brainstorm)
- Does the ZK guarantee cover *derived* data (thumbnails, embeddings, faces, place) at rest, or only originals?
- Where does dedup/face-clustering run — transient server window vs fully client-side?
- Are gallery counts/structure in the hiding scope, or accepted as visible?
- How much of the current server-op feature set is kept vs dropped?

_Note: agent had Bash/WebFetch permission-blocked; sourced via Read + WebSearch + one WebFetch, citations year-filtered 2025/2026._
