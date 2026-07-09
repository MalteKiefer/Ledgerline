# Zero-Knowledge Gallery — Design

Date: 2026-07-09
Status: approved (brainstorm) — pending implementation plan

## Goal

Make the Gallery module zero-knowledge **at rest**, matching the posture already
applied to Files/Notes/Bookmarks/Todos: the server stores only opaque ciphertext
blobs and a minimal ownership ledger, never plaintext photos, thumbnails,
metadata, embeddings, faces, or a searchable vector index. Full feature parity is
kept (map, geocoding, metadata + content search, duplicate detection, People/face
recognition, Live Photos, HEIC/AVIF/video, albums, favourites, trash, export) —
**except cross-user/public sharing, which is physically impossible under ZK.**

## Threat model (explicit, accepted downgrade)

Gallery is **ZK-at-rest, NOT ZK-during-processing** — a strictly weaker guarantee
than the opaque-manifest store, and this must be documented as such.

- The vault key **never leaves the browser**.
- The server sees plaintext **only in a transient processing window**: the client
  decrypts an original, uploads the plaintext to a stateless worker endpoint over
  TLS, the worker extracts derived data and **immediately discards the plaintext**
  (tmpfs, no persistence). Nothing plaintext is written to disk/DB at rest.
- Accepted leaks (documented): (a) transient plaintext during processing; (b)
  approximate blob count/sizes visible in the ledger (padding optional, deferred);
  (c) reverse-geocoding sends GPS coordinates to the self-hosted geocoder during
  processing; (d) per-record loss if a single photo is mid-processing at a crash —
  never the whole library.

Two hard impossibilities (design deletions, not TODOs):
- No persistent server-side pgvector index — similarity search on encrypted
  vectors is impossible. Duplicate detection, face clustering and content search
  run **client-side** on decrypted vectors.
- Cross-user sharing + public album links are removed (recipient/public cannot
  decrypt), consistent with the already-disabled Notes/Files sharing.

## Data at rest (all opaque, per-user)

- **Content blobs** on the object store at `gallery/{blob_id}` — XChaCha20, each
  with its own wrapped key: original, thumbnail, medium, motion (Live), each face
  crop. One photo = several blobs.
- **Per-photo sealed metadata blob** `gallery/{metaId}` — one sealed JSON per
  photo: `{name, original_name, mime, checksum, taken_at, camera, lat, lng,
  place, media_type, embedding[512], faces:[{bbox, embedding[512], cropRef+key}],
  blobRefs:{original,thumb,medium,motion}+wrapped keys}`. Loaded lazily.
- **Sealed gallery index** — a dedicated opaque store `vault_gallery
  {user_id pk, ciphertext, version}`, separate from the shared `/store` manifest
  so gallery churn doesn't re-seal notes/todos. Kept small (~120 B/photo):
  - `photos[]`: `{id, metaRef+key, thumbRef+key, taken_at, media_type, favorite,
    trashed, albumIds[]}`
  - `albums[]`: `{id, name, coverPhotoId}`
  - `people[]`: `{id, name, hidden, faceRefs[]}` (client-computed clustering)

## Server surface

- **Ledger** `gallery_blobs {blob_id uuid pk, user_id, size, created_at}` — the
  only server-side gallery state. Drives quota, owner-scoped `raw` download,
  owner-scoped `deleteBlob`, and grace-gated `reconcile` (client sends its live
  blob-id set; unreferenced owned blobs are reclaimed) — mirrors `file_blobs`.
- **`POST /gallery/process`** — stateless transform, no key, no persisted
  plaintext. Input: one photo's plaintext bytes. Runs inline: EXIF/GPS
  (`ExifReader`) → thumbnail + medium (`intervention/image`) → motion extract
  (Live) → ML sidecar (`MachineLearning`: CLIP embedding + face detect: bboxes +
  face embeddings + crops) → reverse geocode (`ReverseGeocoder`, GPS → place).
  Output: derived data (rendition bytes + EXIF + embedding + faces + place).
  `finally`: delete all plaintext (tmpfs). No DB writes.
- **`POST /gallery/embed-text`** — embed a search query string via the ML sidecar
  (no image plaintext); returns the query vector for client-side content search.
- **`GET/PUT /gallery/store`** — get/put the sealed `vault_gallery` ciphertext
  with optimistic-concurrency (version, 409 on conflict) — same as `/store`.
- **Blob endpoints**: `upload` (+ chunked for large originals/videos), `raw/{blob}`
  (owner-scoped stream), `deleteBlob/{blob}`, `blobs/reconcile`.
- **Dropped**: all 11 async jobs (ProcessPhoto, ReadPhotoMetadata,
  GeneratePhotoRenditions, EmbedPhoto, DetectFaces, ClusterFace,
  DetectDuplicatesJob, PairLivePhotos, BuildExport for photos); the
  `photos`/`faces`/`people`/`albums` tables + pgvector columns; gallery
  `ResourceShare`/`PublicShare`; the async gallery export in the Downloads centre.
- **Kept**: `MachineLearning` sidecar client, `ReverseGeocoder`, `ExifReader`,
  `PhotoTransform`/rendition + `MotionPhotoExtractor` + `VideoProcessor` +
  `PerceptualHash` logic — now invoked inline by `/gallery/process` instead of
  from jobs.

## Processing pipeline (synchronous, client-driven)

The async server queue is replaced: a job cannot process encrypted-at-rest bytes,
so processing is a client-driven request/response during the unlocked window.

**Upload:** client encrypts the original → uploads the opaque blob (recorded in
`gallery_blobs`) → adds an index entry `{id, originalRef, no metaRef}` → seals +
PUTs `vault_gallery`. The photo is now in the backlog.

**Process (per backlog photo, throttled to N concurrent):**
1. Client decrypts the original blob → plaintext.
2. `POST /gallery/process` with the plaintext. Worker transforms + discards.
3. Client encrypts the derived data (thumbnail/medium/motion/crops → blobs; EXIF +
   embedding + faces → the per-photo meta blob) → uploads → gets refs.
4. Client writes `metaRef` + refs into the index entry → seals + PUTs.

**Backlog / resume / progress (all client-side, no server state → no structure
leak):**
- Backlog = index entries lacking `metaRef`. The sealed index **is** the resume
  state — no separate state files. On reopen, the client re-scans and continues.
- Progress bar = `processed / total` from the index; starts right after unlock,
  updates per photo. Nothing runs while the vault is locked.
- Resilience: a worker crash mid-photo leaves that one entry without `metaRef` →
  retried next pass. Per-record, never the whole set.

## Cross-photo features (client-side on decrypted vectors)

Embeddings are bulk-fetched from the per-photo meta blobs once and cached in
IndexedDB (rebuildable from blobs, never persisted unencrypted outside the origin).
- **Duplicates**: pHash + CLIP cosine in the browser → groups → the review page.
- **People**: cluster face embeddings client-side → `people[]` in the index;
  rename/merge/hide are index edits.
- **Live Photo pairing**: the worker extracts each file's `content_id` + motion;
  pairing a JPEG with its MOV is a **cross-photo** match on `content_id`, done
  client-side over the decrypted index (like dedup), not inside the per-photo
  worker call.
- **Search**: metadata = client filter on the decrypted index/meta; content =
  `/gallery/embed-text` embeds the query, then client cosine vs cached vectors.

## Feature mapping (parity)

Map (GPS from meta → Leaflet, already client-side) · geocoding (worker, transient)
· Live Photos (worker extracts motion + content_id; pairing is client-side, see
Cross-photo) · HEIC/AVIF/video (worker transcodes transiently) ·
albums/favourites/trash (index flags) · export (moves **client-side**: browser
decrypts + zips, like the Files download).

## Migration / rollout (Export + Wipe — user-chosen)

Existing prod photos are plaintext. Per the user's decision (matching the Files
Phase-3 approach): export the plaintext metadata as a safety backup, drop the
`photos`/`faces`/`people`/`albums` tables + their bytes, and the user re-uploads.
The gallery starts empty under ZK. **Pre-deploy warning is mandatory: the user
must download/back up any photos to keep before deploying, because the wipe
removes the server-side originals.** Content blobs orphaned by the wipe are
reclaimed by the reconcile/sweep.

## Error handling

- Per-photo failure isolated (entry stays in the backlog, retried).
- Worker plaintext always discarded (`finally` + tmpfs); `/gallery/process` never
  writes to disk/DB.
- Quota enforced server-side from `gallery_blobs` sizes; upload rejects over quota.
- Orphan reclaim: client-driven `reconcile` (live blob set, grace-gated) +
  scheduled leaked-bytes sweep, mirroring the Files model.

## Testing

- Server (Pest/PHPUnit): `/gallery/process` with a mocked ML sidecar (asserts
  derived shape + **no plaintext left on disk/DB**); blob endpoints owner-scoped
  (raw/deleteBlob 404/403 for non-owner); `/gallery/store` optimistic-lock (409);
  quota rejection; reconcile prunes only unreferenced aged owned blobs.
- Client: no automated JS tests in this project — manual smoke (upload → process →
  browse → search → duplicates → People → album → favourite → trash → export;
  lock clears; reopen resumes backlog).

## Out of scope / deferred

- Blob size-padding to hide approximate sizes (optional hardening, later).
- Any server-side similarity/search (impossible — deleted by design).
- Cross-user + public sharing for gallery (removed).
