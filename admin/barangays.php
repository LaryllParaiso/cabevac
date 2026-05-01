<?php
/**
 * CabEvac - Admin Barangays Management
 *
 * List, add, edit, and dependency-safe delete for barangay records.
 *
 * @package CabEvac
 */

declare(strict_types=1);

$pageTitle = 'Barangays';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/barangay_admin.php';

$db = getDb();

$stmt = $db->query(
    'SELECT b.id, b.name, b.pcode, b.population, b.population_density,
            b.area_sqkm, b.flood_risk_level, b.land_use, b.description,
            COUNT(DISTINCT ec.id) AS center_count,
            COUNT(DISTINCT fhz.id) AS flood_zone_count
     FROM barangays b
     LEFT JOIN evacuation_centers ec ON ec.barangay_id = b.id
     LEFT JOIN flood_hazard_zones fhz ON fhz.barangay_id = b.id
     GROUP BY b.id, b.name, b.pcode, b.population, b.population_density,
              b.area_sqkm, b.flood_risk_level, b.land_use, b.description
     ORDER BY b.name ASC'
);
$barangays = $stmt->fetchAll();

$riskMeta = [
    'low' => ['label' => 'Low', 'color' => '#10B981'],
    'moderate' => ['label' => 'Moderate', 'color' => '#EAB308'],
    'high' => ['label' => 'High', 'color' => '#F97316'],
    'very_high' => ['label' => 'Very High', 'color' => '#EF4444'],
];

require_once __DIR__ . '/layout.php';
?>

    <div class="admin-content__header">
      <div>
        <h1 class="admin-content__title">Barangays</h1>
        <p style="margin-top:4px; font-size:var(--font-size-sm); color:var(--color-text-tertiary);">
          Manage <?= count($barangays) ?> barangay<?= count($barangays) !== 1 ? 's' : '' ?> and their public map metadata.
        </p>
      </div>
      <button class="btn btn--primary" id="add-barangay-btn" onclick="showForm('add')">
        + Add New Barangay
      </button>
    </div>

    <div id="list-view">
      <div class="admin-table-controls">
        <div class="admin-table-search">
          <label for="barangays-table-search">Search barangays</label>
          <input type="search" class="input" id="barangays-table-search" placeholder="Search by name, risk, land use, or description">
        </div>
        <div class="admin-table-page-size">
          <label for="barangays-page-size">Rows</label>
          <select class="input select" id="barangays-page-size">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>
      </div>
      <div class="data-table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th class="sortable-th" data-admin-table="barangays" data-sort-key="name">Name</th>
              <th class="sortable-th" data-admin-table="barangays" data-sort-key="flood_risk_level">Flood Risk</th>
              <th class="sortable-th" data-admin-table="barangays" data-sort-key="land_use">Land Use</th>
              <th class="sortable-th" data-admin-table="barangays" data-sort-key="population_density">Pop. Density</th>
              <th class="sortable-th" data-admin-table="barangays" data-sort-key="center_count">Centers</th>
              <th class="sortable-th" data-admin-table="barangays" data-sort-key="flood_zone_count">Flood Zones</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="barangays-table-body">
            <?php foreach ($barangays as $barangay): ?>
              <?php
                $risk = (string)($barangay['flood_risk_level'] ?? '');
                $meta = $riskMeta[$risk] ?? ['label' => 'Not Set', 'color' => '#6B7280'];
              ?>
              <tr id="row-<?= (int)$barangay['id'] ?>">
                <td>
                  <div style="font-weight:600; color:var(--color-text-primary);">
                    <?= htmlspecialchars($barangay['name'], ENT_QUOTES, 'UTF-8') ?>
                  </div>
                  <?php if (!empty($barangay['description'])): ?>
                    <div style="max-width:360px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:var(--font-size-xs); color:var(--color-text-tertiary); margin-top:2px;">
                      <?= htmlspecialchars((string)$barangay['description'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <span style="display:inline-flex; align-items:center; gap:6px; padding:3px 9px; border-radius:999px; background:<?= $meta['color'] ?>22; color:<?= $meta['color'] ?>; font-size:var(--font-size-xs); font-weight:700;">
                    <span style="width:7px; height:7px; border-radius:999px; background:<?= $meta['color'] ?>;"></span>
                    <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </td>
                <td><?= $barangay['land_use'] !== null && $barangay['land_use'] !== '' ? htmlspecialchars((string)$barangay['land_use'], ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td>
                <td><?= $barangay['population_density'] !== null ? number_format((float)$barangay['population_density'], 2) : '&mdash;' ?></td>
                <td><?= (int)$barangay['center_count'] ?></td>
                <td><?= (int)$barangay['flood_zone_count'] ?></td>
                <td>
                  <div class="data-table__actions">
                    <button class="btn btn--ghost" style="font-size:0.75rem;" onclick="showForm('edit', <?= (int)$barangay['id'] ?>)">Edit</button>
                    <button class="btn btn--ghost" style="font-size:0.75rem; color:var(--color-status-error);" onclick="deleteBarangay(<?= (int)$barangay['id'] ?>)">Delete</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($barangays)): ?>
              <tr id="empty-row">
                <td colspan="7" style="text-align:center; padding:var(--space-8); color:var(--color-text-tertiary);">No barangays found</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="admin-table-footer">
        <div class="admin-table-summary" id="barangays-table-summary"></div>
        <div class="admin-pagination" id="barangays-pagination" aria-label="Barangays pagination"></div>
      </div>
    </div>

    <div id="form-view" style="display:none;">
      <div style="margin-bottom:var(--space-6);">
        <button class="btn btn--ghost" onclick="showList()">&larr; Back to List</button>
      </div>

      <h2 id="form-title" style="font-size:var(--font-size-lg); font-weight:700; margin-bottom:var(--space-6);">Add Barangay</h2>

      <form id="barangay-form" novalidate>
        <input type="hidden" id="form-barangay-id" value="">

        <div class="admin-form-grid">
          <div class="form-column">
            <div class="form-group">
              <label for="form-name">Barangay Name *</label>
              <input type="text" class="input" id="form-name" placeholder="Enter barangay name" required>
            </div>

            <div class="form-group">
              <label for="form-pcode">PCode</label>
              <input type="text" class="input" id="form-pcode" placeholder="Optional PSGC or boundary code">
            </div>

            <div class="form-group">
              <label for="form-flood-risk">Flood Risk Level</label>
              <select class="input select" id="form-flood-risk">
                <option value="">Not set</option>
                <option value="low">Low</option>
                <option value="moderate">Moderate</option>
                <option value="high">High</option>
                <option value="very_high">Very High</option>
              </select>
            </div>

            <div class="form-group">
              <label for="form-land-use">Land Use</label>
              <input type="text" class="input" id="form-land-use" placeholder="e.g. Residential, Agricultural">
            </div>
          </div>

          <div class="form-column">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4);">
              <div class="form-group">
                <label for="form-population">Population</label>
                <input type="number" class="input" id="form-population" min="0" step="1" placeholder="e.g. 1200">
              </div>
              <div class="form-group">
                <label for="form-density">Population Density</label>
                <input type="number" class="input" id="form-density" min="0" step="0.0001" placeholder="people/km2">
              </div>
            </div>

            <div class="form-group">
              <label for="form-area">Area (km2)</label>
              <input type="number" class="input" id="form-area" min="0" step="0.000001" placeholder="e.g. 2.500000">
            </div>

            <div class="form-group" id="dependency-context" style="display:none;">
              <label>Linked Records</label>
              <div style="display:flex; gap:var(--space-3); flex-wrap:wrap; color:var(--color-text-secondary); font-size:var(--font-size-sm);">
                <span id="dependency-centers" style="padding:6px 10px; border:1px solid var(--color-border); border-radius:6px;">0 centers</span>
                <span id="dependency-zones" style="padding:6px 10px; border:1px solid var(--color-border); border-radius:6px;">0 flood zones</span>
              </div>
            </div>
          </div>
        </div>

        <div class="form-group" style="margin-top:var(--space-4);">
          <label for="form-description">Description</label>
          <textarea class="input textarea" id="form-description" rows="4" placeholder="Description shown in the public barangay sidebar"></textarea>
        </div>

        <div style="display:flex; gap:var(--space-4); margin-top:var(--space-8); justify-content:flex-end;">
          <button type="button" class="btn btn--secondary" onclick="showList()">Cancel</button>
          <button type="submit" class="btn btn--primary" id="form-submit">Save Barangay</button>
        </div>
      </form>
    </div>

  </div><!-- /.admin-content -->

  <div class="toast-container" id="toast-container" aria-live="polite"></div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
  <script src="../js/adminTableControls.js"></script>
  <script>
    'use strict';

    const barangays = <?= json_encode($barangays, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const barangayById = new Map(barangays.map((barangay) => [String(barangay.id), barangay]));
    const adminTable = window.AdminTableControls;
    const riskMeta = {
      low: { label: 'Low', color: '#10B981' },
      moderate: { label: 'Moderate', color: '#EAB308' },
      high: { label: 'High', color: '#F97316' },
      very_high: { label: 'Very High', color: '#EF4444' },
    };

    adminTable.initAdminDataTable({
      tableId: 'barangays',
      rows: barangays,
      searchableKeys: ['name', 'pcode', 'flood_risk_level', 'land_use', 'description', 'population_density'],
      defaultSortKey: 'name',
      defaultSortDirection: 'asc',
      defaultPageSize: 10,
      searchInputId: 'barangays-table-search',
      pageSizeSelectId: 'barangays-page-size',
      tbodyId: 'barangays-table-body',
      summaryId: 'barangays-table-summary',
      paginationId: 'barangays-pagination',
      renderEmptyRow: () => '<tr><td colspan="7" style="text-align:center; padding:var(--space-8); color:var(--color-text-tertiary);">No barangays found</td></tr>',
      renderRow: (barangay) => {
        const meta = riskMeta[barangay.flood_risk_level] || { label: 'Not Set', color: '#6B7280' };
        const density = barangay.population_density !== null && barangay.population_density !== ''
          ? Number(barangay.population_density).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
          : '&mdash;';
        const description = barangay.description
          ? `<div style="max-width:360px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:var(--font-size-xs); color:var(--color-text-tertiary); margin-top:2px;">${adminTable.escapeHtml(barangay.description)}</div>`
          : '';

        return `
          <tr id="row-${barangay.id}">
            <td>
              <div style="font-weight:600; color:var(--color-text-primary);">${adminTable.escapeHtml(barangay.name)}</div>
              ${description}
            </td>
            <td>
              <span style="display:inline-flex; align-items:center; gap:6px; padding:3px 9px; border-radius:999px; background:${meta.color}22; color:${meta.color}; font-size:var(--font-size-xs); font-weight:700;">
                <span style="width:7px; height:7px; border-radius:999px; background:${meta.color};"></span>
                ${adminTable.escapeHtml(meta.label)}
              </span>
            </td>
            <td>${barangay.land_use ? adminTable.escapeHtml(barangay.land_use) : '&mdash;'}</td>
            <td>${density}</td>
            <td>${parseInt(barangay.center_count || 0, 10)}</td>
            <td>${parseInt(barangay.flood_zone_count || 0, 10)}</td>
            <td>
              <div class="data-table__actions">
                <button class="btn btn--ghost" style="font-size:0.75rem;" onclick="showForm('edit', ${parseInt(barangay.id, 10)})">Edit</button>
                <button class="btn btn--ghost" style="font-size:0.75rem; color:var(--color-status-error);" onclick="deleteBarangay(${parseInt(barangay.id, 10)})">Delete</button>
              </div>
            </td>
          </tr>
        `;
      },
    });

    function showToast(msg, type = 'info') {
      const container = document.getElementById('toast-container');
      const toast = document.createElement('div');
      toast.className = `toast toast--${type}`;
      toast.innerHTML = `<span>${DOMPurify.sanitize(msg)}</span><button class="toast__close" onclick="this.parentElement.remove()">x</button>`;
      container.appendChild(toast);
      setTimeout(() => { if (toast.parentNode) toast.remove(); }, 5000);
    }

    function showList() {
      document.getElementById('list-view').style.display = 'block';
      document.getElementById('form-view').style.display = 'none';
    }

    function showForm(mode, barangayId = null) {
      document.getElementById('list-view').style.display = 'none';
      document.getElementById('form-view').style.display = 'block';
      document.getElementById('form-title').textContent = mode === 'edit' ? 'Edit Barangay' : 'Add Barangay';
      resetForm();

      if (mode === 'edit' && barangayId !== null) {
        const barangay = barangayById.get(String(barangayId));
        if (!barangay) {
          showToast('Barangay not found', 'error');
          showList();
          return;
        }
        fillForm(barangay);
      }
    }

    function fillForm(barangay) {
      document.getElementById('form-barangay-id').value = barangay.id || '';
      document.getElementById('form-name').value = barangay.name || '';
      document.getElementById('form-pcode').value = barangay.pcode || '';
      document.getElementById('form-flood-risk').value = barangay.flood_risk_level || '';
      document.getElementById('form-land-use').value = barangay.land_use || '';
      document.getElementById('form-population').value = barangay.population || '';
      document.getElementById('form-density').value = barangay.population_density || '';
      document.getElementById('form-area').value = barangay.area_sqkm || '';
      document.getElementById('form-description').value = barangay.description || '';

      document.getElementById('dependency-context').style.display = '';
      document.getElementById('dependency-centers').textContent = `${parseInt(barangay.center_count || 0, 10)} centers`;
      document.getElementById('dependency-zones').textContent = `${parseInt(barangay.flood_zone_count || 0, 10)} flood zones`;
    }

    function resetForm() {
      document.getElementById('barangay-form').reset();
      document.getElementById('form-barangay-id').value = '';
      document.getElementById('dependency-context').style.display = 'none';
    }

    document.getElementById('barangay-form').addEventListener('submit', async (e) => {
      e.preventDefault();

      const submitBtn = document.getElementById('form-submit');
      submitBtn.classList.add('btn--loading');
      submitBtn.textContent = 'Saving...';

      const barangayId = document.getElementById('form-barangay-id').value;
      const isEdit = barangayId !== '';
      const body = {
        name: document.getElementById('form-name').value.trim(),
        pcode: document.getElementById('form-pcode').value.trim(),
        population: document.getElementById('form-population').value,
        population_density: document.getElementById('form-density').value,
        area_sqkm: document.getElementById('form-area').value,
        flood_risk_level: document.getElementById('form-flood-risk').value,
        land_use: document.getElementById('form-land-use').value.trim(),
        description: document.getElementById('form-description').value.trim(),
      };

      if (!body.name) {
        showToast('Name is required', 'error');
        resetSubmitBtn();
        return;
      }

      try {
        const url = isEdit ? `../api/admin/barangays.php?id=${barangayId}` : '../api/admin/barangays.php';
        const response = await fetch(url, {
          method: isEdit ? 'PUT' : 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body),
        });
        const result = await response.json();

        if (result.success) {
          showToast(isEdit ? 'Barangay updated!' : 'Barangay created!', 'success');
          setTimeout(() => window.location.href = 'barangays.php', 800);
        } else {
          showToast(result.error || 'Failed to save barangay', 'error');
        }
      } catch (error) {
        showToast('Network error', 'error');
      }

      resetSubmitBtn();
    });

    function resetSubmitBtn() {
      const btn = document.getElementById('form-submit');
      btn.classList.remove('btn--loading');
      btn.textContent = 'Save Barangay';
    }

    async function deleteBarangay(barangayId) {
      const barangay = barangayById.get(String(barangayId));
      const name = barangay ? barangay.name : 'this barangay';
      if (!confirm(`Delete "${name}"? This action cannot be undone.`)) { return; }

      try {
        const response = await fetch(`../api/admin/barangays.php?id=${barangayId}`, { method: 'DELETE' });
        const result = await response.json();

        if (result.success) {
          showToast('Barangay deleted', 'success');
          const row = document.getElementById(`row-${barangayId}`);
          if (row) row.remove();
          setTimeout(() => window.location.reload(), 600);
        } else {
          showToast(result.error || 'Delete failed', 'error');
        }
      } catch (error) {
        showToast('Network error', 'error');
      }
    }

    document.getElementById('admin-logout-btn').addEventListener('click', async () => {
      await fetch('../api/auth.php?action=logout', { method: 'POST' });
      window.location.href = 'login.php';
    });
  </script>
</body>
</html>
