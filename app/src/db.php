<?php
declare(strict_types=1);

// Prometheus scrapes metrics without a browser session; avoid creating session files on every scrape.
$isMetricsRequest = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? '')) === 'metrics.php';
if (!$isMetricsRequest && session_status() !== PHP_SESSION_ACTIVE) {
    session_name('bepnha_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function app_log(string $level, string $message, array $context = []): void
{
    $entry = [
        'timestamp' => gmdate('c'),
        'level' => strtoupper($level),
        'service' => 'recipe-php',
        'message' => $message,
        'context' => $context,
    ];
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line !== false) {
        @file_put_contents('/var/log/recipe/app.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    if (strtoupper($level) === 'ERROR') {
        error_log($message);
    }
}

function db(): PDO
{
    static $pdo = null;
    static $adminBootstrapped = false;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'mysql';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'recipe_db';
    $user = getenv('DB_USER') ?: 'recipe_user';
    $password = getenv('DB_PASSWORD') ?: '';
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);

    // Provision the first administrator once, from environment variables, never from public form input.
    if (!$adminBootstrapped) {
        $adminBootstrapped = true;
        $exists = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($exists === 0) {
            $email = trim((string)(getenv('ADMIN_EMAIL') ?: 'admin@bepnha.local'));
            $adminPassword = (string)(getenv('ADMIN_PASSWORD') ?: 'change_me_admin_password');
            if (strlen($adminPassword) < 12) {
                app_log('ERROR', 'ADMIN_PASSWORD must be at least 12 characters before first login');
                throw new RuntimeException('Mật khẩu quản trị trong file .env phải có ít nhất 12 ký tự.');
            }
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $stmt->execute(['Quản trị viên', $email, password_hash($adminPassword, PASSWORD_DEFAULT), 'admin']);
            app_log('INFO', 'Initial administrator account provisioned', ['email' => $email]);
        }
    }
    return $pdo;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = (string)($_POST['csrf_token'] ?? '');
    $saved = (string)($_SESSION['csrf_token'] ?? '');
    if ($sent === '' || $saved === '' || !hash_equals($saved, $sent)) {
        http_response_code(419);
        app_log('WARNING', 'Rejected request with invalid CSRF token', ['path' => parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)]);
        exit('Phiên làm việc hết hạn hoặc mã bảo vệ không hợp lệ. Hãy tải lại trang.');
    }
}

function slugify(string $text): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $slug = strtolower((string)$ascii);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-') ?: 'cong-thuc';
}

function require_admin(): array
{
    if (empty($_SESSION['admin_user_id'])) {
        header('Location: /admin.php');
        exit;
    }
    $stmt = db()->prepare("SELECT id, name, email, role FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
    $stmt->execute([(int)$_SESSION['admin_user_id']]);
    $admin = $stmt->fetch();
    if (!$admin) {
        unset($_SESSION['admin_user_id']);
        header('Location: /admin.php');
        exit;
    }
    return $admin;
}
