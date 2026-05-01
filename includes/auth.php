<?php
/**
 * CabEvac — Authentication Middleware
 * 
 * Handles secure session management, admin login/logout,
 * rate limiting, and session-based access control.
 * 
 * @package CabEvac
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Start a secure PHP session with hardened cookie configuration.
 */
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_name('CABEVAC_SESSION');
    session_start();
}

/**
 * Require admin authentication. Returns 401 JSON for API calls,
 * redirects to login page for page requests.
 */
function requireAdmin(): void
{
    startSecureSession();

    if (empty($_SESSION['admin_id'])) {
        $isApiRequest = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');

        if ($isApiRequest) {
            jsonError('Unauthorized. Please log in.', 401);
        }

        $basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $loginPath = rtrim($basePath, '/') . '/admin/login.php';
        header("Location: {$loginPath}");
        exit;
    }
}

/**
 * Attempt admin login with email and password.
 * 
 * @param string $email Admin email
 * @param string $password Plain text password
 * @return array{success: bool, message: string, user?: array{id: int, email: string, display_name: string}}
 */
function loginAdmin(string $email, string $password): array
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!checkRateLimit($ip)) {
        return [
            'success' => false,
            'message' => 'Too many login attempts. Please try again in 15 minutes.'
        ];
    }

    $email = trim($email);

    if ($email === '' || $password === '') {
        recordFailedAttempt($ip);
        return [
            'success' => false,
            'message' => 'Email and password are required.'
        ];
    }

    $db = getDb();
    $stmt = $db->prepare('SELECT id, email, password_hash, display_name, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user === false || !password_verify($password, $user['password_hash'])) {
        recordFailedAttempt($ip);
        return [
            'success' => false,
            'message' => 'Invalid email or password.'
        ];
    }

    startSecureSession();
    session_regenerate_id(true);

    $_SESSION['admin_id'] = (int)$user['id'];
    $_SESSION['admin_email'] = $user['email'];
    $_SESSION['admin_name'] = $user['display_name'];
    $_SESSION['admin_role'] = $user['role'];
    $_SESSION['login_time'] = time();

    clearFailedAttempts($ip);

    return [
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'display_name' => $user['display_name'],
        ]
    ];
}

/**
 * Log out the admin and destroy the session.
 */
function logoutAdmin(): void
{
    startSecureSession();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Check if admin is currently logged in.
 * 
 * @return bool True if admin session is active
 */
function isAdminLoggedIn(): bool
{
    startSecureSession();
    return !empty($_SESSION['admin_id']);
}

/**
 * Check rate limiting for login attempts.
 * Max 5 attempts per 15 minutes per IP.
 * 
 * @param string $ip Client IP address
 * @return bool True if the request is within rate limits
 */
function checkRateLimit(string $ip): bool
{
    startSecureSession();

    $key = 'rate_limit_' . md5($ip);
    $windowMinutes = 15;
    $maxAttempts = 5;

    if (!isset($_SESSION[$key])) {
        return true;
    }

    $data = $_SESSION[$key];
    $windowStart = time() - ($windowMinutes * 60);

    if ($data['first_attempt'] < $windowStart) {
        unset($_SESSION[$key]);
        return true;
    }

    return $data['attempts'] < $maxAttempts;
}

/**
 * Record a failed login attempt for rate limiting.
 * 
 * @param string $ip Client IP address
 */
function recordFailedAttempt(string $ip): void
{
    startSecureSession();

    $key = 'rate_limit_' . md5($ip);

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 1,
            'first_attempt' => time(),
        ];
    } else {
        $_SESSION[$key]['attempts']++;
    }
}

/**
 * Clear failed login attempts after successful login.
 * 
 * @param string $ip Client IP address
 */
function clearFailedAttempts(string $ip): void
{
    startSecureSession();
    $key = 'rate_limit_' . md5($ip);
    unset($_SESSION[$key]);
}

/**
 * Generate a password reset token, hash it, and store in DB.
 * 
 * @param string $email User email
 * @return array{success: bool, message: string, token?: string}
 */
function generatePasswordResetToken(string $email): array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    if ($user === false) {
        return [
            'success' => false,
            'message' => 'If that email exists, a reset link has been sent.'
        ];
    }

    $token = bin2hex(random_bytes(32));
    $hashedToken = password_hash($token, PASSWORD_BCRYPT);
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $updateStmt = $db->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?');
    $updateStmt->execute([$hashedToken, $expires, $user['id']]);

    return [
        'success' => true,
        'message' => 'If that email exists, a reset link has been sent.',
        'token' => $token
    ];
}
