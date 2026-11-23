#!/bin/bash
# test_endpoints.sh - Calls every endpoint and checks HTTP code and "success": true.
# Usage: ./tests/test_endpoints.sh [base_url]     (default http://localhost:8080)
BASE="${1:-http://localhost:8080}"
PASS=0; FAIL=0

for ep in status health cpu memory errors access; do
    RESPONSE=$(curl -s -m 10 -w "\n%{http_code}" "$BASE/api/$ep.php")
    CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    if [ "$CODE" = "200" ] && echo "$BODY" | grep -q '"success": true'; then
        echo "PASS  $ep.php  (HTTP $CODE)"; PASS=$((PASS+1))
    else
        echo "FAIL  $ep.php  (HTTP $CODE)"; echo "$BODY" | head -n 6; FAIL=$((FAIL+1))
    fi
done
echo "Result: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
