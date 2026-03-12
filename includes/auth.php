<?php
/**
 * Auth Middleware — include at the top of every protected page.
 * Redirects to /index.php (login) if not authenticated.
 */
require_once __DIR__ . '/../config.php';

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

if (empty($_SESSION['user']) || $_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
    session_destroy();
    header('Location: /index.php');
    exit;
}
