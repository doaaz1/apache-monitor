# Apache Web Server Monitoring System

A web-based monitoring system designed to observe Apache HTTP Server status, inspect server activity, and present operational indicators through a clean, organized dashboard interface.

## Overview

The system provides a centralized view of an Apache web server running on a Linux environment, separating server administration, backend data retrieval, frontend presentation, and project documentation into clear modules.

The monitoring application runs **independently** from the Apache server it monitors:

| Service | Port | Started with |
|---|---|---|
| Apache website (monitored) | 80 | `sudo systemctl start apache2` |
| Monitoring backend and dashboard (PHP built-in server) | 8080 | `./scripts/start_backend.sh` |

Because of this, the dashboard stays available and can report **"stopped"** when Apache is shut down. For the same reason, the dashboard (`index.php`) must be served on port 8080 and not by Apache.

```
Browser ──► PHP server :8080 (dashboard + API) ──► systemctl, /proc, Apache logs
                                  └──────────────► HTTP request to Apache :80
```

## Tech Stack

* Web Server: Apache HTTP Server (`apache2`), the monitored service
* Operating System: Ubuntu Linux (VirtualBox environment)
* Backend: native PHP (`php-cli` with the built-in web server and `php-curl`), JSON API, no frameworks or database
* Frontend: HTML5, CSS3, JavaScript (Fetch API)
* Version Control & Collaboration: Git, GitHub, GitHub Desktop, VS Code

## Project Structure

```
apache-monitor/
├── index.php                  # Main dashboard entry point
├── router.php                 # Used by the PHP server; blocks web access to config/ and includes/
├── api/
│   ├── status.php             # Apache service status (running / stopped / unknown) and uptime
│   ├── health.php             # HTTP health check (online / offline, response time)
│   ├── cpu.php                # CPU usage in percent
│   ├── memory.php             # RAM usage in MB and percent
│   ├── errors.php             # Latest 20 Apache error log entries
│   └── access.php             # Latest 20 Apache access log entries
├── includes/
│   ├── apache.php             # Service status and HTTP health check functions
│   ├── system.php             # Safe command execution, CPU and memory functions
│   ├── logs.php               # Log reading and parsing functions
│   └── response.php           # JSON responses and error handling
├── config/
│   └── server.php             # Shared configuration and path settings
├── assets/
│   ├── css/
│   │   └── style.css          # Layout, visual styling, and responsive rules
│   └── js/
│       └── dashboard.js       # Client-side updates and UI interactions
├── scripts/
│   ├── start_backend.sh       # Starts the monitoring server on port 8080
│   └── stop_backend.sh        # Stops it (Apache is not affected)
├── tests/
│   └── test_endpoints.sh      # Tests all six API endpoints with curl
├── docs/
│   ├── setup-guide.md         # Environment setup and deployment notes
│   ├── architecture.md        # System components and data flow overview
│   ├── BACKEND_README.md      # Detailed backend documentation
│   ├── FRONTEND_INTEGRATION.md# API reference with JSON examples and fetch code
│   └── sentinel.service       # Optional systemd service file (start at boot)
├── .gitignore                 # Ignored temporary, log, and local files
└── README.md                  # Project overview and usage guide
```

## Requirements

* Ubuntu Server 22.04 or 24.04 (VirtualBox)
* Packages: `apache2`, `php-cli`, `php-curl`, `curl`, `git`
* The user running the monitor must belong to the `adm` group to read Apache logs

## Setup

```bash
sudo apt update
sudo apt install -y apache2 php-cli php-curl curl git
sudo systemctl enable --now apache2

git clone https://github.com/doaaz1/apache-monitor
cd apache-monitor
chmod +x scripts/*.sh tests/*.sh

# Allow reading Apache logs without root, then log out and log in again
sudo usermod -aG adm $USER
```

## Running the System

```bash
./scripts/start_backend.sh     # start monitoring on port 8080
./scripts/stop_backend.sh      # stop monitoring
```

Manual start (errors are shown in the terminal; stop with Ctrl+C):

```bash
php -S 0.0.0.0:8080 -t . router.php
```

Open `http://SERVER_IP:8080` for the dashboard. In a default VirtualBox NAT setup, add port-forwarding rules for ports 8080 and 80, or use a Bridged Adapter.

## API Endpoints

All endpoints use `GET` and return JSON: `{ "success": true, "data": {...}, "timestamp": "..." }`.

| Endpoint | Description |
|---|---|
| `/api/status.php` | Apache service status and uptime |
| `/api/health.php` | HTTP health check (status code and response time) |
| `/api/cpu.php` | CPU usage percentage |
| `/api/memory.php` | Total, available, and used RAM |
| `/api/errors.php` | Latest Apache error log entries |
| `/api/access.php` | Latest Apache access log entries |

Field descriptions, example responses, and JavaScript `fetch` examples are in [`docs/FRONTEND_INTEGRATION.md`](docs/FRONTEND_INTEGRATION.md).

## Testing

```bash
./tests/test_endpoints.sh      # expected: 6 passed, 0 failed
curl http://localhost:8080/api/status.php
```

Demonstration scenario:

1. Start Apache and the monitor. Status shows `running` and health shows `online`.
2. Run `sudo systemctl stop apache2`.
3. Refresh the dashboard. Status shows `stopped` and health shows `offline`, while port 8080 stays reachable.
4. Run `sudo systemctl start apache2`. Status returns to `running`.

## Security Notes

* Endpoints accept no parameters, and no shell commands are built from user input.
* PHP runs as a normal user (no root, no unrestricted `sudo`). Log access comes from the `adm` group only.
* Access log output omits user-agent, referer, and query strings.
* `config/` and `includes/` cannot be opened from the browser.
* The PHP built-in server is intended for development and demonstration. There is no login, so restrict access to port 8080 with the firewall if needed.

Detailed backend notes: [`docs/BACKEND_README.md`](docs/BACKEND_README.md).
