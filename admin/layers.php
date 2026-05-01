<?php
/**
 * CabEvac — Admin GIS Layers Management
 *
 * Lists all GIS layers grouped by type in a tabbed UI.
 * Allows visibility toggle, reordering, and deletion.
 *
 * @package CabEvac
 */

declare(strict_types=1);

$pageTitle = 'GIS Layers';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';

$db = getDb();

$LAYER_TYPES = [
    'buffer'             => ['label' => 'Buffer',             'color' => '#3B82F6', 'icon' => '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>'],
    'isochrone'          => ['label' => 'Isochrone',          'color' => '#8B5CF6', 'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
    'road'               => ['label' => 'Road',               'color' => '#10B981', 'icon' => '<path d="M3 21l6-18M21 21l-6-18M9 9h6M9 15h6"/>'],
    'boundary'           => ['label' => 'Boundary',           'color' => '#F59E0B', 'icon' => '<path d="M3 7l6-3 6 3 6-3v13l-6 3-6-3-6 3V7z"/><path d="M9 4v13"/><path d="M15 7v13"/>'],
    'network_analysis'   => ['label' => 'Network Analysis',   'color' => '#6366F1', 'icon' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>'],
    'population_density' => ['label' => 'Population Density', 'color' => '#06B6D4', 'icon' => '<path d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m0 0a4 4 0 118 0m-8 0a4 4 0 018 0M12 10a4 4 0 100-8 4 4 0 000 8z"/>'],
    'travel_routes'      => ['label' => 'Travel Routes',      'color' => '#EC4899', 'icon' => '<path d="M3 12h18M3 6l9-3 9 3M3 18l9 3 9-3"/>'],
    'coverage'           => ['label' => 'Coverage',           'color' => '#F97316', 'icon' => '<path d="M1 6l11-4 11 4v6c0 5.55-4.68 10.74-11 12-6.32-1.26-11-6.45-11-12V6z"/>'],
];

// Fetch all GIS layers grouped by type
$stmt = $db->query(
    'SELECT id, name, slug, layer_type, is_visible, sort_order, created_at
     FROM gis_layers
     ORDER BY layer_type ASC, sort_order ASC, name ASC'
);
$allLayers = $stmt->fetchAll();

$layersByType = [];
foreach ($LAYER_TYPES as $typeKey => $typeMeta) {
    $layersByType[$typeKey] = [];
}
foreach ($allLayers as $layer) {
    $t = $layer['layer_type'];
    if (!isset($layersByType[$t])) { $layersByType[$t] = []; }
    $layersByType[$t][] = $layer;
}

$totalCount = count($allLayers);

// Fetch flood hazard zones grouped by risk level
$floodStmt = $db->query(
    'SELECT fhz.id, b.name AS barangay, fhz.risk_level, fhz.area_sqkm, fhz.created_at
     FROM flood_hazard_zones fhz
     JOIN barangays b ON fhz.barangay_id = b.id
     ORDER BY FIELD(fhz.risk_level, "very_high", "high", "moderate"), b.name ASC'
);
$allFloodZones = $floodStmt->fetchAll();
$floodByRisk = ['very_high' => [], 'high' => [], 'moderate' => []];
foreach ($allFloodZones as $zone) {
    $floodByRisk[$zone['risk_level']][] = $zone;
}
$floodRiskMeta = [
    'very_high' => ['label' => 'Very High Risk', 'color' => '#EF4444'],
    'high'      => ['label' => 'High Risk',      'color' => '#F97316'],
    'moderate'  => ['label' => 'Moderate Risk',  'color' => '#EAB308'],
];
$floodTotalCount = count($allFloodZones);
?>

    <!-- Page Header -->
    <div class="admin-content__header">
      <div>
        <h1 class="admin-content__title">GIS Layers</h1>
        <p style="margin-top:4px; font-size:var(--font-size-sm); color:var(--color-text-tertiary);">
          Manage all <?= $totalCount ?> imported GIS layer<?= $totalCount !== 1 ? 's' : '' ?> across <?= count($LAYER_TYPES) ?> categories &nbsp;·&nbsp; <?= $floodTotalCount ?> flood hazard zone<?= $floodTotalCount !== 1 ? 's' : '' ?>
        </p>
      </div>
      <a href="import.php" class="btn btn--primary" style="display:inline-flex;align-items:center;gap:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Import Layer
      </a>
    </div>

    <!-- Layer Type Summary Cards -->
    <div class="layers-summary-grid">
      <?php foreach ($LAYER_TYPES as $typeKey => $typeMeta): ?>
        <?php $count = count($layersByType[$typeKey]); ?>
        <button
          class="layers-type-card js-tab-card <?= $typeKey === 'buffer' ? 'is-active' : '' ?>"
          data-tab="<?= $typeKey ?>"
          style="--card-color: <?= $typeMeta['color'] ?>;"
        >
          <span class="layers-type-card__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <?= $typeMeta['icon'] ?>
            </svg>
          </span>
          <span class="layers-type-card__info">
            <span class="layers-type-card__label"><?= $typeMeta['label'] ?></span>
            <span class="layers-type-card__count"><?= $count ?> layer<?= $count !== 1 ? 's' : '' ?></span>
          </span>
          <?php if ($count > 0): ?>
            <span class="layers-type-card__badge"><?= $count ?></span>
          <?php endif; ?>
        </button>
      <?php endforeach; ?>
      <!-- Flood Hazard Zone card -->
      <button
        class="layers-type-card js-tab-card"
        data-tab="flood_hazard"
        style="--card-color: #EF4444;"
      >
        <span class="layers-type-card__icon" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22C6 16 4 10.5 8 7c1-1 2.5-1.5 4-1.5S14 6 15 7c1 1 1.5 2 1.5 3"/><path d="M15.5 14c.5-1 .5-2 0-3"/><path d="M4 14c0 3 2 5.5 4 7"/><path d="M20 14c0 3-2 5.5-4 7"/><path d="M2 14h20"/>
          </svg>
        </span>
        <span class="layers-type-card__info">
          <span class="layers-type-card__label">Flood Hazard</span>
          <span class="layers-type-card__count"><?= $floodTotalCount ?> zone<?= $floodTotalCount !== 1 ? 's' : '' ?></span>
        </span>
        <?php if ($floodTotalCount > 0): ?>
          <span class="layers-type-card__badge"><?= $floodTotalCount ?></span>
        <?php endif; ?>
      </button>
    </div>

    <!-- Tab Content Panels -->
    <div class="layers-panel">
      <!-- Tab Bar -->
      <div class="layers-tabs" role="tablist" aria-label="Layer types">
        <?php foreach ($LAYER_TYPES as $typeKey => $typeMeta): ?>
          <?php $count = count($layersByType[$typeKey]); ?>
          <button
            class="layers-tab js-tab <?= $typeKey === 'buffer' ? 'is-active' : '' ?>"
            role="tab"
            data-tab="<?= $typeKey ?>"
            aria-selected="<?= $typeKey === 'buffer' ? 'true' : 'false' ?>"
            style="--tab-color: <?= $typeMeta['color'] ?>;"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <?= $typeMeta['icon'] ?>
            </svg>
            <?= $typeMeta['label'] ?>
            <?php if ($count > 0): ?>
              <span class="layers-tab__badge"><?= $count ?></span>
            <?php endif; ?>
          </button>
        <?php endforeach; ?>
        <!-- Flood Hazard tab -->
        <button
          class="layers-tab js-tab"
          role="tab"
          data-tab="flood_hazard"
          aria-selected="false"
          style="--tab-color: #EF4444;"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 9v4m0 4h.01M4.93 19h14.14a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.19 16a2 2 0 001.74 3z"/>
          </svg>
          Flood Hazard
          <?php if ($floodTotalCount > 0): ?>
            <span class="layers-tab__badge"><?= $floodTotalCount ?></span>
          <?php endif; ?>
        </button>
      </div>

      <!-- Panels -->
      <!-- Flood Hazard Zone Panel -->
      <div
        class="layers-tab-panel"
        data-panel="flood_hazard"
        role="tabpanel"
        aria-label="Flood Hazard layers"
      >
        <?php if ($floodTotalCount === 0): ?>
          <div class="layers-empty">
            <div class="layers-empty__icon" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 9v4m0 4h.01M4.93 19h14.14a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.19 16a2 2 0 001.74 3z"/>
              </svg>
            </div>
            <p class="layers-empty__title">No flood hazard zones</p>
            <p class="layers-empty__sub">Import GeoJSON flood hazard data to populate this section.</p>
            <a href="import.php" class="btn btn--primary" style="margin-top:var(--space-4);">Import Flood Hazard</a>
          </div>
        <?php else: ?>
          <?php foreach ($floodRiskMeta as $riskKey => $riskMeta): ?>
            <?php $zones = $floodByRisk[$riskKey]; if (empty($zones)) { continue; } ?>
            <div style="margin-bottom: var(--space-6);">
              <div style="display:flex; align-items:center; gap:8px; padding: var(--space-3) var(--space-5); border-bottom: 1px solid var(--color-border);">
                <span style="width:10px; height:10px; border-radius:50%; background:<?= $riskMeta['color'] ?>; flex-shrink:0;"></span>
                <strong style="font-size:var(--font-size-sm); color:var(--color-text-primary);"><?= $riskMeta['label'] ?></strong>
                <span style="font-size:var(--font-size-xs); color:var(--color-text-tertiary); margin-left:4px;"><?= count($zones) ?> barangay<?= count($zones) !== 1 ? 's' : '' ?></span>
              </div>
              <div class="layers-table-wrap">
                <table class="data-table layers-table">
                  <thead>
                    <tr>
                      <th style="width:40px;"></th>
                      <th>Barangay</th>
                      <th style="width:120px;">Risk Level</th>
                      <th style="width:120px;">Area (km²)</th>
                      <th style="width:130px;">Added</th>
                      <th style="width:80px; text-align:right;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($zones as $zone): ?>
                      <tr class="layers-row" data-flood-id="<?= (int)$zone['id'] ?>">
                        <td>
                          <span class="layers-row__dot" style="background: <?= $riskMeta['color'] ?>;"></span>
                        </td>
                        <td>
                          <span class="layers-row__name"><?= htmlspecialchars($zone['barangay'], ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td style="font-size:var(--font-size-xs);">
                          <span style="padding:2px 8px; border-radius:999px; background:<?= $riskMeta['color'] ?>22; color:<?= $riskMeta['color'] ?>; font-weight:600;">
                            <?= $riskMeta['label'] ?>
                          </span>
                        </td>
                        <td style="font-size:var(--font-size-xs); color:var(--color-text-tertiary);">
                          <?= $zone['area_sqkm'] !== null ? number_format((float)$zone['area_sqkm'], 2) : '—' ?>
                        </td>
                        <td style="font-size:var(--font-size-xs); color:var(--color-text-tertiary);">
                          <?= date('M j, Y', strtotime($zone['created_at'])) ?>
                        </td>
                        <td style="text-align:right;">
                          <button
                            class="btn btn--danger-ghost btn--sm js-delete-flood-zone"
                            data-id="<?= (int)$zone['id'] ?>"
                            data-name="<?= htmlspecialchars($zone['barangay'] . ' (' . $riskMeta['label'] . ')', ENT_QUOTES, 'UTF-8') ?>"
                            title="Delete zone"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            Delete
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <?php foreach ($LAYER_TYPES as $typeKey => $typeMeta): ?>
        <div
          class="layers-tab-panel <?= $typeKey === 'buffer' ? 'is-active' : '' ?>"
          data-panel="<?= $typeKey ?>"
          role="tabpanel"
          aria-label="<?= htmlspecialchars($typeMeta['label'], ENT_QUOTES, 'UTF-8') ?> layers"
        >
          <?php $layers = $layersByType[$typeKey]; ?>

          <?php if (empty($layers)): ?>
            <div class="layers-empty">
              <div class="layers-empty__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <?= $typeMeta['icon'] ?>
                </svg>
              </div>
              <p class="layers-empty__title">No <?= $typeMeta['label'] ?> layers</p>
              <p class="layers-empty__sub">Import a GeoJSON file to add a <?= strtolower($typeMeta['label']) ?> layer.</p>
              <a href="import.php?type=<?= urlencode($typeKey) ?>" class="btn btn--primary" style="margin-top:var(--space-4);">
                Import <?= $typeMeta['label'] ?> Layer
              </a>
            </div>
          <?php else: ?>
            <div class="layers-table-wrap">
              <table class="data-table layers-table">
                <thead>
                  <tr>
                    <th style="width:40px;"></th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th style="width:90px; text-align:center;">Visible</th>
                    <th style="width:80px; text-align:center;">Order</th>
                    <th style="width:130px;">Added</th>
                    <th style="width:80px; text-align:right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($layers as $layer): ?>
                    <tr class="layers-row" data-id="<?= (int)$layer['id'] ?>">
                      <td>
                        <span class="layers-row__dot" style="background: <?= htmlspecialchars($typeMeta['color'], ENT_QUOTES, 'UTF-8') ?>;"></span>
                      </td>
                      <td>
                        <span class="layers-row__name"><?= htmlspecialchars($layer['name'], ENT_QUOTES, 'UTF-8') ?></span>
                      </td>
                      <td>
                        <code class="layers-row__slug"><?= htmlspecialchars($layer['slug'], ENT_QUOTES, 'UTF-8') ?></code>
                      </td>
                      <td style="text-align:center;">
                        <label class="toggle-switch" title="Toggle visibility">
                          <input
                            type="checkbox"
                            class="toggle-switch__input js-visibility-toggle"
                            data-id="<?= (int)$layer['id'] ?>"
                            <?= $layer['is_visible'] ? 'checked' : '' ?>
                          >
                          <span class="toggle-switch__track"></span>
                        </label>
                      </td>
                      <td style="text-align:center; color:var(--color-text-tertiary); font-size:var(--font-size-xs);">
                        <?= (int)$layer['sort_order'] ?>
                      </td>
                      <td style="font-size:var(--font-size-xs); color:var(--color-text-tertiary);">
                        <?= date('M j, Y', strtotime($layer['created_at'])) ?>
                      </td>
                      <td style="text-align:right;">
                        <button
                          class="btn btn--danger-ghost btn--sm js-delete-layer"
                          data-id="<?= (int)$layer['id'] ?>"
                          data-name="<?= htmlspecialchars($layer['name'], ENT_QUOTES, 'UTF-8') ?>"
                          title="Delete layer"
                          aria-label="Delete <?= htmlspecialchars($layer['name'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                          Delete
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

  </div><!-- /.admin-content -->

  <!-- Delete Confirm Modal -->
  <div class="layers-modal" id="delete-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
    <div class="layers-modal__overlay js-modal-close"></div>
    <div class="layers-modal__panel">
      <div class="layers-modal__icon layers-modal__icon--danger">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
      </div>
      <h2 class="layers-modal__title" id="delete-modal-title">Delete Layer</h2>
      <p class="layers-modal__body" id="delete-modal-body">Are you sure you want to delete <strong id="delete-modal-name"></strong>? This action cannot be undone.</p>
      <div class="layers-modal__actions">
        <button class="btn btn--ghost js-modal-close">Cancel</button>
        <button class="btn btn--danger" id="delete-modal-confirm">Delete Layer</button>
      </div>
    </div>
  </div>

  <div class="toast-container" id="toast-container" aria-live="polite"></div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
  <script>
    'use strict';

    // ── Toast ───────────────────────────────────────────────────────────────
    function showToast(msg, type = 'info') {
      const container = document.getElementById('toast-container');
      const toast = document.createElement('div');
      toast.className = `toast toast--${type}`;
      toast.innerHTML = `<span>${DOMPurify.sanitize(msg)}</span><button class="toast__close" onclick="this.parentElement.remove()">✕</button>`;
      container.appendChild(toast);
      setTimeout(() => { if (toast.parentNode) toast.remove(); }, 4000);
    }

    // ── Tab switching ───────────────────────────────────────────────────────
    const allTabs     = document.querySelectorAll('.js-tab');
    const allPanels   = document.querySelectorAll('.layers-tab-panel');
    const allCards    = document.querySelectorAll('.js-tab-card');

    function activateTab(key) {
      allTabs.forEach((t) => {
        const active = t.dataset.tab === key;
        t.classList.toggle('is-active', active);
        t.setAttribute('aria-selected', String(active));
      });
      allPanels.forEach((p) => p.classList.toggle('is-active', p.dataset.panel === key));
      allCards.forEach((c) => c.classList.toggle('is-active', c.dataset.tab === key));
    }

    allTabs.forEach((tab) => tab.addEventListener('click', () => activateTab(tab.dataset.tab)));
    allCards.forEach((card) => card.addEventListener('click', () => activateTab(card.dataset.tab)));

    // ── Visibility toggle ───────────────────────────────────────────────────
    document.querySelectorAll('.js-visibility-toggle').forEach((checkbox) => {
      checkbox.addEventListener('change', async () => {
        const id      = checkbox.dataset.id;
        const visible = checkbox.checked ? 1 : 0;
        try {
          const res  = await fetch('../api/admin/layers.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_visibility', id, visible }),
          });
          const json = await res.json();
          if (json.success) {
            showToast(`Layer visibility updated`, 'success');
          } else {
            checkbox.checked = !checkbox.checked;
            showToast(json.error || 'Update failed', 'error');
          }
        } catch {
          checkbox.checked = !checkbox.checked;
          showToast('Network error', 'error');
        }
      });
    });

    // ── Delete layer ────────────────────────────────────────────────────────
    const deleteModal   = document.getElementById('delete-modal');
    const deleteConfirm = document.getElementById('delete-modal-confirm');
    const deleteNameEl  = document.getElementById('delete-modal-name');
    let pendingDeleteId     = null;
    let pendingDeleteRow    = null;
    let pendingDeleteAction = 'delete';

    function openDeleteModal(id, name, row, action = 'delete') {
      pendingDeleteId     = id;
      pendingDeleteRow    = row;
      pendingDeleteAction = action;
      deleteNameEl.textContent = name;
      deleteModal.setAttribute('aria-hidden', 'false');
    }

    function closeDeleteModal() {
      deleteModal.setAttribute('aria-hidden', 'true');
      pendingDeleteId  = null;
      pendingDeleteRow = null;
    }

    document.querySelectorAll('.js-modal-close').forEach((el) =>
      el.addEventListener('click', closeDeleteModal)
    );
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { closeDeleteModal(); }
    });

    document.querySelectorAll('.js-delete-layer').forEach((btn) => {
      btn.addEventListener('click', () => {
        openDeleteModal(btn.dataset.id, btn.dataset.name, btn.closest('tr'), 'delete');
      });
    });

    document.querySelectorAll('.js-delete-flood-zone').forEach((btn) => {
      btn.addEventListener('click', () => {
        openDeleteModal(btn.dataset.id, btn.dataset.name, btn.closest('tr'), 'delete_flood_zone');
      });
    });

    deleteConfirm.addEventListener('click', async () => {
      if (!pendingDeleteId) { return; }
      deleteConfirm.disabled = true;
      deleteConfirm.textContent = 'Deleting…';
      try {
        const res  = await fetch('../api/admin/layers.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: pendingDeleteAction, id: pendingDeleteId }),
        });
        const json = await res.json();
        if (json.success) {
          if (pendingDeleteRow) { pendingDeleteRow.remove(); }
          showToast('Deleted successfully', 'success');
          closeDeleteModal();
        } else {
          showToast(json.error || 'Delete failed', 'error');
        }
      } catch {
        showToast('Network error', 'error');
      }
      deleteConfirm.disabled = false;
      deleteConfirm.textContent = 'Delete Layer';
    });

    // ── Logout ──────────────────────────────────────────────────────────────
    document.getElementById('admin-logout-btn').addEventListener('click', async () => {
      await fetch('../api/auth.php?action=logout', { method: 'POST' });
      window.location.href = 'login.php';
    });
  </script>
</body>
</html>
