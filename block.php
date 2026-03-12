<?php
/**
 * CentralLog — IP Blocking Panel
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/ssh_exec.php';

$page_title = 'IP Blocking';
$db = getDB();
$message = '';
$messageType = '';

// Generate CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $message = 'Invalid request.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $ip = trim($_POST['ip'] ?? '');

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $message = 'Invalid IP address.';
            $messageType = 'danger';
        } elseif ($action === 'block') {
            $reason = trim($_POST['reason'] ?? 'Manual block from dashboard');

            $result = blockIP($ip);
            $status = $result['success'] ? 'SUCCESS' : 'FAILURE';

            if ($result['success']) {
                // Add to DB
                $stmt = $db->prepare('INSERT IGNORE INTO blocked_ips (ip_address, reason, blocked_by) VALUES (?, ?, ?)');
                $stmt->execute([$ip, $reason, $_SESSION['user']]);
                $message = $result['message'];
                $messageType = 'success';
            } else {
                $message = $result['message'];
                $messageType = 'danger';
            }

            $db->prepare('INSERT INTO action_log (action_type, actor, target, detail, result) VALUES (?,?,?,?,?)')
               ->execute(['BLOCK_IP', $_SESSION['user'], $ip, $reason, $status]);

        } elseif ($action === 'unblock') {
            $result = unblockIP($ip);
            $status = $result['success'] ? 'SUCCESS' : 'FAILURE';

            if ($result['success']) {
                $db->prepare('DELETE FROM blocked_ips WHERE ip_address = ?')->execute([$ip]);
                $message = $result['message'];
                $messageType = 'success';
            } else {
                $message = $result['message'];
                $messageType = 'danger';
            }

            $db->prepare('INSERT INTO action_log (action_type, actor, target, detail, result) VALUES (?,?,?,?,?)')
               ->execute(['UNBLOCK_IP', $_SESSION['user'], $ip, 'IP unblocked', $status]);
        }
    }
}

// Pre-fill IP from query string (from alerts page "Block" button)
$prefillIP = $_GET['ip'] ?? '';

// Get blocked IPs
$blockedIPs = $db->query('SELECT * FROM blocked_ips ORDER BY blocked_at DESC')->fetchAll();

// Recent actions
$recentActions = $db->query(
    "SELECT * FROM action_log WHERE action_type IN ('BLOCK_IP','UNBLOCK_IP') ORDER BY action_at DESC LIMIT 20"
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<h4 class="mb-3"><i class="bi bi-slash-circle me-2"></i>IP Blocking</h4>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> py-2"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="row g-3">
    <!-- Block form -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Block an IP on Target VM</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="block">
                    <div class="mb-2">
                        <label class="form-label small">IP Address</label>
                        <input type="text" name="ip" class="form-control" 
                               value="<?= htmlspecialchars($prefillIP) ?>"
                               placeholder="e.g. 192.168.56.102" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Reason</label>
                        <input type="text" name="reason" class="form-control" 
                               value="Manual block from dashboard" placeholder="Reason for blocking">
                    </div>
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="bi bi-slash-circle me-1"></i> Block IP (iptables DROP)
                    </button>
                </form>
                <hr>
                <small class="text-muted">
                    This executes <code>sudo iptables -A INPUT -s &lt;IP&gt; -j DROP</code> on the target VM via SSH.
                </small>
            </div>
        </div>
    </div>

    <!-- Currently blocked -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                Currently Blocked IPs 
                <span class="badge bg-secondary"><?= count($blockedIPs) ?></span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>IP</th><th>Blocked At</th><th>Reason</th><th>By</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($blockedIPs)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No IPs blocked</td></tr>
                    <?php else: ?>
                        <?php foreach ($blockedIPs as $b): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($b['ip_address']) ?></code></td>
                            <td class="small"><?= htmlspecialchars($b['blocked_at']) ?></td>
                            <td class="small"><?= htmlspecialchars($b['reason'] ?? '') ?></td>
                            <td class="small"><?= htmlspecialchars($b['blocked_by']) ?></td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Unblock this IP?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="unblock">
                                    <input type="hidden" name="ip" value="<?= htmlspecialchars($b['ip_address']) ?>">
                                    <button type="submit" class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-unlock"></i> Unblock
                                    </button>
                                </form>
                                <a href="/logs.php?ip=<?= urlencode($b['ip_address']) ?>" class="btn btn-outline-info btn-sm" title="View logs">
                                    <i class="bi bi-journal-text"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Action audit trail -->
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-clock-history me-2"></i>Recent Block/Unblock Actions</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>Time</th><th>Action</th><th>IP</th><th>By</th><th>Result</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentActions as $act): ?>
                    <tr>
                        <td class="small text-nowrap"><?= htmlspecialchars($act['action_at']) ?></td>
                        <td>
                            <span class="badge bg-<?= $act['action_type'] === 'BLOCK_IP' ? 'danger' : 'success' ?>">
                                <?= htmlspecialchars($act['action_type']) ?>
                            </span>
                        </td>
                        <td><code><?= htmlspecialchars($act['target'] ?? '') ?></code></td>
                        <td class="small"><?= htmlspecialchars($act['actor']) ?></td>
                        <td>
                            <span class="badge bg-<?= $act['result'] === 'SUCCESS' ? 'success' : 'danger' ?>">
                                <?= htmlspecialchars($act['result']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
