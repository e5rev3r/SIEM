<?php
/**
 * CentralLog — Login Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'httponly'  => true,
        'samesite'  => 'Strict',
    ]);
    session_start();
}

// Already logged in? Redirect to dashboard
if (!empty($_SESSION['user'])) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user']    = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['ip']      = $_SERVER['REMOTE_ADDR'];

            // Update last_login
            $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

            // Audit log
            $db->prepare('INSERT INTO action_log (action_type, actor, target, detail, result) VALUES (?,?,?,?,?)')
               ->execute(['LOGIN', $user['username'], $_SERVER['REMOTE_ADDR'], 'Login successful', 'SUCCESS']);

            header('Location: /dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';

            // Log failed attempt
            $db = getDB();
            $db->prepare('INSERT INTO action_log (action_type, actor, target, detail, result) VALUES (?,?,?,?,?)')
               ->execute(['LOGIN', $username, $_SERVER['REMOTE_ADDR'], 'Failed login attempt', 'FAILURE']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CentralLog — Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">

<div class="card shadow" style="width: 400px;">
    <div class="card-body p-4">
        <h3 class="text-center mb-1">
            <i class="bi bi-shield-lock"></i> CentralLog
        </h3>
        <p class="text-center text-muted mb-4">SIEM Dashboard</p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?= htmlspecialchars($username ?? '') ?>" required autofocus>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>

        <p class="text-muted text-center mt-3 mb-0" style="font-size: .8rem;">
            Default: admin / admin
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
