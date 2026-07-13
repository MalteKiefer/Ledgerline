#!/usr/bin/env bash
#
# Ledgerline security rebuild + CVE gate (M7).
#
# Base-image OS packages accrue CVEs between releases; many are only fixed
# upstream weeks after disclosure. This script pulls the latest base layers,
# rebuilds the app image, and scans it with Trivy — FAILING when a *fixable*
# HIGH/CRITICAL vulnerability is present (unfixed ones are reported, not gated,
# since there is nothing to update to yet). Run it weekly (cron) so patches are
# picked up promptly, and before any deliberate base-digest bump.
#
#   ./scripts/security-rebuild.sh            # pull, rebuild, scan, gate
#   TAG=v1.447.1 ./scripts/security-rebuild.sh
#
# Suggested cron (host, Sundays 04:30):
#   30 4 * * 0  cd /srv/ledgerline && ./scripts/security-rebuild.sh >> /var/log/ledgerline-trivy.log 2>&1
#
set -euo pipefail

cd "$(dirname "$0")/.."

TAG="${TAG:-$(git describe --tags --abbrev=0 2>/dev/null || echo local)}"
IMAGE="ledgerline:${TAG}"

echo "==> Pulling current base images (digests in docker-compose.yml / Dockerfile)"
docker compose pull db valkey 2>/dev/null || true

echo "==> Rebuilding ${IMAGE}"
IMAGE_TAG="${TAG}" docker compose build app

echo "==> Trivy scan (report all HIGH/CRITICAL; gate on FIXABLE ones)"
TRIVY="docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy:latest"

# Full report (informational — includes unfixed/fix_deferred).
$TRIVY image --scanners vuln --severity HIGH,CRITICAL --no-progress "${IMAGE}" || true

# Gate: fail only when something is actually fixable (a patch exists).
echo "==> Gate: fail on FIXABLE HIGH/CRITICAL"
if ! $TRIVY image --scanners vuln --severity HIGH,CRITICAL --ignore-unfixed \
      --exit-code 1 --no-progress -q "${IMAGE}"; then
    echo "!! Fixable HIGH/CRITICAL vulnerabilities present — update the base digest and redeploy."
    exit 1
fi

echo "==> OK: no fixable HIGH/CRITICAL vulnerabilities."
