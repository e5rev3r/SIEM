<?php
/**
 * Alert Engine — evaluates alert rules against recent events.
 * Call evaluateAlertRules() after ingesting new events.
 */
require_once __DIR__ . '/db.php';

function evaluateAlertRules(): int {
    $db = getDB();
    $alertsTriggered = 0;

    $rules = $db->query('SELECT * FROM alert_rules WHERE enabled = 1')->fetchAll();

    foreach ($rules as $rule) {
        $count = countMatchingEvents($db, $rule);

        if ($count >= $rule['threshold']) {
            // Check if we already have an unacknowledged alert for this rule
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM alerts WHERE rule_id = ? AND acknowledged = 0 AND triggered_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
            );
            $stmt->execute([$rule['id'], $rule['window_mins']]);

            if ((int)$stmt->fetchColumn() === 0) {
                $sourceIp = getTopSourceIP($db, $rule);
                $detail = sprintf(
                    '%d events matched "%s" in the last %d minutes (threshold: %d)',
                    $count, $rule['condition_key'], $rule['window_mins'], $rule['threshold']
                );

                $insert = $db->prepare(
                    'INSERT INTO alerts (rule_id, rule_name, severity, detail, source_ip) VALUES (?, ?, ?, ?, ?)'
                );
                $insert->execute([
                    $rule['id'],
                    $rule['name'],
                    $rule['severity'],
                    $detail,
                    $sourceIp,
                ]);
                $alertsTriggered++;
            }
        }
    }

    return $alertsTriggered;
}

function countMatchingEvents(PDO $db, array $rule): int {
    $conditionMap = [
        'failed_ssh'     => "log_source = 'auth' AND message LIKE '%Failed password%'",
        'http_404'       => "log_source = 'apache_access' AND message LIKE '%404%'",
        'auth_failure'   => "log_source = 'auth' AND severity IN ('WARNING','ERROR','CRITICAL')",
        'fail2ban_ban'   => "log_source = 'fail2ban' AND message LIKE '%Ban%'",
    ];

    $condition = $conditionMap[$rule['condition_key']] ?? null;
    if (!$condition) return 0;

    $sql = "SELECT COUNT(*) FROM events WHERE $condition AND event_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
    $params = [$rule['window_mins']];

    if ($rule['log_source']) {
        $sql .= " AND log_source = ?";
        $params[] = $rule['log_source'];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function getTopSourceIP(PDO $db, array $rule): ?string {
    $conditionMap = [
        'failed_ssh'     => "log_source = 'auth' AND message LIKE '%Failed password%'",
        'http_404'       => "log_source = 'apache_access' AND message LIKE '%404%'",
        'auth_failure'   => "log_source = 'auth' AND severity IN ('WARNING','ERROR','CRITICAL')",
        'fail2ban_ban'   => "log_source = 'fail2ban' AND message LIKE '%Ban%'",
    ];

    $condition = $conditionMap[$rule['condition_key']] ?? null;
    if (!$condition) return null;

    $sql = "SELECT source_ip, COUNT(*) as cnt FROM events 
            WHERE $condition AND source_ip IS NOT NULL 
            AND event_time > DATE_SUB(NOW(), INTERVAL ? MINUTE) 
            GROUP BY source_ip ORDER BY cnt DESC LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute([$rule['window_mins']]);
    $row = $stmt->fetch();
    return $row['source_ip'] ?? null;
}
