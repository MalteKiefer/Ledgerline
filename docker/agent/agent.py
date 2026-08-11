#!/usr/bin/env python3
"""
Minimal, bounded Docker control agent for Ledgerline.

The main app (internet-facing, non-root, no docker socket) NEVER touches the
docker socket. This tiny sidecar holds the socket and exposes ONLY a fixed
allowlist of actions on the compose project's own services, gated by a shared
bearer token, reachable only on the internal compose network.

It cannot run arbitrary docker/compose commands: `service` is validated against
the live `docker compose config --services` list and `action` against a fixed
set; everything is executed as argv arrays (never a shell string), so a
compromised caller can restart/stop/start/recreate/pull/inspect/log the known
services (incl. DoS) but cannot inject commands or run code on the host.

Pure stdlib (http.server + subprocess) — no third-party deps.
"""
import hmac
import json
import os
import subprocess
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

TOKEN = os.environ.get("AGENT_TOKEN", "")
PROJECT_DIR = os.environ.get("AGENT_PROJECT_DIR", "/project")
PORT = int(os.environ.get("AGENT_PORT", "9000"))

# Fixed action allowlist → the compose/docker argv it maps to. `{svc}` is only
# ever a validated service name from the live services list (never shell).
ACTIONS = {
    "restart":  (["compose", "restart"], 120),
    "stop":     (["compose", "stop"], 60),
    "start":    (["compose", "start"], 120),
    "recreate": (["compose", "up", "-d", "--force-recreate"], 600),
    "pull":     (["compose", "pull"], 900),
    "logs":     (["compose", "logs", "--no-color", "--tail", "200"], 30),
}


def dc(args, timeout):
    """Run `docker <args...>` in the project dir; return (rc, stdout+stderr)."""
    try:
        p = subprocess.run(
            ["docker", *args],
            cwd=PROJECT_DIR, capture_output=True, text=True, timeout=timeout,
        )
        return p.returncode, (p.stdout or "") + (p.stderr or "")
    except subprocess.TimeoutExpired:
        return 124, "timeout"
    except Exception as e:  # noqa: BLE001 — surface a message, never crash the agent
        return 1, str(e)


def services():
    rc, out = dc(["compose", "config", "--services"], 20)
    if rc != 0:
        return []
    return [s.strip() for s in out.splitlines() if s.strip()]


def ps_state():
    rc, out = dc(["compose", "ps", "--all", "--format", "json"], 20)
    rows = []
    if rc == 0:
        for line in out.splitlines():
            line = line.strip()
            if not line:
                continue
            try:
                obj = json.loads(line)
            except json.JSONDecodeError:
                continue
            if isinstance(obj, list):
                rows.extend(obj)
            else:
                rows.append(obj)
    by = {}
    for r in rows:
        if isinstance(r, dict) and r.get("Service"):
            by[r["Service"]] = {
                "state": r.get("State", ""),
                "status": r.get("Status", ""),
                "image": r.get("Image", ""),
                "name": r.get("Name", ""),
            }
    return by


def stats():
    """Live per-container resource usage (one-shot). Keyed by container name."""
    rc, out = dc(["stats", "--no-stream", "--no-trunc", "--format", "{{json .}}"], 20)
    by = {}
    if rc == 0:
        for line in out.splitlines():
            line = line.strip()
            if not line:
                continue
            try:
                o = json.loads(line)
            except json.JSONDecodeError:
                continue
            if isinstance(o, dict) and o.get("Name"):
                by[o["Name"]] = {
                    "cpu": o.get("CPUPerc", ""),
                    "mem": o.get("MemUsage", ""),
                    "mem_perc": o.get("MemPerc", ""),
                }
    return by


class Handler(BaseHTTPRequestHandler):
    def log_message(self, *a):  # quiet
        pass

    def _send(self, code, obj):
        body = json.dumps(obj).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _authed(self):
        if not TOKEN:
            return False
        got = self.headers.get("Authorization", "")
        pfx = "Bearer "
        return got.startswith(pfx) and hmac.compare_digest(got[len(pfx):], TOKEN)

    def do_GET(self):
        if self.path == "/health":
            return self._send(200, {"ok": True})
        if not self._authed():
            return self._send(401, {"error": "unauthorized"})
        if self.path == "/list":
            svcs = services()
            state = ps_state()
            usage = stats()
            out = []
            for s in svcs:
                row = {"service": s, **state.get(s, {"state": "absent", "status": "", "image": "", "name": ""})}
                u = usage.get(row.get("name", ""), {})
                row["cpu"] = u.get("cpu", "")
                row["mem"] = u.get("mem", "")
                row["mem_perc"] = u.get("mem_perc", "")
                out.append(row)
            return self._send(200, {"services": out})
        return self._send(404, {"error": "not_found"})

    def do_POST(self):
        if not self._authed():
            return self._send(401, {"error": "unauthorized"})
        if self.path != "/action":
            return self._send(404, {"error": "not_found"})
        try:
            n = int(self.headers.get("Content-Length", "0"))
            payload = json.loads(self.rfile.read(n) or b"{}")
        except (ValueError, json.JSONDecodeError):
            return self._send(400, {"error": "bad_request"})
        service = payload.get("service")
        action = payload.get("action")
        if action not in ACTIONS:
            return self._send(422, {"error": "bad_action"})
        if not isinstance(service, str) or service not in services():
            return self._send(422, {"error": "bad_service"})
        argv, timeout = ACTIONS[action]
        rc, out = dc([*argv, service], timeout)
        # Cap the returned text so a huge log dump can't blow up the response.
        return self._send(200 if rc == 0 else 502, {
            "ok": rc == 0, "action": action, "service": service,
            "output": out[-20000:],
        })


if __name__ == "__main__":
    ThreadingHTTPServer(("0.0.0.0", PORT), Handler).serve_forever()
