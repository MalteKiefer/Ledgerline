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
];
