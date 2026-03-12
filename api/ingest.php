<?php
/**
 * Ingest API — Target VM pushes log lines here via HTTP POST.
 * 
 * POST /api/ingest.php
 * Header: X-Ingest-Token: <secret>
 * Body JSON: { "source": "auth", "lines": ["line1", "line2", ...] }
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/log_parser.php';
require_once __DIR__ . '/../includes/alert_engine.php';

header('Content-Type: application/json');

// Verify token
$token = $_SERVER['HTTP_X_INGEST_TOKEN'] ?? '';
if (!hash_equals(INGEST_TOKEN, $token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['source']) || !isset($body['lines']) || !is_array($body['lines'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload. Expected: {"source":"auth","lines":["..."]}']);
    exit;
}

$source   = $body['source'];
$lines    = $body['lines'];
$accepted = 0;
$rejected = 0;

$db = getDB();
$stmt = $db->prepare(
    'INSERT INTO events (event_time, hostname, log_source, service, source_ip, severity, message, raw_line) 
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

foreach ($lines as $line) {
    if (!is_string($line)) {
        $rejected++;
        continue;
    }

    $parsed = parseLogLine($line, $source);
    if ($parsed === null) {
        $rejected++;
        continue;
    }

    $stmt->execute([
        $parsed['event_time'],
        $parsed['hostname'],
        $parsed['log_source'],
        $parsed['service'],
        $parsed['source_ip'],
        $parsed['severity'],
        $parsed['message'],
        $parsed['raw_line'],
    ]);
    $accepted++;
}

// Evaluate alert rules after ingest
$alertsTriggered = evaluateAlertRules();

echo json_encode([
    'status'           => 'ok',
    'accepted'         => $accepted,
    'rejected'         => $rejected,
    'alerts_triggered' => $alertsTriggered,
]);
