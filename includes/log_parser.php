<?php
/**
 * Log Parser — regex-based extraction for different log formats.
 * Returns an associative array of parsed fields, or null if unparseable.
 */

function parseLogLine(string $line, string $source): ?array {
    $line = trim($line);
    if ($line === '') return null;

    switch ($source) {
        case 'auth':
            return parseAuthLog($line);
        case 'syslog':
            return parseSyslog($line);
        case 'apache_access':
            return parseApacheAccess($line);
        case 'apache_error':
            return parseApacheError($line);
        case 'fail2ban':
            return parseFail2ban($line);
        default:
            return parseGeneric($line, $source);
    }
}

/**
 * auth.log — handles Failed/Accepted password, sudo, su, invalid user, etc.
 */
function parseAuthLog(string $line): ?array {
    // Standard syslog prefix: "Mar 11 02:14:33 hostname service[pid]: message"
    if (!preg_match('/^(\w{3}\s+\d+\s[\d:]+)\s(\S+)\s(\S+?)(?:\[\d+\])?:\s(.+)$/', $line, $m)) {
        return parseGeneric($line, 'auth');
    }

    $timestamp = $m[1];
    $hostname  = $m[2];
    $service   = $m[3];
    $message   = $m[4];
    $source_ip = null;
    $severity  = 'INFO';

    // Failed password
    if (preg_match('/Failed password for (?:invalid user )?\S+ from ([\d.]+)/', $message, $ip)) {
        $source_ip = $ip[1];
        $severity  = 'WARNING';
    }
    // Accepted password / publickey
    elseif (preg_match('/Accepted \S+ for \S+ from ([\d.]+)/', $message, $ip)) {
        $source_ip = $ip[1];
    }
    // Invalid user
    elseif (preg_match('/Invalid user \S+ from ([\d.]+)/', $message, $ip)) {
        $source_ip = $ip[1];
        $severity  = 'WARNING';
    }
    // sudo
    elseif (stripos($message, 'sudo') !== false) {
        $severity = 'WARNING';
    }
    // Connection closed by / reset by
    elseif (preg_match('/(?:Connection closed|reset) by ([\d.]+)/', $message, $ip)) {
        $source_ip = $ip[1];
    }

    return [
        'event_time' => syslogTimestampToDatetime($timestamp),
        'hostname'   => $hostname,
        'service'    => $service,
        'source_ip'  => $source_ip,
        'severity'   => $severity,
        'message'    => $message,
        'log_source' => 'auth',
        'raw_line'   => $line,
    ];
}

/**
 * syslog — general system log
 */
function parseSyslog(string $line): ?array {
    if (!preg_match('/^(\w{3}\s+\d+\s[\d:]+)\s(\S+)\s(\S+?)(?:\[\d+\])?:\s(.+)$/', $line, $m)) {
        return parseGeneric($line, 'syslog');
    }

    $severity = 'INFO';
    $message = $m[4];
    $source_ip = null;

    if (preg_match('/from ([\d.]+)/', $message, $ip)) {
        $source_ip = $ip[1];
    }
    if (preg_match('/error|fail|crit/i', $message)) {
        $severity = 'ERROR';
    } elseif (preg_match('/warn/i', $message)) {
        $severity = 'WARNING';
    }

    return [
        'event_time' => syslogTimestampToDatetime($m[1]),
        'hostname'   => $m[2],
        'service'    => $m[3],
        'source_ip'  => $source_ip,
        'severity'   => $severity,
        'message'    => $message,
        'log_source' => 'syslog',
        'raw_line'   => $line,
    ];
}

/**
 * Apache access.log — Combined Log Format
 * 192.168.1.100 - - [11/Mar/2026:03:22:14 +0000] "GET /admin HTTP/1.1" 403 512 "-" "curl/7.88"
 */
function parseApacheAccess(string $line): ?array {
    $pattern = '/^([\d.]+)\s\S+\s\S+\s\[([^\]]+)\]\s"([^"]*)"\s(\d{3})\s(\d+|-)/';
    if (!preg_match($pattern, $line, $m)) {
        return parseGeneric($line, 'apache_access');
    }

    $status = (int)$m[4];
    if ($status >= 500) {
        $severity = 'ERROR';
    } elseif ($status >= 400) {
        $severity = 'WARNING';
    } else {
        $severity = 'INFO';
    }

    return [
        'event_time' => apacheTimestampToDatetime($m[2]),
        'hostname'   => '',
        'service'    => 'apache',
        'source_ip'  => $m[1],
        'severity'   => $severity,
        'message'    => $m[3] . ' → ' . $m[4],
        'log_source' => 'apache_access',
        'raw_line'   => $line,
    ];
}

/**
 * Apache error.log
 * [Wed Mar 11 03:22:14.123456 2026] [core:error] [pid 1234] [client 192.168.1.100:54321] ...
 */
function parseApacheError(string $line): ?array {
    $pattern = '/^\[([^\]]+)\]\s\[([^\]]*):([^\]]*)\].*?\[client ([\d.]+)(?::\d+)?\]\s*(.+)$/';
    if (!preg_match($pattern, $line, $m)) {
        // Simpler fallback: no client IP
        if (preg_match('/^\[([^\]]+)\]\s\[([^\]]*):([^\]]*)\]\s*(.+)$/', $line, $m2)) {
            $sev = strtolower($m2[3]);
            $severity = match(true) {
                in_array($sev, ['emerg','alert','crit']) => 'CRITICAL',
                $sev === 'error'                         => 'ERROR',
                $sev === 'warn'                          => 'WARNING',
                default                                  => 'INFO',
            };
            return [
                'event_time' => apacheErrorTimestampToDatetime($m2[1]),
                'hostname'   => '',
                'service'    => 'apache',
                'source_ip'  => null,
                'severity'   => $severity,
                'message'    => $m2[4],
                'log_source' => 'apache_error',
                'raw_line'   => $line,
            ];
        }
        return parseGeneric($line, 'apache_error');
    }

    $sev = strtolower($m[3]);
    $severity = match(true) {
        in_array($sev, ['emerg','alert','crit']) => 'CRITICAL',
        $sev === 'error'                         => 'ERROR',
        $sev === 'warn'                          => 'WARNING',
        default                                  => 'INFO',
    };

    return [
        'event_time' => apacheErrorTimestampToDatetime($m[1]),
        'hostname'   => '',
        'service'    => 'apache',
        'source_ip'  => $m[4],
        'severity'   => $severity,
        'message'    => $m[5],
        'log_source' => 'apache_error',
        'raw_line'   => $line,
    ];
}

/**
 * fail2ban.log
 * 2026-03-11 02:15:00,123 fail2ban.actions [1234]: INFO  [sshd] Ban 192.168.1.50
 */
function parseFail2ban(string $line): ?array {
    $pattern = '/^([\d\-]+\s[\d:,]+)\sfail2ban\.(\S+)\s+\[\d+\]:\s+(\S+)\s+\[([^\]]+)\]\s+(.+)$/';
    if (!preg_match($pattern, $line, $m)) {
        return parseGeneric($line, 'fail2ban');
    }

    $action = $m[5];
    $source_ip = null;
    $severity = 'INFO';

    if (preg_match('/(?:Ban|Found)\s+([\d.]+)/', $action, $ip)) {
        $source_ip = $ip[1];
        $severity = 'CRITICAL';
    } elseif (preg_match('/Unban\s+([\d.]+)/', $action, $ip)) {
        $source_ip = $ip[1];
        $severity = 'WARNING';
    }

    return [
        'event_time' => fail2banTimestampToDatetime($m[1]),
        'hostname'   => '',
        'service'    => 'fail2ban/' . $m[4],
        'source_ip'  => $source_ip,
        'severity'   => $severity,
        'message'    => $action,
        'log_source' => 'fail2ban',
        'raw_line'   => $line,
    ];
}

/**
 * Generic fallback for unrecognised lines
 */
function parseGeneric(string $line, string $source): array {
    return [
        'event_time' => date('Y-m-d H:i:s'),
        'hostname'   => '',
        'service'    => null,
        'source_ip'  => null,
        'severity'   => 'INFO',
        'message'    => $line,
        'log_source' => $source,
        'raw_line'   => $line,
    ];
}

// --- Timestamp helpers ---

function syslogTimestampToDatetime(string $ts): string {
    $year = date('Y');
    $dt = DateTime::createFromFormat('Y M j H:i:s', "$year $ts");
    if (!$dt) {
        $dt = DateTime::createFromFormat('Y M  j H:i:s', "$year $ts");
    }
    return $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
}

function apacheTimestampToDatetime(string $ts): string {
    // 11/Mar/2026:03:22:14 +0000
    $dt = DateTime::createFromFormat('d/M/Y:H:i:s O', $ts);
    return $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
}

function apacheErrorTimestampToDatetime(string $ts): string {
    // "Wed Mar 11 03:22:14.123456 2026"
    $ts = preg_replace('/\.\d+/', '', $ts); // strip microseconds
    $dt = DateTime::createFromFormat('D M d H:i:s Y', $ts);
    if (!$dt) {
        $dt = DateTime::createFromFormat('D M  d H:i:s Y', $ts);
    }
    return $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
}

function fail2banTimestampToDatetime(string $ts): string {
    // 2026-03-11 02:15:00,123
    $ts = preg_replace('/,\d+$/', '', $ts);
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $ts);
    return $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
}
