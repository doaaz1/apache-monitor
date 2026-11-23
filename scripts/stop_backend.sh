#!/bin/bash
# stop_backend.sh - Stops the Sentinel backend (does NOT touch Apache).
PIDFILE="/tmp/sentinel.pid"
if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
    kill "$(cat "$PIDFILE")" && rm -f "$PIDFILE" && echo "Sentinel stopped."
else
    echo "Sentinel is not running."; rm -f "$PIDFILE"
fi
