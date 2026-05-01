<?php
/**
 * CabEvac — Admin Dashboard
 * 
 * Shows statistics cards, recent evacuation centers table,
 * and a small map preview.
 * 
 * @package CabEvac
 */

declare(strict_types=1);

$pageTitle = 'Dashboard';

require_once __DIR__ . '/../includes/db.php';

$db = getDb();

// Fetch stats
$centerCount = (int)$db->query('SELECT COUNT(*) FROM evacuation_centers')->fetchColumn();
$barangayCount = (int)$db->query('SELECT COUNT(*) FROM barangays')->fetchColumn();
$floodZoneCount = (int)$db->query('SELECT COUNT(DISTINCT risk_level) FROM flood_hazard_zones')->fetchColumn();
$layerCount = (int)$db->query('SELECT COUNT(*) FROM gis_layers')->fetchColumn();

// Fetch recent centers
$centersStmt = $db->query(
    'SELECT ec.id, ec.name, ec.status, ec.capacity, ec.latitude, ec.longitude, b.name AS barangay
     FROM evacuation_centers ec
     JOIN barangays b ON ec.barangay_id = b.id
     ORDER BY ec.updated_at DESC
     LIMIT 10'
);
$recentCenters = $centersStmt->fetchAll();

require_once __DIR__ . '/layout.php';
?>

    <!-- Dashboard Header -->
    <div class="admin-content__header">
      <h1 class="admin-content__title">Dashboard</h1>
    </div>

    <!-- Stat Cards -->
    <div class="stat-cards">
      <div class="stat-card stat-card--green">
        <div class="stat-card__number"><?= $centerCount ?></div>
        <div class="stat-card__label">Evacuation Centers</div>
      </div>
      <div class="stat-card stat-card--blue">
        <div class="stat-card__number"><?= $barangayCount ?></div>
        <div class="stat-card__label">Barangays</div>
      </div>
      <div class="stat-card stat-card--red">
        <div class="stat-card__number"><?= $floodZoneCount ?></div>
        <div class="stat-card__label">Hazard Levels</div>
      </div>
      <div class="stat-card stat-card--purple">
        <div class="stat-card__number"><?= $layerCount ?></div>
        <div class="stat-card__label">GIS Layers</div>
      </div>
    </div>

    <!-- Recent Evacuation Centers Table -->
    <div style="margin-bottom: var(--space-8);">
      <h2 style="font-size: var(--font-size-lg); font-weight: 700; margin-bottom: var(--space-4);">
        Evacuation Centers
      </h2>
      <div class="data-table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Barangay</th>
              <th>Capacity</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentCenters as $center): ?>
              <tr>
                <td style="font-weight: 600;"><?= htmlspecialchars($center['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($center['barangay'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $center['capacity'] !== null ? number_format((int)$center['capacity']) : '—' ?></td>
                <td>
                  <span class="badge badge--<?= $center['status'] === 'active' ? 'active' : 'inactive' ?>">
                    <span class="badge__dot"></span>
                    <?= ucfirst($center['status']) ?>
                  </span>
                </td>
                <td>
                  <div class="data-table__actions">
                    <a href="centers.php?action=edit&id=<?= (int)$center['id'] ?>" class="btn btn--ghost" style="font-size:0.75rem;">Edit</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($recentCenters)): ?>
              <tr>
                <td colspan="5" style="text-align:center; padding: var(--space-8); color: var(--color-text-tertiary);">
                  No evacuation centers found
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Map Preview -->
    <div>
      <h2 style="font-size: var(--font-size-lg); font-weight: 700; margin-bottom: var(--space-4);">
        Map Overview
      </h2>
      <div id="admin-map-preview" class="admin-map-preview"></div>
    </div>

  </div><!-- /.admin-content -->

  <div class="toast-container" id="toast-container" aria-live="polite"></div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
  <script>
    'use strict';

    // Logout handler
    document.getElementById('admin-logout-btn').addEventListener('click', async () => {
      await fetch('../api/auth.php?action=logout', { method: 'POST' });
      window.location.href = 'login.php';
    });

    // Mini map preview
    const previewMap = L.map('admin-map-preview', {
      center: [15.486, 120.965],
      zoom: 13,
      zoomControl: true,
      scrollWheelZoom: false,
      dragging: true,
      attributionControl: false,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
    }).addTo(previewMap);

    // Add center markers from table data
    const centers = <?= json_encode(array_map(function ($c) {
        return [
            'name' => $c['name'],
            'lat' => (float)$c['latitude'],
            'lng' => (float)$c['longitude'],
            'status' => $c['status'],
        ];
    }, $recentCenters)) ?>;

    centers.forEach((c) => {
      if (c.lat && c.lng) {
        const color = c.status === 'active' ? '#10B981' : '#6B7280';
        L.circleMarker([c.lat, c.lng], {
          radius: 8,
          fillColor: color,
          fillOpacity: 0.9,
          color: '#fff',
          weight: 2,
        }).addTo(previewMap).bindTooltip(c.name);
      }
    });
  </script>
</body>
</html>
