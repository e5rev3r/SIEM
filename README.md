# CentralLog

A web-based SIEM dashboard built with PHP — a personal lab project to demonstrate security monitoring, log analysis, and incident response skills.

Runs on a **host-only lab network**:
- **Host machine** → runs the PHP dashboard (Apache + MySQL)
- **Target VM** → vulnerable machine being monitored (Metasploitable / Ubuntu)
- **Attacker VM** → Kali Linux generating real attack traffic (Hydra, Nmap, Nikto)

Attack traffic hits the target, the dashboard ingests its logs, detects the events, and lets you respond — all from the browser.

---

## What It Does

- Ingests logs from the target VM (`auth.log`, `syslog`, Apache, fail2ban) via SSH pull and HTTP push
- Parses and stores events in MySQL
- Live dashboard — events over time, severity breakdown, top attacking IPs
- Custom alert rules (e.g. "5 failed SSH logins from same IP in 5 min → CRITICAL")
- Block / unblock IPs on the target via SSH + iptables — directly from the browser
- Audit trail of every action taken
- Phase 2: IP geolocation, AbuseIPDB threat intel, service status, firewall viewer, file integrity monitoring, nmap trigger

---

## Tech Stack

| Layer      | Technology                        |
|------------|-----------------------------------|
| Backend    | PHP 8.1+                          |
| Database   | MySQL 8 / MariaDB 10.6            |
| Frontend   | Bootstrap 5.3, Chart.js 4, jQuery |
| SSH exec   | phpseclib 3.x                     |
| Web server | Apache 2.4 (mod_rewrite enabled)  |

---

## Project Structure

```
project/
├── index.php           # Login
├── dashboard.php       # Main dashboard
├── logs.php            # Log viewer
├── alerts.php          # Alerts
├── block.php           # IP blocking panel
├── settings.php        # Configuration
├── config.php          # DB + SSH credentials
├── includes/           # Backend modules
├── api/ingest.php      # Log ingest endpoint
├── assets/             # CSS + JS
├── sql/schema.sql      # Database schema
└── docs/               # All documentation
    ├── REQUIREMENTS.md
    ├── PROJECT_MANAGEMENT.md
    └── TECHNICAL.md
```

---

## Setup

### 1. Lab Network
Configure VirtualBox / VMware with a **host-only adapter** (e.g. `192.168.56.0/24`):
- Host machine: `192.168.56.1` (runs dashboard)
- Target VM: `192.168.56.101` (monitored machine)
- Attacker VM: `192.168.56.102` (Kali — generates attack traffic)

### 2. SSH Key Auth to Target VM
```bash
ssh-keygen -t ed25519 -f ~/.ssh/centrallog
ssh-copy-id -i ~/.ssh/centrallog.pub user@192.168.56.101
```

### 3. Database
```bash
mysql -u root -p < sql/schema.sql
```

### 4. Config
Edit `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'centrallog');
define('DB_USER', 'centrallog_user');
define('DB_PASS', 'your_db_password');

define('SSH_HOST', '192.168.56.101');     // target VM IP
define('SSH_USER', 'your_user');
define('SSH_KEY',  '/home/you/.ssh/centrallog');

define('INGEST_TOKEN', 'your_secret_token');
```

### 5. Install PHP dependencies
```bash
composer require phpseclib/phpseclib:~3.0
```

### 6. Apache vhost
```apache
<VirtualHost *:80>
    DocumentRoot /var/www/centrallog
    <Directory /var/www/centrallog>
        AllowOverride All
    </Directory>
</VirtualHost>
```

### 7. Log push cron on target VM
Add to crontab on the target machine (`crontab -e`):
```
* * * * * /opt/centrallog/push_logs.sh
```

---

## Docs

- [Requirements](docs/REQUIREMENTS.md)
- [Project Management & Workflow](docs/PROJECT_MANAGEMENT.md)
- [Technical Documentation](docs/TECHNICAL.md)
