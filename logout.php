<?php
/**
 * CentralLog — Logout
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

if (!empty($_SESSION['user'])) {
    $db = getDB();
    $db->prepare('INSERT INTO action_log (action_type, actor, target, detail, result) VALUES (?,?,?,?,?)')
       ->execute(['LOGOUT', $_SESSION['user'], $_SERVER['REMOTE_ADDR'], 'User logged out', 'SUCCESS']);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

header('Location: /index.php');
exit;
