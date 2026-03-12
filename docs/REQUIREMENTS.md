# Requirements Document
## Project: CentralLog — PHP-Based SIEM Dashboard

**Version:** 1.0  
**Date:** 12 March 2026  

---

## 1. Project Overview

CentralLog is a web-based SIEM dashboard built with PHP, designed as a personal lab and GitHub portfolio project to demonstrate security knowledge and skills.

**Lab setup:**
- The dashboard (PHP + MySQL) runs on the host machine (Linux)
- The target machine is a vulnerable VM (e.g. Metasploitable, DVWA, or a custom Kali/Ubuntu VM)
- Both machines are on the **same host-only network** — no internet exposure, no production concerns
- The attacker machine generates the attack traffic (brute-force, scanning, exploitation) against the target VM, and the dashboard collects and visualises those events in real time

---

## 2. Functional Requirements

### 2.1 Authentication
- FR-01: The system SHALL provide a login page with username and password.
- FR-02: The system SHALL use PHP sessions to maintain authentication state.
- FR-03: All pages SHALL redirect unauthenticated users to the login page.
- FR-04: Passwords SHALL be stored as bcrypt hashes, never plain text.

### 2.2 Log Ingestion
- FR-05: The system SHALL accept log data pushed from the victim machine via HTTP POST to an ingest API endpoint.
- FR-06: The system SHALL support pulling logs from the victim machine over SSH as an alternative.
- FR-07: Supported log sources SHALL include:
  - `/var/log/auth.log` (SSH brute force, sudo events)
  - `/var/log/syslog` (general system events)
  - `/var/log/apache2/access.log` (web requests)
  - `/var/log/apache2/error.log` (web errors)
  - `/var/log/fail2ban.log` (banned IPs)

### 2.3 Log Parsing
- FR-08: The system SHALL parse raw log lines using regex to extract:
  - Timestamp
  - Hostname
  - Service/process name
  - Event message
  - Source IP address (where applicable)
  - Severity level (INFO, WARNING, ERROR, CRITICAL)
- FR-09: Parsed events SHALL be stored in a MySQL database.

### 2.4 Dashboard
- FR-10: The dashboard SHALL display a real-time summary including:
  - Total events in the last 24 hours
  - Count of CRITICAL and WARNING events
  - Top 5 source IPs by event count
  - Events-per-hour timeline chart
- FR-11: Charts SHALL be rendered using Chart.js.
- FR-12: The dashboard SHALL auto-refresh every 30 seconds.

### 2.5 Log Viewer
- FR-13: The log viewer SHALL display a paginated, filterable table of all ingested log events.
- FR-14: Filters SHALL include: date range, severity, source IP, log source, keyword search.
- FR-15: The table SHALL support sorting by timestamp and severity.

### 2.6 Alerting
- FR-16: The system SHALL allow operators to define alert rules, for example:
  - "More than N failed SSH logins from the same IP in M minutes → trigger alert"
  - "HTTP 4xx rate exceeds threshold → trigger alert"
- FR-17: Triggered alerts SHALL be displayed on the dashboard with timestamp, rule name, and details.
- FR-18: Alert status SHALL be acknowledg-able (mark as reviewed).

### 2.7 IP Blocking / Response Actions
- FR-19: The operator SHALL be able to block a source IP directly from the dashboard.
- FR-20: Blocking SHALL execute `iptables -A INPUT -s <IP> -j DROP` on the victim machine via SSH.
- FR-21: The operator SHALL be able to unblock/remove a previously blocked IP.
- FR-22: The system SHALL maintain a log of all response actions taken (who, when, what IP).

### 2.8 System Settings
- FR-23: Settings page SHALL allow configuring: SSH credentials for victim VM, alert thresholds, log pull interval.

---

## 3. Non-Functional Requirements

| ID     | Requirement                                                          |
|--------|----------------------------------------------------------------------|
| NFR-01 | The web UI SHALL be responsive and usable on desktop browsers.       |
| NFR-02 | All database queries SHALL use prepared statements (prevent SQL injection). |
| NFR-03 | SSH credentials SHALL be stored in a config file outside the web root. |
| NFR-04 | The ingest API endpoint SHALL require a shared secret token.         |
| NFR-05 | The system SHALL handle malformed log lines without crashing.        |
| NFR-06 | Page load time SHOULD be under 3 seconds for up to 10,000 log entries. |

---

## 4. Constraints

- Language: PHP 8.x (backend), HTML/CSS/JavaScript (frontend)
- Database: MySQL 8 or MariaDB 10.x
- All machines are on the same host-only network (VirtualBox/VMware host-only adapter)
- Dashboard runs on the host; target VM is the monitored machine
- Deployable with a standard LAMP stack on Linux host

---

## 5. Out of Scope

- Production deployment / internet-facing use
- Mobile app interface
- Machine learning based anomaly detection
- Email / SMS alerting notifications
- Windows Event Log collection

---

## 6. Feature Checklist

### Core
- [ ] Login / logout with session auth
- [ ] Dashboard with live charts (events/hour, severity, top IPs)
- [ ] Log ingestion from target VM (SSH pull + HTTP push)
- [ ] Log viewer with filters (IP, severity, source, keyword, date)
- [ ] Alert rules engine with custom thresholds
- [ ] Triggered alerts page with acknowledge
- [ ] IP block / unblock via iptables over SSH
- [ ] Action audit trail

### Phase 2
- [ ] Live log tail view (real-time feed)
- [ ] Geolocation of source IPs
- [ ] AbuseIPDB threat intel lookup per IP
- [ ] Service status panel (sshd, apache2, etc.)
- [ ] Firewall rules viewer (iptables -L output)
- [ ] Running processes viewer (ps aux)
- [ ] File integrity monitor (sha256 baseline diff)
- [ ] Custom SSH script runner from UI

### Phase 3
- [ ] Nmap scan trigger from UI
- [ ] Activity heatmap (hour vs day)
- [ ] Export logs to CSV
- [ ] Alert notes / analyst comments
- [ ] IP whitelist management
- [ ] Dark / light theme toggle
