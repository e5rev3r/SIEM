# Technical Documentation
## Project: CentralLog — PHP-Based SIEM Dashboard

**Version:** 1.0  
**Date:** 12 March 2026

**Lab context:** Host-only network. Dashboard on Linux host, target is a vulnerable VM, attacker is Kali VM. All traffic is internal — no internet exposure.

---

## 1. Project Folder Structure

```
project/
├── index.php                  # Login page
├── logout.php                 # Destroys session, redirects to login
├── dashboard.php              # Main dashboard (charts + stats)
├── logs.php                   # Log viewer (filterable table)
├── alerts.php                 # Alert rules and triggered alerts
├── block.php                  # IP blocking / unblocking panel
├── settings.php               # SSH config, alert thresholds
│
├── config.php                 # DB credentials + SSH config (NOT web accessible ideally)
│
├── includes/
│   ├── auth.php               # Session check middleware (include on every page)
│   ├── db.php                 # PDO database connection
│   ├── log_parser.php         # Regex parsers for each log format
│   ├── alert_engine.php       # Evaluates alert rules against new events
│   └── ssh_exec.php           # Executes commands on target machine via SSH
│
├── api/
│   └── ingest.php             # HTTP POST endpoint — target machine pushes logs here
│
├── assets/
│   ├── css/
│   │   └── style.css          # Custom styles on top of Bootstrap
│   └── js/
│       ├── charts.js          # Chart.js chart initialisation
│       └── dashboard.js       # AJAX polling / auto-refresh logic
│
└── sql/
    └── schema.sql             # Full database schema (CREATE TABLE statements)
```

---

## 2. Database Schema

### Table: `events`
Stores every parsed log event ingested from the target machine.

```sql
CREATE TABLE events (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ingested_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_time    DATETIME     NOT NULL,
    hostname      VARCHAR(255) NOT NULL,
    log_source    ENUM('auth','syslog','apache_access','apache_error','fail2ban') NOT NULL,
    service       VARCHAR(128),
    source_ip     VARCHAR(45),          -- supports IPv6
    severity      ENUM('INFO','WARNING','ERROR','CRITICAL') NOT NULL DEFAULT 'INFO',
    message       TEXT         NOT NULL,
    raw_line      TEXT         NOT NULL,
    INDEX idx_event_time  (event_time),
    INDEX idx_source_ip   (source_ip),
    INDEX idx_severity    (severity),
    INDEX idx_log_source  (log_source)
);
```

### Table: `alerts`
Triggered alert instances.

```sql
CREATE TABLE alerts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    triggered_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rule_name     VARCHAR(255) NOT NULL,
    severity      ENUM('WARNING','CRITICAL') NOT NULL,
    detail        TEXT,
    source_ip     VARCHAR(45),
    acknowledged  TINYINT(1)   NOT NULL DEFAULT 0,
    ack_at        DATETIME,
    INDEX idx_triggered_at (triggered_at),
    INDEX idx_acknowledged (acknowledged)
);
```

### Table: `alert_rules`
User-defined rules evaluated on each ingest.

```sql
CREATE TABLE alert_rules (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    log_source    VARCHAR(64),
    condition_key VARCHAR(64)  NOT NULL,   -- e.g. 'failed_ssh_count'
    threshold     INT          NOT NULL,
    window_mins   INT          NOT NULL,   -- time window to count events in
    severity      ENUM('WARNING','CRITICAL') NOT NULL,
    enabled       TINYINT(1)   NOT NULL DEFAULT 1
);
```

### Table: `blocked_ips`
IPs currently blocked on the target machine.

```sql
CREATE TABLE blocked_ips (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address  VARCHAR(45)  NOT NULL UNIQUE,
    blocked_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason      VARCHAR(255),
    blocked_by  VARCHAR(64)  NOT NULL DEFAULT 'admin'
);
```

### Table: `action_log`
Audit trail of every action taken from the dashboard.

```sql
CREATE TABLE action_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    action_type ENUM('BLOCK_IP','UNBLOCK_IP','ACK_ALERT','LOGIN','LOGOUT') NOT NULL,
    actor       VARCHAR(64)  NOT NULL,
    target      VARCHAR(255),           -- IP or alert ID
    detail      TEXT,
    result      ENUM('SUCCESS','FAILURE') NOT NULL
);
```

### Table: `users`
Dashboard operator accounts.

```sql
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(64)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,   -- bcrypt via password_hash()
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login    DATETIME
);
```

---

## 3. API Endpoint — Log Ingest

**File:** `api/ingest.php`  
**Method:** `POST`  
**Auth:** Shared secret token in header `X-Ingest-Token`

### Request

```
POST /api/ingest.php
X-Ingest-Token: <secret>
Content-Type: application/json

{
  "source": "auth",
  "lines": [
    "Mar 11 02:14:33 kali sshd[1234]: Failed password for root from 192.168.1.50 port 22 ssh2",
    "Mar 11 02:14:35 kali sshd[1234]: Failed password for root from 192.168.1.50 port 22 ssh2"
  ]
}
```

### Response

```json
{ "status": "ok", "accepted": 2, "rejected": 0 }
```

### On the Target Machine (bash push script)

```bash
#!/bin/bash
# /opt/centrallog/push_logs.sh  — run via cron every minute

TOKEN="your_secret_token_here"
ENDPOINT="http://<dashboard_ip>/api/ingest.php"

tail -n 50 /var/log/auth.log | curl -s -X POST "$ENDPOINT" \
  -H "X-Ingest-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"source\":\"auth\",\"lines\":$(tail -n 50 /var/log/auth.log | jq -R . | jq -s .)}"
```

---

## 4. Log Parsing — Regex Patterns

### auth.log — Failed SSH login

```
Input:  Mar 11 02:14:33 kali sshd[1234]: Failed password for root from 192.168.1.50 port 22 ssh2
Regex:  /^(\w{3}\s+\d+\s[\d:]+)\s(\S+)\s\S+\[\d+\]:\sFailed password for \S+ from ([\d.]+)/
Groups: [1] timestamp  [2] hostname  [3] source_ip
Severity: WARNING
```

### auth.log — Accepted SSH login

```
Input:  Mar 11 02:20:01 kali sshd[1235]: Accepted password for admin from 10.0.0.5 port 55234 ssh2
Regex:  /^(\w{3}\s+\d+\s[\d:]+)\s(\S+)\s\S+\[\d+\]:\sAccepted \S+ for (\S+) from ([\d.]+)/
Severity: INFO
```

### Apache access.log

```
Input:  192.168.1.100 - - [11/Mar/2026:03:22:14 +0000] "GET /admin HTTP/1.1" 403 512
Regex:  /^([\d.]+)\s\S+\s\S+\s\[([^\]]+)\]\s"(\S+\s\S+\s\S+)"\s(\d{3})\s(\d+)/
Groups: [1] source_ip  [2] timestamp  [3] request  [4] status_code  [5] bytes
Severity: ERROR if 5xx, WARNING if 4xx, INFO otherwise
```

### fail2ban.log — Ban event

```
Input:  2026-03-11 02:15:00,123 fail2ban.actions [INFO] [sshd] Ban 192.168.1.50
Regex:  /^([\d\-]+ [\d:,]+)\sfail2ban\.\S+\s\[INFO\]\s\[(\S+)\]\sBan\s([\d.]+)/
Severity: CRITICAL
```

---

## 5. SSH Execution — IP Blocking

**File:** `includes/ssh_exec.php`  
**Library:** phpseclib 3.x

```php
// Block an IP on the target machine
function blockIP(string $ip): bool {
    // Validate IP to prevent command injection
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    $ssh = getSshConnection();  // returns phpseclib SSH2 object
    $result = $ssh->exec("sudo iptables -A INPUT -s " . escapeshellarg($ip) . " -j DROP");
    return $ssh->getExitStatus() === 0;
}

// Unblock
function unblockIP(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    $ssh = getSshConnection();
    $result = $ssh->exec("sudo iptables -D INPUT -s " . escapeshellarg($ip) . " -j DROP");
    return $ssh->getExitStatus() === 0;
}
```

**Security note:** The dashboard server's SSH key must be in `~/.ssh/authorized_keys` on the target machine. Never store plain-text passwords — use key-based auth.

---

## 6. Authentication Flow

```
Browser                       PHP Server
  │                               │
  │── POST /index.php ──────────► │
  │   username + password         │
  │                               │  1. Fetch user row by username (prepared stmt)
  │                               │  2. password_verify($input, $hash)
  │                               │  3. If ok → session_regenerate_id(true)
  │                               │     $_SESSION['user'] = $username
  │                               │     $_SESSION['ip']   = $_SERVER['REMOTE_ADDR']
  │◄── 302 /dashboard.php ──────  │
  │                               │
  │── GET /dashboard.php ───────► │
  │                               │  auth.php: check $_SESSION['user'] exists
  │                               │  check $_SESSION['ip'] == REMOTE_ADDR (session fixation guard)
  │◄── 200 dashboard HTML ──────  │
```

Every protected page starts with:
```php
require_once __DIR__ . '/includes/auth.php';
```

---

## 7. Alert Engine Logic

Runs on every ingest call (or via cron every minute).

```
For each enabled rule in alert_rules:
  1. Count matching events in the last window_mins minutes
  2. If count >= threshold:
       a. Check if an unacknowledged alert for this rule already exists (avoid spam)
       b. If not → INSERT into alerts table
       c. Mark on dashboard
```

Example rules to seed:

| Rule Name              | Condition              | Threshold | Window | Severity |
|------------------------|------------------------|-----------|--------|----------|
| SSH Brute Force        | failed_ssh_count       | 5         | 5 min  | CRITICAL |
| HTTP Scanning          | http_404_count         | 20        | 2 min  | WARNING  |
| Repeated Auth Failures | auth_failure_count     | 10        | 10 min | WARNING  |
| Fail2ban Ban           | fail2ban_ban_count     | 1         | 1 min  | CRITICAL |

---

## 8. Security Checklist

- [x] All DB queries use PDO prepared statements
- [x] Passwords hashed with `password_hash()` (bcrypt, cost 12)
- [x] `session_regenerate_id(true)` on login
- [x] Session IP binding to prevent fixation
- [x] Ingest API requires secret token header
- [x] IP addresses validated with `filter_var(FILTER_VALIDATE_IP)` before SSH exec
- [x] SSH uses key-based auth, not passwords
- [x] `config.php` stored outside document root (or denied via `.htaccess`)
- [x] All user-facing output escaped with `htmlspecialchars()`
- [x] HTTP-only, SameSite=Strict cookies for session

---

## 9. Dependencies

| Library      | Version | Purpose                          | Install              |
|--------------|---------|----------------------------------|----------------------|
| phpseclib    | 3.x     | SSH2 connection to target VM     | `composer require phpseclib/phpseclib` |
| Bootstrap    | 5.3     | UI layout and components         | CDN                  |
| Chart.js     | 4.x     | Dashboard charts                 | CDN                  |
| jQuery       | 3.7     | AJAX calls and DOM manipulation  | CDN                  |
