<?php
/**
 * CentralLog — Log Viewer
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Log Viewer';
$db = getDB();

// Filters from GET params
$filter_severity  = $_GET['severity'] ?? '';
$filter_source    = $_GET['source'] ?? '';
$filter_ip        = $_GET['ip'] ?? '';
$filter_keyword   = $_GET['keyword'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to   = $_GET['date_to'] ?? '';
$page_num         = max(1, (int)($_GET['page'] ?? 1));
$per_page         = 50;
$offset           = ($page_num - 1) * $per_page;

// Build query
$where = [];
$params = [];

if ($filter_severity !== '') {
    $where[] = 'severity = ?';
    $params[] = $filter_severity;
}
if ($filter_source !== '') {
    $where[] = 'log_source = ?';
    $params[] = $filter_source;
}
if ($filter_ip !== '') {
    if (!filter_var($filter_ip, FILTER_VALIDATE_IP)) {
        $filter_ip = '';
    } else {
        $where[] = 'source_ip = ?';
        $params[] = $filter_ip;
    }
}
if ($filter_keyword !== '') {
    $where[] = 'message LIKE ?';
    $params[] = '%' . $filter_keyword . '%';
}
if ($filter_date_from !== '') {
    $where[] = 'event_time >= ?';
    $params[] = $filter_date_from . ' 00:00:00';
}
if ($filter_date_to !== '') {
    $where[] = 'event_time <= ?';
    $params[] = $filter_date_to . ' 23:59:59';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countStmt = $db->prepare("SELECT COUNT(*) FROM events $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$total_pages = max(1, (int) ceil($total / $per_page));

// Get page data
$sort = $_GET['sort'] ?? 'event_time';
$dir  = ($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$allowedSort = ['event_time', 'severity', 'source_ip', 'log_source'];
if (!in_array($sort, $allowedSort, true)) $sort = 'event_time';

$dataStmt = $db->prepare("SELECT * FROM events $whereSql ORDER BY $sort $dir LIMIT $per_page OFFSET $offset");
$dataStmt->execute($params);
$events = $dataStmt->fetchAll();

// Log sources for filter dropdown
$sources = $db->query("SELECT DISTINCT log_source FROM events ORDER BY log_source")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/includes/header.php';
?>

<h4 class="mb-3"><i class="bi bi-journal-text me-2"></i>Log Viewer</h4>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-0">Severity</label>
                <select name="severity" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['INFO','WARNING','ERROR','CRITICAL'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_severity === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Source</label>
                <select name="source" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($sources as $src): ?>
                        <option value="<?= htmlspecialchars($src) ?>" <?= $filter_source === $src ? 'selected' : '' ?>>
                            <?= htmlspecialchars($src) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Source IP</label>
                <input type="text" name="ip" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filter_ip) ?>" placeholder="e.g. 192.168.56.102">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Keyword</label>
                <input type="text" name="keyword" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filter_keyword) ?>" placeholder="Search message...">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-0">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-0">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_to) ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Filter</button>
                <a href="/logs.php" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Results info -->
<div class="d-flex justify-content-between align-items-center mb-2">
    <small class="text-muted"><?= number_format($total) ?> events found — Page <?= $page_num ?> of <?= $total_pages ?></small>
</div>

<!-- Events Table -->
<div class="table-responsive">
    <table class="table table-sm table-hover table-striped">
        <thead>
            <tr>
                <?php
                function sortLink(string $field, string $label, string $currentSort, string $currentDir): string {
                    $newDir = ($currentSort === $field && $currentDir === 'DESC') ? 'ASC' : 'DESC';
                    $arrow = '';
                    if ($currentSort === $field) {
                        $arrow = $currentDir === 'ASC' ? ' &uarr;' : ' &darr;';
                    }
                    $qs = $_GET;
                    $qs['sort'] = $field;
                    $qs['dir'] = $newDir;
                    return '<a href="?' . htmlspecialchars(http_build_query($qs)) . '" class="text-decoration-none">' . $label . $arrow . '</a>';
                }
                ?>
                <th><?= sortLink('event_time', 'Time', $sort, $dir) ?></th>
                <th><?= sortLink('severity', 'Severity', $sort, $dir) ?></th>
                <th><?= sortLink('log_source', 'Source', $sort, $dir) ?></th>
                <th><?= sortLink('source_ip', 'IP', $sort, $dir) ?></th>
                <th>Service</th>
                <th>Message</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($events)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No events found</td></tr>
        <?php else: ?>
            <?php foreach ($events as $ev): ?>
            <tr>
                <td class="text-nowrap small"><?= htmlspecialchars($ev['event_time']) ?></td>
                <td>
                    <?php
                    $sevClass = match($ev['severity']) {
                        'CRITICAL' => 'danger',
                        'ERROR'    => 'danger',
                        'WARNING'  => 'warning',
                        default    => 'secondary',
                    };
                    ?>
                    <span class="badge bg-<?= $sevClass ?>"><?= htmlspecialchars($ev['severity']) ?></span>
                </td>
                <td><span class="badge bg-dark"><?= htmlspecialchars($ev['log_source']) ?></span></td>
                <td>
                    <?php if ($ev['source_ip']): ?>
                        <a href="/logs.php?ip=<?= urlencode($ev['source_ip']) ?>" class="text-decoration-none">
                            <code><?= htmlspecialchars($ev['source_ip']) ?></code>
                        </a>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="small"><?= htmlspecialchars($ev['service'] ?? '—') ?></td>
                <td class="small text-break" style="max-width: 400px;"><?= htmlspecialchars($ev['message']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<nav>
    <ul class="pagination pagination-sm justify-content-center">
        <?php
        $qs = $_GET;
        $qs['page'] = $page_num - 1;
        ?>
        <li class="page-item <?= $page_num <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= htmlspecialchars(http_build_query($qs)) ?>">Prev</a>
        </li>
        <?php
        $start = max(1, $page_num - 3);
        $end = min($total_pages, $page_num + 3);
        for ($i = $start; $i <= $end; $i++):
            $qs['page'] = $i;
        ?>
            <li class="page-item <?= $i === $page_num ? 'active' : '' ?>">
                <a class="page-link" href="?<?= htmlspecialchars(http_build_query($qs)) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <?php $qs['page'] = $page_num + 1; ?>
        <li class="page-item <?= $page_num >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= htmlspecialchars(http_build_query($qs)) ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
