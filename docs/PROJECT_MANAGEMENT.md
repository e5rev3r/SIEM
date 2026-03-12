# Project Management Document
## Project: CentralLog — PHP-Based SIEM Dashboard

**Version:** 1.0  
**Date:** 12 March 2026  

**Context:** Personal lab project on a host-only network. Dashboard runs on the host machine. Target is a vulnerable VM on the same host-only adapter. Attacker machine generates real attack traffic that the dashboard monitors.

---

## 1. Project Phases & Milestones

```
Phase 1: Setup & Foundation        [Week 1]
Phase 2: Core Backend              [Week 2]
Phase 3: Frontend & Dashboard      [Week 3]
Phase 4: Actions & Alerting        [Week 4]
Phase 5: Phase 2 Features          [Week 5]
Phase 6: Polish & Documentation    [Week 6]
```

---

## 2. Detailed Task Breakdown

### Phase 1 — Setup & Foundation (Week 1)

| Task ID | Task                                          | Status      | Priority |
|---------|-----------------------------------------------|-------------|----------|
| T-01    | Set up local LAMP stack (Apache, MySQL, PHP)  | Not Started | High     |
| T-02    | Create MySQL database and schema              | Not Started | High     |
| T-03    | Set up victim vulnerable machine (VM)         | Not Started | High     |
| T-04    | Verify SSH access from host to victim VM      | Not Started | High     |
| T-05    | Create project folder structure               | Not Started | High     |
| T-06    | Write `config.php` (DB, SSH settings)         | Not Started | High     |

### Phase 2 — Core Backend (Week 2)

| Task ID | Task                                               | Status      | Priority |
|---------|----------------------------------------------------|-------------|----------|
| T-07    | Build login / logout system (sessions + bcrypt)    | Not Started | High     |
| T-08    | Build auth middleware (redirect if not logged in)  | Not Started | High     |
| T-09    | Write log parser for `auth.log` (regex)            | Not Started | High     |
| T-10    | Write log parser for `syslog`                      | Not Started | Medium   |
| T-11    | Write log parser for Apache access log             | Not Started | Medium   |
| T-12    | Build ingest API endpoint (`api/ingest.php`)       | Not Started | High     |
| T-13    | Build SSH pull mechanism using phpseclib           | Not Started | Medium   |
| T-14    | Store parsed events to MySQL                       | Not Started | High     |

### Phase 3 — Frontend & Dashboard (Week 3)

| Task ID | Task                                                    | Status      | Priority |
|---------|---------------------------------------------------------|-------------|----------|
| T-15    | Design base HTML layout with Bootstrap navbar/sidebar   | Not Started | High     |
| T-16    | Build dashboard page with stat cards                    | Not Started | High     |
| T-17    | Integrate Chart.js — events-per-hour line chart         | Not Started | High     |
| T-18    | Integrate Chart.js — severity breakdown pie chart       | Not Started | Medium   |
| T-19    | Integrate Chart.js — top source IPs bar chart           | Not Started | Medium   |
| T-20    | Build log viewer table with pagination and filters      | Not Started | High     |
| T-21    | Add AJAX auto-refresh to dashboard (every 30s)          | Not Started | Medium   |

### Phase 4 — Actions & Alerting (Week 4)

| Task ID | Task                                                    | Status      | Priority |
|---------|---------------------------------------------------------|-------------|----------|
| T-22    | Build alert rules engine (PHP cron or on-ingest check)  | Not Started | High     |
| T-23    | Build alerts list page with acknowledge button          | Not Started | High     |
| T-24    | Build IP blocking page (list blocked IPs)               | Not Started | High     |
| T-25    | Implement SSH-based `iptables` block command            | Not Started | High     |
| T-26    | Implement SSH-based `iptables` unblock command          | Not Started | High     |
| T-27    | Build action log (audit trail of block/unblock actions) | Not Started | Medium   |
| T-28    | Build settings/config page in UI                        | Not Started | Low      |

### Phase 5 — Phase 2 Features (Week 5)

| Task ID | Task                                                         | Status      | Priority |
|---------|--------------------------------------------------------------|-------------|----------|
| T-29    | Build live log tail view (AJAX polling)                      | Not Started | High     |
| T-30    | Integrate AbuseIPDB API — IP reputation lookup               | Not Started | High     |
| T-31    | Add IP geolocation (ip-api.com or MaxMind GeoLite2)          | Not Started | Medium   |
| T-32    | Service status panel (SSH → systemctl status)                | Not Started | Medium   |
| T-33    | Firewall rules viewer (SSH → iptables -L -n)                 | Not Started | Medium   |
| T-34    | File integrity monitor (sha256 baseline + diff via SSH)      | Not Started | Medium   |
| T-35    | Custom SSH script runner panel                               | Not Started | Low      |

### Phase 6 — Polish & Documentation (Week 6)

| Task ID | Task                                                         | Status      | Priority |
|---------|--------------------------------------------------------------|-------------|----------|
| T-36    | Activity heatmap (Chart.js — hour vs day of week)            | Not Started | Medium   |
| T-37    | Export logs to CSV download                                  | Not Started | Medium   |
| T-38    | Dark / light theme toggle                                    | Not Started | Low      |
| T-39    | Nmap scan trigger from UI (SSH exec + display results)       | Not Started | Medium   |
| T-40    | Finalize README with lab setup instructions                  | Not Started | High     |
| T-41    | Add screenshots to GitHub repo                               | Not Started | High     |

---

## 3. Lab Network & Workflow Diagram

```
 HOST-ONLY NETWORK  (e.g. 192.168.56.0/24)

 ┌──────────────────────┐      attack traffic       ┌─────────────────────────┐
 │   ATTACKER MACHINE   │  ──── SSH brute, scan ──► │    TARGET / VICTIM VM   │
 │  (Kali Linux VM)     │                           │  (Metasploitable/Ubuntu)│
 │  192.168.56.102      │                           │  192.168.56.101         │
 └──────────────────────┘                           │                         │
                                                    │  /var/log/auth.log      │
                                                    │  /var/log/syslog        │
 ┌──────────────────────┐                           │  /var/log/apache2/*.log │
 │   HOST MACHINE       │ ◄── SSH pull (cron) ───── │  /var/log/fail2ban.log  │
 │  (runs PHP+MySQL)    │                           └─────────────────────────┘
 │  192.168.56.1        │ ◄── HTTP POST push ───────────────────────────────┘
 │                      │
 │  Apache + PHP 8.1    │
 │  MySQL 8             │
 │                      │
 │  api/ingest.php ──►  log_parser.php ──► MySQL
 │  alert_engine.php ──► alerts table
 └──────────┬───────────┘
            │
            ▼  (browser on host)
 ┌──────────────────────────────────────────────────────┐
 │              CENTRALLOG DASHBOARD                    │
 │                                                      │
 │  Login → Dashboard → Logs → Alerts → Block Panel     │
 │                                                      │
 │  "Block IP 192.168.56.102"                           │
 │       └──► ssh_exec.php                              │
 │               └──► SSH to target VM                  │
 │                       └──► iptables -A INPUT         │
 │                             -s 192.168.56.102 -j DROP│
 └──────────────────────────────────────────────────────┘
```

---

## 4. Data Flow

```
RAW LOG LINE (text)
    │
    ▼
log_parser.php  (regex extraction)
    │
    ▼
Structured Event {
    timestamp, hostname, service,
    message, source_ip, severity, log_source
}
    │
    ▼
MySQL: events table
    │
    ├──► Dashboard queries (charts, stats)
    ├──► Log viewer queries (table, filters)
    └──► Alert engine (rule evaluation)
              │
              ▼
         alerts table  ──►  Alerts page
```

---

## 5. Risk Register

| Risk                                      | Likelihood | Impact | Mitigation                                                  |
|-------------------------------------------|------------|--------|-------------------------------------------------------------|
| Host-only network misconfigured           | Medium     | High   | Verify IPs with `ip a`; check VirtualBox adapter settings   |
| SSH key auth not set up on target VM      | Medium     | High   | Set up early: `ssh-copy-id user@192.168.56.101`             |
| phpseclib not available / Composer issues | Low        | Medium | Fallback: use PHP `exec('ssh ...')` with key auth           |
| Log format differs from expected regex    | Medium     | Medium | Test parsers against actual log samples before coding UI    |
| iptables rules lost on VM reboot          | Medium     | Low    | Run `iptables-save > /etc/iptables/rules.v4` after blocking |
| SQL injection via malicious log content   | Low        | High   | Always use PDO prepared statements — never string concat    |

---

## 6. Core Milestone

The project is considered functional (and worth showcasing) once:

1. Attacker machine runs a brute-force or scan against the target VM
2. Those events appear in the dashboard log viewer within ~1 minute
3. An alert triggers automatically from the rule engine
4. The operator clicks "Block IP" and the block is confirmed live on the target VM
5. The action appears in the audit trail

Everything beyond that (geolocation, threat intel, file integrity, nmap trigger) adds depth to the showcase.

---

## 7. Lab Environment

| Component          | Tool/Version                                      |
|--------------------|---------------------------------------------------|
| Host OS            | Linux (runs dashboard)                            |
| Web Server         | Apache 2.4 (mod_rewrite enabled)                  |
| PHP                | 8.1+                                              |
| Database           | MySQL 8 or MariaDB 10.6                           |
| SSH Library        | phpseclib 3.x (via Composer)                      |
| Frontend CSS       | Bootstrap 5.3                                     |
| Charts             | Chart.js 4.x                                      |
| Target VM          | Metasploitable 2/3, Ubuntu 22.04, or custom VM    |
| Attacker VM        | Kali Linux                                        |
| Network type       | VirtualBox / VMware Host-Only (e.g. 192.168.56.x) |
| Attack tools used  | Hydra (SSH brute), Nmap (scanning), Nikto (web)   |
