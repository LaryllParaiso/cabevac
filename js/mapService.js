/**
 * CabEvac — Map Service
 * Initializes the Leaflet map, base tile layer, zoom controls,
 * and provides map utility functions.
 * 
 * @module mapService
 */

/** @type {L.Map|null} */
let mapInstance = null;

/** @type {L.TileLayer|null} */
let currentTileLayer = null;

// Define PRS92 / Philippines zone 3 for GeoJSON layers
if (typeof proj4 !== 'undefined') {
  proj4.defs("EPSG:3123", "+proj=tmerc +lat_0=10.66666666666667 +lon_0=121 +k=0.99995 +x_0=500000 +y_0=0 +ellps=clrk66 +towgs84=-127.62,-67.24,-47.04,-3.068,4.903,1.578,-1.06 +units=m +no_defs +type=crs");
  proj4.defs("urn:ogc:def:crs:EPSG::3123", proj4.defs("EPSG:3123"));
}

/** Available basemap tile layer configurations */
const BASEMAPS = {
  dark: {
    name: 'Dark',
    url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/">CARTO</a>',
    options: { subdomains: 'abcd', maxZoom: 20 },
  },
  light: {
    name: 'Light',
    url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/">CARTO</a>',
    options: { subdomains: 'abcd', maxZoom: 20 },
  },
  streets: {
    name: 'Streets',
    url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    options: { maxZoom: 19 },
  },
  satellite: {
    name: 'Satellite',
    url: 'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
    attribution: '&copy; Google',
    options: { maxZoom: 20, subdomains: [] },
  },
  terrain: {
    name: 'Terrain',
    url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
    attribution: '&copy; <a href="https://opentopomap.org">OpenTopoMap</a>',
    options: { maxZoom: 17 },
  },
  topo: {
    name: 'Nat Geo',
    url: 'https://services.arcgisonline.com/ArcGIS/rest/services/NatGeo_World_Map/MapServer/tile/{z}/{y}/{x}',
    attribution: '&copy; Esri, National Geographic',
    options: { maxZoom: 16, subdomains: [] },
  },
};

/**
 * Initialize the Leaflet map.
 * 
 * @param {string} containerId - DOM element ID for the map container
 * @returns {L.Map} The initialized map instance
 */
function initializeMap(containerId) {
  const isMobile = window.innerWidth < 768;

  mapInstance = L.map(containerId, {
    center: isMobile ? [15.486, 120.965] : [15.500, 121.000],
    zoom: isMobile ? 11.3 : 13,
    minZoom: 10,
    maxZoom: 19,
    zoomControl: false,
    attributionControl: true,
  });

  addBaseTileLayer();
  // addZoomControls();

  let wasMobile = isMobile;
  window.addEventListener('resize', () => {
    const nowMobile = window.innerWidth < 768;
    if (nowMobile !== wasMobile) {
      wasMobile = nowMobile;
      mapInstance.setView(
        [15.486, 121.000],
        nowMobile ? 11 : 13
      );
    }
  });

  return mapInstance;
}

/**
 * Add the default base tile layer (Dark).
 */
function addBaseTileLayer() {
  switchBasemap('dark');
}

/**
 * Switch the basemap tile layer.
 * 
 * @param {string} basemapKey - Key from BASEMAPS object
 */
function switchBasemap(basemapKey) {
  if (mapInstance === null) { return; }

  const config = BASEMAPS[basemapKey];
  if (config === undefined) { return; }

  if (currentTileLayer !== null) {
    mapInstance.removeLayer(currentTileLayer);
  }

  currentTileLayer = L.tileLayer(config.url, {
    attribution: config.attribution,
    ...config.options,
  });

  currentTileLayer.addTo(mapInstance);
  currentTileLayer.bringToBack();
}

/**
 * Add zoom controls to the bottom-right of the map.
 */
function addZoomControls() {
  if (mapInstance === null) { return; }

  L.control.zoom({ position: 'bottomright' }).addTo(mapInstance);
}

/**
 * Create a custom DivIcon for evacuation center markers.
 * Green pulsing circle by default; sky-blue when highlighted
 * (nearest center, search target, or modal selection).
 * 
 * @param {boolean} [highlighted=false] - Use the sky-blue highlighted style
 * @returns {L.DivIcon} Custom marker icon
 */
function createEvacuationMarkerIcon(highlighted = false) {
  const cls = highlighted ? 'marker-evacuation marker-evacuation--highlight' : 'marker-evacuation';
  return L.divIcon({
    className: '',
    html: `<div class="${cls}"></div>`,
    iconSize: [28, 28],
    iconAnchor: [14, 14],
    popupAnchor: [0, -16],
  });
}

/**
 * Get the current map instance.
 * 
 * @returns {L.Map|null}
 */
function getMap() {
  return mapInstance;
}

/**
 * Fly the map to a specific point with animation.
 * 
 * @param {number} lat - Latitude
 * @param {number} lng - Longitude
 * @param {number} [zoom=16] - Target zoom level
 */
function flyToPoint(lat, lng, zoom = 16) {
  if (mapInstance === null) { return; }
  mapInstance.flyTo([lat, lng], zoom, { duration: 1.5 });
}

/**
 * Fit the map to given bounds with padding.
 * 
 * @param {L.LatLngBounds} bounds - Map bounds to fit
 */
function fitToBounds(bounds) {
  if (mapInstance === null) { return; }
  mapInstance.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
}
