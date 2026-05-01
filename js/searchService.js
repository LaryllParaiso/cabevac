/**
 * CabEvac — Search Service
 * Handles search input, debounced API queries, dropdown rendering,
 * keyboard navigation, and filter chip logic.
 * 
 * @module searchService
 */

/** @type {number|null} Debounce timer ID */
let searchDebounceTimer = null;

/** @type {number} Current highlighted index in search results */
let highlightedIndex = -1;

/**
 * Initialize the search bar with event listeners.
 */
function initSearchBar() {
  const input = document.getElementById('search-input');
  const clearBtn = document.getElementById('search-clear');
  const resultsContainer = document.getElementById('search-results');

  if (input === null) { return; }

  input.addEventListener('input', () => {
    const query = input.value.trim();

    if (clearBtn !== null) {
      clearBtn.style.display = query.length > 0 ? 'flex' : 'none';
    }

    if (query.length < 2) {
      clearSearchResults();
      return;
    }


    if (searchDebounceTimer !== null) {
      clearTimeout(searchDebounceTimer);
    }

    searchDebounceTimer = setTimeout(() => {
      searchCenters(query);
    }, 300);
  });

  input.addEventListener('keydown', (e) => {
    handleSearchKeyboard(e);
  });

  input.addEventListener('focus', () => {
    const query = input.value.trim();
    if (query.length === 0) {
      searchCenters(''); // Show all centers on focus
    } else if (query.length >= 2) {
      resultsContainer.style.display = 'block';
    }
  });

  if (clearBtn !== null) {
    clearBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      input.value = '';
      clearBtn.style.display = 'none';
      clearSearchResults();
      input.focus();
    });
  }

  const submitBtn = document.getElementById('search-submit-btn');
  if (submitBtn !== null) {
    submitBtn.addEventListener('click', () => {
      const query = input.value.trim();
      if (query.length >= 2) {
        searchCenters(query);
      }
    });
  }

  // Close results when clicking outside
  document.addEventListener('click', (e) => {
    const container = document.getElementById('search-container');
    if (container !== null && !container.contains(e.target)) {
      clearSearchResults();
    }
  });
}

/**
 * Search evacuation centers via API.
 * 
 * @param {string} query - Search text
 */
async function searchCenters(query) {
  const sanitized = DOMPurify.sanitize(query);
  const data = await fetchLayer(`api/centers.php?search=${encodeURIComponent(sanitized)}`);

  if (data === null) {
    renderSearchResults([], query);
    return;
  }

  renderSearchResults(data, query);
}

/**
 * Render search results in the dropdown.
 * 
 * @param {Array<Object>} results - API results
 * @param {string} query - Original search query for highlighting
 */
function renderSearchResults(results, query) {
  const container = document.getElementById('search-results');
  if (container === null) { return; }

  highlightedIndex = -1;
  container.innerHTML = '';

  if (query === '' && results.length > 0) {
    const header = document.createElement('div');
    header.className = 'search-results__header';
    header.innerHTML = '<span style="font-size: 11px; font-weight: 700; color: var(--color-text-tertiary); text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 16px 8px; display: block;">Suggested Centers</span>';
    container.appendChild(header);
  }

  if (results.length === 0) {
    container.innerHTML = `<div class="search-results__empty" style="padding: 16px; text-align: center; color: var(--color-text-tertiary); font-size: var(--font-size-sm);">No results found for "${DOMPurify.sanitize(query)}"</div>`;
    container.style.display = 'block';
    return;
  }

  results.forEach((center, index) => {
    const item = document.createElement('div');
    item.className = 'search-result-item';
    item.setAttribute('role', 'option');
    item.dataset.index = String(index);

    const highlightedName = highlightMatch(center.name, query);

    item.innerHTML = `
      <div class="search-result-item__icon" style="color: var(--color-text-tertiary);">
        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
      </div>
      <div class="search-result-item__info">
        <div class="search-result-item__name" style="font-weight: 600; font-size: var(--font-size-sm);">${highlightedName}</div>
        <div class="search-result-item__meta" style="font-size: 11px; color: var(--color-text-tertiary);">${center.barangay || 'Cabanatuan City'}</div>
      </div>
    `;

    item.addEventListener('click', () => {
      handleResultClick(center);
    });

    container.appendChild(item);
  });

  container.style.display = 'block';
}

/**
 * Highlight matching text in a string.
 * 
 * @param {string} text - Full text
 * @param {string} query - Text to highlight
 * @returns {string} HTML with <mark> around matched text
 */
function highlightMatch(text, query) {
  if (query === '') { return DOMPurify.sanitize(text); }

  const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const regex = new RegExp(`(${escaped})`, 'gi');

  return DOMPurify.sanitize(text.replace(regex, '<mark>$1</mark>'));
}

/**
 * Handle a search result click.
 * 
 * @param {Object} center - Evacuation center data
 */
function handleResultClick(center) {
  clearSearchResults();

  const input = document.getElementById('search-input');
  if (input !== null) {
    input.value = center.name;
    document.getElementById('search-clear')?.classList.add('visible');
  }

  flyToPoint(center.latitude, center.longitude, 16);

  const isMobile = window.innerWidth <= 768;
  if (isMobile) {
    // Show bottom sheet with coverage auto-loaded — no marker tap needed
    if (typeof openMobileSheet === 'function') { openMobileSheet(center); }
  } else {
    openDetailSidebar(center);
  }

  if (typeof highlightEvacuationMarker === 'function') {
    highlightEvacuationMarker(center.id);
  }

  // Close mobile search
  const wrapper = document.getElementById('search-wrapper');
  if (wrapper !== null) {
    wrapper.classList.remove('mobile-open');
  }
}

/**
 * Handle keyboard navigation in search results.
 * 
 * @param {KeyboardEvent} e - Keyboard event
 */
function handleSearchKeyboard(e) {
  const container = document.getElementById('search-results');
  if (container === null || !container.classList.contains('active')) { return; }

  const items = container.querySelectorAll('.search-result-item');
  if (items.length === 0) { return; }

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
    updateHighlight(items);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    highlightedIndex = Math.max(highlightedIndex - 1, 0);
    updateHighlight(items);
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (highlightedIndex >= 0 && highlightedIndex < items.length) {
      items[highlightedIndex].click();
    }
  } else if (e.key === 'Escape') {
    clearSearchResults();
  }
}

/**
 * Update visual highlight on search result items.
 * 
 * @param {NodeList} items - Search result DOM items
 */
function updateHighlight(items) {
  items.forEach((item, i) => {
    item.classList.toggle('highlighted', i === highlightedIndex);
  });
}

/**
 * Clear and hide search results dropdown.
 */
function clearSearchResults() {
  const container = document.getElementById('search-results');
  if (container !== null) {
    container.style.display = 'none';
    container.innerHTML = '';
  }
  highlightedIndex = -1;
}

/**
 * Reset the search input: clear value, hide clear button, drop results.
 */
function clearSearchInput() {
  const input = document.getElementById('search-input');
  if (input !== null) { input.value = ''; }
  const clearBtn = document.getElementById('search-clear');
  if (clearBtn !== null) { clearBtn.classList.remove('visible'); }
  clearSearchResults();
}

/**
 * Initialize filter chip click handlers.
 */
function initFilterChips() {
  const chips = document.querySelectorAll('.filter-chip');
  if (chips.length === 0) { return; }

  chips.forEach((chip) => {
    chip.addEventListener('click', () => {
      const risk = chip.dataset.risk;

      if (chip.classList.contains('active')) {
        // Deselect: hide all flood zones
        chips.forEach((c) => c.classList.remove('active'));
        applyFilter('none');
        return;
      }

      // Toggle active state
      chips.forEach((c) => c.classList.remove('active'));
      chip.classList.add('active');

      applyFilter(risk);
    });
  });
}

/**
 * Apply a flood risk filter — show/hide flood zone layers.
 *
 * @param {string} riskLevel - 'all', 'very_high', 'high', or 'moderate'
 */
async function applyFilter(riskLevel) {
  const riskLevels = ['very_high', 'high', 'moderate', 'low'];

  for (const risk of riskLevels) {
    const layerKey = `flood-${risk.replace('_', '-')}`;
    const checkbox = document.getElementById(`layer-flood-${risk.replace('_', '-')}`);
    const shouldShow = riskLevel === 'all' || riskLevel === risk;

    if (shouldShow && layerCache[layerKey] === undefined) {
      if (risk === 'low' && typeof loadFloodZonesLow === 'function') {
        await loadFloodZonesLow();
      } else if (typeof loadFloodZones === 'function') {
        await loadFloodZones(risk);
      }
    }

    toggleLayerVisibility(layerKey, shouldShow);

    if (checkbox !== null) {
      checkbox.checked = shouldShow;
    }

    if (typeof window !== 'undefined' && window.visibleLayers) {
      window.visibleLayers[layerKey] = shouldShow;
    }
  }

  refreshLegend();
  if (typeof AppStorage !== 'undefined') {
    AppStorage.setLayers(window.visibleLayers);
  }
}

/**
 * Initialize mobile search button.
 */
function initMobileSearch() {
  const searchBtn = document.getElementById('mobile-search-btn');
  const searchWrapper = document.getElementById('search-wrapper');

  if (searchBtn === null || searchWrapper === null) { return; }

  const updateMobileVisibility = () => {
    if (window.innerWidth <= 768) {
      searchBtn.style.display = 'flex';
    } else {
      searchBtn.style.display = 'none';
      searchWrapper.classList.remove('mobile-open');
    }
  };

  searchBtn.addEventListener('click', () => {
    searchWrapper.classList.toggle('mobile-open');
    if (searchWrapper.classList.contains('mobile-open')) {
      const input = document.getElementById('search-input');
      if (input !== null) { input.focus(); }
    }
  });

  window.addEventListener('resize', updateMobileVisibility);
  updateMobileVisibility();
}
