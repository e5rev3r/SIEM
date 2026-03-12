<?php
/**
 * CentralLog — Alerts Page
 * Lists triggered alerts and allows managing alert rules.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Alerts';
$db = getDB();
$message = '';
$messageType = '';

// Handle acknowledge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $message = 'Invalid request.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'];

        if ($action === 'acknowledge' && isset($_POST['alert_id'])) {
            $alertId = (int) $_POST['alert_id'];
            $stmt = $db->prepare('UPDATE alerts SET acknowledged = 1, ack_by = ?, ack_at = NOW() WHERE id = ? AND acknowledged = 0');
            $stmt->execute([$_SESSION['user'], $alertId]);
            if ($stmt->rowCount() > 0) {
                $db->prepare('INSERT INTO action_log (action_type, actor, target, detail, result) VALUES (?,?,?,?,?)')
                   ->execute(['ACK_ALERT', $_SESSION['user'], "Alert #$alertId", 'Alert acknowledged', 'SUCCESS']);
                $message = "Alert #$alertId acknowledged.";
                $messageType = 'success';
            }
        }

        if ($action === 'add_rule') {
            $name = trim($_POST['rule_name'] ?? '');
            $logSrc = trim($_POST['rule_log_source'] ?? '');
            $condKey = trim($_POST['rule_condition'] ?? '');
            $threshold = (int)($_POST['rule_threshold'] ?? 0);
            $window = (int)($_POST['rule_window'] ?? 0);
            $severity = $_POST['rule_severity'] ?? 'WARNING';

            if ($name && $condKey && $threshold > 0 && $window > 0) {
                $stmt = $db->prepare('INSERT INTO alert_rules (name, log_source, condition_key, threshold, window_mins, severity) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$name, $logSrc ?: null, $condKey, $threshold, $window, $severity]);
                $message = "Rule \"$name\" added.";
                $messageType = 'success';
            } else {
                $message = 'All rule fields are required.';
                $messageType = 'danger';
            }
        }

        if ($action === 'toggle_rule' && isset($_POST['rule_id'])) {
            $ruleId = (int)$_POST['rule_id'];
            $db->prepare('UPDATE alert_rules SET enabled = NOT enabled WHERE id = ?')->execute([$ruleId]);
            $message = "Rule toggled.";
            $messageType = 'info';
        }

        if ($action === 'delete_rule' && isset($_POST['rule_id'])) {
            $ruleId = (int)$_POST['rule_id'];
            $db->prepare('DELETE FROM alert_rules WHERE id = ?')->execute([$ruleId]);
            $message = "Rule deleted.";
            $messageType = 'warning';
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get alerts
$filter = $_GET['filter'] ?? 'open';
if ($filter === 'all') {
    $alerts = $db->query('SELECT * FROM alerts ORDER BY triggered_at DESC LIMIT 200')->fetchAll();
} else {
    $alerts = $db->query('SELECT * FROM alerts WHERE acknowledged = 0 ORDER BY triggered_at DESC LIMIT 200')->fetchAll();
}

// Get rules
$rules = $db->query('SELECT * FROM alert_rules ORDER BY id')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<h4 class="mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Alerts</h4>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> py-2"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'open' ? 'active' : '' ?>" href="/alerts.php?filter=open">
            Open Alerts
            <?php
            $openCount = $db->query("SELECT COUNT(*) FROM alerts WHERE acknowledged = 0")->fetchColumn();
            if ($openCount > 0):
            ?>
                <span class="badge bg-danger"><?= $openCount ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="/alerts.php?filter=all">All Alerts</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="collapse" href="#rulesSection">Alert Rules</a>
    </li>
</ul>

<!-- Alerts Table -->
<div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead>
            <tr>
                <th>#</th>
                <th>Triggered</th>
                <th>Rule</th>
                <th>Severity</th>
                <th>Source IP</th>
                <th>Detail</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($alerts)): ?>
            <tr><td colspan="8" class="text-center text-muted py-3">No alerts</td></tr>
        <?php else: ?>
            <?php foreach ($alerts as $a): ?>
            <tr class="<?= !$a['acknowledged'] && $a['severity'] === 'CRITICAL' ? 'table-danger' : '' ?>">
                <td><?= $a['id'] ?></td>
                <td class="small text-nowrap"><?= htmlspecialchars($a['triggered_at']) ?></td>
                <td><?= htmlspecialchars($a['rule_name']) ?></td>
                <td>
                    <span class="badge bg-<?= $a['severity'] === 'CRITICAL' ? 'danger' : 'warning' ?>">
                        <?= htmlspecialchars($a['severity']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($a['source_ip']): ?>
                        <a href="/logs.php?ip=<?= urlencode($a['source_ip']) ?>"><code><?= htmlspecialchars($a['source_ip']) ?></code></a>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="small" style="max-width: 350px;"><?= htmlspecialchars($a['detail'] ?? '') ?></td>
                <td>
                    <?php if ($a['acknowledged']): ?>
                        <span class="badge bg-success">ACK by <?= htmlspecialchars($a['ack_by']) ?></span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Open</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$a['acknowledged']): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="acknowledge">
                            <input type="hidden" name="alert_id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn btn-outline-success btn-sm" title="Acknowledge">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </form>
                        <?php if ($a['source_ip']): ?>
                            <a href="/block.php?ip=<?= urlencode($a['source_ip']) ?>" class="btn btn-outline-danger btn-sm" title="Block IP">
                                <i class="bi bi-slash-circle"></i>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Alert Rules (collapsible) -->
<div class="collapse mt-4" id="rulesSection">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-gear me-2"></i>Alert Rules</span>
        </div>
        <div class="card-body">
            <table class="table table-sm mb-3">
                <thead>
                    <tr><th>Name</th><th>Source</th><th>Condition</th><th>Threshold</th><th>Window</th><th>Severity</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rules as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td><code><?= htmlspecialchars($r['log_source'] ?? 'any') ?></code></td>
                    <td><code><?= htmlspecialchars($r['condition_key']) ?></code></td>
                    <td><?= $r['threshold'] ?></td>
                    <td><?= $r['window_mins'] ?> min</td>
                    <td><span class="badge bg-<?= $r['severity'] === 'CRITICAL' ? 'danger' : 'warning' ?>"><?= $r['severity'] ?></span></td>
                    <td><?= $r['enabled'] ? '<span class="text-success">Active</span>' : '<span class="text-muted">Disabled</span>' ?></td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="toggle_rule">
                            <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                            <button class="btn btn-outline-secondary btn-sm"><?= $r['enabled'] ? 'Disable' : 'Enable' ?></button>
                        </form>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this rule?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="delete_rule">
                            <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Add Rule Form -->
            <h6>Add New Rule</h6>
            <form method="POST" class="row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="add_rule">
                <div class="col-md-2">
                    <label class="form-label small mb-0">Name</label>
                    <input type="text" name="rule_name" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Log Source</label>
                    <select name="rule_log_source" class="form-select form-select-sm">
                        <option value="">Any</option>
                        <option value="auth">auth</option>
                        <option value="syslog">syslog</option>
                        <option value="apache_access">apache_access</option>
                        <option value="apache_error">apache_error</option>
                        <option value="fail2ban">fail2ban</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Condition Key</label>
                    <select name="rule_condition" class="form-select form-select-sm" required>
                        <option value="failed_ssh">failed_ssh</option>
                        <option value="http_404">http_404</option>
                        <option value="auth_failure">auth_failure</option>
                        <option value="fail2ban_ban">fail2ban_ban</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-0">Threshold</label>
                    <input type="number" name="rule_threshold" class="form-control form-control-sm" min="1" value="5" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-0">Window (min)</label>
                    <input type="number" name="rule_window" class="form-control form-control-sm" min="1" value="5" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Severity</label>
                    <select name="rule_severity" class="form-select form-select-sm">
                        <option value="WARNING">WARNING</option>
                        <option value="CRITICAL">CRITICAL</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
