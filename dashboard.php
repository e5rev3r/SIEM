<?php
/**
 * CentralLog — Dashboard
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Dashboard';
$extra_js = ['/assets/js/dashboard.js'];

$db = getDB();

// Stats
$stats = [];

// Total events last 24h
$row = $db->query("SELECT COUNT(*) as cnt FROM events WHERE event_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch();
$stats['total_24h'] = $row['cnt'];

// Critical last 24h
$row = $db->query("SELECT COUNT(*) as cnt FROM events WHERE severity = 'CRITICAL' AND event_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch();
$stats['critical_24h'] = $row['cnt'];

// Warnings last 24h
$row = $db->query("SELECT COUNT(*) as cnt FROM events WHERE severity = 'WARNING' AND event_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch();
$stats['warning_24h'] = $row['cnt'];

// Unacknowledged alerts
$row = $db->query("SELECT COUNT(*) as cnt FROM alerts WHERE acknowledged = 0")->fetch();
$stats['open_alerts'] = $row['cnt'];

// Blocked IPs
$row = $db->query("SELECT COUNT(*) as cnt FROM blocked_ips")->fetch();
$stats['blocked_ips'] = $row['cnt'];

// Unique source IPs last 24h
$row = $db->query("SELECT COUNT(DISTINCT source_ip) as cnt FROM events WHERE source_ip IS NOT NULL AND event_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch();
$stats['unique_ips_24h'] = $row['cnt'];

// Events per hour (last 24h)
$eventsPerHour = $db->query(
    "SELECT DATE_FORMAT(event_time, '%Y-%m-%d %H:00') as hour_bucket, COUNT(*) as cnt 
     FROM events WHERE event_time > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
     GROUP BY hour_bucket ORDER BY hour_bucket"
)->fetchAll();

// Severity breakdown
$severityBreakdown = $db->query(
    "SELECT severity, COUNT(*) as cnt FROM events 
     WHERE event_time > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
     GROUP BY severity ORDER BY FIELD(severity, 'CRITICAL','ERROR','WARNING','INFO')"
)->fetchAll();

// Top 10 source IPs
$topIPs = $db->query(
    "SELECT source_ip, COUNT(*) as cnt FROM events 
     WHERE source_ip IS NOT NULL AND event_time > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
     GROUP BY source_ip ORDER BY cnt DESC LIMIT 10"
)->fetchAll();

// Recent alerts (last 5)
$recentAlerts = $db->query(
    "SELECT * FROM alerts ORDER BY triggered_at DESC LIMIT 5"
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-white-50">Events (24h)</div>
                        <div class="fs-3 fw-bold"><?= number_format($stats['total_24h']) ?></div>
                    </div>
                    <i class="bi bi-journal-text fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-danger text-white">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-white-50">Critical</div>
                        <div class="fs-3 fw-bold"><?= number_format($stats['critical_24h']) ?></div>
                    </div>
                    <i class="bi bi-exclamation-octagon fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-dark">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small">Warnings</div>
                        <div class="fs-3 fw-bold"><?= number_format($stats['warning_24h']) ?></div>
                    </div>
                    <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-white-50">Open Alerts</div>
                        <div class="fs-3 fw-bold"><?= number_format($stats['open_alerts']) ?></div>
                    </div>
                    <i class="bi bi-bell fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-dark border-secondary text-white">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-white-50">Blocked IPs</div>
                        <div class="fs-3 fw-bold"><?= number_format($stats['blocked_ips']) ?></div>
                    </div>
                    <i class="bi bi-slash-circle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-white-50">Unique IPs</div>
                        <div class="fs-3 fw-bold"><?= number_format($stats['unique_ips_24h']) ?></div>
                    </div>
                    <i class="bi bi-people fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <!-- Events per Hour -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-graph-up me-2"></i>Events Per Hour (Last 24h)</div>
            <div class="card-body">
                <canvas id="eventsPerHourChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <!-- Severity Breakdown -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Severity Breakdown</div>
            <div class="card-body">
                <canvas id="severityChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Top IPs -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Top Source IPs (24h)</div>
            <div class="card-body">
                <canvas id="topIPsChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <!-- Recent Alerts -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-bell me-2"></i>Recent Alerts</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>Time</th><th>Rule</th><th>Severity</th><th>IP</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recentAlerts)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No alerts yet</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentAlerts as $alert): ?>
                        <tr>
                            <td class="small"><?= htmlspecialchars($alert['triggered_at']) ?></td>
                            <td><?= htmlspecialchars($alert['rule_name']) ?></td>
                            <td>
                                <span class="badge bg-<?= $alert['severity'] === 'CRITICAL' ? 'danger' : 'warning' ?>">
                                    <?= htmlspecialchars($alert['severity']) ?>
                                </span>
                            </td>
                            <td><code><?= htmlspecialchars($alert['source_ip'] ?? '—') ?></code></td>
                            <td>
                                <?= $alert['acknowledged']
                                    ? '<span class="badge bg-success">ACK</span>'
                                    : '<span class="badge bg-secondary">Open</span>' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart data as JSON for JS -->
<script>
    window.chartData = {
        eventsPerHour: {
            labels: <?= json_encode(array_column($eventsPerHour, 'hour_bucket')) ?>,
            values: <?= json_encode(array_map('intval', array_column($eventsPerHour, 'cnt'))) ?>
        },
        severity: {
            labels: <?= json_encode(array_column($severityBreakdown, 'severity')) ?>,
            values: <?= json_encode(array_map('intval', array_column($severityBreakdown, 'cnt'))) ?>
        },
        topIPs: {
            labels: <?= json_encode(array_column($topIPs, 'source_ip')) ?>,
            values: <?= json_encode(array_map('intval', array_column($topIPs, 'cnt'))) ?>
        }
    };
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
