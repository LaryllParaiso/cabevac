<?php
/**
 * CabEvac — Admin Evacuation Centers Management
 * 
 * List, add, edit, delete evacuation centers with
 * interactive map pin placement and image upload.
 * 
 * @package CabEvac
 */

declare(strict_types=1);

$pageTitle = 'Evacuation Centers';

require_once __DIR__ . '/../includes/db.php';

$db = getDb();
$action = $_GET['action'] ?? 'list';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Fetch all centers for list view
$centersStmt = $db->query(
    'SELECT ec.id, ec.name, ec.status, ec.capacity, ec.latitude, ec.longitude,
            b.name AS barangay
     FROM evacuation_centers ec
     JOIN barangays b ON ec.barangay_id = b.id
     ORDER BY ec.name ASC'
);
$allCenters = $centersStmt->fetchAll();

// Fetch barangays for dropdown
$barangays = $db->query('SELECT id, name FROM barangays ORDER BY name ASC')->fetchAll();

// Fetch edit data if editing
$editData = null;
$editImages = [];
if ($action === 'edit' && $editId !== null) {
    $editStmt = $db->prepare(
        'SELECT ec.*, b.name AS barangay
         FROM evacuation_centers ec
         JOIN barangays b ON ec.barangay_id = b.id
         WHERE ec.id = ? LIMIT 1'
    );
    $editStmt->execute([$editId]);
    $editData = $editStmt->fetch();

    if ($editData !== false) {
        $imgStmt = $db->prepare(
            'SELECT id, image_path, alt_text FROM evacuation_center_images
             WHERE evacuation_center_id = ? ORDER BY sort_order'
        );
        $imgStmt->execute([$editId]);
        $editImages = $imgStmt->fetchAll();
    }
}

require_once __DIR__ . '/layout.php';
?>

    <!-- Header -->
    <div class="admin-content__header">
      <h1 class="admin-content__title">Evacuation Centers</h1>
      <button class="btn btn--primary" id="add-center-btn" onclick="showForm('add')">
        + Add New Center
      </button>
    </div>

    <!-- LIST VIEW -->
    <div id="list-view">
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
            <?php foreach ($allCenters as $center): ?>
              <tr id="row-<?= (int)$center['id'] ?>">
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
                    <button class="btn btn--ghost" style="font-size:0.75rem;" onclick="showForm('edit', <?= (int)$center['id'] ?>)">Edit</button>
                    <button class="btn btn--ghost" style="font-size:0.75rem; color:var(--color-status-error);" onclick="deleteCenter(<?= (int)$center['id'] ?>, '<?= htmlspecialchars($center['name'], ENT_QUOTES, 'UTF-8') ?>')">Delete</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($allCenters)): ?>
              <tr><td colspan="5" style="text-align:center; padding: var(--space-8); color: var(--color-text-tertiary);">No evacuation centers found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- FORM VIEW -->
    <div id="form-view" style="display: none;">
      <div style="margin-bottom: var(--space-6);">
        <button class="btn btn--ghost" onclick="showList()">← Back to List</button>
      </div>
      <h2 id="form-title" style="font-size: var(--font-size-lg); font-weight: 700; margin-bottom: var(--space-6);">Add Evacuation Center</h2>

      <form id="center-form" novalidate>
        <input type="hidden" id="form-center-id" value="">

        <div class="admin-form-grid">
          <!-- Left Column: Form Fields -->
          <div class="form-column">
            <div class="form-group">
              <label for="form-name">Location Name *</label>
              <input type="text" class="input" id="form-name" placeholder="Enter facility name" required>
            </div>

            <div class="form-group">
              <label for="form-barangay">Barangay *</label>
              <select class="input select" id="form-barangay" required>
                <option value="">Select barangay</option>
                <?php foreach ($barangays as $brgy): ?>
                  <option value="<?= (int)$brgy['id'] ?>"><?= htmlspecialchars($brgy['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Barangay metadata fields -->
            <div class="form-group" id="barangay-meta-fields" style="display: none;">
              <label for="form-barangay-land-use">Barangay Land Use</label>
              <input type="text" class="input" id="form-barangay-land-use" placeholder="e.g. Residential, Agricultural">
            </div>

            <div class="form-group" id="barangay-desc-field" style="display: none;">
              <label for="form-barangay-description">Barangay Description</label>
              <textarea class="input textarea" id="form-barangay-description" placeholder="Description of the barangay..." rows="3"></textarea>
            </div>

            <div class="form-group">
              <label for="form-description">Evacuation Center Description</label>
              <textarea class="input textarea" id="form-description" placeholder="Describe the evacuation center..." rows="3"></textarea>
            </div>

            <div class="form-group">
              <label for="form-capacity">Capacity (persons)</label>
              <input type="number" class="input" id="form-capacity" placeholder="e.g. 500" min="0">
            </div>

            <div class="form-group">
              <label>Status</label>
              <div style="display: flex; gap: var(--space-4); align-items: center;">
                <label style="display:flex; align-items:center; gap: var(--space-2); cursor:pointer;">
                  <input type="radio" name="form-status" value="active" checked> Active
                </label>
                <label style="display:flex; align-items:center; gap: var(--space-2); cursor:pointer;">
                  <input type="radio" name="form-status" value="inactive"> Inactive
                </label>
              </div>
            </div>
          </div>

          <!-- Right Column: Map + Coordinates -->
          <div class="form-column">
            <div class="form-group">
              <label>📍 Click map to set coordinates</label>
              <div id="form-map" class="admin-map-preview" style="cursor: crosshair;"></div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
              <div class="form-group">
                <label for="form-latitude">Latitude *</label>
                <input type="number" class="input" id="form-latitude" step="any" placeholder="15.4953" required>
              </div>
              <div class="form-group">
                <label for="form-longitude">Longitude *</label>
                <input type="number" class="input" id="form-longitude" step="any" placeholder="120.9747" required>
              </div>
            </div>
          </div>
        </div>

        <!-- Image Upload -->
        <div style="margin-top: var(--space-6);">
          <label style="display:block; margin-bottom: var(--space-2); font-weight: 600; font-size: var(--font-size-sm); color: var(--color-text-secondary);">Images</label>
          <div class="upload-zone" id="upload-zone">
            <div class="upload-zone__icon">📤</div>
            <div class="upload-zone__text">Drop images here or click to browse</div>
            <div style="font-size: var(--font-size-xs); color: var(--color-text-tertiary); margin-top: 4px;">JPEG, PNG, WebP — max 5MB</div>
            <input type="file" id="file-input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none;">
          </div>
          <div class="upload-previews" id="upload-previews"></div>
        </div>

        <!-- Submit -->
        <div style="display: flex; gap: var(--space-4); margin-top: var(--space-8); justify-content: flex-end;">
          <button type="button" class="btn btn--secondary" onclick="showList()">Cancel</button>
          <button type="submit" class="btn btn--primary" id="form-submit">Save Center</button>
        </div>
      </form>
    </div>

  </div><!-- /.admin-content -->

  <div class="toast-container" id="toast-container" aria-live="polite"></div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
  <script>
    'use strict';

    /**
     * Ray-casting algorithm for Point-in-Polygon
     * @param {number[]} point [lng, lat]
     * @param {number[][]} vs Array of [lng, lat] coordinates
     * @returns {boolean}
     */
    function isPointInPolygon(point, vs) {
      const x = point[0], y = point[1];
      let inside = false;
      for (let i = 0, j = vs.length - 1; i < vs.length; j = i++) {
        const xi = vs[i][0], yi = vs[i][1];
        const xj = vs[j][0], yj = vs[j][1];
        const intersect = ((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
      }
      return inside;
    }

    /**
     * Checks if a point is within a GeoJSON Feature (Polygon or MultiPolygon)
     * @param {number} lng
     * @param {number} lat
     * @param {Object} feature GeoJSON Feature
     * @returns {boolean}
     */
    function pointInFeature(lng, lat, feature) {
      if (!feature || !feature.geometry) return false;
      const geom = feature.geometry;
      const pt = [lng, lat];
      
      if (geom.type === 'Polygon') {
        return isPointInPolygon(pt, geom.coordinates[0]);
      } else if (geom.type === 'MultiPolygon') {
        return geom.coordinates.some(poly => isPointInPolygon(pt, poly[0]));
      }
      return false;
    }

    /** @type {L.Map|null} */
    let formMap = null;
    /** @type {L.Marker|null} */
    let formMarker = null;
    /** @type {string|null} Current mode */
    let currentMode = 'list';
    
    /** @type {Array} Array of GeoJSON features for barangays */
    let barangayFeatures = [];
    /** @type {L.GeoJSON|null} Highlight layer for selected barangay */
    let highlightLayer = null;

    // Fetch barangay geometries on load
    fetch('../api/layers.php?slug=cabanatuan-boundary')
      .then(res => res.json())
      .then(result => {
        if (result.success && result.data && result.data.geojson_data) {
          barangayFeatures = result.data.geojson_data.features || [];
        }
      })
      .catch(err => console.error('Failed to load barangay bounds', err));

    const editData = <?= json_encode($editData ?: null) ?>;
    const editImages = <?= json_encode($editImages) ?>;

    // ============================================
    // SIMPLE TOAST
    // ============================================
    function showToast(msg, type = 'info') {
      const container = document.getElementById('toast-container');
      const toast = document.createElement('div');
      toast.className = `toast toast--${type}`;
      toast.innerHTML = `<span>${DOMPurify.sanitize(msg)}</span><button class="toast__close" onclick="this.parentElement.remove()">✕</button>`;
      container.appendChild(toast);
      setTimeout(() => { if (toast.parentNode) toast.remove(); }, 5000);
    }

    // ============================================
    // VIEW SWITCHING
    // ============================================
    function showList() {
      document.getElementById('list-view').style.display = 'block';
      document.getElementById('form-view').style.display = 'none';
      currentMode = 'list';
    }

    function showForm(mode, centerId = null) {
      document.getElementById('list-view').style.display = 'none';
      document.getElementById('form-view').style.display = 'block';
      currentMode = mode;

      const title = document.getElementById('form-title');
      title.textContent = mode === 'edit' ? 'Edit Evacuation Center' : 'Add Evacuation Center';

      resetForm();

      if (mode === 'edit' && centerId) {
        loadCenterForEdit(centerId);
      }

      initFormMap();
    }

    // ============================================
    // FORM MAP
    // ============================================
    function initFormMap() {
      const container = document.getElementById('form-map');
      if (formMap) { formMap.remove(); formMap = null; }

      formMap = L.map(container, {
        center: [15.486, 120.965],
        zoom: 13,
        zoomControl: true,
      });

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
      }).addTo(formMap);

      formMap.on('click', (e) => {
        setMapPin(e.latlng.lat, e.latlng.lng);
      });

      // Set pin from existing inputs
      const lat = parseFloat(document.getElementById('form-latitude').value);
      const lng = parseFloat(document.getElementById('form-longitude').value);
      if (!isNaN(lat) && !isNaN(lng)) {
        setMapPin(lat, lng);
        formMap.setView([lat, lng], 16);
      }

      setTimeout(() => formMap.invalidateSize(), 100);
    }

    function setMapPin(lat, lng) {
      document.getElementById('form-latitude').value = lat.toFixed(8);
      document.getElementById('form-longitude').value = lng.toFixed(8);

      const emojiIcon = L.divIcon({
        html: '<div style="font-size: 24px; transform: translate(-2px, -20px);">📍</div>',
        className: 'custom-emoji-icon',
        iconSize: [24, 24],
        iconAnchor: [12, 12]
      });

      if (formMarker) {
        formMarker.setLatLng([lat, lng]);
      } else {
        formMarker = L.marker([lat, lng], { 
          draggable: true,
          icon: emojiIcon
        }).addTo(formMap);
        
        formMarker.on('dragend', (e) => {
          const pos = e.target.getLatLng();
          document.getElementById('form-latitude').value = pos.lat.toFixed(8);
          document.getElementById('form-longitude').value = pos.lng.toFixed(8);
          syncDropdownToMap(pos.lat, pos.lng);
        });
      }
      
      syncDropdownToMap(lat, lng);
    }
    
    // Map -> Dropdown Auto-Sync
    async function syncDropdownToMap(lat, lng) {
      if (!barangayFeatures || barangayFeatures.length === 0) return;
      
      try {
        for (const feature of barangayFeatures) {
          if (pointInFeature(lng, lat, feature)) {
            const props = feature.properties || {};
            const brgyName = (props.ADM4_EN || props.adm4_en || '').toLowerCase();
            const select = document.getElementById('form-barangay');
            
            for (let i = 0; i < select.options.length; i++) {
              if (select.options[i].text.toLowerCase() === brgyName) {
                if (select.selectedIndex !== i) {
                  select.selectedIndex = i;
                }
                highlightBarangay(feature);
                // Also load barangay metadata
                const brgyId = select.value;
                if (brgyId) {
                  document.getElementById('barangay-meta-fields').style.display = '';
                  document.getElementById('barangay-desc-field').style.display = '';
                  await fetchBarangayData(brgyId);
                }
                return;
              }
            }
          }
        }
      } catch (err) {
        console.error('Sync error:', err);
      }
    }

    function highlightBarangay(feature) {
      if (!formMap) return;
      if (highlightLayer) {
        formMap.removeLayer(highlightLayer);
      }
      
      highlightLayer = L.geoJSON(feature, {
        style: {
          color: '#3b82f6',
          weight: 2,
          fillColor: '#3b82f6',
          fillOpacity: 0.1,
          dashArray: '5, 5'
        }
      }).addTo(formMap);
    }

    // Coordinate input sync
    ['form-latitude', 'form-longitude'].forEach((fieldId) => {
      document.getElementById(fieldId).addEventListener('change', () => {
        const lat = parseFloat(document.getElementById('form-latitude').value);
        const lng = parseFloat(document.getElementById('form-longitude').value);
        if (!isNaN(lat) && !isNaN(lng) && formMap) {
          setMapPin(lat, lng);
          formMap.setView([lat, lng], 16);
        }
      });
    });

    // Dropdown -> Map Auto-Sync & Barangay Metadata
    document.getElementById('form-barangay').addEventListener('change', async (e) => {
      const brgyId = e.target.value;
      const brgyName = e.target.options[e.target.selectedIndex].text.toLowerCase();

      // Show/hide barangay metadata fields
      const metaFields = document.getElementById('barangay-meta-fields');
      const descField = document.getElementById('barangay-desc-field');

      if (brgyId) {
        metaFields.style.display = '';
        descField.style.display = '';
        await fetchBarangayData(brgyId);
      } else {
        metaFields.style.display = 'none';
        descField.style.display = 'none';
        document.getElementById('form-barangay-land-use').value = '';
        document.getElementById('form-barangay-description').value = '';
      }

      if (!brgyName || !barangayFeatures || barangayFeatures.length === 0) return;

      const feature = barangayFeatures.find(f => {
        const props = f.properties || {};
        return (props.ADM4_EN || props.adm4_en || '').toLowerCase() === brgyName;
      });

      if (feature && formMap) {
        const bounds = L.geoJSON(feature).getBounds();
        formMap.fitBounds(bounds, { padding: [20, 20], maxZoom: 16 });
        highlightBarangay(feature);
      }
    });

    // Fetch barangay data including description and land_use
    async function fetchBarangayData(barangayId) {
      try {
        const response = await fetch(`../api/barangays.php?id=${barangayId}`);
        const result = await response.json();
        if (result.success && result.data) {
          const brgy = result.data;
          document.getElementById('form-barangay-land-use').value = brgy.land_use || '';
          document.getElementById('form-barangay-description').value = brgy.description || '';
        }
      } catch (err) {
        console.error('Failed to load barangay data:', err);
      }
    }

    // ============================================
    // LOAD CENTER FOR EDIT
    // ============================================
    async function loadCenterForEdit(centerId) {
      try {
        const response = await fetch(`../api/centers.php?id=${centerId}`);
        const result = await response.json();
        if (!result.success) { showToast('Failed to load center', 'error'); return; }

        const center = result.data;
        document.getElementById('form-center-id').value = center.id;
        document.getElementById('form-name').value = center.name || '';
        document.getElementById('form-barangay').value = center.barangay_id || '';
        document.getElementById('form-description').value = center.description || '';
        document.getElementById('form-capacity').value = center.capacity || '';
        document.getElementById('form-latitude').value = center.latitude || '';
        document.getElementById('form-longitude').value = center.longitude || '';

        const statusRadio = document.querySelector(`input[name="form-status"][value="${center.status}"]`);
        if (statusRadio) statusRadio.checked = true;

        // Fetch barangay data if barangay_id exists
        if (center.barangay_id) {
          document.getElementById('barangay-meta-fields').style.display = '';
          document.getElementById('barangay-desc-field').style.display = '';
          await fetchBarangayData(center.barangay_id);
        }

        // Load existing images
        const previews = document.getElementById('upload-previews');
        (center.images || []).forEach((img) => {
          const div = document.createElement('div');
          div.className = 'upload-preview';
          div.innerHTML = `
            <img src="../${DOMPurify.sanitize(img.image_path)}" alt="${DOMPurify.sanitize(img.alt_text || '')}">
            <div class="upload-preview__name" style="font-size: 10px; text-align: center; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%;" title="${DOMPurify.sanitize(img.original_filename || '')}">${DOMPurify.sanitize(img.original_filename || '')}</div>
            <button type="button" class="upload-preview__remove" onclick="deleteImage(${img.id}, this)">✕</button>
          `;
          previews.appendChild(div);
        });

        // Re-init map after a delay to ensure container is visible
        setTimeout(() => {
          if (formMap) {
            formMap.invalidateSize();
            const lat = parseFloat(center.latitude);
            const lng = parseFloat(center.longitude);
            if (!isNaN(lat) && !isNaN(lng)) {
              setMapPin(lat, lng);
              formMap.setView([lat, lng], 16);
            }
          }
        }, 200);

      } catch (error) {
        showToast('Network error loading center', 'error');
      }
    }

    // ============================================
    // FORM SUBMIT
    // ============================================
    document.getElementById('center-form').addEventListener('submit', async (e) => {
      e.preventDefault();

      const submitBtn = document.getElementById('form-submit');
      submitBtn.classList.add('btn--loading');
      submitBtn.textContent = 'Saving...';

      const centerId = document.getElementById('form-center-id').value;
      const isEdit = centerId !== '';

      const status = document.querySelector('input[name="form-status"]:checked')?.value || 'active';

      const body = {
        name: document.getElementById('form-name').value.trim(),
        barangay_id: parseInt(document.getElementById('form-barangay').value, 10),
        description: document.getElementById('form-description').value.trim(),
        capacity: document.getElementById('form-capacity').value,
        latitude: parseFloat(document.getElementById('form-latitude').value),
        longitude: parseFloat(document.getElementById('form-longitude').value),
        status: status,
        category_id: 1,
        // Barangay metadata
        barangay_land_use: document.getElementById('form-barangay-land-use').value.trim(),
        barangay_description: document.getElementById('form-barangay-description').value.trim(),
      };

      // Validate
      if (!body.name) { showToast('Name is required', 'error'); resetSubmitBtn(); return; }
      if (isNaN(body.latitude) || isNaN(body.longitude)) { showToast('Valid coordinates are required', 'error'); resetSubmitBtn(); return; }
      if (isNaN(body.barangay_id)) { showToast('Barangay is required', 'error'); resetSubmitBtn(); return; }

      try {
        const url = isEdit
          ? `../api/admin/centers.php?id=${centerId}`
          : '../api/admin/centers.php';

        const response = await fetch(url, {
          method: isEdit ? 'PUT' : 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body),
        });

        const result = await response.json();

        if (result.success) {
          showToast(isEdit ? 'Center updated!' : 'Center created!', 'success');
          const savedId = isEdit ? centerId : (result.data?.id || '');
          setTimeout(() => {
            window.location.href = savedId ? `centers.php?action=edit&id=${savedId}` : 'centers.php';
          }, 1000);
        } else {
          showToast(result.error || 'Failed to save', 'error');
        }
      } catch (error) {
        showToast('Network error', 'error');
      }

      resetSubmitBtn();
    });

    function resetSubmitBtn() {
      const btn = document.getElementById('form-submit');
      btn.classList.remove('btn--loading');
      btn.textContent = 'Save Center';
    }

    // ============================================
    // IMAGE UPLOAD
    // ============================================
    const uploadZone = document.getElementById('upload-zone');
    const fileInput = document.getElementById('file-input');

    uploadZone.addEventListener('click', () => fileInput.click());
    uploadZone.addEventListener('dragover', (e) => { e.preventDefault(); uploadZone.classList.add('dragover'); });
    uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
    uploadZone.addEventListener('drop', (e) => {
      e.preventDefault();
      uploadZone.classList.remove('dragover');
      handleFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', () => {
      handleFiles(fileInput.files);
      fileInput.value = '';
    });

    async function handleFiles(files) {
      const centerId = document.getElementById('form-center-id').value;

      if (!centerId) {
        showToast('Save the center first before uploading images', 'warning');
        return;
      }

      for (const file of files) {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('evacuation_center_id', centerId);

        try {
          const response = await fetch('../api/admin/upload.php', {
            method: 'POST',
            body: formData,
          });

          const result = await response.json();

          if (result.success) {
            const previews = document.getElementById('upload-previews');
            const div = document.createElement('div');
            div.className = 'upload-preview';
            div.innerHTML = `
              <img src="../${DOMPurify.sanitize(result.data.image_path)}" alt="">
              <div class="upload-preview__name" style="font-size: 10px; text-align: center; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%;" title="${DOMPurify.sanitize(result.data.original_filename || '')}">${DOMPurify.sanitize(result.data.original_filename || '')}</div>
              <button type="button" class="upload-preview__remove" onclick="deleteImage(${result.data.id}, this)">✕</button>
            `;
            previews.appendChild(div);
            showToast('Image uploaded', 'success');
          } else {
            showToast(result.error || 'Upload failed', 'error');
          }
        } catch (error) {
          showToast('Upload failed: network error', 'error');
        }
      }
    }

    async function deleteImage(imageId, btnElement) {
      try {
        const res = await fetch(`../api/admin/upload.php?id=${imageId}`, { method: 'DELETE' });
        const data = await res.json();
        if (data.success) {
          btnElement.closest('.upload-preview').remove();
          showToast('Image deleted', 'success');
        } else {
          showToast(data.error || 'Delete failed', 'error');
        }
      } catch (error) {
        showToast('Delete failed', 'error');
      }
    }

    // ============================================
    // DELETE CENTER
    // ============================================
    async function deleteCenter(centerId, centerName) {
      if (!confirm(`Delete "${centerName}"? This action cannot be undone.`)) { return; }

      try {
        const response = await fetch(`../api/admin/centers.php?id=${centerId}`, { method: 'DELETE' });
        const result = await response.json();

        if (result.success) {
          showToast('Center deleted', 'success');
          const row = document.getElementById(`row-${centerId}`);
          if (row) row.remove();
        } else {
          showToast(result.error || 'Delete failed', 'error');
        }
      } catch (error) {
        showToast('Network error', 'error');
      }
    }

    function resetForm() {
      document.getElementById('center-form').reset();
      document.getElementById('form-center-id').value = '';
      document.getElementById('upload-previews').innerHTML = '';
      if (formMarker) { formMarker.remove(); formMarker = null; }
      // Hide barangay metadata fields
      document.getElementById('barangay-meta-fields').style.display = 'none';
      document.getElementById('barangay-desc-field').style.display = 'none';
      document.getElementById('form-barangay-land-use').value = '';
      document.getElementById('form-barangay-description').value = '';
    }

    // ============================================
    // LOGOUT
    // ============================================
    document.getElementById('admin-logout-btn').addEventListener('click', async () => {
      await fetch('../api/auth.php?action=logout', { method: 'POST' });
      window.location.href = 'login.php';
    });

    // Auto-show edit form if URL has action=edit
    <?php if ($action === 'edit' && $editId !== null): ?>
      showForm('edit', <?= $editId ?>);
    <?php endif; ?>
  </script>
</body>
</html>
