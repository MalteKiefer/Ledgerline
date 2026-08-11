<?php

declare(strict_types=1);

// immich-machine-learning sidecar (profile "ml" in docker-compose). Off by
// default; the gallery degrades gracefully when disabled/unreachable (no
// embeddings, semantic search returns empty and the client falls back to a name
// filter). Enable with ML_ENABLED=true and `docker compose --profile ml up -d`.
return [
    'enabled' => (bool) env('ML_ENABLED', false),

    // Internal sidecar URL (not user-controlled). Reachable on the compose
    // internal network.
    'url' => env('ML_URL', 'http://ml:3003'),

    // CLIP model — image + text embeddings share this space (512-dim for ViT-B-32).
    'clip_model' => env('ML_CLIP_MODEL', 'ViT-B-32__openai'),

    // Cosine-distance ceiling for a semantic search hit (0 = identical, 2 =
    // opposite). Lower = stricter. Tuned for CLIP ViT-B-32.
    'search_max_distance' => (float) env('ML_SEARCH_MAX_DISTANCE', 0.78),

    // Cosine-distance ceiling for near-duplicate grouping (much tighter than
    // search — only visually (near-)identical photos: bursts, re-saves, crops).
    'dup_max_distance' => (float) env('ML_DUP_MAX_DISTANCE', 0.08),

    // Face recognition (immich InsightFace). Off by default — biometric data;
    // opt-in like the rest of ML.
    'face_enabled' => (bool) env('ML_FACE_ENABLED', false),
    'face_model' => env('ML_FACE_MODEL', 'buffalo_l'),
    // Minimum detection confidence to keep a face.
    'face_min_score' => (float) env('ML_FACE_MIN_SCORE', 0.7),
    // Cosine-distance ceiling to attach a new face to an existing person.
    'face_match_distance' => (float) env('ML_FACE_MATCH_DISTANCE', 0.35),
];
