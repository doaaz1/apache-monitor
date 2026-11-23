# Sentinel – Apache Web Server Monitoring System (Backend)

**Course:** IT361 – System Administration and Maintenance
**Component:** Part 2 – PHP Backend

## 1. Backend Description

Sentinel monitors an Apache web server that runs on Ubuntu Server inside VirtualBox. This backend collects information from the operating system and publishes it as JSON through six API endpoints. The dashboard (HTML, CSS, JavaScript) reads these endpoints with the Fetch API.

The backend runs on **port 8080** with PHP's built-in web server. Apache runs on **port 80**. They are two separate processes, so Sentinel keeps working when Apache is stopped.

```
Dashboard / curl ──► PHP backend :8080 ──► systemctl, /proc, Apache logs
                          └──────────────► HTTP request to Apache :80
```

## 2. Technologies Used

Ubuntu Server, VirtualBox, Apache2, native PHP (CLI + built-in server, cURL extension), JSON, Linux commands (`systemctl`) and system files (`/proc/stat`, `/proc/meminfo`, Apache logs). No frameworks and no database.

## 3. Folder Structure

```
apache-monitor/
├── api/            status.php health.php cpu.php memory.php errors.php access.php
├── includes/       apache.php system.php logs.php response.php
├── config/         server.php        (all settings)
├── router.php      (blocks web access to config/ and includes/)
├── scripts/        start_backend.sh  stop_backend.sh
├── tests/          test_endpoints.sh
└── docs/           BACKEND_README.md  FRONTEND_INTEGRATION.md  sentinel.service
```

| File | Purpose |
|---|---|
| `config/server.php` | Monitored URL, log paths, timeout, number of log lines, timezone |
| `includes/response.php` | JSON output, headers, status codes, error handling |
| `includes/apache.php` | Apache service status and HTTP health check |
| `includes/system.php` | Safe command execution, CPU and memory usage |
| `includes/logs.php` | Reads and parses the last log lines |
| `api/*.php` | One small file per endpoint |
| `router.php` | Used by the built-in server to protect internal folders |

## 4. Installation Requirements

- Ubuntu Server 22.04 or 24.04 (PHP 7.4 or newer)
- Packages: `apache2`, `php-cli`, `php-curl`, `curl`
- A user that belongs to the `adm` group (to read Apache logs)

## 5. Setup Instructions

```bash
sudo apt update
sudo apt install -y apache2 php-cli php-curl curl
sudo systemctl enable --now apache2

# Copy the Sentinel folder to the VM (for example to /home/USER/Sentinel), then:
cd ~/apache-monitor
chmod +x scripts/*.sh tests/*.sh

# Allow your user to read Apache logs (see section 9), then log out and log in again
sudo usermod -aG adm $USER
```

VirtualBox network: use **Bridged Adapter**, or add NAT port-forward rules for ports 80 and 8080. Then the host computer can open `http://SERVER_IP:8080`.

## 6. How to Run the Backend

Start and stop the two services **independently**:

| Action | Command |
|---|---|
| Start Apache | `sudo systemctl start apache2` |
| Stop Apache | `sudo systemctl stop apache2` |
| Start Sentinel (port 8080) | `./scripts/start_backend.sh` |
| Stop Sentinel | `./scripts/stop_backend.sh` |

Manual start (shows errors in the terminal, stop with Ctrl+C):

```bash
cd ~/apache-monitor
php -S 0.0.0.0:8080 -t . router.php
```

Check Apache is not the one hosting Sentinel: `ss -tlnp | grep -E ':80 |:8080 '` shows Apache on 80 and `php` on 8080.

Optional: `docs/sentinel.service` starts Sentinel automatically at boot.

## 7. API Endpoint Documentation

All endpoints use **GET** and return JSON in this form:

```json
{ "success": true, "data": { }, "timestamp": "2026-11-22 16:47:41" }
```

| Endpoint | Function | Main fields in `data` |
|---|---|---|
| `/api/status.php` | Apache service status | `apache_status` (`running`/`stopped`/`unknown`), `raw_state`, `uptime_seconds`, `uptime_human` |
| `/api/health.php` | HTTP health check | `http_status` (`online`/`offline`), `http_code`, `response_time_ms`, `error` |
| `/api/cpu.php` | CPU usage | `cpu_usage_percent`, `cpu_cores` |
| `/api/memory.php` | Memory usage | `total_mb`, `available_mb`, `used_mb`, `usage_percent` |
| `/api/errors.php` | Last 20 error log entries | `entries[]` (`timestamp`, `level`, `message`, `raw`) |
| `/api/access.php` | Last 20 access log entries | `entries[]` (`ip`, `timestamp`, `method`, `path`, `status_code`, `bytes`) |

Errors use `{ "success": false, "error": { "message": "..." } }` with HTTP codes `403` (no permission), `404` (file not found), `405` (wrong method) or `500` (internal error). Full examples, field explanations and `fetch` code are in **docs/FRONTEND_INTEGRATION.md**.

### How the CPU usage is calculated

The file `/proc/stat` has a line `cpu` with counters of CPU time since boot (user, nice, system, idle, iowait, irq, softirq, steal). One reading alone is not useful, so the backend:

1. reads the counters (reading 1),
2. waits 500 ms,
3. reads them again (reading 2),
4. calculates the differences:
   - `total = sum of all counters` (difference between the readings)
   - `idle = idle + iowait` (difference between the readings)
5. `CPU usage % = (total − idle) / total × 100`

### How memory usage is calculated

From `/proc/meminfo`: `used = MemTotal − MemAvailable` and `usage % = used / MemTotal × 100`. Values are converted from kB to MB. `MemAvailable` is used (not `MemFree`) because Linux uses free RAM for caches that can be released when needed.

## 8. Testing Instructions

```bash
curl http://localhost:8080/api/status.php
curl http://localhost:8080/api/health.php
curl http://localhost:8080/api/cpu.php
curl http://localhost:8080/api/memory.php
curl http://localhost:8080/api/errors.php
curl http://localhost:8080/api/access.php

./tests/test_endpoints.sh            # tests all six endpoints
```

Example output of `status.php` (Apache running):

```json
{ "success": true,
  "data": { "service": "apache2", "apache_status": "running", "raw_state": "active" },
  "timestamp": "2026-11-22 16:47:41" }
```

Create some access log entries first with `curl http://localhost/` a few times.

### Demonstration scenario (Apache stopped)

1. `sudo systemctl start apache2` and `./scripts/start_backend.sh`
2. Open the dashboard, or run `curl localhost:8080/api/status.php` → `"apache_status": "running"`
3. `sudo systemctl stop apache2`
4. Refresh the dashboard, or run:
   - `curl localhost:8080/api/status.php` → `"apache_status": "stopped"`
   - `curl localhost:8080/api/health.php` → `"http_status": "offline"`
   - `curl localhost:8080/api/cpu.php` → still works (port 8080 stays alive)
5. `sudo systemctl start apache2`
6. Refresh → `"running"` and `"online"` again.

### Common errors

| Problem | Fix |
|---|---|
| `php: command not found` | `sudo apt install php-cli` |
| `PHP cURL extension is missing` (HTTP 500 on health) | `sudo apt install php-curl`, restart Sentinel |
| `Permission denied reading error.log` (403) | Add user to `adm` group, log out and in, restart Sentinel |
| `Log file not found` (404) | Apache is not installed or logs are elsewhere; edit `config/server.php` |
| `Address already in use` | Another program uses 8080: `ss -tlnp \| grep 8080` or use another port |
| Cannot open `:8080` from the host PC | Use Bridged Adapter or NAT port forwarding; check `sudo ufw status` and run `sudo ufw allow 8080/tcp` |
| `apache_status` is `unknown` | `systemctl` could not be run or returned an unusual state; run `systemctl is-active apache2` manually |
| Group change has no effect | The running process keeps old groups; log in again and restart Sentinel |

## 9. Security and Permissions Notes

- **No shell commands from the URL.** Endpoints take no parameters. The only command is `systemctl is-active apache2`, started as an array (no shell) with a validated service name from `server.php`.
- **No sudo and no root.** `systemctl is-active` works for normal users. Apache logs on Ubuntu belong to `root:adm` with mode `640`, so one command gives read access:
  ```bash
  sudo usermod -aG adm $USER
  ```
  This gives read-only access to logs (the `adm` group is the standard Ubuntu group for reading logs). Do not run PHP as root and do not use `chmod 777` on logs.
- **Fixed file paths.** Only the two log paths in `server.php` and `/proc` files are read.
- **Privacy in logs.** Access log output omits user-agent, referer, login names and query strings. Long lines are shortened.
- **Protected folders.** `router.php` returns 403 for `/config/` and `/includes/`. No passwords or secrets exist in the project.
- **Read-only API.** Only GET is accepted (405 otherwise). PHP warnings are hidden from the output and written to the server log.

### Limitations

- PHP's built-in server is for **development and demonstration**, not production. For production use a separate Nginx or Apache instance (different from the monitored one) with PHP-FPM.
- There is **no login**. Anyone who can reach port 8080 can read the data (including client IP addresses from the logs). Use the VM firewall (`ufw`) to limit access to trusted computers.
- CORS is open (`Access-Control-Allow-Origin: *`) so the dashboard works from any origin. Restrict this in `response.php` if needed.
- The built-in server handles one request at a time by default; this is enough for one dashboard refreshing every few seconds.
- Sentinel does not restart itself if it crashes. Use `docs/sentinel.service` if automatic restart is wanted.
- CPU usage is an average over a 500 ms sample, so it can change quickly between requests.
- If Apache is configured with a different log format or log paths, adjust `config/server.php` (non-matching lines are returned as `parsed: false`).
