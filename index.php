<?php
/**
 * CabEvac — Public Map Page
 * Main entry point for the flood evacuation mapping system.
 * 
 * @package CabEvac
 */

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CabEvac — Cabanatuan City Flood Evacuation Map</title>
  <meta name="description" content="Interactive flood risk and evacuation mapping system for Cabanatuan City, Nueva Ecija. Find evacuation centers, check flood hazard zones, and get directions.">
  <meta property="og:title" content="CabEvac — Flood Evacuation Map">
  <meta property="og:description" content="Find evacuation centers and check flood risk in Cabanatuan City">
  <meta property="og:type" content="website">
  <link rel="canonical" href="http://localhost/cabevac/">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <!-- Leaflet MarkerCluster CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">

  <!-- App CSS -->
  <link rel="stylesheet" href="css/index.css">
</head>
<body>

  <!-- FLOATING SEARCH BAR -->
  <div class="search-container" id="search-container">
    <div class="search-pill glass-panel">
      <button class="search-pill__menu" id="hamburger-btn" aria-label="Open menu">
        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
      </button>
      
      <div class="search-wrapper" id="search-wrapper">
        <input
          type="search"
          class="search-input"
          id="search-input"
          placeholder="Search evacuation centers..."
          aria-label="Search evacuation centers"
          autocomplete="off"
        >
        <button class="search-clear" id="search-clear" aria-label="Clear search">
          <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </div>

      <div class="search-divider"></div>

      <button class="search-pill__action" id="search-submit-btn" aria-label="Search">
        <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      </button>
    </div>
    
    <div class="search-results" id="search-results" role="listbox" aria-label="Search results"></div>
  </div>

  <!-- FLOATING FILTER CHIPS -->
  <div class="floating-filters" id="filter-chips">
    <button class="filter-chip active" data-risk="all">All Risk</button>
    <button class="filter-chip" data-risk="very_high">Very High</button>
    <button class="filter-chip" data-risk="high">High</button>
    <button class="filter-chip" data-risk="moderate">Moderate</button>
    <button class="filter-chip" data-risk="low">Low</button>
  </div>

  <!-- MAP -->
  <main id="map-container" role="main" aria-label="Interactive flood risk map of Cabanatuan City"></main>

  <!-- LAYER CONTROL PANEL -->
  <aside class="layer-panel glass-panel" id="layer-panel" role="complementary" aria-label="Map layer controls">
    <div class="layer-panel__header">
      <span class="layer-panel__title">Map Layers</span>
      <button class="layer-panel__toggle" id="layer-panel-toggle" aria-label="Collapse layer panel">−</button>
    </div>
    <div id="layer-panel-body">
      <!-- Basemap Selector -->
      <div class="layer-section__label">Basemap</div>
      <div class="basemap-selector" id="basemap-selector" role="radiogroup" aria-label="Select basemap style">
        <label class="basemap-option active" data-basemap="dark" role="radio" aria-checked="true" tabindex="0">
          <span class="basemap-option__preview basemap-option__preview--dark"></span>
          <span class="basemap-option__name">Dark</span>
        </label>
        <label class="basemap-option" data-basemap="light" role="radio" aria-checked="false" tabindex="0">
          <span class="basemap-option__preview basemap-option__preview--light"></span>
          <span class="basemap-option__name">Light</span>
        </label>
        <label class="basemap-option" data-basemap="streets" role="radio" aria-checked="false" tabindex="0">
          <span class="basemap-option__preview basemap-option__preview--streets"></span>
          <span class="basemap-option__name">Streets</span>
        </label>
        <label class="basemap-option" data-basemap="satellite" role="radio" aria-checked="false" tabindex="0">
          <span class="basemap-option__preview basemap-option__preview--satellite"></span>
          <span class="basemap-option__name">Satellite</span>
        </label>
        <label class="basemap-option" data-basemap="terrain" role="radio" aria-checked="false" tabindex="0">
          <span class="basemap-option__preview basemap-option__preview--terrain"></span>
          <span class="basemap-option__name">Terrain</span>
        </label>
        <label class="basemap-option" data-basemap="topo" role="radio" aria-checked="false" tabindex="0">
          <span class="basemap-option__preview basemap-option__preview--topo"></span>
          <span class="basemap-option__name">Nat Geo</span>
        </label>
      </div>

      <!-- Hazard Data -->
      <div class="layer-section__label">Hazard Data</div>
      <label class="layer-item" for="layer-evacuation">
        <div class="layer-item__indicator" style="background: var(--color-evacuation); border-radius: 50%;"></div>
        <input type="checkbox" id="layer-evacuation" checked>
        <span class="layer-item__label">Evacuation Centers</span>
        <span class="layer-item__spinner" id="spinner-evacuation"></span>
      </label>
      <label class="layer-item" for="layer-flood-very-high">
        <div class="layer-item__indicator" style="background: var(--color-risk-very-high);"></div>
        <input type="checkbox" id="layer-flood-very-high" checked>
        <span class="layer-item__label">Very High Risk</span>
        <span class="layer-item__spinner" id="spinner-flood-very-high"></span>
      </label>
      <label class="layer-item" for="layer-flood-high">
        <div class="layer-item__indicator" style="background: var(--color-risk-high);"></div>
        <input type="checkbox" id="layer-flood-high" checked>
        <span class="layer-item__label">High Risk</span>
        <span class="layer-item__spinner" id="spinner-flood-high"></span>
      </label>
      <label class="layer-item" for="layer-flood-moderate">
        <div class="layer-item__indicator" style="background: var(--color-risk-moderate);"></div>
        <input type="checkbox" id="layer-flood-moderate" checked>
        <span class="layer-item__label">Moderate Risk</span>
        <span class="layer-item__spinner" id="spinner-flood-moderate"></span>
      </label>
      <label class="layer-item" for="layer-flood-low">
        <div class="layer-item__indicator" style="background: var(--color-risk-low);"></div>
        <input type="checkbox" id="layer-flood-low" checked>
        <span class="layer-item__label">Low Risk</span>
        <span class="layer-item__spinner" id="spinner-flood-low"></span>
      </label>
      <div class="layer-buffer" data-slug="buffer-500m">
        <div class="layer-buffer__head">
          <label class="layer-item" for="layer-buffer-500">
            <div class="layer-item__indicator" style="background: #3B82F6;"></div>
            <input type="checkbox" id="layer-buffer-500">
            <span class="layer-item__label">Buffer 500m</span>
            <span class="layer-item__spinner" id="spinner-buffer-500"></span>
          </label>
          <button type="button" class="layer-buffer__chevron" aria-expanded="false" aria-controls="sublist-buffer-500m" aria-label="Show Buffer 500m facilities">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
          </button>
        </div>
        <div class="buffer-sublist" id="sublist-buffer-500m" data-slug="buffer-500m"></div>
      </div>
      <div class="layer-buffer" data-slug="buffer-1km">
        <div class="layer-buffer__head">
          <label class="layer-item" for="layer-buffer-1km">
            <div class="layer-item__indicator" style="background: #06B6D4;"></div>
            <input type="checkbox" id="layer-buffer-1km">
            <span class="layer-item__label">Buffer 1km</span>
            <span class="layer-item__spinner" id="spinner-buffer-1km"></span>
          </label>
          <button type="button" class="layer-buffer__chevron" aria-expanded="false" aria-controls="sublist-buffer-1km" aria-label="Show Buffer 1km facilities">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
          </button>
        </div>
        <div class="buffer-sublist" id="sublist-buffer-1km" data-slug="buffer-1km"></div>
      </div>
      <div class="layer-buffer" data-slug="buffer-2km">
        <div class="layer-buffer__head">
          <label class="layer-item" for="layer-buffer-2km">
            <div class="layer-item__indicator" style="background: #6366F1;"></div>
            <input type="checkbox" id="layer-buffer-2km">
            <span class="layer-item__label">Buffer 2km</span>
            <span class="layer-item__spinner" id="spinner-buffer-2km"></span>
          </label>
          <button type="button" class="layer-buffer__chevron" aria-expanded="false" aria-controls="sublist-buffer-2km" aria-label="Show Buffer 2km facilities">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
          </button>
        </div>
        <div class="buffer-sublist" id="sublist-buffer-2km" data-slug="buffer-2km"></div>
      </div>
      <div class="layer-buffer" data-slug="isochrone-layers">
        <div class="layer-buffer__head">
          <label class="layer-item" for="layer-isochrone">
            <div class="layer-item__indicator" style="background: #22C55E;"></div>
            <input type="checkbox" id="layer-isochrone">
            <span class="layer-item__label">Isochrone (Walking)</span>
            <span class="layer-item__spinner" id="spinner-isochrone"></span>
          </label>
          <button type="button" class="layer-buffer__chevron" aria-expanded="false" aria-controls="sublist-isochrone-layers" aria-label="Show Isochrone facilities">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
          </button>
        </div>
        <div class="buffer-sublist" id="sublist-isochrone-layers" data-slug="isochrone-layers"></div>
      </div>

      <!-- Analysis -->
      <div class="layer-section__label">Analysis</div>
      <label class="layer-item" for="layer-network-analysis">
        <div class="layer-item__indicator layer-item__indicator--line" style="background: #6366F1; height: 3px; width: 14px; border-radius: 2px;"></div>
        <input type="checkbox" id="layer-network-analysis">
        <span class="layer-item__label">Network Analysis</span>
        <span class="layer-item__spinner" id="spinner-network-analysis"></span>
      </label>
      <label class="layer-item" for="layer-road">
        <div class="layer-item__indicator layer-item__indicator--line" style="background: var(--color-text-tertiary); height: 3px; width: 14px; border-radius: 2px;"></div>
        <input type="checkbox" id="layer-road">
        <span class="layer-item__label">Road Network</span>
        <span class="layer-item__spinner" id="spinner-road"></span>
      </label>
      <label class="layer-item" for="layer-pop-density">
        <div class="layer-item__indicator" style="background: #A855F7;"></div>
        <input type="checkbox" id="layer-pop-density">
        <span class="layer-item__label">Pop. Density</span>
        <span class="layer-item__spinner" id="spinner-pop-density"></span>
      </label>
      <label class="layer-item" for="layer-travel-time">
        <div class="layer-item__indicator layer-item__indicator--line" style="background: #EC4899; height: 3px; width: 14px; border-radius: 2px;"></div>
        <input type="checkbox" id="layer-travel-time">
        <span class="layer-item__label">Travel Routes</span>
        <span class="layer-item__spinner" id="spinner-travel-time"></span>
      </label>

      <!-- Base -->
      <div class="layer-section__label">Base</div>
      <label class="layer-item" for="layer-boundary">
        <div class="layer-item__indicator" style="background: #8B5CF6; border-radius: 50%;"></div>
        <input type="checkbox" id="layer-boundary" checked>
        <span class="layer-item__label">City Boundary</span>
        <span class="layer-item__spinner" id="spinner-boundary"></span>
      </label>
    </div>
  </aside>

  <!-- LEFT UI GROUP (Location Tools + Legend) -->
  <div class="left-ui-group">
    <!-- LOCATION TOOLS (FABs) -->
    <div class="location-tools" id="location-tools">
      <button class="location-btn" id="locate-me-btn" title="Detect my location" aria-label="Locate me">
        <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="3"></circle><line x1="12" y1="2" x2="12" y2="5"></line><line x1="12" y1="19" x2="12" y2="22"></line><line x1="2" y1="12" x2="5" y2="12"></line><line x1="19" y1="12" x2="22" y2="12"></line></svg>
      </button>
      <button class="location-btn" id="pin-mode-btn" title="Pin location on map" aria-label="Toggle pin mode">
        <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
      </button>
      <button class="location-btn" id="coord-input-btn" title="Enter coordinates manually" aria-label="Enter coordinates">
        <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect><line x1="7" y1="8" x2="7" y2="8"></line><line x1="12" y1="8" x2="12" y2="8"></line><line x1="17" y1="8" x2="17" y2="8"></line><line x1="7" y1="12" x2="7" y2="12"></line><line x1="12" y1="12" x2="12" y2="12"></line><line x1="17" y1="12" x2="17" y2="12"></line><line x1="7" y1="16" x2="7" y2="16"></line><line x1="12" y1="16" x2="12" y2="16"></line><line x1="17" y1="16" x2="17" y2="16"></line></svg>
      </button>
    </div>

    <!-- LEGEND -->
    <aside class="legend-panel glass-panel" id="legend-panel" role="complementary" aria-label="Map legend">
      <div class="legend-panel__header">
        <div class="legend-panel__title">Legend</div>
        <button class="legend-panel__toggle" id="legend-toggle" aria-label="Toggle legend" aria-expanded="true" aria-controls="legend-items">
          <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="chevron-up">
            <polyline points="18 15 12 9 6 15"></polyline>
          </svg>
        </button>
      </div>
      <div id="legend-items" class="legend-panel__content">
        <!-- Populated dynamically by JS -->
      </div>
    </aside>
  </div>

  <!-- COORDINATE INPUT PANEL -->
  <div class="coord-panel glass-panel" id="coord-panel" aria-hidden="true">
    <div class="coord-panel__header">
      <span>Enter Coordinates</span>
      <button class="coord-panel__close" id="close-coord-btn">✕</button>
    </div>
    <div class="coord-panel__body">
      <div class="form-group">
        <label for="input-lat">Latitude</label>
        <input type="number" id="input-lat" step="any" placeholder="15.485..." class="input">
      </div>
      <div class="form-group">
        <label for="input-lng">Longitude</label>
        <input type="number" id="input-lng" step="any" placeholder="120.972..." class="input">
      </div>
      <button class="btn btn--primary btn--full" id="apply-coord-btn">Apply Location</button>
    </div>
  </div>

  <!-- NEAREST CENTER DISCOVERY CARD -->
  <div class="nearest-card glass-panel" id="nearest-card" aria-hidden="true">
    <div class="nearest-card__header">
      <span class="nearest-card__tag">Nearest Center</span>
      <button class="nearest-card__close" id="close-nearest-btn">✕</button>
    </div>
    <div class="nearest-card__body">
      <div class="nearest-card__info">
        <h3 class="nearest-card__name" id="nearest-name">Finding...</h3>
        <p class="nearest-card__distance" id="nearest-distance">— km away</p>
      </div>
      <button class="btn btn--success" id="nearest-directions-btn">Directions</button>
    </div>
  </div>

  <!-- BARANGAY DETAIL SIDEBAR -->
  <section class="detail-sidebar barangay-sidebar" id="barangay-sidebar" role="dialog" aria-label="Barangay details" aria-hidden="true">
    <!-- Drag handle (mobile only) -->
    <div class="barangay-sidebar__handle" id="barangay-sidebar-handle" aria-hidden="true">
      <div class="barangay-sidebar__handle-bar"></div>
    </div>
    <button class="detail-sidebar__close" id="close-barangay-sidebar-btn" aria-label="Close barangay details">✕</button>

    <!-- Hero — gradient banner with risk color -->
    <div class="barangay-sidebar__hero" id="barangay-hero">
      <div class="barangay-sidebar__hero-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="28" height="28" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 7l6-3 6 3 6-3v13l-6 3-6-3-6 3V7z"/>
          <path d="M9 4v13"/><path d="M15 7v13"/>
        </svg>
      </div>
      <div class="barangay-sidebar__hero-text">
        <div class="barangay-sidebar__eyebrow">Barangay</div>
        <h2 class="barangay-sidebar__name" id="barangay-name">—</h2>
      </div>
    </div>

    <div class="barangay-sidebar__content">
      <!-- Flood Risk Badge -->
      <div class="barangay-risk" id="barangay-risk-wrapper">
        <div class="barangay-risk__label">
          <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Flood Risk Level
        </div>
        <span class="barangay-risk__pill" id="barangay-risk-pill">—</span>
      </div>

      <!-- Stats grid: Population / Density / Area -->
      <div class="barangay-stats">
        <div class="barangay-stat">
          <div class="barangay-stat__icon">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div class="barangay-stat__body">
            <div class="barangay-stat__label">Population</div>
            <div class="barangay-stat__value" id="barangay-population">—</div>
          </div>
        </div>
        <div class="barangay-stat">
          <div class="barangay-stat__icon">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
          </div>
          <div class="barangay-stat__body">
            <div class="barangay-stat__label">Density</div>
            <div class="barangay-stat__value" id="barangay-density">—</div>
          </div>
        </div>
        <div class="barangay-stat">
          <div class="barangay-stat__icon">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 19 21 12 17 5 21 12 2"/></svg>
          </div>
          <div class="barangay-stat__body">
            <div class="barangay-stat__label">Area</div>
            <div class="barangay-stat__value" id="barangay-area">—</div>
          </div>
        </div>
      </div>

      <!-- Land Use card -->
      <div class="barangay-card" id="barangay-landuse-wrapper">
        <div class="barangay-card__title">
          <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M2 22l1-1h18l1 1"/><path d="M6 18V11l6-4 6 4v7"/><path d="M10 18v-4h4v4"/></svg>
          Land Use
        </div>
        <div class="barangay-card__body" id="barangay-landuse">—</div>
      </div>

      <!-- Description card -->
      <div class="barangay-card" id="barangay-desc-wrapper">
        <div class="barangay-card__title">
          <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          Description
        </div>
        <p class="barangay-card__body" id="barangay-description">—</p>
      </div>
    </div>
  </section>

  <!-- DETAIL SIDEBAR -->
  <section class="detail-sidebar" id="detail-sidebar" role="dialog" aria-label="Location details" aria-hidden="true">
    <button class="detail-sidebar__close" id="close-sidebar-btn" aria-label="Close details">✕</button>
    <div class="detail-gallery" id="detail-gallery">
      <div class="detail-gallery__placeholder">🏫</div>
    </div>
    <div class="detail-content" id="detail-content">
      <div>
        <h2 class="detail-content__name" id="detail-name">—</h2>
        <span class="badge badge--active" id="detail-status">
          <span class="badge__dot"></span> Active
        </span>
      </div>
      <div class="detail-info-row">
        <span class="detail-info-row__icon">
          <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
        </span>
        <div>
          <div class="detail-info-row__label">Barangay</div>
          <div class="detail-info-row__value" id="detail-barangay">—</div>
        </div>
      </div>
      <div class="detail-info-row">
        <span class="detail-info-row__icon">
          <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
        </span>
        <div>
          <div class="detail-info-row__label">Capacity</div>
          <div class="detail-info-row__value" id="detail-capacity">—</div>
        </div>
      </div>
      <div class="detail-info-row">
        <span class="detail-info-row__icon">
          <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
        </span>
        <div>
          <div class="detail-info-row__label">Coordinates</div>
          <div class="detail-info-row__value detail-info-row__value--mono" id="detail-coords">—</div>
        </div>
      </div>
      <div id="detail-coverage-wrapper" style="display:none;">
        <div class="detail-info-row">
          <span class="detail-info-row__icon">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M1 6l11-4 11 4v6c0 5.55-4.68 10.74-11 12-6.32-1.26-11-6.45-11-12V6z"/></svg>
          </span>
          <div style="flex:1; min-width:0;">
            <div class="detail-info-row__label">Coverage Area</div>
            <div id="detail-coverage-list" class="detail-coverage-list"></div>
          </div>
        </div>
      </div>
      <div id="detail-desc-wrapper" style="display:none;">
        <div class="detail-info-row__label" style="margin-bottom:4px;">
          <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" style="vertical-align: middle; margin-right: 4px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
          Description
        </div>
        <p class="detail-description" id="detail-description"></p>
      </div>
      <div class="detail-actions">
        <a class="btn btn--success btn--full" id="detail-directions" href="#" target="_blank" rel="noopener noreferrer" aria-label="Get directions">
          🧭 Get Directions
        </a>
        <button class="btn btn--secondary btn--full" id="detail-copy-coords" aria-label="Copy coordinates">
          📋 Copy Coordinates
        </button>
      </div>
    </div>
  </section>

  <!-- MOBILE BOTTOM SHEET (mobile-only) -->
  <!-- States: aria-hidden=true (off) | --peek (~120px) | --mid (~55vh) | --full (~92vh) -->
  <div class="mobile-sheet" id="mobile-sheet" aria-hidden="true">

    <!-- Drag handle — always on top -->
    <div class="mobile-sheet__handle" id="mobile-sheet-handle">
      <div class="mobile-sheet__handle-bar"></div>
    </div>

    <!-- Peek bar — always visible when sheet is open (image 3 compact card) -->
    <div class="mobile-sheet__peek-bar" id="mobile-sheet-peek-bar">
      <div class="mobile-sheet__thumb" id="mobile-sheet-thumb">
        <div class="mobile-sheet__thumb-placeholder">🏫</div>
      </div>
      <div class="mobile-sheet__peek-info">
        <div class="mobile-sheet__peek-name" id="mobile-sheet-peek-name">—</div>
        <div class="mobile-sheet__peek-meta" id="mobile-sheet-peek-meta"></div>
      </div>
      <button class="mobile-sheet__close-btn" id="mobile-sheet-close" aria-label="Close">
        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Scrollable body — hidden in peek, revealed in mid/full (images 1 & 2) -->
    <div class="mobile-sheet__body" id="mobile-sheet-body">

      <!-- Swipeable image gallery (image 2 top portion) -->
      <div class="mobile-sheet__gallery" id="mobile-sheet-gallery">
        <div class="mobile-sheet__gallery-placeholder">🏫</div>
      </div>

      <!-- Info rows -->
      <div class="mobile-sheet__content">

        <!-- Name + status header -->
        <div class="mobile-sheet__content-header">
          <h2 class="mobile-sheet__content-name" id="mobile-sheet-full-name">—</h2>
          <span class="badge badge--active" id="mobile-sheet-full-status"><span class="badge__dot"></span> Active</span>
        </div>

        <div class="mobile-sheet__divider"></div>

        <div class="detail-info-row">
          <span class="detail-info-row__icon">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
          </span>
          <div>
            <div class="detail-info-row__label">Barangay</div>
            <div class="detail-info-row__value" id="mobile-sheet-barangay">—</div>
          </div>
        </div>

        <div class="mobile-sheet__divider"></div>

        <div class="detail-info-row">
          <span class="detail-info-row__icon">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
          </span>
          <div>
            <div class="detail-info-row__label">Capacity</div>
            <div class="detail-info-row__value" id="mobile-sheet-capacity">—</div>
          </div>
        </div>

        <div class="mobile-sheet__divider"></div>

        <div id="mobile-sheet-coverage-wrapper" style="display:none;">
          <div class="detail-info-row">
            <span class="detail-info-row__icon">
              <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M1 6l11-4 11 4v6c0 5.55-4.68 10.74-11 12-6.32-1.26-11-6.45-11-12V6z"/></svg>
            </span>
            <div style="flex:1;min-width:0;">
              <div class="detail-info-row__label">Coverage Area</div>
              <div id="mobile-sheet-coverage-list" class="detail-coverage-list"></div>
            </div>
          </div>
          <div class="mobile-sheet__divider"></div>
        </div>

        <!-- Actions always at bottom -->
        <div class="mobile-sheet__actions">
          <a class="btn btn--success btn--full" id="mobile-sheet-directions" href="#" target="_blank" rel="noopener noreferrer">🧭 Get Directions</a>
          <button class="btn btn--secondary btn--full" id="mobile-sheet-copy-coords">📋 Copy Coordinates</button>
        </div>

      </div><!-- /.mobile-sheet__content -->
    </div><!-- /.mobile-sheet__body -->
  </div>

  <!-- MOBILE DRAWER OVERLAY -->
  <div class="drawer-overlay" id="drawer-overlay"></div>

  <!-- TOAST CONTAINER -->
  <div class="toast-container" id="toast-container" aria-live="polite"></div>

  <!-- Layer Info Modal -->
  <div class="info-modal" id="info-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="info-modal-title">
    <div class="info-modal__overlay" data-info-close></div>
    <div class="info-modal__panel">
      <header class="info-modal__header">
        <div class="info-modal__header-left">
          <div class="info-modal__icon" id="info-modal-icon"></div>
          <div class="info-modal__title-wrap">
            <h3 class="info-modal__title" id="info-modal-title">Layer Info</h3>
            <p class="info-modal__subtitle" id="info-modal-subtitle"></p>
          </div>
        </div>
        <button type="button" class="info-modal__close" data-info-close aria-label="Close modal">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </header>
      <div class="info-modal__search">
        <input type="search" id="info-modal-search" placeholder="Search..." autocomplete="off">
      </div>
      <div class="info-modal__body" id="info-modal-body">
        <ul class="info-modal__list" id="info-modal-list"></ul>
      </div>
      <footer class="info-modal__footer">
        <span id="info-modal-count">0 items</span>
        <span id="info-modal-hint">Click an item to locate on map</span>
      </footer>
    </div>
  </div>

  <!-- SCRIPTS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.9.0/proj4.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4leaflet/1.0.2/proj4leaflet.min.js"></script>
  <script src="js/mapService.js" defer></script>
  <script src="js/layerService.js" defer></script>
  <script src="js/uiService.js" defer></script>
  <script src="js/searchService.js" defer></script>
  <script src="js/locationService.js" defer></script>
  <script src="js/mobileSheet.js" defer></script>
  <script src="js/barangaySidebar.js" defer></script>
  <script src="js/app.js" defer></script>
</body>
</html>
