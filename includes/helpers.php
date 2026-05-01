<?php
/**
 * CabEvac — Helper Utilities
 * 
 * Shared utility functions for sanitization, CSRF, JSON responses,
 * validation, and environment variable loading.
 * 
 * @package CabEvac
 */

declare(strict_types=1);

/**
 * Load environment variables from a .env file into $_ENV and putenv().
 * 
 * @param string $path Absolute path to the .env file
 * @throws RuntimeException If .env file is not found
 */
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        throw new RuntimeException("Environment file not found: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        throw new RuntimeException("Failed to read environment file: {$path}");
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

/**
 * Send a JSON success response and exit.
 * 
 * @param mixed $data Response payload
 * @param int $status HTTP status code
 */
function jsonResponse(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send a JSON error response and exit.
 * 
 * @param string $message Error message
 * @param int $status HTTP status code
 */
function jsonError(string $message, int $status = 400): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sanitize user input string for safe output.
 * 
 * @param string $input Raw user input
 * @return string Sanitized string
 */
function sanitizeInput(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a CSRF token and store it in the session.
 * 
 * @return string The generated CSRF token
 */
function generateCsrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        startSecureSession();
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();

    return $token;
}

/**
 * Validate a CSRF token against the session-stored token.
 * Tokens expire after 1 hour.
 * 
 * @param string $token The token to validate
 * @return bool True if valid
 */
function validateCsrfToken(string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) {
        return false;
    }

    $maxAge = 3600;
    if ((time() - (int)$_SESSION['csrf_token_time']) > $maxAge) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate latitude and longitude ranges.
 * 
 * @param float $lat Latitude (-90 to 90)
 * @param float $lng Longitude (-180 to 180)
 * @return bool True if coordinates are valid
 */
function validateCoordinates(float $lat, float $lng): bool
{
    return ($lat >= -90.0 && $lat <= 90.0) && ($lng >= -180.0 && $lng <= 180.0);
}

/**
 * Validate an uploaded image file.
 * 
 * @param array<string, mixed> $file The $_FILES entry
 * @return true|string True if valid, error message string if invalid
 */
function validateImageFile(array $file): true|string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        ];
        return $errors[$file['error']] ?? 'Unknown upload error';
    }

    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return 'File too large. Maximum size is 5MB';
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedMimes, true)) {
        return 'Invalid file type. Allowed: JPEG, PNG, WebP';
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        return 'Invalid file extension. Allowed: jpg, jpeg, png, webp';
    }

    return true;
}

/**
 * Generate a unique filename for uploaded files.
 * 
 * @param string $extension File extension (without dot)
 * @return string UUID-based filename
 */
function generateUniqueFilename(string $extension): string
{
    $uuid = bin2hex(random_bytes(16));
    $formatted = substr($uuid, 0, 8) . '-' . substr($uuid, 8, 4) . '-' .
                 substr($uuid, 12, 4) . '-' . substr($uuid, 16, 4) . '-' .
                 substr($uuid, 20);

    return $formatted . '.' . strtolower($extension);
}

/**
 * Get the base URL of the application.
 * 
 * @return string Base URL (e.g., "http://localhost/cabevac")
 */
function getBaseUrl(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim($scriptDir, '/');

    return "{$protocol}://{$host}{$basePath}";
}

/**
 * Log an error message to the application error log.
 * 
 * @param string $message Error message
 * @param array<string, mixed> $context Additional context data
 */
function logError(string $message, array $context = []): void
{
    $logDir = __DIR__ . '/../logs';
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $contextStr = $context !== [] ? ' | ' . json_encode($context) : '';
    $logEntry = "[{$timestamp}] {$message}{$contextStr}" . PHP_EOL;

    error_log($logEntry, 3, $logDir . '/error.log');
}

/**
 * Set common API response headers (CORS, content type).
 */
function setApiHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: http://localhost');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Credentials: true');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Get JSON body from a POST/PUT request.
 * 
 * @return array<string, mixed> Decoded JSON body
 */
function getJsonBody(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * Create a URL-friendly slug from a string.
 * 
 * @param string $text Input text
 * @return string Slugified string
 */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text) ?? $text;
    $text = preg_replace('/[\s-]+/', '-', $text) ?? $text;

    return trim($text, '-');
}
