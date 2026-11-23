#!/bin/bash
# start_backend.sh - Starts the Sentinel backend on port 8080 (independent of Apache).
# Usage: ./scripts/start_backend.sh [port]
PORT="${1:-8080}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PIDFILE="/tmp/sentinel.pid"
LOGFILE="/tmp/sentinel.log"

if ! command -v php >/dev/null 2>&1; then
    echo "Error: PHP is not installed. Run: sudo apt install php-cli php-curl"; exit 1
fi
if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
    echo "Sentinel is already running (PID $(cat "$PIDFILE"))."; exit 0
fi

# setsid + nohup keep the server alive after the terminal is closed.
cd "$ROOT" || exit 1
nohup setsid php -S "0.0.0.0:$PORT" -t "$ROOT" "$ROOT/router.php" >"$LOGFILE" 2>&1 &
echo $! > "$PIDFILE"
sleep 1
if kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
    echo "Sentinel started on port $PORT (PID $(cat "$PIDFILE")). Log: $LOGFILE"
else
    echo "Sentinel failed to start. See $LOGFILE"; rm -f "$PIDFILE"; exit 1
fi
