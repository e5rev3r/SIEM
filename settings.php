<?php
/**
 * CentralLog — Settings Page
 * View action logs, test SSH connection, and manage user password.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/ssh_exec.php';

$page_title = 'Settings';
$db = getDB();
$message = '';
$messageType = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $message = 'Invalid request.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        // Change password
        if ($action === 'change_password') {
            $current  = $_POST['current_password'] ?? '';
            $newPass  = $_POST['new_password'] ?? '';
            $confirm  = $_POST['confirm_password'] ?? '';

            $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($current, $user['password_hash'])) {
                $message = 'Current password is incorrect.';
                $messageType = 'danger';
            } elseif (strlen($newPass) < 6) {
                $message = 'New password must be at least 6 characters.';
                $messageType = 'danger';
            } elseif ($newPass !== $confirm) {
                $message = 'New passwords do not match.';
                $messageType = 'danger';
            } else {
                $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $_SESSION['user_id']]);
                $message = 'Password changed successfully.';
                $messageType = 'success';
            }
        }

        // Test SSH
        if ($action === 'test_ssh') {
            try {
                $result = sshExec('echo "SSH OK: $(hostname) $(date)"');
                if ($result['exit_code'] === 0) {
                    $message = 'SSH connection successful: ' . $result['output'];
                    $messageType = 'success';
                } else {
                    $message = 'SSH connection failed: ' . $result['output'];
                    $messageType = 'danger';
                }
            } catch (Exception $e) {
                $message = 'SSH error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Get full action log
$actionLog = $db->query('SELECT * FROM action_log ORDER BY action_at DESC LIMIT 100')->fetchAll();

// DB stats
$dbStats = [];
$dbStats['total_events'] = $db->query('SELECT COUNT(*) FROM events')->fetchColumn();
$dbStats['total_alerts'] = $db->query('SELECT COUNT(*) FROM alerts')->fetchColumn();
$dbStats['blocked_ips']  = $db->query('SELECT COUNT(*) FROM blocked_ips')->fetchColumn();
$dbStats['active_rules'] = $db->query('SELECT COUNT(*) FROM alert_rules WHERE enabled = 1')->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<h4 class="mb-3"><i class="bi bi-gear me-2"></i>Settings</h4>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> py-2"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <!-- Connection Info -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Target VM Connection</div>
            <div class="card-body">
                <table class="table table-sm mb-3">
                    <tr><td class="text-muted">SSH Host</td><td><code><?= htmlspecialchars(SSH_HOST) ?></code></td></tr>
                    <tr><td class="text-muted">SSH Port</td><td><?= SSH_PORT ?></td></tr>
                    <tr><td class="text-muted">SSH User</td><td><code><?= htmlspecialchars(SSH_USER) ?></code></td></tr>
                    <tr><td class="text-muted">Key File</td><td class="small"><code><?= htmlspecialchars(SSH_KEY) ?></code></td></tr>
                </table>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="test_ssh">
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-plug me-1"></i> Test SSH Connection
                    </button>
                </form>
                <small class="text-muted d-block mt-2">Edit <code>config.php</code> to change SSH settings.</small>
            </div>
        </div>
    </div>

    <!-- DB Stats -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Database Stats</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted">Total Events</td><td class="fw-bold"><?= number_format($dbStats['total_events']) ?></td></tr>
                    <tr><td class="text-muted">Total Alerts</td><td class="fw-bold"><?= number_format($dbStats['total_alerts']) ?></td></tr>
                    <tr><td class="text-muted">Blocked IPs</td><td class="fw-bold"><?= number_format($dbStats['blocked_ips']) ?></td></tr>
                    <tr><td class="text-muted">Active Rules</td><td class="fw-bold"><?= number_format($dbStats['active_rules']) ?></td></tr>
                    <tr><td class="text-muted">DB Host</td><td><code><?= htmlspecialchars(DB_HOST) ?></code></td></tr>
                    <tr><td class="text-muted">DB Name</td><td><code><?= htmlspecialchars(DB_NAME) ?></code></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Change Password -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Change Password</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-2">
                        <label class="form-label small">Current Password</label>
                        <input type="password" name="current_password" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">New Password</label>
                        <input type="password" name="new_password" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control form-control-sm" required>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm w-100">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Full Action Log -->
<div class="card">
    <div class="card-header"><i class="bi bi-clock-history me-2"></i>Full Action Log (last 100)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr><th>Time</th><th>Action</th><th>Actor</th><th>Target</th><th>Detail</th><th>Result</th></tr>
                </thead>
                <tbody>
                <?php if (empty($actionLog)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No actions logged yet</td></tr>
                <?php else: ?>
                    <?php foreach ($actionLog as $act): ?>
                    <tr>
                        <td class="small text-nowrap"><?= htmlspecialchars($act['action_at']) ?></td>
                        <td><span class="badge bg-dark"><?= htmlspecialchars($act['action_type']) ?></span></td>
                        <td class="small"><?= htmlspecialchars($act['actor']) ?></td>
                        <td><code><?= htmlspecialchars($act['target'] ?? '') ?></code></td>
                        <td class="small"><?= htmlspecialchars($act['detail'] ?? '') ?></td>
                        <td>
                            <span class="badge bg-<?= $act['result'] === 'SUCCESS' ? 'success' : 'danger' ?>">
                                <?= htmlspecialchars($act['result']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
