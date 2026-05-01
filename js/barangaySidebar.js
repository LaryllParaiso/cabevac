/**
 * CabEvac — Barangay Detail Sidebar
 *
 * Opens a slide-in sidebar showing details for a single barangay:
 * name, flood risk level, land use, description, plus optional
 * population / density / area stats from the GIS layer.
 *
 * Triggered by clicking a polygon in the city-boundary GeoJSON layer.
 *
 * @module barangaySidebar
 */

/** Risk-level → human label */
const RISK_LABELS = {
  low:        'Low',
  moderate:   'Moderate',
  high:       'High',
  very_high:  'Very High',
};

/** All risk modifier classes — used to clear before applying a new one */
const RISK_MODIFIERS = [
  'barangay-sidebar--risk-low',
  'barangay-sidebar--risk-moderate',
  'barangay-sidebar--risk-high',
  'barangay-sidebar--risk-very_high',
];

/** Mobile bottom-sheet state classes */
const BARANGAY_STATE_CLASSES = [
  'barangay-sidebar--state-peek',
  'barangay-sidebar--state-mid',
  'barangay-sidebar--state-full',
];

/** @type {'hidden'|'peek'|'mid'|'full'} Current sheet state on mobile */
let barangaySheetState = 'hidden';

/** Drag tracking for mobile handle */
const barangayDrag = { active: false, startY: 0 };

/**
 * @returns {boolean} true when viewport is mobile (≤768px).
 */
function isMobileViewportBarangay() {
  return typeof window !== 'undefined' && window.innerWidth <= 768;
}

/**
 * Apply one of the mobile state classes to the sidebar.
 *
 * @param {'peek'|'mid'|'full'} state
 */
function setBarangaySheetState(state) {
  const sidebar = document.getElementById('barangay-sidebar');
  if (sidebar === null) { return; }
  sidebar.classList.remove(...BARANGAY_STATE_CLASSES);
  sidebar.classList.add(`barangay-sidebar--state-${state}`);
  barangaySheetState = state;
}
const RISK_PILL_MODIFIERS = [
  'barangay-risk__pill--low',
  'barangay-risk__pill--moderate',
  'barangay-risk__pill--high',
  'barangay-risk__pill--very_high',
];

/**
 * Format a number with thousands separators, or em-dash when missing.
 *
 * @param  {number|string|null|undefined} v
 * @param  {string}                       suffix
 * @returns {string}
 */
function formatBarangayNumber(v, suffix = '') {
  if (v === null || v === undefined || v === '') { return '—'; }
  const n = Number(v);
  if (!Number.isFinite(n)) { return '—'; }
  return n.toLocaleString(undefined, { maximumFractionDigits: 2 }) + suffix;
}

/**
 * Fetch a single barangay by name and open the sidebar with its data.
 * Falls back to whatever properties the GeoJSON feature already carries
 * if the API request fails.
 *
 * @param {string} name              Barangay name from GeoJSON properties.
 * @param {Object} [featureProps={}] Original feature.properties for fallback values.
 */
async function openBarangaySidebarByName(name, featureProps = {}) {
  if (typeof name !== 'string' || name.trim() === '') { return; }

  // Close any open evacuation-center sidebar first
  if (typeof closeDetailSidebar === 'function') { closeDetailSidebar(); }

  let data = null;
  try {
    const res = await fetch(`api/barangays.php?name=${encodeURIComponent(name)}`);
    if (res.ok) {
      const body = await res.json();
      // Helper jsonResponse() wraps payloads as { success, data }
      data = (body && typeof body === 'object' && 'data' in body) ? body.data : body;
    }
  } catch (err) {
    console.warn('[barangaySidebar] fetch failed', err);
  }

  // Merge fallbacks from feature properties
  const merged = {
    name:               (data && data.name)               ?? name,
    flood_risk_level:   (data && data.flood_risk_level)   ?? null,
    land_use:           (data && data.land_use)           ?? null,
    description:        (data && data.description)        ?? null,
    population:         (data && data.population)         ?? featureProps.population         ?? null,
    population_density: (data && data.population_density) ?? featureProps['pop den']         ?? featureProps.density ?? null,
    area_sqkm:          (data && data.area_sqkm)          ?? featureProps.area_sqkm          ?? null,
  };

  openBarangaySidebar(merged);
}

/**
 * Render the barangay sidebar with the given data.
 *
 * @param {Object} b
 */
function openBarangaySidebar(b) {
  const sidebar = document.getElementById('barangay-sidebar');
  if (sidebar === null) { return; }

  // Name
  const nameEl = document.getElementById('barangay-name');
  if (nameEl !== null) { nameEl.textContent = b.name || '—'; }

  // Flood risk pill + hero gradient
  const pill = document.getElementById('barangay-risk-pill');
  sidebar.classList.remove(...RISK_MODIFIERS);
  if (pill !== null) { pill.classList.remove(...RISK_PILL_MODIFIERS); }

  const risk = b.flood_risk_level;
  if (pill !== null) {
    if (risk && RISK_LABELS[risk] !== undefined) {
      pill.textContent = RISK_LABELS[risk];
      pill.classList.add(`barangay-risk__pill--${risk}`);
      sidebar.classList.add(`barangay-sidebar--risk-${risk}`);
    } else {
      pill.textContent = 'Unknown';
    }
  }

  // Stats
  const popEl = document.getElementById('barangay-population');
  if (popEl !== null) { popEl.textContent = formatBarangayNumber(b.population); }

  const densityEl = document.getElementById('barangay-density');
  if (densityEl !== null) {
    densityEl.textContent = b.population_density
      ? formatBarangayNumber(b.population_density, ' /km²')
      : '—';
  }

  const areaEl = document.getElementById('barangay-area');
  if (areaEl !== null) {
    areaEl.textContent = b.area_sqkm
      ? formatBarangayNumber(b.area_sqkm, ' km²')
      : '—';
  }

  // Land use
  const landUseWrap = document.getElementById('barangay-landuse-wrapper');
  const landUseEl   = document.getElementById('barangay-landuse');
  if (landUseEl !== null && landUseWrap !== null) {
    if (b.land_use && String(b.land_use).trim() !== '') {
      landUseEl.textContent = b.land_use;
      landUseWrap.style.display = '';
    } else {
      landUseWrap.style.display = 'none';
    }
  }

  // Description
  const descWrap = document.getElementById('barangay-desc-wrapper');
  const descEl   = document.getElementById('barangay-description');
  if (descEl !== null && descWrap !== null) {
    if (b.description && String(b.description).trim() !== '') {
      descEl.textContent = b.description;
      descWrap.style.display = '';
    } else {
      descWrap.style.display = 'none';
    }
  }

  // Open
  sidebar.classList.add('open');
  sidebar.setAttribute('aria-hidden', 'false');

  // On mobile, start in peek state. On desktop, clear any leftover state class.
  if (isMobileViewportBarangay()) {
    setBarangaySheetState('peek');
  } else {
    sidebar.classList.remove(...BARANGAY_STATE_CLASSES);
    barangaySheetState = 'hidden';
  }

  if (typeof sidebar.focus === 'function') { sidebar.focus(); }

  // Defer adding the outside-click listener so the click that opened
  // the sidebar (a polygon click bubbling up to document) doesn't
  // immediately close it.
  document.removeEventListener('mousedown', _outsideBarangaySidebarClick, true);
  setTimeout(() => {
    document.addEventListener('mousedown', _outsideBarangaySidebarClick, true);
  }, 0);
}

/**
 * Outside-click guard. Closes the sidebar when the user clicks anywhere
 * outside it, EXCEPT on a Leaflet interactive path — those clicks belong
 * to barangay-polygon click handlers which will repopulate the sidebar.
 *
 * @param {MouseEvent} e
 */
function _outsideBarangaySidebarClick(e) {
  const sidebar = document.getElementById('barangay-sidebar');
  if (sidebar === null || !sidebar.classList.contains('open')) { return; }

  const target = e.target;
  if (target instanceof Node && sidebar.contains(target)) { return; }

  // Clicks on map polygons (boundary / flood layers) will swap content
  // via their own click handlers — do not close.
  if (target && typeof target.closest === 'function') {
    if (target.closest('.leaflet-interactive')) { return; }
  }

  closeBarangaySidebar();
}

/**
 * Close the barangay sidebar.
 */
function closeBarangaySidebar() {
  const sidebar = document.getElementById('barangay-sidebar');
  if (sidebar === null) { return; }
  sidebar.classList.remove('open');
  sidebar.classList.remove(...BARANGAY_STATE_CLASSES);
  sidebar.setAttribute('aria-hidden', 'true');
  barangaySheetState = 'hidden';
  document.removeEventListener('mousedown', _outsideBarangaySidebarClick, true);
}

/**
 * Wire up close button + backdrop / Esc behaviour.
 * Idempotent — safe to call multiple times.
 */
function initBarangaySidebar() {
  const sidebar = document.getElementById('barangay-sidebar');
  if (sidebar === null) { return; }

  // ── Close button ────────────────────────────
  const closeBtn = document.getElementById('close-barangay-sidebar-btn');
  if (closeBtn !== null && closeBtn.dataset.wired !== '1') {
    closeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      closeBarangaySidebar();
    });
    closeBtn.dataset.wired = '1';
  }

  // ── Esc key closes ──────────────────────────
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') { return; }
    if (sidebar.classList.contains('open')) { closeBarangaySidebar(); }
  });

  // ── Tap hero (peek-bar) toggles peek↔mid on mobile ─
  const hero = document.getElementById('barangay-hero');
  if (hero !== null && hero.dataset.wired !== '1') {
    hero.addEventListener('click', (e) => {
      if (!isMobileViewportBarangay()) { return; }
      // Don't trigger when clicking the close button area
      if (e.target.closest && e.target.closest('.detail-sidebar__close')) { return; }
      if (barangaySheetState === 'peek')      { setBarangaySheetState('mid');  }
      else if (barangaySheetState === 'mid')  { setBarangaySheetState('peek'); }
      else if (barangaySheetState === 'full') { setBarangaySheetState('mid');  }
    });
    hero.dataset.wired = '1';
  }

  // ── Drag handle: gesture transitions ──────
  const handle = document.getElementById('barangay-sidebar-handle');
  if (handle !== null && handle.dataset.wired !== '1') {
    handle.addEventListener('touchstart', _barangayDragStart, { passive: true });
    handle.addEventListener('touchmove',  _barangayDragMove,  { passive: false });
    handle.addEventListener('touchend',   _barangayDragEnd,   { passive: true });
    handle.dataset.wired = '1';
  }

  // ── Reset to peek when viewport flips desktop ↔ mobile ──
  window.addEventListener('resize', () => {
    if (!sidebar.classList.contains('open')) { return; }
    if (isMobileViewportBarangay()) {
      if (barangaySheetState === 'hidden') { setBarangaySheetState('peek'); }
    } else {
      sidebar.classList.remove(...BARANGAY_STATE_CLASSES);
      barangaySheetState = 'hidden';
    }
  });
}

function _barangayDragStart(e) {
  barangayDrag.active = true;
  barangayDrag.startY = e.touches[0].clientY;
  const sidebar = document.getElementById('barangay-sidebar');
  if (sidebar !== null) { sidebar.style.transition = 'none'; }
}

function _barangayDragMove(e) {
  if (!barangayDrag.active) { return; }
  const dy = e.touches[0].clientY - barangayDrag.startY;
  if (dy < 0) { return; } // upward drag handled on release
  e.preventDefault();
  const sidebar = document.getElementById('barangay-sidebar');
  if (sidebar !== null) { sidebar.style.transform = `translateY(${dy}px)`; }
}

function _barangayDragEnd(e) {
  if (!barangayDrag.active) { return; }
  barangayDrag.active = false;

  const sidebar = document.getElementById('barangay-sidebar');
  if (sidebar === null) { return; }
  sidebar.style.transition = '';
  sidebar.style.transform  = '';

  const dy = e.changedTouches[0].clientY - barangayDrag.startY;
  const THRESHOLD = 70;

  if (dy > THRESHOLD) {
    // Dragged down — step back
    if (barangaySheetState === 'full')      { setBarangaySheetState('mid');  }
    else if (barangaySheetState === 'mid')  { setBarangaySheetState('peek'); }
    else if (barangaySheetState === 'peek') { closeBarangaySidebar();        }
  } else if (dy < -THRESHOLD) {
    // Dragged up — step forward
    if (barangaySheetState === 'peek')     { setBarangaySheetState('mid');  }
    else if (barangaySheetState === 'mid') { setBarangaySheetState('full'); }
  }
}
