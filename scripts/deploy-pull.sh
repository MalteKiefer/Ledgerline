#!/usr/bin/env bash
#
# Pull-deploy: roll out a CI-built image from GHCR instead of building on-box.
# GitHub Actions (.github/workflows/build-image.yml) builds + pushes
# ghcr.io/<owner>/ledgerline:<tag>; this script just pulls + recreates.
#
#   ./scripts/deploy-pull.sh                 # default tag: feat/vue-vuetify-spa branch slug
#   ./scripts/deploy-pull.sh feat-vue-vuetify-spa
#   ./scripts/deploy-pull.sh sha-1a2b3c4     # immutable per-commit tag
#   ./scripts/deploy-pull.sh v1.560.0
#
# Migrations + caches run in the image entrypoint on app start. Requires the box
# to be able to PULL the image: either the GHCR package is public, or run once:
#   echo <PAT-with-read:packages> | docker login ghcr.io -u <github-user> --password-stdin
#
set -Eeuo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

OWNER="${GHCR_OWNER:-maltekiefer}"
REPO="ghcr.io/${OWNER}/ledgerline"
TAG="${1:-feat-vue-vuetify-spa}"           # branch slugs replace '/' with '-'
IMAGE="${REPO}:${TAG}"

DC() { docker compose "$@"; }

echo ">> pull-deploy ${IMAGE}"
[[ -f docker-compose.yml ]] || { echo "run from the stack dir"; exit 1; }
[[ -f .env ]] || { echo ".env missing"; exit 1; }

# Persist the image ref into .env so compose (all of app/worker/scheduler, which
# share the anchor) resolves the GHCR image, not the local build tag.
if grep -qE '^LEDGERLINE_IMAGE=' .env; then
  sed -i.bak "s|^LEDGERLINE_IMAGE=.*|LEDGERLINE_IMAGE=${IMAGE}|" .env && rm -f .env.bak
else
  printf '\nLEDGERLINE_IMAGE=%s\n' "$IMAGE" >> .env
fi

echo ">> pulling"
LEDGERLINE_IMAGE="$IMAGE" DC pull app

echo ">> bringing up datastores"
LEDGERLINE_IMAGE="$IMAGE" DC up -d db valkey

echo ">> rolling out app/worker/scheduler (migrations run on app start)"
LEDGERLINE_IMAGE="$IMAGE" DC up -d

# Wait for app health.
for i in $(seq 1 40); do
  state="$(docker inspect -f '{{ if .State.Health }}{{ .State.Health.Status }}{{ else }}{{ .State.Status }}{{ end }}' \
    "$(DC ps -q app 2>/dev/null)" 2>/dev/null || echo missing)"
  case "$state" in
    healthy|running) echo ">> app: ${state}"; break ;;
    unhealthy) echo "app unhealthy — check: docker compose logs app"; exit 1 ;;
  esac
  sleep 3
done

docker image prune -f >/dev/null 2>&1 || true
echo ">> deployed ${IMAGE}"
