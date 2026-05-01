/**
 * CabEvac — Main Application Entry Point
 * Initializes all services and wires up UI interactions.
 *
 * @module app
 */

/** LocalStorage helper for layer visibility and UI state */
const AppStorage = {
  STORAGE_KEY: 'cabevac_ui_state',

  load() {
    try {
      const raw = localStorage.getItem(this.STORAGE_KEY);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch {
      return null;
    }
  },

  save(state) {
    try {
      localStorage.setItem(this.STORAGE_KEY, JSON.stringify(state));
    } catch (err) {
      console.warn('[AppStorage] Failed to save state:', err);
    }
  },

  getLayers() {
    const state = this.load();
    return state?.layers ?? null;
  },

  getLegendCollapsed() {
    const state = this.load();
    return state?.legendCollapsed ?? false;
  },

  setLayers(layers) {
    const state = this.load() ?? {};
    state.layers = layers;
    this.save(state);
  },

  setLegendCollapsed(collapsed) {
    const state = this.load() ?? {};
    state.legendCollapsed = collapsed;
    this.save(state);
  },

  getBasemap() {
    const state = this.load();
    return state?.basemap ?? null;
  },

  setBasemap(basemap) {
    const state = this.load() ?? {};
    state.basemap = basemap;
    this.save(state);
  },

  getBufferFacilities() {
    const state = this.load();
    return state?.bufferFacilities ?? {};
  },

  setBufferFacility(slug, facility, visible) {
    const state = this.load() ?? {};
    if (!state.bufferFacilities) { state.bufferFacilities = {}; }
    if (!state.bufferFacilities[slug]) { state.bufferFacilities[slug] = {}; }
    state.bufferFacilities[slug][facility] = visible;
    this.save(state);
  },

  getIsochroneFacilities() {
    const state = this.load();
    return state?.isochroneFacilities ?? {};
  },

  setIsochroneFacilityMins(slug, facility, mins, visible) {
    const state = this.load() ?? {};
    if (!state.isochroneFacilities) { state.isochroneFacilities = {}; }
    if (!state.isochroneFacilities[slug]) { state.isochroneFacilities[slug] = {}; }
    if (!state.isochroneFacilities[slug][facility]) { state.isochroneFacilities[slug][facility] = {}; }
    state.isochroneFacilities[slug][facility][mins] = visible;
    this.save(state);
  },
};

document.addEventListener('DOMContentLoaded', async () => {
  'use strict';

  // ============================================
  // 1. Initialize Map
  // ============================================
  const map = initializeMap('map-container');
  initLocationService(map);

  // ============================================
  // 2. Track visible layers for legend
  // ============================================
  const defaultLayers = {
    'evacuation': true,
    'flood-very-high': true,
    'flood-high': true,
    'flood-moderate': true,
    'flood-low': true,
    'cabanatuan-boundary': true,
  };

  const savedLayers = AppStorage.getLayers();
  const visibleLayers = savedLayers ?? { ...defaultLayers };
  window.visibleLayers = visibleLayers;

  /** Refresh the legend panel based on current state */
  window.refreshLegend = () => updateLegend(visibleLayers);

  // ============================================
  // 3. Load default layers (conditional on saved state)
  // ============================================
  const layerLoaders = [
    { key: 'cabanatuan-boundary', load: () => loadGisLayer('cabanatuan-boundary', 'boundary', 'boundary') },
    { key: 'evacuation', load: () => loadEvacuationCenters() },
    { key: 'flood-very-high', load: () => loadFloodZones('very_high') },
    { key: 'flood-high', load: () => loadFloodZones('high') },
    { key: 'flood-moderate', load: () => loadFloodZones('moderate') },
    { key: 'flood-low', load: () => loadFloodZonesLow() },
    { key: 'buffer-500m', load: () => loadGisLayer('buffer-500m', 'buffer-500m', 'buffer-500') },
    { key: 'buffer-1km', load: () => loadGisLayer('buffer-1km', 'buffer-1km', 'buffer-1km') },
    { key: 'buffer-2km', load: () => loadGisLayer('buffer-2km', 'buffer-2km', 'buffer-2km') },
    { key: 'isochrone-layers', load: () => loadGisLayer('isochrone-layers', 'isochrone', 'isochrone') },
    { key: 'network-analysis', load: () => loadNetworkAnalysis() },
    { key: 'road-network', load: () => loadGisLayer('road-network', 'road', 'road') },
    { key: 'pop-density', load: () => loadGisLayer('pop-density', 'population-density', 'pop-density') },
    { key: 'travel-time', load: () => loadGisLayer('travel-time', 'travel-time', 'travel-time') },
  ];

  try {
    // Load boundary first so pop-density join can use it
    const boundaryLoader = layerLoaders.find(({ key }) => key === 'cabanatuan-boundary');
    if (boundaryLoader && visibleLayers['cabanatuan-boundary'] === true) {
      await boundaryLoader.load();
    }

    const remaining = layerLoaders
      .filter(({ key }) => key !== 'cabanatuan-boundary' && visibleLayers[key] === true)
      .map(({ load }) => load());

    await Promise.all(remaining);
  } catch (error) {
    console.error('[App] Failed to load default layers:', error);
    showToast('Some layers failed to load. Please refresh.', 'warning');
  }

  // Initial legend render
  updateLegend(visibleLayers);

  // ============================================
  // 4. Layer Toggle Listeners
  // ============================================
  const layerMappings = {
    'layer-evacuation': {
      key: 'evacuation',
      load: () => loadEvacuationCenters(),
    },
    'layer-flood-very-high': {
      key: 'flood-very-high',
      load: () => loadFloodZones('very_high'),
    },
    'layer-flood-high': {
      key: 'flood-high',
      load: () => loadFloodZones('high'),
    },
    'layer-flood-moderate': {
      key: 'flood-moderate',
      load: () => loadFloodZones('moderate'),
    },
    'layer-flood-low': {
      key: 'flood-low',
      load: () => loadFloodZonesLow(),
    },
    'layer-buffer-500': {
      key: 'buffer-500m',
      load: () => loadGisLayer('buffer-500m', 'buffer-500m', 'buffer-500'),
    },
    'layer-buffer-1km': {
      key: 'buffer-1km',
      load: () => loadGisLayer('buffer-1km', 'buffer-1km', 'buffer-1km'),
    },
    'layer-buffer-2km': {
      key: 'buffer-2km',
      load: () => loadGisLayer('buffer-2km', 'buffer-2km', 'buffer-2km'),
    },
    'layer-isochrone': {
      key: 'isochrone-layers',
      load: () => loadGisLayer('isochrone-layers', 'isochrone', 'isochrone'),
    },
    'layer-network-analysis': {
      key: 'network-analysis',
      load: () => loadNetworkAnalysis(),
    },
    'layer-road': {
      key: 'road-network',
      load: () => loadGisLayer('road-network', 'road', 'road'),
    },
    'layer-pop-density': {
      key: 'pop-density',
      load: () => loadGisLayer('pop-density', 'population-density', 'pop-density'),
    },
    'layer-travel-time': {
      key: 'travel-time',
      load: () => loadGisLayer('travel-time', 'travel-time', 'travel-time'),
    },
    'layer-boundary': {
      key: 'cabanatuan-boundary',
      load: () => loadGisLayer('cabanatuan-boundary', 'boundary', 'boundary'),
    },
  };

  const BUFFER_SLUGS = ['buffer-500m', 'buffer-1km', 'buffer-2km'];
  const ISOCHRONE_SLUG = 'isochrone-layers';
  const EXPANDABLE_SLUGS = [...BUFFER_SLUGS, ISOCHRONE_SLUG];

  /** Map slug -> parent checkbox element ID. */
  const SLUG_TO_CHECKBOX_ID = {
    'buffer-500m': 'layer-buffer-500',
    'buffer-1km': 'layer-buffer-1km',
    'buffer-2km': 'layer-buffer-2km',
    'isochrone-layers': 'layer-isochrone',
  };

  /** Escape HTML special chars to safely inject user-facing strings. */
  const escapeHtml = (str) => String(str).replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[c]);

  /** Ensure a .buffer-sublist has an inner wrapper (required for grid transition). */
  const getSublistInner = (sublist) => {
    let inner = sublist.querySelector(':scope > .buffer-sublist__inner');
    if (inner === null) {
      inner = document.createElement('div');
      inner.className = 'buffer-sublist__inner';
      sublist.appendChild(inner);
    } else {
      inner.innerHTML = '';
    }
    return inner;
  };

  /**
   * Populate a buffer's facility sublist and wire per-facility checkboxes.
   */
  const populateBufferSublist = (slug) => {
    const wrapper = document.querySelector(`.layer-buffer[data-slug="${slug}"]`);
    if (wrapper === null) { return; }
    const sublist = wrapper.querySelector('.buffer-sublist');
    if (sublist === null) { return; }

    indexBufferFacilities(slug);
    const facilities = getBufferFacilities(slug);
    const saved = AppStorage.getBufferFacilities()[slug] ?? {};

    const inner = getSublistInner(sublist);

    if (facilities.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'buffer-sublist__empty';
      empty.textContent = 'No facilities available';
      inner.appendChild(empty);
      sublist.dataset.populated = 'true';
      return;
    }

    facilities.forEach((name, i) => {
      const id = `facility-${slug}-${i}`;
      const visible = saved[name] !== false;

      const item = document.createElement('label');
      item.className = 'buffer-sublist__item';
      item.htmlFor = id;
      item.innerHTML = `
        <input type="checkbox" id="${id}"${visible ? ' checked' : ''}>
        <span class="buffer-sublist__name" title="${escapeHtml(name)}">${escapeHtml(name)}</span>
      `;

      const cb = item.querySelector('input');
      cb.addEventListener('change', () => {
        setBufferFacilityVisible(slug, name, cb.checked);
        AppStorage.setBufferFacility(slug, name, cb.checked);
      });

      inner.appendChild(item);
      if (!visible) { setBufferFacilityVisible(slug, name, false); }
    });

    sublist.dataset.populated = 'true';
  };

  /**
   * Populate the isochrone sublist: each facility with 5m/10m toggle chips.
   */
  const populateIsochroneSublist = (slug) => {
    const wrapper = document.querySelector(`.layer-buffer[data-slug="${slug}"]`);
    if (wrapper === null) { return; }
    const sublist = wrapper.querySelector('.buffer-sublist');
    if (sublist === null) { return; }

    indexIsochroneFacilities(slug);
    const facilities = getIsochroneFacilities(slug);
    const saved = AppStorage.getIsochroneFacilities()[slug] ?? {};

    const inner = getSublistInner(sublist);

    if (facilities.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'buffer-sublist__empty';
      empty.textContent = 'No isochrone data available';
      inner.appendChild(empty);
      sublist.dataset.populated = 'true';
      return;
    }

    facilities.forEach(({ name, mins }) => {
      const savedForFacility = saved[name] ?? {};

      const item = document.createElement('div');
      item.className = 'buffer-sublist__item buffer-sublist__item--iso';
      item.innerHTML = `
        <span class="buffer-sublist__name" title="${escapeHtml(name)}">${escapeHtml(name)}</span>
        <div class="time-chips" role="group" aria-label="Walk time for ${escapeHtml(name)}"></div>
      `;

      const chipsContainer = item.querySelector('.time-chips');
      mins.forEach((m) => {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'time-chip';
        chip.dataset.mins = String(m);
        chip.setAttribute('aria-pressed', 'true');
        chip.textContent = `${m}m`;

        const initialVisible = savedForFacility[m] !== false;
        if (initialVisible) { chip.classList.add('is-active'); }
        else {
          chip.setAttribute('aria-pressed', 'false');
          setIsochroneFacilityMinsVisible(slug, name, m, false);
        }

        chip.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const nowActive = !chip.classList.contains('is-active');
          chip.classList.toggle('is-active', nowActive);
          chip.setAttribute('aria-pressed', String(nowActive));
          setIsochroneFacilityMinsVisible(slug, name, m, nowActive);
          AppStorage.setIsochroneFacilityMins(slug, name, m, nowActive);
        });

        chipsContainer.appendChild(chip);
      });

      inner.appendChild(item);
    });

    sublist.dataset.populated = 'true';
  };

  /** Populate the correct sublist type for a slug. */
  const populateSublist = (slug) => {
    if (slug === ISOCHRONE_SLUG) { populateIsochroneSublist(slug); }
    else if (BUFFER_SLUGS.includes(slug)) { populateBufferSublist(slug); }
  };

  /** Ensure a layer's data is loaded, then populate its sublist. */
  const ensureLayerLoadedAndPopulated = async (slug) => {
    const config = Object.values(layerMappings).find((c) => c.key === slug);
    if (config === undefined) { return; }
    if (layerCache[slug] === undefined) { await config.load(); }
    populateSublist(slug);
  };

  Object.entries(layerMappings).forEach(([checkboxId, config]) => {
    const checkbox = document.getElementById(checkboxId);
    if (checkbox === null) { return; }

    if (savedLayers !== null && config.key in savedLayers) {
      checkbox.checked = savedLayers[config.key];
    }

    checkbox.addEventListener('change', async () => {
      const isChecked = checkbox.checked;
      visibleLayers[config.key] = isChecked;

      if (isChecked) {
        await config.load();
        toggleLayerVisibility(config.key, true);
        if (EXPANDABLE_SLUGS.includes(config.key)) {
          populateSublist(config.key);
        }
      } else {
        toggleLayerVisibility(config.key, false);
      }

      updateLegend(visibleLayers);
      AppStorage.setLayers(visibleLayers);
    });
  });

  // Populate sublists for expandable layers that loaded during startup
  EXPANDABLE_SLUGS.forEach((slug) => {
    if (layerCache[slug] !== undefined) { populateSublist(slug); }
  });

  // Wire chevron expand/collapse
  document.querySelectorAll('.layer-buffer__chevron').forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      const wrapper = btn.closest('.layer-buffer');
      if (wrapper === null) { return; }
      const slug = wrapper.dataset.slug;
      const sublist = wrapper.querySelector('.buffer-sublist');
      if (sublist === null) { return; }

      const nextOpen = btn.getAttribute('aria-expanded') !== 'true';
      btn.setAttribute('aria-expanded', String(nextOpen));
      wrapper.classList.toggle('layer-buffer--open', nextOpen);

      if (nextOpen && sublist.dataset.populated !== 'true') {
        await ensureLayerLoadedAndPopulated(slug);
        const parentId = SLUG_TO_CHECKBOX_ID[slug];
        const parent = parentId ? document.getElementById(parentId) : null;
        if (parent !== null && !parent.checked) {
          toggleLayerVisibility(slug, false);
        }
      }
    });
  });

  // ============================================
  // 4b. Layer Info Modal (eye button)
  // ============================================

  /** Eye SVG icon string. */
  const EYE_SVG = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';

  /** Config per layer that supports the info modal. */
  const INFO_LAYERS = {
    'layer-evacuation': {
      title: 'Evacuation Centers',
      subtitle: 'Active shelters available for evacuation',
      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>',
      source: 'evacuation',
      slug: null,
    },
    'layer-flood-very-high': {
      title: 'Very High Risk Zones',
      subtitle: 'Areas with severe flooding risk',
      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M4.93 19h14.14a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.19 16a2 2 0 001.74 3z"></path></svg>',
      source: 'flood',
      risk: 'very_high',
      badgeClass: 'danger',
    },
    'layer-flood-high': {
      title: 'High Risk Zones',
      subtitle: 'Significant flooding exposure',
      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M4.93 19h14.14a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.19 16a2 2 0 001.74 3z"></path></svg>',
      source: 'flood',
      risk: 'high',
      badgeClass: 'warning',
    },
    'layer-flood-moderate': {
      title: 'Moderate Risk Zones',
      subtitle: 'Moderate flooding exposure',
      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M4.93 19h14.14a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.19 16a2 2 0 001.74 3z"></path></svg>',
      source: 'flood',
      risk: 'moderate',
      badgeClass: 'info',
    },
    'layer-flood-low': {
      title: 'Low Risk Zones',
      subtitle: 'Minimal flooding exposure',
      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
      source: 'flood',
      risk: 'low',
      badgeClass: 'success',
    },
    'layer-network-analysis': {
      title: 'Network Analysis',
      subtitle: 'Shortest evacuation routes per barangay',
      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>',
      source: 'gis',
      slug: 'network-analysis',
    },
    'layer-road': {
      title: 'Road Network',
      subtitle: 'Mapped roads in the coverage area',
      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21l6-18M21 21l-6-18M9 9h6M9 15h6"></path></svg>',
      source: 'gis',
      slug: 'road-network',
      nameKey: 'name',
    },
    'layer-pop-density': {
      title: 'Population Density',
      subtitle: 'Barangay-level density (persons/km²)',
      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m0 0a4 4 0 118 0m-8 0a4 4 0 018 0M12 10a4 4 0 100-8 4 4 0 000 8z"></path></svg>',
      source: 'gis',
      slug: 'pop-density',
      nameKey: 'ADM4_EN',
      densityKey: 'Untitled spreadsheet - Sheet1 (5)_pop den',
    },
    'layer-travel-time': {
      title: 'Travel Routes',
      subtitle: 'Estimated travel time per destination',
      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
      source: 'gis',
      slug: 'travel-time',
      nameKey: 'layer',
      timeKey: 'TIME FINAL',
    },
    'layer-boundary': {
      title: 'City Boundary',
      subtitle: 'Barangays within Cabanatuan city',
      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l6-3 6 3 6-3v13l-6 3-6-3-6 3V7z"></path><path d="M9 4v13"></path><path d="M15 7v13"></path></svg>',
      source: 'barangays',
    },
  };

  /** Inject eye buttons next to supported layer items. */
  Object.entries(INFO_LAYERS).forEach(([checkboxId, cfg]) => {
    const checkbox = document.getElementById(checkboxId);
    if (checkbox === null) { return; }
    const label = checkbox.closest('label.layer-item');
    if (label === null || label.parentElement.classList.contains('layer-row')) { return; }

    const row = document.createElement('div');
    row.className = 'layer-row';
    label.parentNode.insertBefore(row, label);
    row.appendChild(label);

    const eye = document.createElement('button');
    eye.type = 'button';
    eye.className = 'layer-row__eye';
    eye.setAttribute('aria-label', `View ${cfg.title} details`);
    eye.innerHTML = EYE_SVG;
    eye.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      openInfoModal(checkboxId);
    });
    row.appendChild(eye);
  });

  // Modal DOM refs
  const infoModal = document.getElementById('info-modal');
  const infoTitle = document.getElementById('info-modal-title');
  const infoSubtitle = document.getElementById('info-modal-subtitle');
  const infoIcon = document.getElementById('info-modal-icon');
  const infoList = document.getElementById('info-modal-list');
  const infoSearch = document.getElementById('info-modal-search');
  const infoCount = document.getElementById('info-modal-count');

  let currentInfoItems = [];

  /**
   * Feature spotlight — single active sky-blue glow on a polygon/polyline
   * that the user just focused from the info modal.
   */
  let spotlightedLayer = null;
  const clearFeatureSpotlight = () => {
    if (spotlightedLayer === null) { return; }
    // Remove from top-level path (single-ring polygons, polylines)
    if (spotlightedLayer._path && spotlightedLayer._path.classList) {
      spotlightedLayer._path.classList.remove('leaflet-feature--spotlight');
    }
    // Remove from multi-ring / feature-group sublayers if applicable
    if (typeof spotlightedLayer.eachLayer === 'function') {
      spotlightedLayer.eachLayer((sub) => {
        if (sub._path && sub._path.classList) {
          sub._path.classList.remove('leaflet-feature--spotlight');
        }
      });
    }
    spotlightedLayer = null;
  };
  const spotlightFeature = (layer) => {
    clearFeatureSpotlight();
    if (!layer) { return; }
    const applyTo = (p) => {
      if (p && p.classList) { p.classList.add('leaflet-feature--spotlight'); }
    };
    applyTo(layer._path);
    if (typeof layer.eachLayer === 'function') {
      layer.eachLayer((sub) => applyTo(sub._path));
    }
    spotlightedLayer = layer;
  };

  // Click anywhere on the map (background or the glow itself, since it has
  // pointer-events:none) to dismiss the current spotlight.
  if (typeof getMap === 'function') {
    const mapInstance = getMap();
    if (mapInstance !== null && typeof mapInstance.on === 'function') {
      mapInstance.on('click', () => clearFeatureSpotlight());
    }
  }

  /** Render the item list with optional search filter. */
  const renderInfoList = (query) => {
    if (infoList === null) { return; }
    const q = (query ?? '').trim().toLowerCase();
    const filtered = q
      ? currentInfoItems.filter((it) => (it.name + ' ' + (it.meta || '')).toLowerCase().includes(q))
      : currentInfoItems;

    infoList.innerHTML = '';

    if (filtered.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'info-modal__empty';
      empty.textContent = q ? 'No matches found' : 'No data available';
      infoList.appendChild(empty);
    } else {
      filtered.forEach((it) => {
        const li = document.createElement('li');
        li.className = 'info-list__item';
        const badge = it.badge
          ? `<span class="info-list__badge info-list__badge--${it.badgeClass ?? 'info'}">${escapeHtml(it.badge)}</span>`
          : '';
        li.innerHTML = `
          <div class="info-list__top">
            <div class="info-list__name" title="${escapeHtml(it.name)}">${escapeHtml(it.name)}</div>
            ${badge}
          </div>
          ${it.meta ? `<div class="info-list__meta">${it.meta}</div>` : ''}
        `;
        if (it.focus) {
          li.addEventListener('click', () => {
            it.focus();
            closeInfoModal();
            // On mobile, close the Map Layers drawer so the user can see the
            // map + the highlighted marker without the panel covering it.
            if (window.innerWidth <= 768 && typeof toggleLayerPanel === 'function') {
              toggleLayerPanel(false);
            }
          });
        }
        infoList.appendChild(li);
      });
    }

    if (infoCount !== null) {
      infoCount.textContent = `${filtered.length} item${filtered.length === 1 ? '' : 's'}`;
    }
  };

  /** Build item list for a given layer config. */
  const buildInfoItems = async (cfg) => {
    if (cfg.source === 'evacuation') {
      let centers = typeof getEvacuationCentersData === 'function' ? getEvacuationCentersData() : [];
      if (centers.length === 0 && typeof loadEvacuationCenters === 'function') {
        await loadEvacuationCenters();
        centers = getEvacuationCentersData();
      }
      return centers.map((c) => ({
        name: c.name || 'Unnamed Center',
        badge: c.status,
        badgeClass: 'info',
        meta: [
          c.barangay ? `<span>📍 ${escapeHtml(c.barangay)}</span>` : '',
          c.capacity ? `<span>Capacity: ${escapeHtml(String(c.capacity))}</span>` : '',
          c.address ? `<span>${escapeHtml(c.address)}</span>` : '',
        ].filter(Boolean).join(''),
        focus: () => {
          if (c.latitude && c.longitude && typeof flyToPoint === 'function') {
            flyToPoint(c.latitude, c.longitude, 16);
          }
          const isMobile = window.innerWidth <= 768;
          if (isMobile) {
            if (typeof openMobileSheet === 'function') { openMobileSheet(c); }
          } else if (typeof openDetailSidebar === 'function') {
            openDetailSidebar(c);
          }
          if (typeof highlightEvacuationMarker === 'function') {
            highlightEvacuationMarker(c.id);
          }
        },
      }));
    }

    if (cfg.source === 'flood') {
      const slug = `flood-${cfg.risk.replace('_', '-')}`;
      if (layerCache[slug] === undefined) {
        if (cfg.risk === 'low' && typeof loadFloodZonesLow === 'function') {
          await loadFloodZonesLow();
        } else if (typeof loadFloodZones === 'function') {
          await loadFloodZones(cfg.risk);
        }
      }
      const group = layerCache[slug];
      if (!group) { return []; }
      const items = [];
      group.eachLayer((l) => {
        const p = l.feature?.properties || {};
        items.push({
          name: p.barangay || 'Unnamed Barangay',
          badge: cfg.risk.replace('_', ' '),
          badgeClass: cfg.badgeClass,
          meta: p.area_sqkm ? `<span>Area: ${Number(p.area_sqkm).toFixed(2)} km²</span>` : '',
          focus: () => {
            try {
              const bounds = l.getBounds?.();
              if (bounds && typeof getMap === 'function') { getMap().fitBounds(bounds, { padding: [40, 40] }); }
            } catch (err) { /* noop */ }
            spotlightFeature(l);
          },
        });
      });
      return items.sort((a, b) => a.name.localeCompare(b.name));
    }

    if (cfg.source === 'gis') {
      // Ensure the layer is loaded so we have features to list
      if (layerCache[cfg.slug] === undefined) {
        const mapping = Object.values(layerMappings).find((m) => m.key === cfg.slug);
        if (mapping) { await mapping.load(); }
      }
      const group = layerCache[cfg.slug];
      if (!group) { return []; }
      const items = [];
      group.eachLayer((l) => {
        const p = l.feature?.properties || {};
        let name = '';
        let meta = '';
        let badge;

        if (cfg.slug === 'network-analysis') {
          name = p._barangay || p.start || 'Route';
          const cost = p.cost !== undefined ? `${Math.round(Number(p.cost)).toLocaleString()} m` : null;
          if (cost) { meta = `<span>Distance: ${cost}</span>`; }
        } else if (cfg.slug === 'road-network') {
          name = p.name || p.road || 'Unnamed Road';
          if (p.highway) { badge = p.highway; }
        } else if (cfg.slug === 'pop-density') {
          name = p[cfg.nameKey] || 'Barangay';
          const density = p[cfg.densityKey] || p['pop_den'] || p['density'];
          if (density) {
            meta = `<span>Density: ${Math.round(Number(density)).toLocaleString()} /km²</span>`;
          }
        } else if (cfg.slug === 'travel-time') {
          name = String(p[cfg.nameKey] || p.barangay || 'Destination').toUpperCase();
          const t = p[cfg.timeKey] || p.time;
          if (t) { badge = String(t); }
        }

        if (!name) { return; }

        items.push({
          name,
          badge,
          badgeClass: 'info',
          meta,
          focus: () => {
            try {
              const bounds = l.getBounds?.() || (l.getLatLng ? L.latLngBounds([l.getLatLng()]) : null);
              if (bounds && typeof getMap === 'function') { getMap().fitBounds(bounds, { padding: [40, 40] }); }
            } catch (err) { /* noop */ }
            spotlightFeature(l);
          },
        });
      });
      // Dedup roads by name
      if (cfg.slug === 'road-network') {
        const seen = new Map();
        items.forEach((it) => {
          if (!seen.has(it.name)) { seen.set(it.name, it); }
        });
        return Array.from(seen.values()).sort((a, b) => a.name.localeCompare(b.name));
      }
      return items.sort((a, b) => a.name.localeCompare(b.name));
    }

    if (cfg.source === 'barangays') {
      // Fetch barangay list from the DB API
      let barangays = [];
      try {
        const res = await fetch('api/barangays.php');
        const json = await res.json();
        if (json.success && Array.isArray(json.data)) { barangays = json.data; }
      } catch (err) {
        console.error('[InfoModal] Failed to load barangays', err);
      }
      if (barangays.length === 0) { return []; }

      // Use the already-on-map cabanatuan-boundary layer for fly-to + spotlight.
      // This layer is rendered as individual barangay polygons so glow works.
      // We do NOT load pop-density — that would add it to the map.
      const boundaryGroup = layerCache['cabanatuan-boundary'];
      const boundaryLayerByName = new Map();
      if (boundaryGroup && typeof boundaryGroup.eachLayer === 'function') {
        boundaryGroup.eachLayer((l) => {
          const p = l.feature?.properties || {};
          // Try every common name field the GeoJSON might use
          const rawName = (
            p.ADM4_EN || p.ADM3_EN || p.name || p.NAME ||
            p.BARANGAY || p.barangay || p.Barangay || ''
          ).trim().toUpperCase();
          if (rawName) { boundaryLayerByName.set(rawName, l); }
        });
      }

      return barangays.map((b) => {
        const name = b.name || 'Barangay';
        const metaParts = [];
        if (b.population) { metaParts.push(`<span>Population: ${Number(b.population).toLocaleString()}</span>`); }
        if (b.population_density) { metaParts.push(`<span>Density: ${Number(b.population_density).toLocaleString()} /km²</span>`); }
        if (b.area_sqkm) { metaParts.push(`<span>Area: ${Number(b.area_sqkm).toFixed(2)} km²</span>`); }
        return {
          name,
          badge: 'Barangay',
          badgeClass: 'info',
          meta: metaParts.join(''),
          focus: () => {
            const key = name.trim().toUpperCase();
            const matched = boundaryLayerByName.get(key);
            if (matched) {
              try {
                const bounds = matched.getBounds?.();
                if (bounds && typeof getMap === 'function') {
                  getMap().fitBounds(bounds, { padding: [60, 60] });
                }
              } catch (e) { /* noop */ }
              spotlightFeature(matched);
            }
            // Also open the barangay detail sidebar
            if (typeof openBarangaySidebarByName === 'function') {
              const featureProps = matched && matched.feature
                ? (matched.feature.properties || {})
                : {};
              openBarangaySidebarByName(name, featureProps);
            }
          },
        };
      }).sort((a, b) => a.name.localeCompare(b.name));
    }

    return [];
  };

  /** Open the info modal for a given layer config id. */
  const openInfoModal = async (checkboxId) => {
    const cfg = INFO_LAYERS[checkboxId];
    if (!cfg || infoModal === null) { return; }

    infoTitle.textContent = cfg.title;
    infoSubtitle.textContent = cfg.subtitle || '';
    infoIcon.innerHTML = cfg.icon || '';
    infoList.innerHTML = '<div class="info-modal__empty">Loading…</div>';
    if (infoSearch) { infoSearch.value = ''; }
    if (infoCount) { infoCount.textContent = '…'; }

    infoModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    currentInfoItems = await buildInfoItems(cfg);

    // If the parent layer checkbox is off, make sure the (possibly just-loaded)
    // layer stays hidden on the map — opening the modal should never reveal it.
    const parentCheckbox = document.getElementById(checkboxId);
    if (parentCheckbox !== null && !parentCheckbox.checked) {
      if (cfg.source === 'flood') {
        toggleLayerVisibility(`flood-${cfg.risk.replace('_', '-')}`, false);
      } else if (cfg.source === 'gis' && cfg.slug) {
        toggleLayerVisibility(cfg.slug, false);
      } else if (cfg.source === 'evacuation') {
        toggleLayerVisibility('evacuation', false);
      }
    }

    renderInfoList('');
  };

  /** Close the info modal. */
  const closeInfoModal = () => {
    if (infoModal === null) { return; }
    infoModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    currentInfoItems = [];
  };

  // Wire modal close + search
  if (infoModal !== null) {
    infoModal.querySelectorAll('[data-info-close]').forEach((el) => {
      el.addEventListener('click', closeInfoModal);
    });
    if (infoSearch !== null) {
      infoSearch.addEventListener('input', () => renderInfoList(infoSearch.value));
    }
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && infoModal.getAttribute('aria-hidden') === 'false') {
        closeInfoModal();
      }
    });
  }

  // ============================================
  // 5. Initialize Search & Filters
  // ============================================
  initSearchBar();
  initFilterChips();
  initMobileSearch();

  // ============================================
  // 6. Sidebar Controls
  // ============================================
  const closeSidebarBtn = document.getElementById('close-sidebar-btn');
  if (closeSidebarBtn !== null) {
    closeSidebarBtn.addEventListener('click', closeDetailSidebar);
  }

  // Close sidebar when clicking on the map
  const mapContainer = document.getElementById('map-container');
  if (mapContainer !== null) {
    mapContainer.addEventListener('click', (e) => {
      const sidebar = document.getElementById('detail-sidebar');
      if (sidebar === null || !sidebar.classList.contains('open')) { return; }
      // Only close if click is not on a Leaflet marker or popup
      if (!e.target.closest('.leaflet-marker-icon, .leaflet-popup-pane')) {
        closeDetailSidebar();
      }
    });
  }

  // Close sidebar on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeDetailSidebar();
      clearSearchResults();
      toggleLayerPanel(false);
    }
  });

  // ============================================
  // 7. Hamburger Menu (Mobile)
  // ============================================
  const hamburgerBtn = document.getElementById('hamburger-btn');
  const drawerOverlay = document.getElementById('drawer-overlay');

  if (hamburgerBtn !== null) {
    hamburgerBtn.addEventListener('click', () => toggleLayerPanel());
  }

  if (drawerOverlay !== null) {
    drawerOverlay.addEventListener('click', () => toggleLayerPanel(false));
  }

  // ============================================
  // 8. Layer Panel Collapse Toggle
  // ============================================
  const layerPanelToggle = document.getElementById('layer-panel-toggle');
  const layerPanelBody = document.getElementById('layer-panel-body');

  if (layerPanelToggle !== null && layerPanelBody !== null) {
    layerPanelToggle.addEventListener('click', () => {
      const isCollapsed = layerPanelBody.style.display === 'none';
      layerPanelBody.style.display = isCollapsed ? 'block' : 'none';
      layerPanelToggle.textContent = isCollapsed ? '−' : '+';
    });
  }

  // ============================================
  // 8.5 Legend Panel Collapse Toggle
  // ============================================
  const legendToggle = document.getElementById('legend-toggle');
  const legendItems = document.getElementById('legend-items');
  const legendPanel = document.getElementById('legend-panel');

  if (legendToggle !== null && legendItems !== null && legendPanel !== null) {
    // Restore saved collapsed state
    const savedCollapsed = AppStorage.getLegendCollapsed();
    if (savedCollapsed) {
      legendToggle.setAttribute('aria-expanded', 'false');
      legendItems.setAttribute('aria-hidden', 'true');
      legendPanel.classList.add('legend-panel--collapsed');
    }

    // Re-measure legend height after state restore (CSS transition takes 250ms)
    setTimeout(() => {
      if (typeof updateLegendHeightVar === 'function') { updateLegendHeightVar(); }
    }, 300);

    legendToggle.addEventListener('click', () => {
      const isExpanded = legendToggle.getAttribute('aria-expanded') === 'true';
      const newState = !isExpanded;
      legendToggle.setAttribute('aria-expanded', String(newState));
      legendItems.setAttribute('aria-hidden', String(!newState));
      legendPanel.classList.toggle('legend-panel--collapsed', !newState);
      document.body.classList.toggle('legend-expanded', newState);
      AppStorage.setLegendCollapsed(!newState);
      setTimeout(() => {
        if (typeof updateLegendHeightVar === 'function') { updateLegendHeightVar(); }
      }, 280);
    });

    // Initial body class sync
    document.body.classList.toggle('legend-expanded', !savedCollapsed);
  }

  // ============================================
  // 9. Basemap Switcher
  // ============================================
  const basemapSelector = document.getElementById('basemap-selector');
  if (basemapSelector !== null) {
    const basemapOptions = basemapSelector.querySelectorAll('.basemap-option');

    // Restore saved basemap
    const savedBasemap = AppStorage.getBasemap();
    if (savedBasemap && typeof BASEMAPS !== 'undefined' && BASEMAPS[savedBasemap]) {
      switchBasemap(savedBasemap);
      basemapOptions.forEach((opt) => {
        const isMatch = opt.dataset.basemap === savedBasemap;
        opt.classList.toggle('active', isMatch);
        opt.setAttribute('aria-checked', String(isMatch));
      });
    }

    basemapOptions.forEach((option) => {
      option.addEventListener('click', () => {
        const basemapKey = option.dataset.basemap;

        // Update active state
        basemapOptions.forEach((opt) => {
          opt.classList.remove('active');
          opt.setAttribute('aria-checked', 'false');
        });
        option.classList.add('active');
        option.setAttribute('aria-checked', 'true');

        // Switch tile layer
        switchBasemap(basemapKey);
        AppStorage.setBasemap(basemapKey);
      });

      // Keyboard support
      option.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          option.click();
        }
      });
    });
  }

  // Mobile bottom sheet
  if (typeof initMobileSheet === 'function') { initMobileSheet(); }

  // Barangay detail sidebar (close button + Esc)
  if (typeof initBarangaySidebar === 'function') { initBarangaySidebar(); }
});
