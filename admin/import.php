<?php
/**
 * CabEvac — Admin Data Import Page
 *
 * Upload and import GeoJSON or CSV files for
 * evacuation centers or GIS layers.
 *
 * @package CabEvac
 */

declare(strict_types=1);

$pageTitle = 'Data Import';

require_once __DIR__ . '/layout.php';
?>

    <!-- Header -->
    <div class="admin-content__header">
      <h1 class="admin-content__title">Data Import</h1>
    </div>

    <!-- Import Form -->
    <div class="data-table-wrapper" style="padding: var(--space-8);">
      <form id="import-form" enctype="multipart/form-data">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6); margin-bottom: var(--space-4);">
          <div class="form-group">
            <label for="import-type">Import Type</label>
            <select class="input select" id="import-type" name="import_type" required>
              <option value="evacuation_centers">Evacuation Centers</option>
              <option value="gis_layer">GIS Layer</option>
              <option value="flood_hazard">Flood Hazard Zone</option>
            </select>
          </div>

          <div class="form-group" id="layer-type-group" style="display:none;">
            <label for="layer-type">Layer Type</label>
            <select class="input select" id="layer-type" name="layer_type">
              <option value="buffer">Buffer</option>
              <option value="isochrone">Isochrone</option>
              <option value="road">Road</option>
              <option value="boundary">Boundary</option>
              <option value="network_analysis">Network Analysis</option>
              <option value="population_density">Population Density</option>
              <option value="travel_routes">Travel Routes</option>
              <option value="coverage">Coverage</option>
            </select>
          </div>

          <div class="form-group" id="risk-level-group" style="display:none;">
            <label for="risk-level">Risk Level</label>
            <select class="input select" id="risk-level" name="risk_level">
              <option value="very_high">Very High</option>
              <option value="high">High</option>
              <option value="moderate">Moderate</option>
            </select>
          </div>
        </div>

        <!-- Format guidance hint -->        
        <div id="import-hint-ec" style="margin-bottom:var(--space-5); padding: var(--space-3) var(--space-4); background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.25); border-radius: var(--radius-md); font-size: var(--font-size-xs); color: var(--color-text-secondary); line-height: 1.6;">
          <strong style="color:var(--color-text-primary);">Expected file:</strong> <code>evacuation centers.geojson</code> — a GeoJSON FeatureCollection of <strong>Point</strong> features.<br>
          Each feature must have these properties: <code>Facility Name</code>, <code>Barangay</code>, <code>Latitude</code>, <code>Longitude</code>.
        </div>
        <div id="import-hint-gis" style="display:none; margin-bottom:var(--space-5); padding: var(--space-3) var(--space-4); background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.25); border-radius: var(--radius-md); font-size: var(--font-size-xs); color: var(--color-text-secondary); line-height: 1.6;">
          <strong style="color:var(--color-text-primary);">Expected file:</strong> Any GeoJSON file (Polygon, MultiPolygon, LineString, or Point features).<br>
          The individual school/barangay <code>.geojson</code> files (e.g. <em>araullo university.geojson</em>) should be imported here.
        </div>
        <div id="import-hint-flood" style="display:none; margin-bottom:var(--space-5); padding: var(--space-3) var(--space-4); background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); border-radius: var(--radius-md); font-size: var(--font-size-xs); color: var(--color-text-secondary); line-height: 1.6;">
          <strong style="color:var(--color-text-primary);">Expected file:</strong> GeoJSON with <strong>MultiPolygon</strong> features per barangay.<br>
          Each feature must have <code>ADM4_EN</code> (barangay name) and optionally <code>AREA_SQKM</code> properties. Select the matching risk level before importing.
        </div>

        <div class="upload-zone" id="import-upload-zone">
          <div class="upload-zone__icon">📁</div>
          <div class="upload-zone__text">Drop files here or click to browse</div>
          <div style="font-size: var(--font-size-xs); color: var(--color-text-tertiary); margin-top: 4px;">
            .geojson, .json, or .csv &nbsp;·&nbsp; Multiple files supported
          </div>
          <input type="file" id="import-file-input" name="file" accept=".geojson,.json,.csv" multiple style="display:none;">
        </div>

        <div id="import-file-list" style="display: none; margin-top: var(--space-4); display: none; flex-direction: column; gap: var(--space-2);"></div>

        <div style="display: flex; align-items: center; gap: var(--space-4); margin-top: var(--space-6);">
          <button type="submit" class="btn btn--primary" id="import-submit" disabled>
            Import Data
          </button>
          <span id="import-progress-label" style="font-size: var(--font-size-xs); color: var(--color-text-tertiary); display: none;"></span>
        </div>
      </form>

      <!-- Import Results -->
      <div id="import-results" style="display: none; margin-top: var(--space-6); padding: var(--space-4); border-radius: var(--radius-md);">
        <p id="import-results-text" style="font-weight: 600; margin: 0 0 4px;"></p>
        <p id="import-results-hint" style="font-size: var(--font-size-xs); margin: 0; opacity: 0.85;"></p>
      </div>
    </div>

  </div><!-- /.admin-content -->

  <div class="toast-container" id="toast-container" aria-live="polite"></div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
  <script>
    'use strict';

    function showToast(msg, type = 'info') {
      const container = document.getElementById('toast-container');
      const toast = document.createElement('div');
      toast.className = `toast toast--${type}`;
      toast.innerHTML = `<span>${DOMPurify.sanitize(msg)}</span><button class="toast__close" onclick="this.parentElement.remove()">✕</button>`;
      container.appendChild(toast);
      setTimeout(() => { if (toast.parentNode) toast.remove(); }, 5000);
    }

    // Toggle layer type visibility + hints
    const importTypeSelect = document.getElementById('import-type');
    const layerTypeGroup   = document.getElementById('layer-type-group');
    const hintEc           = document.getElementById('import-hint-ec');
    const hintGis          = document.getElementById('import-hint-gis');

    function syncImportType() {
      const val = importTypeSelect.value;
      const isGis   = val === 'gis_layer';
      const isFlood = val === 'flood_hazard';
      layerTypeGroup.style.display  = isGis   ? 'flex'  : 'none';
      document.getElementById('risk-level-group').style.display = isFlood ? 'flex' : 'none';
      hintEc.style.display    = (!isGis && !isFlood) ? 'block' : 'none';
      hintGis.style.display   = isGis   ? 'block' : 'none';
      document.getElementById('import-hint-flood').style.display = isFlood ? 'block' : 'none';
      document.getElementById('import-results').style.display = 'none';
    }

    importTypeSelect.addEventListener('change', syncImportType);

    // File upload zone
    const uploadZone = document.getElementById('import-upload-zone');
    const fileInput  = document.getElementById('import-file-input');
    const submitBtn  = document.getElementById('import-submit');
    const fileList   = document.getElementById('import-file-list');

    /** @type {File[]} */
    let selectedFiles = [];

    uploadZone.addEventListener('click', () => fileInput.click());
    uploadZone.addEventListener('dragover', (e) => { e.preventDefault(); uploadZone.classList.add('dragover'); });
    uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
    uploadZone.addEventListener('drop', (e) => {
      e.preventDefault();
      uploadZone.classList.remove('dragover');
      selectedFiles = Array.from(e.dataTransfer.files).filter(f => /\.(geojson|json|csv)$/i.test(f.name));
      renderFileList();
    });

    fileInput.addEventListener('change', () => {
      selectedFiles = Array.from(fileInput.files);
      renderFileList();
    });

    function renderFileList() {
      if (selectedFiles.length === 0) {
        fileList.style.display = 'none';
        submitBtn.disabled = true;
        return;
      }
      fileList.style.display = 'flex';
      fileList.innerHTML = selectedFiles.map((f, i) =>
        `<div id="file-row-${i}" style="display:flex; justify-content:space-between; align-items:center; padding:var(--space-2) var(--space-3); background:var(--color-bg-primary); border-radius:var(--radius-sm);">
          <span style="font-size:var(--font-size-sm); font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:60%;">${DOMPurify.sanitize(f.name)}</span>
          <span style="font-size:var(--font-size-xs); color:var(--color-text-tertiary); flex-shrink:0;">${(f.size / 1024).toFixed(1)} KB</span>
          <span id="file-status-${i}" style="font-size:var(--font-size-xs); flex-shrink:0; margin-left:var(--space-3);">Pending</span>
        </div>`
      ).join('');
      submitBtn.disabled = false;
      document.getElementById('import-results').style.display = 'none';
    }

    // Form submit — sequential multi-file import
    document.getElementById('import-form').addEventListener('submit', async (e) => {
      e.preventDefault();

      if (selectedFiles.length === 0) { return; }

      submitBtn.classList.add('btn--loading');
      submitBtn.textContent = 'Importing...';
      submitBtn.disabled = true;

      const progressLabel = document.getElementById('import-progress-label');
      progressLabel.style.display = 'inline';

      const importType = document.getElementById('import-type').value;
      const layerType  = document.getElementById('layer-type')?.value ?? '';

      let successCount = 0;
      let failCount = 0;

      for (let i = 0; i < selectedFiles.length; i++) {
        const file = selectedFiles[i];
        const statusEl = document.getElementById(`file-status-${i}`);
        progressLabel.textContent = `File ${i + 1} of ${selectedFiles.length}…`;

        if (statusEl) {
          statusEl.textContent = '⏳ Uploading…';
          statusEl.style.color = 'var(--color-text-tertiary)';
        }

        const riskLevel = document.getElementById('risk-level')?.value ?? '';
        const formData = new FormData();
        formData.append('import_type', importType);
        formData.append('layer_type', layerType);
        formData.append('risk_level', riskLevel);
        formData.append('file', file, file.name);

        try {
          const response = await fetch('../api/admin/import.php', { method: 'POST', body: formData });
          const result = await response.json();

          if (result.success) {
            successCount++;
            if (statusEl) { statusEl.textContent = '✓ Done'; statusEl.style.color = 'var(--color-status-active)'; }
          } else {
            failCount++;
            const msg = result.error || 'Failed';
            if (statusEl) { statusEl.textContent = `✕ ${msg}`; statusEl.style.color = '#ef4444'; }
          }
        } catch {
          failCount++;
          if (statusEl) { statusEl.textContent = '✕ Network error'; statusEl.style.color = '#ef4444'; }
        }
      }

      progressLabel.style.display = 'none';

      const resultsDiv  = document.getElementById('import-results');
      const resultsText = document.getElementById('import-results-text');
      const resultsHint = document.getElementById('import-results-hint');

      if (failCount === 0) {
        resultsDiv.style.cssText = 'display:block; margin-top:var(--space-6); padding:var(--space-4); border-radius:var(--radius-md); background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3);';
        resultsText.style.color = 'var(--color-status-active)';
        resultsText.textContent = `✓ Successfully imported ${successCount} file(s)`;
        resultsHint.textContent = '';
        showToast(`Imported ${successCount} file(s)`, 'success');
      } else {
        resultsDiv.style.cssText = 'display:block; margin-top:var(--space-6); padding:var(--space-4); border-radius:var(--radius-md); background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.3);';
        resultsText.style.color = '#ef4444';
        resultsText.textContent = `${successCount} succeeded · ${failCount} failed`;
        resultsHint.style.color = '#b91c1c';
        resultsHint.textContent = 'Check the file rows above for details.';
        showToast(`${failCount} file(s) failed`, 'error');
      }

      submitBtn.classList.remove('btn--loading');
      submitBtn.textContent = 'Import Data';
      submitBtn.disabled = false;
    });

    // Logout
    document.getElementById('admin-logout-btn').addEventListener('click', async () => {
      await fetch('../api/auth.php?action=logout', { method: 'POST' });
      window.location.href = 'login.php';
    });
  </script>
</body>
</html>
