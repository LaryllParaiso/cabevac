<?php
/**
 * CabEvac — Admin Login Page
 * 
 * @package CabEvac
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSecureSession();

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — CabEvac</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/index.css">
</head>
<body>
  <div class="admin-login-page">
    <div class="admin-login-card glass-panel">
      <div class="admin-login-card__header">
        <div class="admin-login-card__icon">🌊</div>
        <h1 class="admin-login-card__title">CabEvac</h1>
        <p class="admin-login-card__subtitle">Admin Panel</p>
      </div>

      <div class="login-error" id="login-error" role="alert"></div>

      <form id="login-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
          <label for="login-email">Email Address</label>
          <input type="email" class="input" id="login-email" name="email"
                 placeholder="admin@cabevac.local" autocomplete="email" required
                 aria-label="Email address">
        </div>

        <div class="form-group">
          <label for="login-password">Password</label>
          <div class="password-wrapper">
            <input type="password" class="input" id="login-password" name="password"
                   placeholder="Enter your password" autocomplete="current-password" required
                   aria-label="Password">
            <button type="button" class="password-toggle" id="password-toggle" aria-label="Toggle password visibility">
              👁
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn--primary btn--full" id="login-submit">
          Login
        </button>
      </form>

      <div class="admin-login-card__footer">
        <a href="#" id="forgot-password-link">Forgot Password?</a>
        <br><br>
        <a href="../index.php" class="btn--ghost" style="font-size:0.875rem; color:var(--color-text-tertiary);">← Back to Map</a>
      </div>
    </div>
  </div>

  <div class="toast-container" id="toast-container" aria-live="polite"></div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
  <script>
    'use strict';

    const loginForm = document.getElementById('login-form');
    const loginError = document.getElementById('login-error');
    const submitBtn = document.getElementById('login-submit');

    // Password toggle
    document.getElementById('password-toggle').addEventListener('click', () => {
      const passInput = document.getElementById('login-password');
      const isPassword = passInput.type === 'password';
      passInput.type = isPassword ? 'text' : 'password';
    });

    // Login form submit
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      loginError.classList.remove('visible');
      submitBtn.classList.add('btn--loading');
      submitBtn.textContent = 'Logging in...';

      const formData = new FormData(loginForm);

      try {
        const response = await fetch('../api/auth.php?action=login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            email: formData.get('email'),
            password: formData.get('password'),
            csrf_token: formData.get('csrf_token'),
          }),
        });

        const data = await response.json();

        if (data.success) {
          window.location.href = 'index.php';
        } else {
          loginError.textContent = data.error || 'Login failed.';
          loginError.classList.add('visible');

          const card = document.querySelector('.admin-login-card');
          card.style.animation = 'shake 0.4s ease-in-out';
          setTimeout(() => { card.style.animation = ''; }, 500);
        }
      } catch (error) {
        loginError.textContent = 'Network error. Please check your connection.';
        loginError.classList.add('visible');
      } finally {
        submitBtn.classList.remove('btn--loading');
        submitBtn.textContent = 'Login';
      }
    });
  </script>
</body>
</html>
