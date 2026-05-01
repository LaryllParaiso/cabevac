<?php
/**
 * CabEvac — Auth API
 * 
 * POST /api/auth.php?action=login   — Admin login
 * POST /api/auth.php?action=logout  — Admin logout
 * POST /api/auth.php?action=forgot  — Forgot password
 * GET  /api/auth.php?action=status  — Check login status
 * 
 * @package CabEvac
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

setApiHeaders();
startSecureSession();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'forgot':
        handleForgotPassword();
        break;
    case 'status':
        handleStatus();
        break;
    default:
        jsonError('Invalid action', 400);
}

/**
 * Handle login request.
 */
function handleLogin(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Method not allowed', 405);
    }

    $body = getJsonBody();

    if (empty($body)) {
        $body = [
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'csrf_token' => $_POST['csrf_token'] ?? '',
        ];
    }

    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if ($email === '' || $password === '') {
        jsonError('Email and password are required.');
    }

    $result = loginAdmin($email, $password);

    if (!$result['success']) {
        jsonError($result['message'], 401);
    }

    jsonResponse([
        'message' => $result['message'],
        'user' => $result['user'],
    ]);
}

/**
 * Handle logout request.
 */
function handleLogout(): void
{
    logoutAdmin();
    jsonResponse(['message' => 'Logged out successfully.']);
}

/**
 * Handle forgot password request.
 */
function handleForgotPassword(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Method not allowed', 405);
    }

    $body = getJsonBody();
    $email = trim($body['email'] ?? $_POST['email'] ?? '');

    if ($email === '') {
        jsonError('Email is required.');
    }

    $result = generatePasswordResetToken($email);

    jsonResponse([
        'message' => $result['message'],
        'token' => $result['token'] ?? null,
    ]);
}

/**
 * Handle auth status check.
 */
function handleStatus(): void
{
    if (isAdminLoggedIn()) {
        jsonResponse([
            'logged_in' => true,
            'user' => [
                'id' => $_SESSION['admin_id'],
                'email' => $_SESSION['admin_email'],
                'name' => $_SESSION['admin_name'],
            ],
        ]);
    } else {
        jsonResponse(['logged_in' => false]);
    }
}
