# Sentinel – Frontend Integration Guide

For the frontend developer. You do **not** need to change any backend code.

**Base URL:** `http://SERVER_IP:8080` (replace `SERVER_IP` with the Ubuntu VM address).
All endpoints use **GET** and return JSON. CORS is enabled, so the dashboard can be opened from any origin (or from the same server).

## Response structure (same for all endpoints)

Success:
```json
{ "success": true, "data": { ... }, "timestamp": "2026-11-22 16:47:41" }
```
Error:
```json
{ "success": false, "error": { "message": "Log file not found: error.log" }, "timestamp": "2026-11-22 16:47:41" }
```
| Field | Meaning |
|---|---|
| `success` | `true` if the check worked, `false` if the backend had an error. **Always check this first.** |
| `data` | The result (different for each endpoint). Only on success. |
| `error.message` | Human-readable error text. Only on failure. |
| `timestamp` | Server time when the response was created. |

HTTP codes: `200` OK, `403` no permission to read a log, `404` log file not found, `405` wrong method, `500` internal error.

**Important:** "Apache is stopped" is NOT an error. `status.php` and `health.php` still return `success: true` with `stopped` / `offline`.

## Reusable helper (put this at the top of your script)

```javascript
const API = "http://SERVER_IP:8080/api";

async function getJSON(endpoint) {
  try {
    const res = await fetch(`${API}/${endpoint}`);
    const json = await res.json();
    if (!json.success) throw new Error(json.error.message);
    return json.data;
  } catch (err) {
    console.error(endpoint, err.message);
    return null; // show "N/A" in the dashboard
  }
}
```

---

## 1. Apache service status

**URL:** `/api/status.php`  **Method:** GET

```json
{
  "success": true,
  "data": {
    "service": "apache2",
    "apache_status": "running",
    "raw_state": "active",
    "uptime_seconds": 93784,
    "uptime_human": "1d 2h 3m 4s"
  },
  "timestamp": "2026-11-22 16:47:41"
}
```
| Field | Meaning |
|---|---|
| `service` | Name of the monitored service |
| `apache_status` | `"running"`, `"stopped"` or `"unknown"` |
| `raw_state` | Original text from `systemctl is-active` (`active`, `inactive`, `failed`, ...) or `null` |
| `uptime_seconds` | Seconds since Apache started (number), or `null` if not running |
| `uptime_human` | Same uptime as text, e.g. `"1d 2h 3m 4s"`, or `null` |
| `message` | Only present when status is `unknown` (explains why) |

```javascript
const s = await getJSON("status.php");
document.getElementById("apache-status").textContent = s ? s.apache_status : "N/A";
```

## 2. HTTP health check

**URL:** `/api/health.php`  **Method:** GET

```json
{
  "success": true,
  "data": {
    "url": "http://localhost/",
    "http_status": "online",
    "http_code": 200,
    "response_time_ms": 3.41,
    "error": null
  },
  "timestamp": "2026-11-22 16:47:41"
}
```
| Field | Meaning |
|---|---|
| `url` | Website that was tested |
| `http_status` | `"online"` (HTTP 200–399) or `"offline"` |
| `http_code` | HTTP status code, or `null` if no response |
| `response_time_ms` | Response time in milliseconds, or `null` if offline |
| `error` | Error text (`Connection failed.`, `Request timed out.`, ...) or `null` |

When Apache is stopped: `http_status: "offline"`, `http_code: null`, `response_time_ms: null`, `error: "Connection failed."`.

```javascript
const h = await getJSON("health.php");
const text = h && h.http_status === "online" ? `Online (${h.response_time_ms} ms)` : "Offline";
```

## 3. CPU usage

**URL:** `/api/cpu.php`  **Method:** GET  (takes about 0.5 second to answer)

```json
{
  "success": true,
  "data": { "cpu_usage_percent": 12.5, "cpu_cores": 2, "sample_ms": 500 },
  "timestamp": "2026-11-22 16:47:41"
}
```
| Field | Meaning |
|---|---|
| `cpu_usage_percent` | CPU usage from 0 to 100 (number) |
| `cpu_cores` | Number of CPU cores |
| `sample_ms` | Time between the two readings used for the calculation |

```javascript
const c = await getJSON("cpu.php");
if (c) bar.style.width = c.cpu_usage_percent + "%";
```

## 4. Memory usage

**URL:** `/api/memory.php`  **Method:** GET

```json
{
  "success": true,
  "data": { "total_mb": 3992.27, "available_mb": 3727.5, "used_mb": 264.78, "usage_percent": 6.63 },
  "timestamp": "2026-11-22 16:47:41"
}
```
| Field | Meaning |
|---|---|
| `total_mb` | Total RAM in MB |
| `available_mb` | RAM available for programs in MB |
| `used_mb` | `total_mb - available_mb` |
| `usage_percent` | Used memory as a percentage (0–100) |

```javascript
const m = await getJSON("memory.php");
if (m) label.textContent = `${m.used_mb} / ${m.total_mb} MB (${m.usage_percent}%)`;
```

## 5. Apache error logs

**URL:** `/api/errors.php`  **Method:** GET  (latest 20 entries, **newest first**)

```json
{
  "success": true,
  "data": {
    "file": "error.log",
    "count": 2,
    "entries": [
      {
        "timestamp": "2026-11-22 15:22:00",
        "module": "mpm_event",
        "level": "notice",
        "message": "AH00491: caught SIGTERM, shutting down",
        "raw": "[Sun Nov 22 15:22:00.000001 2026] [mpm_event:notice] [pid 812:tid 140] AH00491: caught SIGTERM, shutting down"
      },
      {
        "timestamp": null, "module": null, "level": null,
        "message": "some unparsed line", "raw": "some unparsed line"
      }
    ]
  },
  "timestamp": "2026-11-22 16:47:41"
}
```
| Field | Meaning |
|---|---|
| `file` | Log file name |
| `count` | Number of entries returned |
| `entries[].timestamp` | Time of the entry (`null` if the line could not be parsed) |
| `entries[].module` / `level` | Apache module and severity (`notice`, `warn`, `error`, ...) |
| `entries[].message` | Message text |
| `entries[].raw` | Original log line (max 500 characters) |

Errors: `404` if the file is missing, `403` if the backend has no permission (see README).

```javascript
const e = await getJSON("errors.php");
if (e) e.entries.forEach(x => addRow(x.timestamp, x.level, x.message));
```

## 6. Apache access logs

**URL:** `/api/access.php`  **Method:** GET  (latest 20 entries, **newest first**)

```json
{
  "success": true,
  "data": {
    "file": "access.log",
    "count": 2,
    "entries": [
      {
        "parsed": true,
        "ip": "192.168.1.5",
        "timestamp": "2026-11-22 15:21:15",
        "method": "POST",
        "path": "/login",
        "protocol": "HTTP/1.1",
        "status_code": 302,
        "bytes": 0
      },
      { "parsed": false, "raw": "garbage line here" }
    ]
  },
  "timestamp": "2026-11-22 16:47:41"
}
```
| Field | Meaning |
|---|---|
| `entries[].parsed` | `true` if the line was understood. If `false`, only `raw` exists. |
| `ip` | Client IP address |
| `timestamp` | Time of the request |
| `method` / `path` / `protocol` | Request parts. The query string (`?x=y`) is removed for privacy. |
| `status_code` | HTTP response code (200, 404, ...) as a number |
| `bytes` | Size of the response in bytes |

User-agent, referer and login names are intentionally **not** returned.

```javascript
const a = await getJSON("access.php");
if (a) a.entries.filter(x => x.parsed).forEach(x => addRow(x.ip, x.method, x.path, x.status_code));
```

## Auto-refresh example

```javascript
async function refresh() {
  const [s, h, c, m] = await Promise.all([
    getJSON("status.php"), getJSON("health.php"), getJSON("cpu.php"), getJSON("memory.php")
  ]);
  // update the page here
}
refresh();
setInterval(refresh, 5000);   // every 5 seconds
```
