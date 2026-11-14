<?php
$dbConfig = [
    'host' => getenv('PORTFOLIO_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('PORTFOLIO_DB_PORT') ?: '3306',
    'name' => getenv('PORTFOLIO_DB_NAME') ?: 'samson_portfolio',
    'user' => getenv('PORTFOLIO_DB_USER') ?: 'root',
    'pass' => getenv('PORTFOLIO_DB_PASS') ?: '',
];

if (session_status() === PHP_SESSION_NONE) {
    session_name('samson_portfolio_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    global $dbConfig;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $name = $dbConfig['name'];

    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('The database name contains invalid characters.');
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $server = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['pass'],
        $options
    );

    $server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$name};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['pass'],
        $options
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS messages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(80) NOT NULL,
            email VARCHAR(120) NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX messages_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS owners (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(30) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY owners_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    return $pdo;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_ok(): bool
{
    $token = $_POST['csrf'] ?? '';

    return is_string($token)
        && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

function set_flash(string $type, string $text): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function set_contact_state(array $errors, array $old): void
{
    $_SESSION['contact_state'] = ['errors' => $errors, 'old' => $old];
}

function get_contact_state(): array
{
    $state = $_SESSION['contact_state'] ?? ['errors' => [], 'old' => []];
    unset($_SESSION['contact_state']);

    return $state;
}

function current_owner(): ?array
{
    if (empty($_SESSION['owner_id'])) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['owner_id'],
        'username' => (string) ($_SESSION['owner_username'] ?? 'Owner'),
    ];
}

function owner_count(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM owners')->fetchColumn();
}
