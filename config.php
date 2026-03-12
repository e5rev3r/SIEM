<?php
/**
 * CentralLog — Configuration
 * Edit these values to match your lab environment.
 */

// Database
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'centrallog');
define('DB_USER', 'centrallog_user');
define('DB_PASS', 'change_me');

// SSH to target VM
define('SSH_HOST', '192.168.56.101');
define('SSH_PORT', 22);
define('SSH_USER', 'root');
define('SSH_KEY',  '/home/user/.ssh/centrallog');  // path to private key

// Ingest API shared secret
define('INGEST_TOKEN', 'change_me_to_a_random_string');

// Session
define('SESSION_NAME', 'centrallog_sess');
define('SESSION_LIFETIME', 3600); // 1 hour

// Timezone
date_default_timezone_set('UTC');
