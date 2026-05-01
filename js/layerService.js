/**
 * CabEvac — Layer Service
 * Handles loading GeoJSON data from API, styling layers,
 * and toggling visibility on the map.
 * 
 * @module layerService
 */

/** @type {Object.<string, L.Layer>} Cache of loaded layers */
const layerCache = {};

/** @type {L.MarkerClusterGroup|null} */
let markerClusterGroup = null;

/** @type {Array<Object>} Cached evacuation center data */
let evacuationCentersData = [];

/** @type {Object<string, L.Marker>} Lookup of evacuation markers by center id */
const evacuationMarkers = {};

/** @type {Set<string>} IDs of currently highlighted (sky-blue) evacuation markers */
const highlightedEvacuationIds = new Set();

/** API base path */
const API_BASE = 'api';

/**
 * Fetch JSON from an API endpoint with error handling.
 * 
 * @param {string} url - API URL
 * @returns {Promise<Object|null>} Parsed JSON response or null on error
 */
async function fetchLayer(url) {
  try {
    const response = await fetch(url);

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.error || 'API returned unsuccessful response');
    }

    return data.data;
  } catch (error) {
    console.error(`[LayerService] Fetch failed: ${url}`, error);
    return null;
  }
}

/**
 * Load evacuation center markers onto the map.
 * Creates marker cluster group for performance.
 * 
 * @returns {Promise<void>}
 */
async function loadEvacuationCenters() {
  const map = getMap();
  if (map === null) { return; }

  showLayerSpinner('evacuation');

  const centers = await fetchLayer(`${API_BASE}/centers.php?status=active`);

  hideLayerSpinner('evacuation');

  if (centers === null || centers.length === 0) { return; }

  evacuationCentersData = centers;

  if (markerClusterGroup !== null) {
    map.removeLayer(markerClusterGroup);
  }

  markerClusterGroup = L.markerClusterGroup({
    maxClusterRadius: 50,
    spiderfyOnMaxZoom: true,
    showCoverageOnHover: false,
    iconCreateFunction: (cluster) => {
      const count = cluster.getChildCount();
      let sizeClass = 'small';
      if (count > 5) { sizeClass = 'large'; }
      else if (count > 3) { sizeClass = 'medium'; }

      return L.divIcon({
        html: `<div class="marker-cluster marker-cluster--${sizeClass}"><span>${count}</span></div>`,
        className: '',
        iconSize: [40, 40],
      });
    },
  });

  // Reset marker lookup; preserve highlight set so previously-highlighted
  // markers remain sky-blue after a reload.
  Object.keys(evacuationMarkers).forEach((k) => delete evacuationMarkers[k]);

  centers.forEach((center) => {
    const id = String(center.id);
    const isHighlighted = highlightedEvacuationIds.has(id);

    const marker = L.marker([center.latitude, center.longitude], {
      icon: createEvacuationMarkerIcon(isHighlighted),
      title: center.name,
    });

    marker.on('click', () => {
      flyToPoint(center.latitude, center.longitude, 16);
      highlightEvacuationMarker(center.id);
      if (typeof clearSearchInput === 'function') { clearSearchInput(); }
      const isMobile = window.innerWidth <= 768;
      if (isMobile && typeof openMobileSheet === 'function') {
        openMobileSheet(center);
      } else {
        openDetailSidebar(center);
      }
    });

    evacuationMarkers[id] = marker;
    markerClusterGroup.addLayer(marker);
  });

  map.addLayer(markerClusterGroup);
  layerCache['evacuation'] = markerClusterGroup;
}

/**
 * Apply a new icon to a marker that may be inside a markerClusterGroup.
 * Leaflet.markercluster does not always repaint after a plain setIcon,
 * so we remove → setIcon → re-add to force the DOM refresh.
 * 
 * @param {L.Marker} marker
 * @param {boolean} highlighted
 */
function applyEvacuationMarkerIcon(marker, highlighted) {
  const newIcon = createEvacuationMarkerIcon(highlighted);
  if (markerClusterGroup !== null && markerClusterGroup.hasLayer(marker)) {
    markerClusterGroup.removeLayer(marker);
    marker.setIcon(newIcon);
    markerClusterGroup.addLayer(marker);
  } else {
    marker.setIcon(newIcon);
  }
}

/**
 * Highlight a single evacuation marker (sky-blue).
 * Exclusive by default: any other highlighted markers are reverted to green
 * so only the currently-selected center stands out.
 * 
 * @param {string|number} centerId
 */
function highlightEvacuationMarker(centerId) {
  const id = String(centerId);

  // Revert any other highlighted markers (keep only this one).
  Array.from(highlightedEvacuationIds).forEach((otherId) => {
    if (otherId === id) { return; }
    highlightedEvacuationIds.delete(otherId);
    const otherMarker = evacuationMarkers[otherId];
    if (otherMarker !== undefined) { applyEvacuationMarkerIcon(otherMarker, false); }
  });

  highlightedEvacuationIds.add(id);
  const marker = evacuationMarkers[id];
  if (marker !== undefined) { applyEvacuationMarkerIcon(marker, true); }
}

/**
 * Remove highlight from a single evacuation marker.
 * 
 * @param {string|number} centerId
 */
function unhighlightEvacuationMarker(centerId) {
  const id = String(centerId);
  highlightedEvacuationIds.delete(id);
  const marker = evacuationMarkers[id];
  if (marker !== undefined) { applyEvacuationMarkerIcon(marker, false); }
}

/**
 * Clear highlights from all evacuation markers.
 */
function clearEvacuationMarkerHighlights() {
  const ids = Array.from(highlightedEvacuationIds);
  highlightedEvacuationIds.clear();
  ids.forEach((id) => {
    const marker = evacuationMarkers[id];
    if (marker !== undefined) { applyEvacuationMarkerIcon(marker, false); }
  });
}

/**
 * Get color based on population density value.
 * Uses a Purples choropleth scale.
 * 
 * @param {number} d - Density value
 * @returns {string} Hex color
 */
function getPopulationDensityColor(d) {
  return d > 20000 ? '#3f007d' :
         d > 12000 ? '#54278f' :
         d > 6000  ? '#6a51a3' :
         d > 3000  ? '#807dba' :
         d > 1000  ? '#9e9ac8' :
         d > 500   ? '#bcbddc' :
                     '#efedf5';
}

/**
 * Get style configuration for a specific layer type.
 * Colors match the UI/UX design tokens.
 * 
 * @param {string} layerType - Type identifier
 * @param {Object} [featureProps] - Properties of the specific feature
 * @param {Object} [options] - Additional style options
 * @returns {Object} Leaflet path style options
 */
function getLayerStyle(layerType, featureProps = {}, options = {}) {
  if (layerType === 'population-density') {
    const density = featureProps['pop den'] ||
                    featureProps['Untitled spreadsheet - Sheet1 (5)_pop den'] || 
                    featureProps['pop_den'] || 
                    featureProps['density'] || 0;
    return {
      fillColor: getPopulationDensityColor(density),
      fillOpacity: 0.7,
      color: 'rgba(255,255,255,0.3)',
      weight: 1,
      ...options
    };
  }

  const styles = {
    'flood-very-high': { fillColor: '#DC2626', fillOpacity: 0.35, color: 'rgba(220,38,38,0.8)', weight: 2 },
    'flood-high': { fillColor: '#F97316', fillOpacity: 0.30, color: 'rgba(249,115,22,0.75)', weight: 2 },
    'flood-moderate': { fillColor: '#EAB308', fillOpacity: 0.25, color: 'rgba(234,179,8,0.7)', weight: 2 },
    'flood-low': { fillColor: '#10B981', fillOpacity: 0.25, color: 'rgba(16,185,129,0.7)', weight: 2 },
    'buffer-500m': { fillColor: '#3B82F6', fillOpacity: 0.15, color: 'rgba(59,130,246,0.5)', weight: 1.5 },
    'buffer-1km': { fillColor: '#06B6D4', fillOpacity: 0.12, color: 'rgba(6,182,212,0.45)', weight: 1.5 },
    'buffer-2km': { fillColor: '#6366F1', fillOpacity: 0.10, color: 'rgba(99,102,241,0.4)', weight: 1.5 },
    'isochrone-5': { fillColor: '#22C55E', fillOpacity: 0.20, color: 'rgba(34,197,94,0.6)', weight: 1.5 },
    'isochrone-10': { fillColor: '#84CC16', fillOpacity: 0.15, color: 'rgba(132,204,22,0.5)', weight: 1.5 },
    'road': { fillOpacity: 0, color: 'rgba(1, 255, 6, 0.6)', weight: 1.5 },
    'highway': { fillOpacity: 0, color: '#01ff06', weight: 2.5 },
    'boundary': { fillColor: '#8B5CF6', fillOpacity: 0.05, color: 'rgba(139,92,246,0.5)', weight: 2.5 },
    'network-analysis': { fillOpacity: 0, color: 'rgba(99,102,241,0.85)', weight: 2.5 },
    'travel-time': { fillOpacity: 0, color: 'rgba(236,72,153,0.6)', weight: 2 },
    'travel-routes': { fillOpacity: 0, color: 'rgba(236,72,153,0.6)', weight: 2 },
    'coverage': { fillColor: '#06B6D4', fillOpacity: 0.1, color: 'rgba(6,182,212,0.4)', weight: 1.5 },
  };

  return { ...styles[layerType] || styles['boundary'], ...options };
}

/**
 * Load flood hazard zones for a specific risk level.
 * 
 * @param {string} riskLevel - 'very_high', 'high', or 'moderate'
 * @returns {Promise<void>}
 */
async function loadFloodZones(riskLevel) {
  const map = getMap();
  if (map === null) { return; }

  const layerKey = `flood-${riskLevel.replace('_', '-')}`;
  const spinnerKey = `flood-${riskLevel.replace('_', '-')}`;

  if (layerCache[layerKey]) { return; }

  showLayerSpinner(spinnerKey);

  const zones = await fetchLayer(`${API_BASE}/flood-zones.php?risk=${riskLevel}`);

  hideLayerSpinner(spinnerKey);

  if (zones === null || zones.length === 0) { return; }

  const styleKey = `flood-${riskLevel.replace('_', '-')}`;

  const geoJsonLayer = L.geoJSON(
    zones.map((z) => ({
      type: 'Feature',
      geometry: z.geometry,
      properties: { barangay: z.barangay, risk_level: z.risk_level, area_sqkm: z.area_sqkm },
    })),
    {
      style: () => getLayerStyle(styleKey),
      onEachFeature: (feature, layer) => {
        const props = feature.properties || {};
        const riskLabel = riskLevel.replace('_', ' ');
        const barangayName = props.barangay || '';

        layer.bindTooltip(
          `<strong>${barangayName}</strong><br>${riskLabel} risk`,
          { sticky: true, className: 'flood-tooltip' }
        );

        // Clicking a flood polygon opens the barangay detail sidebar.
        // This avoids the z-order issue where flood layers above the
        // boundary polygons would otherwise swallow click events.
        if (barangayName !== '') {
          layer.on('click', (e) => {
            if (e.originalEvent && typeof e.originalEvent.stopPropagation === 'function') {
              e.originalEvent.stopPropagation();
            }
            if (typeof openBarangaySidebarByName === 'function') {
              openBarangaySidebarByName(String(barangayName), props);
            }
          });
          if (layer._path) { layer._path.style.cursor = 'pointer'; }
        }
      },
    }
  );

  geoJsonLayer.addTo(map);
  layerCache[layerKey] = geoJsonLayer;
}

/**
 * Normalize a barangay name for fuzzy matching (mirrors PHP logic).
 *
 * @param  {string} name
 * @returns {string}
 */
function normalizeBarangayKey(name) {
  let n = String(name || '').toLowerCase().trim();
  n = n.replace(/\s*\([^)]*\)\s*$/, '');
  n = n.replace(/sta\./g, 'santa').replace(/sto\./g, 'santo').replace(/pob\./g, 'poblacion');
  n = n.replace(/\bdistrict\b/g, '');
  n = n.replace(/\s+/g, ' ').trim();
  return n;
}

/**
 * Load the synthesized "Low" flood-risk layer.
 *
 * Unlike the other risk levels, Low risk has no polygons in the
 * `flood_hazard_zones` table. Instead, we identify low-risk barangays
 * via their `flood_risk_level` column and reuse their boundary
 * polygons from the already-loaded city boundary layer.
 *
 * @returns {Promise<void>}
 */
async function loadFloodZonesLow() {
  const map = getMap();
  if (map === null) { return; }

  const layerKey = 'flood-low';
  if (layerCache[layerKey]) { return; }

  showLayerSpinner('flood-low');

  try {
    // Ensure the boundary layer is loaded so we can borrow its polygons.
    if (layerCache['cabanatuan-boundary'] === undefined) {
      await loadGisLayer('cabanatuan-boundary', 'boundary', 'boundary');
    }
    const boundaryGroup = layerCache['cabanatuan-boundary'];
    if (!boundaryGroup) { return; }

    // Fetch barangay metadata to know which ones are low risk.
    const res = await fetch('api/barangays.php');
    if (!res.ok) { return; }
    const body = await res.json();
    const barangays = (body && body.data) ? body.data : body;
    if (!Array.isArray(barangays)) { return; }

    const lowSet = new Set(
      barangays
        .filter((b) => b && b.flood_risk_level === 'low')
        .map((b) => normalizeBarangayKey(b.name))
    );
    if (lowSet.size === 0) { return; }

    // Collect matching boundary features
    const features = [];
    boundaryGroup.eachLayer((subLayer) => {
      const f = subLayer.feature;
      if (!f || !f.geometry) { return; }
      const props = f.properties || {};
      const rawName =
        props.ADM4_EN || props.ADM3_EN ||
        props.name || props.NAME ||
        props.BARANGAY || props.barangay || props.Barangay || '';
      if (lowSet.has(normalizeBarangayKey(rawName))) {
        features.push({
          type: 'Feature',
          geometry: f.geometry,
          properties: { ...props, barangay: rawName, risk_level: 'low' },
        });
      }
    });

    if (features.length === 0) { return; }

    const geoJsonLayer = L.geoJSON(
      { type: 'FeatureCollection', features },
      {
        style: () => getLayerStyle('flood-low'),
        onEachFeature: (feature, layer) => {
          const props = feature.properties || {};
          const barangayName = props.barangay || '';
          layer.bindTooltip(
            `<strong>${barangayName}</strong><br>low risk`,
            { sticky: true, className: 'flood-tooltip' }
          );
          if (barangayName !== '') {
            layer.on('click', (e) => {
              if (e.originalEvent && typeof e.originalEvent.stopPropagation === 'function') {
                e.originalEvent.stopPropagation();
              }
              if (typeof openBarangaySidebarByName === 'function') {
                openBarangaySidebarByName(String(barangayName), props);
              }
            });
            if (layer._path) { layer._path.style.cursor = 'pointer'; }
          }
        },
      }
    );

    geoJsonLayer.addTo(map);
    layerCache[layerKey] = geoJsonLayer;
  } finally {
    hideLayerSpinner('flood-low');
  }
}

/**
 * Load a GIS layer by slug from the API.
 * 
 * @param {string} slug - Layer slug
 * @param {string} styleKey - Key for getLayerStyle()
 * @param {string} spinnerKey - Key for the loading spinner
 * @returns {Promise<void>}
 */
async function loadGisLayer(slug, styleKey, spinnerKey) {
  const map = getMap();
  if (map === null) { return; }

  if (layerCache[slug]) { return; }

  showLayerSpinner(spinnerKey);

  const layerData = await fetchLayer(`${API_BASE}/layers.php?slug=${slug}`);

  hideLayerSpinner(spinnerKey);

  if (layerData === null || layerData.geojson_data === null) { return; }

  // Skip layers that are hidden in admin
  if (layerData.is_visible === 0 || layerData.is_visible === false) { return; }

  // Pop density has no geometry — join against boundary polygons by barangay name
  if (styleKey === 'population-density') {
    const boundaryLayer = layerCache['cabanatuan-boundary'];
    const features = layerData.geojson_data.features ?? [];
    const hasNullGeom = features.length > 0 && features.every((f) => f.geometry === null);

    if (hasNullGeom && boundaryLayer !== undefined) {
      const densityMap = {};
      features.forEach((f) => {
        const name = (f.properties?.ADM4_EN ?? '').trim().toLowerCase();
        if (name !== '') { densityMap[name] = f.properties; }
      });

      const joined = [];
      boundaryLayer.eachLayer((subLayer) => {
        const props = subLayer.feature?.properties ?? {};
        const bname = (props.ADM4_EN ?? '').trim().toLowerCase();
        const densityProps = densityMap[bname] ?? {};
        joined.push({
          type: 'Feature',
          geometry: subLayer.feature?.geometry ?? null,
          properties: { ...props, ...densityProps },
        });
      });

      if (joined.length > 0) {
        layerData.geojson_data = { type: 'FeatureCollection', features: joined };
      }
    }
  }

  const geoJsonOptions = {
    style: (feature) => {
      // Special handling for isochrone (different style per travel time)
      if (styleKey === 'isochrone') {
        const minutes = feature.properties?.AA_MINS || feature.properties?.aa_mins || 10;
        return getLayerStyle(minutes <= 5 ? 'isochrone-5' : 'isochrone-10');
      }
      return getLayerStyle(styleKey, feature.properties || {});
    },
    onEachFeature: (feature, layer) => {
      const props = feature.properties || {};
      let tooltipContent = '';

      if (styleKey === 'population-density') {
        const density = props['pop den'] || props['Untitled spreadsheet - Sheet1 (5)_pop den'] || props['pop_den'] || props['density'] || 0;
        tooltipContent = `<strong>${props.ADM4_EN || 'Barangay'}</strong><br>Density: ${Math.round(density).toLocaleString()} /km²`;
      } else if (styleKey === 'network-analysis') {
        const dest = props.end || props.destination || 'Destination';
        const cost = props.cost !== undefined ? `${Math.round(Number(props.cost)).toLocaleString()} m` : 'N/A';
        tooltipContent = `<strong>Route to Evacuation Center</strong><br>Distance: ${cost}`;
      } else if (styleKey === 'road') {
        tooltipContent = `<strong>${props.name || 'Road'}</strong>`;
      } else if (styleKey === 'travel-time' || styleKey === 'travel-routes') {
        const barangay = props.layer || props.barangay || 'Destination';
        const time = props['TIME FINAL'] || props.time || 'N/A';
        tooltipContent = `<strong>Travel Route to ${barangay.toUpperCase()}</strong><br>Estimated Time: ${time}`;
      } else if (styleKey.startsWith('buffer-')) {
        const bufferSize = styleKey === 'buffer-500m' ? '500m' : (styleKey === 'buffer-1km' ? '1km' : '2km');
        const facility = props.Facility_N || props['Facility Name'] || 'Facility';
        tooltipContent = `<strong>${facility}</strong><br><span style="font-size: 0.85em; opacity: 0.8;">${bufferSize} Buffer</span>`;
      } else if (props.Facility_N || props['Facility Name']) {
        tooltipContent = `<strong>${props.Facility_N || props['Facility Name']}</strong>`;
      } else if (props.ADM4_EN) {
        tooltipContent = `<strong>${props.ADM4_EN}</strong>`;
      } else if (props.AA_MINS || props.aa_mins) {
        tooltipContent = `${props.AA_MINS || props.aa_mins} min walk`;
      }

      if (tooltipContent !== '') {
        layer.bindTooltip(tooltipContent, { sticky: true });
      }

      // Boundary polygons: clicking one opens the barangay detail sidebar.
      if (styleKey === 'boundary') {
        const barangayName =
          props.ADM4_EN || props.ADM3_EN ||
          props.name    || props.NAME    ||
          props.BARANGAY || props.barangay || props.Barangay || '';

        if (barangayName !== '') {
          layer.on('click', (e) => {
            // Stop click bubbling to the map / cluster handler
            if (e.originalEvent && typeof e.originalEvent.stopPropagation === 'function') {
              e.originalEvent.stopPropagation();
            }
            if (typeof openBarangaySidebarByName === 'function') {
              openBarangaySidebarByName(String(barangayName), props);
            }
          });
          // Add a pointer cursor so users know it's interactive
          if (layer.getElement && typeof layer.getElement === 'function') {
            const el = layer.getElement();
            if (el !== null && el !== undefined) { el.style.cursor = 'pointer'; }
          } else if (layer._path) {
            layer._path.style.cursor = 'pointer';
          }
        }
      }
    },
    pointToLayer: (feature, latlng) => {
      return L.circleMarker(latlng, {
        radius: 3,
        ...getLayerStyle(styleKey),
      });
    },
  };

  // Manual PRS92 to WGS84 Coordinate Conversion
  if (layerData.geojson_data.crs && typeof proj4 !== 'undefined') {
    const crsName = layerData.geojson_data.crs.properties?.name || '';
    if (crsName.includes('3123') || crsName.includes('PRS92')) {
      const transform = proj4("EPSG:3123", "EPSG:4326");

      function convertCoords(coords, depth) {
        if (depth === 0) {
          const pt = transform.forward([coords[0], coords[1]]);
          coords[0] = pt[0]; // lng
          coords[1] = pt[1]; // lat
        } else {
          for (let i = 0; i < coords.length; i++) {
            convertCoords(coords[i], depth - 1);
          }
        }
      }

      layerData.geojson_data.features.forEach(feature => {
        if (!feature.geometry || !feature.geometry.coordinates) return;
        const type = feature.geometry.type;
        let depth = 0;
        if (type === 'LineString' || type === 'MultiPoint') depth = 1;
        else if (type === 'Polygon' || type === 'MultiLineString') depth = 2;
        else if (type === 'MultiPolygon') depth = 3;
        
        convertCoords(feature.geometry.coordinates, depth);
      });

      // Remove custom CRS so Leaflet doesn't get confused
      delete layerData.geojson_data.crs;
    }
  }

  let geoJsonLayer;
  try {
    geoJsonLayer = L.geoJSON(layerData.geojson_data, geoJsonOptions);
  } catch (err) {
    console.error(`[LayerService] Failed to render layer ${slug}`, err);
    return;
  }

  geoJsonLayer.addTo(map);

  layerCache[slug] = geoJsonLayer;
}

/**
 * Load all network analysis route layers from the API (fetched by type).
 * All per-barangay route GeoJSON files are merged into one Leaflet layer.
 *
 * @returns {Promise<void>}
 */
async function loadNetworkAnalysis() {
  const map = getMap();
  if (map === null) { return; }

  const cacheKey = 'network-analysis';
  if (layerCache[cacheKey]) { return; }

  showLayerSpinner('network-analysis');

  const layers = await fetchLayer(`${API_BASE}/layers.php?type=network_analysis`);

  hideLayerSpinner('network-analysis');

  if (layers === null || !Array.isArray(layers) || layers.length === 0) { return; }

  const allFeatures = [];
  layers.forEach((layerData) => {
    const features = layerData.geojson_data?.features ?? [];
    features.forEach((f) => {
      allFeatures.push({
        type: 'Feature',
        geometry: f.geometry,
        properties: { ...f.properties, _barangay: layerData.name },
      });
    });
  });

  if (allFeatures.length === 0) { return; }

  const geoJsonLayer = L.geoJSON(
    { type: 'FeatureCollection', features: allFeatures },
    {
      style: () => getLayerStyle('network-analysis'),
      onEachFeature: (feature, layer) => {
        const props = feature.properties || {};
        const barangay = props._barangay || '';
        const cost = props.cost !== undefined ? `${Math.round(Number(props.cost)).toLocaleString()} m` : 'N/A';
        const label = barangay ? `<strong>${barangay}</strong><br>` : '';
        layer.bindTooltip(`${label}Route Distance: ${cost}`, { sticky: true });
      },
    }
  );

  geoJsonLayer.addTo(map);
  layerCache[cacheKey] = geoJsonLayer;
}

/**
 * Per-buffer-slug index of facility name -> array of sub-layers.
 * Populated lazily after a buffer layer loads.
 * @type {Object<string, Object<string, Array>>}
 */
const bufferFacilityLayers = {};

/**
 * Build facility index for a buffer layer from its loaded features.
 * Safe to call repeatedly (idempotent).
 * 
 * @param {string} slug - Buffer slug (e.g. 'buffer-500m')
 * @returns {Object<string, Array>} Facility name -> sub-layers
 */
function indexBufferFacilities(slug) {
  if (bufferFacilityLayers[slug]) { return bufferFacilityLayers[slug]; }

  const group = layerCache[slug];
  if (group === undefined || typeof group.eachLayer !== 'function') {
    return {};
  }

  const index = {};
  group.eachLayer((sublayer) => {
    const props = sublayer.feature?.properties || {};
    const name = props.Facility_N || props['Facility Name'] || 'Unnamed Facility';
    if (!index[name]) { index[name] = []; }
    index[name].push(sublayer);
  });

  bufferFacilityLayers[slug] = index;
  return index;
}

/**
 * Get sorted list of unique facility names for a buffer layer.
 * 
 * @param {string} slug - Buffer slug
 * @returns {Array<string>}
 */
function getBufferFacilities(slug) {
  const index = bufferFacilityLayers[slug];
  if (!index) { return []; }
  return Object.keys(index).sort((a, b) => a.localeCompare(b));
}

/**
 * Show or hide all sub-layers belonging to a specific facility
 * within a buffer layer group.
 * 
 * @param {string} slug - Buffer slug
 * @param {string} facility - Facility name
 * @param {boolean} visible - Whether to show or hide
 */
function setBufferFacilityVisible(slug, facility, visible) {
  const group = layerCache[slug];
  const index = bufferFacilityLayers[slug];
  if (group === undefined || !index || !index[facility]) { return; }

  index[facility].forEach((sublayer) => {
    if (visible) {
      if (!group.hasLayer(sublayer)) { group.addLayer(sublayer); }
    } else {
      if (group.hasLayer(sublayer)) { group.removeLayer(sublayer); }
    }
  });
}

/**
 * Per-slug index: facility name -> { minutes bucket -> [sub-layers] }
 * Used for Isochrone (Walking) layer two-level grouping.
 * @type {Object<string, Object<string, Object<number, Array>>>}
 */
const isochroneFacilityIndex = {};

/**
 * Build facility-and-minutes index for isochrone layer.
 * Each feature has AA_MINS (or aa_mins) and optionally Facility_N.
 * Minutes are bucketed into 5 or 10.
 * 
 * @param {string} slug - Layer slug (e.g. 'isochrone-layers')
 * @returns {Object<string, Object<number, Array>>}
 */
function indexIsochroneFacilities(slug) {
  if (isochroneFacilityIndex[slug]) { return isochroneFacilityIndex[slug]; }

  const group = layerCache[slug];
  if (group === undefined || typeof group.eachLayer !== 'function') {
    return {};
  }

  const index = {};
  group.eachLayer((sublayer) => {
    const props = sublayer.feature?.properties || {};
    const name = props.Facility_N || props['Facility Name'] || 'Unnamed Facility';
    const rawMins = props.AA_MINS ?? props.aa_mins ?? 10;
    const bucket = Number(rawMins) <= 5 ? 5 : 10;

    if (!index[name]) { index[name] = {}; }
    if (!index[name][bucket]) { index[name][bucket] = []; }
    index[name][bucket].push(sublayer);
  });

  isochroneFacilityIndex[slug] = index;
  return index;
}

/**
 * Get sorted array of { name, mins: [5, 10] } for the isochrone layer.
 * 
 * @param {string} slug - Layer slug
 * @returns {Array<{name: string, mins: Array<number>}>}
 */
function getIsochroneFacilities(slug) {
  const index = isochroneFacilityIndex[slug];
  if (!index) { return []; }

  return Object.keys(index)
    .sort((a, b) => a.localeCompare(b))
    .map((name) => ({
      name,
      mins: Object.keys(index[name]).map(Number).sort((a, b) => a - b),
    }));
}

/**
 * Show or hide isochrone sub-layers for a specific facility + minute bucket.
 * 
 * @param {string} slug
 * @param {string} facility
 * @param {number} mins - 5 or 10
 * @param {boolean} visible
 */
function setIsochroneFacilityMinsVisible(slug, facility, mins, visible) {
  const group = layerCache[slug];
  const index = isochroneFacilityIndex[slug];
  if (group === undefined || !index || !index[facility] || !index[facility][mins]) {
    return;
  }

  index[facility][mins].forEach((sublayer) => {
    if (visible) {
      if (!group.hasLayer(sublayer)) { group.addLayer(sublayer); }
    } else {
      if (group.hasLayer(sublayer)) { group.removeLayer(sublayer); }
    }
  });
}

/**
 * Toggle a layer's visibility on the map.
 * 
 * @param {string} layerKey - Key in layerCache
 * @param {boolean} visible - Show or hide
 */
function toggleLayerVisibility(layerKey, visible) {
  const map = getMap();
  if (map === null) { return; }

  const layer = layerCache[layerKey];
  if (layer === undefined) { return; }

  if (visible) {
    if (!map.hasLayer(layer)) {
      map.addLayer(layer);
    }
  } else {
    if (map.hasLayer(layer)) {
      map.removeLayer(layer);
    }
  }
}

/**
 * Show loading spinner next to a layer toggle.
 * 
 * @param {string} key - Spinner key (matches element ID: spinner-{key})
 */
function showLayerSpinner(key) {
  const spinner = document.getElementById(`spinner-${key}`);
  if (spinner !== null) { spinner.classList.add('active'); }
}

/**
 * Hide loading spinner.
 * 
 * @param {string} key - Spinner key
 */
function hideLayerSpinner(key) {
  const spinner = document.getElementById(`spinner-${key}`);
  if (spinner !== null) { spinner.classList.remove('active'); }
}

/**
 * Get all cached evacuation center data.
 * 
 * @returns {Array<Object>}
 */
function getEvacuationCentersData() {
  return evacuationCentersData;
}

/** @type {L.GeoJSON|null} Currently displayed coverage glow layer */
let activeCoverageLayer = null;

/**
 * Convert a human-readable center name to the expected coverage slug format.
 * e.g. "Araullo University" → "araullo-university"
 * 
 * @param {string} name
 * @returns {string}
 */
function nameToSlug(name) {
  return name
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, '')
    .trim()
    .replace(/\s+/g, '-');
}

/**
 * Remove the active coverage glow layer from the map.
 */
function hideCoverageHighlight() {
  const map = getMap();
  if (activeCoverageLayer !== null && map !== null) {
    if (map.hasLayer(activeCoverageLayer)) {
      map.removeLayer(activeCoverageLayer);
    }
    activeCoverageLayer = null;
  }
}

/**
 * Fetch and display the coverage polygon for an evacuation center.
 * Matches the center name to a coverage layer slug in the DB.
 * If no matching coverage layer is found, does nothing silently.
 *
 * @param {string} centerName - The evacuation center name
 * @param {function(Array<{name:string,area:string|null}>):void} [onLoaded] - Called with barangay list once loaded
 * @returns {Promise<void>}
 */
async function showCoverageForCenter(centerName, onLoaded) {
  hideCoverageHighlight();

  const map = getMap();
  if (map === null || !centerName) { return; }

  const slug = nameToSlug(centerName);

  let layerData = null;
  try {
    const res = await fetch(`${API_BASE}/layers.php?type=coverage`);
    if (!res.ok) { return; }
    const json = await res.json();
    if (!json.success || !Array.isArray(json.data)) { return; }

    const match = json.data.find((l) => {
      const lSlug = l.slug ?? '';
      const lName = nameToSlug(l.name ?? '');
      return lSlug === slug || lName === slug;
    });

    if (!match) { return; }

    const detailRes = await fetch(`${API_BASE}/layers.php?slug=${match.slug}`);
    if (!detailRes.ok) { return; }
    const detailJson = await detailRes.json();
    if (!detailJson.success) { return; }
    layerData = detailJson.data;
  } catch {
    return;
  }

  if (!layerData?.geojson_data) { return; }

  // Build barangay list for the sidebar and pass it to the caller
  const features = layerData.geojson_data.features ?? [];
  const barangays = features
    .map((f) => {
      const props = f.properties || {};
      const name = props.ADM4_EN || '';
      const area = props.AREA_SQKM != null ? Number(props.AREA_SQKM).toFixed(2) : null;
      return name ? { name, area } : null;
    })
    .filter(Boolean)
    .sort((a, b) => a.name.localeCompare(b.name));

  if (typeof onLoaded === 'function') {
    onLoaded(barangays);
  }

  try {
    activeCoverageLayer = L.geoJSON(layerData.geojson_data, {
      style: () => ({
        stroke: true,
        color: '#06B6D4',
        weight: 3,
        opacity: 1,
        fill: true,
        fillColor: '#06B6D4',
        fillOpacity: 0.18,
        className: 'leaflet-coverage--glow',
      }),
      onEachFeature: (feature, layer) => {
        const props = feature.properties || {};
        const barangay = props.ADM4_EN || '';
        if (barangay) {
          layer.bindTooltip(
            `<strong>${centerName}</strong><br><span style="font-size:0.85em;opacity:0.8;">Coverage · ${barangay}</span>`,
            { sticky: true }
          );
        }
      },
    });

    activeCoverageLayer.addTo(map);
  } catch {
    activeCoverageLayer = null;
  }
}
