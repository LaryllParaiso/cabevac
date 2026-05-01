/**
 * CabEvac — Location Service
 * Handles geolocation, manual pinning, and nearest center discovery.
 * 
 * @module locationService
 */

let userMarker = null;
let isPinMode = false;
let evacuationCenters = [];

/**
 * Initialize Location Service
 * @param {L.Map} map - Leaflet map instance
 */
function initLocationService(map) {
  const locateBtn = document.getElementById('locate-me-btn');
  const pinModeBtn = document.getElementById('pin-mode-btn');
  const coordBtn = document.getElementById('coord-input-btn');
  const coordPanel = document.getElementById('coord-panel');
  const applyCoordBtn = document.getElementById('apply-coord-btn');
  
  // 1. Locate Me
  locateBtn.addEventListener('click', () => {
    if (!navigator.geolocation) {
      showToast('Geolocation is not supported by your browser', 'error');
      return;
    }
    
    locateBtn.classList.add('active');
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const { latitude, longitude } = position.coords;
        updateUserLocation(map, latitude, longitude, true);
        locateBtn.classList.remove('active');
        showToast('Location detected', 'success');
      },
      (error) => {
        locateBtn.classList.remove('active');
        showToast('Unable to retrieve your location', 'error');
      },
      { enableHighAccuracy: true }
    );
  });

  // 2. Pin Mode
  pinModeBtn.addEventListener('click', () => {
    isPinMode = !isPinMode;
    pinModeBtn.classList.toggle('active', isPinMode);
    
    if (isPinMode) {
      map.getContainer().style.cursor = 'crosshair';
      showToast('Click anywhere on the map to set location', 'info');
    } else {
      map.getContainer().style.cursor = '';
    }
  });

  map.on('click', (e) => {
    if (!isPinMode) return;
    updateUserLocation(map, e.latlng.lat, e.latlng.lng, false);
    
    // Auto-disable pin mode after one click for better UX
    isPinMode = false;
    pinModeBtn.classList.remove('active');
    map.getContainer().style.cursor = '';
  });

  // 3. Coordinate Input
  coordBtn.addEventListener('click', () => {
    const isHidden = coordPanel.getAttribute('aria-hidden') === 'true';
    coordPanel.setAttribute('aria-hidden', !isHidden);
  });

  document.getElementById('close-coord-btn').addEventListener('click', () => {
    coordPanel.setAttribute('aria-hidden', 'true');
  });

  applyCoordBtn.addEventListener('click', () => {
    const lat = parseFloat(document.getElementById('input-lat').value);
    const lng = parseFloat(document.getElementById('input-lng').value);
    
    if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
      showToast('Please enter valid coordinates', 'warning');
      return;
    }
    
    updateUserLocation(map, lat, lng, true);
    coordPanel.setAttribute('aria-hidden', 'true');
  });

  // 4. Close Nearest Card — also clear user marker
  document.getElementById('close-nearest-btn').addEventListener('click', () => {
    document.getElementById('nearest-card').setAttribute('aria-hidden', 'true');
    clearUserMarker(map);
  });
}

/**
 * Remove user location marker from the map.
 */
function clearUserMarker(map) {
  if (userMarker !== null && map !== undefined) {
    map.removeLayer(userMarker);
    userMarker = null;
  }
}

/**
 * Compute responsive padding for fitBounds based on viewport.
 * Accounts for the search bar at top and the nearest-card / legend at bottom-left.
 */
function getResponsiveBoundsPadding() {
  const width = window.innerWidth;
  if (width < 480) {
    return { topLeft: [20, 120], bottomRight: [20, 260] };
  }
  if (width < 768) {
    return { topLeft: [40, 140], bottomRight: [40, 220] };
  }
  if (width < 1024) {
    return { topLeft: [60, 120], bottomRight: [60, 180] };
  }
  return { topLeft: [80, 120], bottomRight: [320, 180] };
}

/**
 * Update user location on map and find nearest center
 */
function updateUserLocation(map, lat, lng, zoom = true) {
  if (userMarker !== null) {
    userMarker.setLatLng([lat, lng]);
  } else {
    const pinIcon = L.divIcon({
      className: 'user-location-pin',
      html: '<div class="user-location-pin__emoji">\uD83D\uDCCD</div>',
      iconSize: [36, 36],
      iconAnchor: [18, 34],
    });
    userMarker = L.marker([lat, lng], { icon: pinIcon, zIndexOffset: 1000 }).addTo(map);
  }

  findAndDisplayNearestCenter(lat, lng, zoom ? map : null);
}

/**
 * Fetch centers if not already loaded and find nearest
 */
async function findAndDisplayNearestCenter(lat, lng, mapForZoom = null) {
  try {
    // If we don't have centers yet, fetch them
    if (evacuationCenters.length === 0) {
      const response = await fetch('api/centers.php');
      const result = await response.json();
      if (result.success) {
        evacuationCenters = result.data;
      }
    }

    if (evacuationCenters.length === 0) { return; }

    let nearest = null;
    let minDistance = Infinity;

    evacuationCenters.forEach((center) => {
      const dist = calculateDistance(lat, lng, center.latitude, center.longitude);
      if (dist < minDistance) {
        minDistance = dist;
        nearest = center;
      }
    });

    if (nearest === null) { return; }

    // Highlight the nearest marker in sky-blue.
    if (typeof highlightEvacuationMarker === 'function') {
      highlightEvacuationMarker(nearest.id);
    }

    displayNearestCard(nearest, minDistance);

    if (mapForZoom !== null) {
      const bounds = L.latLngBounds([
        [lat, lng],
        [Number(nearest.latitude), Number(nearest.longitude)],
      ]);
      const pad = getResponsiveBoundsPadding();
      mapForZoom.flyToBounds(bounds, {
        paddingTopLeft: pad.topLeft,
        paddingBottomRight: pad.bottomRight,
        maxZoom: 16,
        duration: 1.2,
      });
    }
  } catch (err) {
    console.error('Failed to find nearest center:', err);
  }
}

/**
 * Calculate distance between two points in km (Haversine)
 */
function calculateDistance(lat1, lon1, lat2, lon2) {
  const R = 6371; // Earth's radius in km
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = 
    Math.sin(dLat/2) * Math.sin(dLat/2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
    Math.sin(dLon/2) * Math.sin(dLon/2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R * c;
}

/**
 * Display the nearest center result card
 */
function displayNearestCard(center, distance) {
  const card = document.getElementById('nearest-card');
  const nameEl = document.getElementById('nearest-name');
  const distEl = document.getElementById('nearest-distance');
  const dirBtn = document.getElementById('nearest-directions-btn');

  nameEl.textContent = center.name;
  distEl.textContent = `${distance.toFixed(2)} km away`;
  
  dirBtn.onclick = () => {
    window.open(`https://www.google.com/maps/dir/?api=1&destination=${center.latitude},${center.longitude}`, '_blank');
  };

  card.setAttribute('aria-hidden', 'false');
}
