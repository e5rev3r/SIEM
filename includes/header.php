<?php
/**
 * Layout header — include at the top of every page after auth.php
 * Sets $page_title before including this.
 */
$page_title = $page_title ?? 'CentralLog';
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — CentralLog</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- Sidebar -->
<div class="d-flex">
    <nav id="sidebar" class="bg-dark border-end" style="width: 250px; min-height: 100vh;">
        <div class="p-3">
            <h5 class="text-white mb-0"><i class="bi bi-shield-lock"></i> CentralLog</h5>
            <small class="text-muted">SIEM Dashboard</small>
        </div>
        <hr class="text-secondary my-0">
        <ul class="nav flex-column p-2">
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>" href="/dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'logs' ? 'active' : '' ?>" href="/logs.php">
                    <i class="bi bi-journal-text me-2"></i> Log Viewer
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'alerts' ? 'active' : '' ?>" href="/alerts.php">
                    <i class="bi bi-exclamation-triangle me-2"></i> Alerts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'block' ? 'active' : '' ?>" href="/block.php">
                    <i class="bi bi-slash-circle me-2"></i> IP Blocking
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'settings' ? 'active' : '' ?>" href="/settings.php">
                    <i class="bi bi-gear me-2"></i> Settings
                </a>
            </li>
        </ul>
        <hr class="text-secondary">
        <div class="p-2">
            <div class="d-flex align-items-center justify-content-between px-2">
                <small class="text-muted">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($_SESSION['user'] ?? '') ?>
                </small>
                <a href="/logout.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main content area -->
    <div class="flex-grow-1">
        <div class="container-fluid p-4">
