<?php
/**
 * SSH Exec — execute commands on the target VM via phpseclib.
 * Falls back to shell exec('ssh ...') if phpseclib is not available.
 */
require_once __DIR__ . '/../config.php';

function getSshConnection() {
    // Try phpseclib first
    if (class_exists('phpseclib3\Net\SSH2')) {
        $ssh = new \phpseclib3\Net\SSH2(SSH_HOST, SSH_PORT);
        $key = \phpseclib3\Crypt\PublicKeyLoader::load(file_get_contents(SSH_KEY));
        if (!$ssh->login(SSH_USER, $key)) {
            throw new RuntimeException('SSH login failed to ' . SSH_HOST);
        }
        return $ssh;
    }
    // Fallback returns null — use execViaSsh() instead
    return null;
}

function sshExec(string $command): array {
    $ssh = getSshConnection();

    if ($ssh !== null) {
        $output = $ssh->exec($command);
        $exitCode = $ssh->getExitStatus();
        return ['output' => $output, 'exit_code' => $exitCode];
    }

    // Fallback: native ssh command with key
    $escapedCmd = escapeshellarg($command);
    $sshCmd = sprintf(
        'ssh -i %s -o StrictHostKeyChecking=no -o ConnectTimeout=5 -p %d %s@%s %s 2>&1',
        escapeshellarg(SSH_KEY),
        SSH_PORT,
        escapeshellarg(SSH_USER),
        escapeshellarg(SSH_HOST),
        $escapedCmd
    );

    $output = [];
    $exitCode = 0;
    exec($sshCmd, $output, $exitCode);
    return ['output' => implode("\n", $output), 'exit_code' => $exitCode];
}

function blockIP(string $ip): array {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return ['success' => false, 'message' => 'Invalid IP address'];
    }
    $result = sshExec('sudo iptables -A INPUT -s ' . escapeshellarg($ip) . ' -j DROP');
    return [
        'success' => $result['exit_code'] === 0,
        'message' => $result['exit_code'] === 0 ? "Blocked $ip" : "Failed to block $ip: " . $result['output'],
    ];
}

function unblockIP(string $ip): array {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return ['success' => false, 'message' => 'Invalid IP address'];
    }
    $result = sshExec('sudo iptables -D INPUT -s ' . escapeshellarg($ip) . ' -j DROP');
    return [
        'success' => $result['exit_code'] === 0,
        'message' => $result['exit_code'] === 0 ? "Unblocked $ip" : "Failed to unblock $ip: " . $result['output'],
    ];
}

function getIptablesRules(): string {
    $result = sshExec('sudo iptables -L -n --line-numbers');
    return $result['output'] ?? '';
}

function getServiceStatus(string $service): string {
    $allowed = ['sshd', 'apache2', 'nginx', 'mysql', 'fail2ban', 'ufw'];
    if (!in_array($service, $allowed, true)) {
        return 'Service not allowed';
    }
    $result = sshExec('systemctl status ' . escapeshellarg($service) . ' 2>&1 | head -20');
    return $result['output'] ?? '';
}
