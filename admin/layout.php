<?php
/**
 * CabEvac — Admin Layout Template
 * 
 * Shared layout included by all admin pages.
 * Provides the top bar, sidebar navigation, and content wrapper.
 * 
 * @package CabEvac
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$csrfToken = generateCsrfToken();

/**
 * Check if a nav item is the current active page.
 * 
 * @param string $page Page name
 * @return string CSS class
 */
function isActivePage(string $page): string
{
    global $currentPage;
    return ($currentPage === $page) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Admin Dashboard', ENT_QUOTES, 'UTF-8') ?> — CabEvac Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="../css/index.css">
</head>
<body class="admin-page">

  <!-- Admin Top Bar -->
  <header class="admin-topbar" role="banner">
    <div class="admin-topbar__left">
      <span class="admin-topbar__logo" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a7 7 0 0 0-7 7c0 4.5 5 9 7 13 2-4 7-8.5 7-13a7 7 0 0 0-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
      </span>
      <span class="admin-topbar__brand">CabEvac Admin</span>
    </div>
    <div class="admin-topbar__right">
      <span class="admin-topbar__user">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></span>
      </span>
      <button class="btn btn--ghost" id="admin-logout-btn" aria-label="Log out">Logout</button>
    </div>
  </header>

  <!-- Admin Sidebar -->
  <nav class="admin-sidebar" role="navigation" aria-label="Admin navigation">
    <a href="index.php" class="admin-nav-item <?= isActivePage('index') ?>" aria-label="Dashboard">
      <span class="admin-nav-item__icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
      </span>
      <span class="admin-nav-item__label">Dashboard</span>
    </a>
    <a href="centers.php" class="admin-nav-item <?= isActivePage('centers') ?>" aria-label="Evacuation Centers">
      <span class="admin-nav-item__icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 7-8 12-8 12s-8-5-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
      </span>
      <span class="admin-nav-item__label">Evacuation Centers</span>
    </a>
    <a href="barangays.php" class="admin-nav-item <?= isActivePage('barangays') ?>" aria-label="Barangays">
      <span class="admin-nav-item__icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l6-3 6 3 6-3v13l-6 3-6-3-6 3V7z"/><path d="M9 4v13"/><path d="M15 7v13"/></svg>
      </span>
      <span class="admin-nav-item__label">Barangays</span>
    </a>
    <a href="import.php" class="admin-nav-item <?= isActivePage('import') ?>" aria-label="Data Import">
      <span class="admin-nav-item__icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      </span>
      <span class="admin-nav-item__label">Data Import</span>
    </a>
    <a href="layers.php" class="admin-nav-item <?= isActivePage('layers') ?>" aria-label="GIS Layers">
      <span class="admin-nav-item__icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
      </span>
      <span class="admin-nav-item__label">GIS Layers</span>
    </a>
  </nav>

  <!-- Content Area -->
  <div class="admin-content" id="admin-content">
