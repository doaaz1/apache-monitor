# Apache Web Server Monitoring System

A web-based monitoring system designed to observe Apache HTTP Server status, inspect server activity, and present operational indicators through a clean, organized dashboard interface.

## Overview

The system provides a centralized view of an Apache web server running on a Linux environment, separating server administration, backend data retrieval, frontend presentation, and project documentation into clear modules.

## Tech Stack

* **Web Server:** Apache HTTP Server (`apache2`)
* **Operating System:** Ubuntu Linux (VirtualBox environment)
* **Backend:** PHP (`libapache2-mod-php`)
* **Frontend:** HTML5, CSS3, JavaScript
* **Version Control & Collaboration:** Git, GitHub, GitHub Desktop, VS Code

## Project Structure

```text
apache-monitor/
├── index.php                  # Main dashboard entry point
├── api/
│   └── status.php             # Endpoint for retrieving server/Apache status data
├── assets/
│   ├── css/
│   │   └── style.css          # Layout, visual styling, and responsive rules
│   └── js/
│       └── dashboard.js       # Client-side updates and UI interactions
├── config/
│   └── server.php             # Shared configuration and path settings
├── docs/
│   ├── setup-guide.md         # Environment setup and deployment notes
│   └── architecture.md        # System components and data flow overview
├── .gitignore                 # Ignored temporary, log, and local files
└── README.md                  # Project overview and usage guide