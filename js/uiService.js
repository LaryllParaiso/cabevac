/**
 * CabEvac — UI Service
 * Handles all UI interactions: sidebar, toasts, legend, lightbox, modals.
 * 
 * @module uiService
 */

/**
 * Open the location detail sidebar with center data.
 * 
 * @param {Object} centerData - Evacuation center data from API
 */
function openDetailSidebar(centerData) {
  const sidebar = document.getElementById('detail-sidebar');
  if (sidebar === null) { return; }

  // Close the barangay sidebar so the two never overlap on the right edge
  if (typeof closeBarangaySidebar === 'function') { closeBarangaySidebar(); }

  // Populate data
  document.getElementById('detail-name').textContent = centerData.name || '—';

  // Status badge
  const statusBadge = document.getElementById('detail-status');
  const isActive = centerData.status === 'active';
  statusBadge.className = `badge badge--${isActive ? 'active' : 'inactive'}`;
  statusBadge.innerHTML = `<span class="badge__dot"></span> ${isActive ? 'Active' : 'Inactive'}`;

  // Info rows
  document.getElementById('detail-barangay').textContent = centerData.barangay || '—';
  document.getElementById('detail-capacity').textContent = centerData.capacity
    ? `${Number(centerData.capacity).toLocaleString()} persons`
    : 'Not specified';
  document.getElementById('detail-coords').textContent =
    `${Number(centerData.latitude).toFixed(6)}° N, ${Number(centerData.longitude).toFixed(6)}° E`;

  // Description
  const descWrapper = document.getElementById('detail-desc-wrapper');
  const descEl = document.getElementById('detail-description');
  if (centerData.description && centerData.description.trim() !== '') {
    descEl.textContent = centerData.description;
    descWrapper.style.display = 'block';
  } else {
    descWrapper.style.display = 'none';
  }

  // Get Directions link
  const directionsLink = document.getElementById('detail-directions');
  directionsLink.href =
    `https://www.google.com/maps/dir/?api=1&destination=${centerData.latitude},${centerData.longitude}`;

  // Copy coordinates handler
  const copyBtn = document.getElementById('detail-copy-coords');
  const newCopyBtn = copyBtn.cloneNode(true);
  copyBtn.parentNode.replaceChild(newCopyBtn, copyBtn);
  newCopyBtn.addEventListener('click', () => {
    const coordText = `${centerData.latitude}, ${centerData.longitude}`;
    navigator.clipboard.writeText(coordText).then(() => {
      showToast('Coordinates copied to clipboard', 'success');
    }).catch(() => {
      showToast('Failed to copy coordinates', 'error');
    });
  });

  // Image gallery
  renderImageGallery(centerData.images || []);

  // Coverage area section — show loading state, populate on callback
  const coverageWrapper = document.getElementById('detail-coverage-wrapper');
  const coverageList    = document.getElementById('detail-coverage-list');
  if (coverageWrapper !== null && coverageList !== null) {
    coverageWrapper.style.display = 'none';
    coverageList.innerHTML = '';

    if (typeof showCoverageForCenter === 'function') {
      // Show loading indicator immediately
      coverageWrapper.style.display = 'block';
      coverageList.innerHTML = `
        <div class="detail-coverage-list__loading">
          <div class="detail-coverage-list__spinner"></div>
          <span>Loading coverage…</span>
        </div>`;

      showCoverageForCenter(centerData.name || '', (barangays) => {
        if (barangays.length === 0) {
          coverageWrapper.style.display = 'none';
          return;
        }
        coverageList.innerHTML = '';
        barangays.forEach((b) => {
          const chip = document.createElement('span');
          chip.className = 'detail-coverage-chip';
          chip.title = b.area ? `${b.name} · ${b.area} km²` : b.name;
          chip.innerHTML = `
            <span class="detail-coverage-chip__dot"></span>
            ${b.name}${b.area ? `<span class="detail-coverage-chip__area">${b.area} km²</span>` : ''}`;
          coverageList.appendChild(chip);
        });
      });
    }
  }

  // Open sidebar
  sidebar.classList.add('open');
  sidebar.setAttribute('aria-hidden', 'false');
  sidebar.focus();
}

/**
 * Close the detail sidebar.
 */
function closeDetailSidebar() {
  const sidebar = document.getElementById('detail-sidebar');
  if (sidebar === null) { return; }

  sidebar.classList.remove('open');
  sidebar.setAttribute('aria-hidden', 'true');

  // Reset selection state: clear search and remove sky-blue highlights.
  if (typeof clearSearchInput === 'function') { clearSearchInput(); }
  if (typeof clearEvacuationMarkerHighlights === 'function') {
    clearEvacuationMarkerHighlights();
  }
  // Remove coverage glow polygon and reset sidebar section
  if (typeof hideCoverageHighlight === 'function') {
    hideCoverageHighlight();
  }
  const coverageWrapper = document.getElementById('detail-coverage-wrapper');
  if (coverageWrapper !== null) { coverageWrapper.style.display = 'none'; }
  const coverageList = document.getElementById('detail-coverage-list');
  if (coverageList !== null) { coverageList.innerHTML = ''; }
}

/**
 * Render the image gallery in the detail sidebar.
 * 
 * @param {Array<Object>} images - Array of image objects {image_path, alt_text}
 */
function renderImageGallery(images) {
  const gallery = document.getElementById('detail-gallery');
  if (gallery === null) { return; }

  // Clear existing auto-slide interval if any
  if (gallery.dataset.intervalId) {
    clearInterval(parseInt(gallery.dataset.intervalId));
    gallery.removeAttribute('data-interval-id');
  }

  gallery.innerHTML = '';
  gallery.style.position = 'relative';

  if (images.length === 0) {
    gallery.innerHTML = '<div class="detail-gallery__placeholder">🏫</div>';
    return;
  }

  // Create image element
  const imgEl = document.createElement('img');
  imgEl.className = 'detail-gallery__image';
  imgEl.src = DOMPurify.sanitize(images[0].image_path);
  imgEl.alt = DOMPurify.sanitize(images[0].alt_text || 'Evacuation center image');
  imgEl.loading = 'lazy';
  imgEl.style.transition = 'opacity 300ms ease';
  imgEl.style.cursor = 'pointer';
  
  gallery.appendChild(imgEl);

  const hoverOverlay = document.createElement('div');
  hoverOverlay.className = 'gallery-hover-overlay';
  hoverOverlay.textContent = 'VIEW IMAGE';
  gallery.appendChild(hoverOverlay);

  let currentIndex = 0;

  imgEl.addEventListener('click', () => openImageLightbox(images, currentIndex));

  if (images.length > 1) {
    // Add Dots Container
    const dotsContainer = document.createElement('div');
    dotsContainer.style.cssText = `
      position: absolute; bottom: 8px; left: 50%; transform: translateX(-50%);
      display: flex; gap: 6px; z-index: 10;
      background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 12px;
      backdrop-filter: blur(4px);
    `;

    const dots = [];
    images.forEach((_, i) => {
      const dot = document.createElement('div');
      dot.style.cssText = `
        width: 6px; height: 6px; border-radius: 3px; cursor: pointer;
        background: ${i === 0 ? 'var(--color-primary, #fff)' : 'rgba(255,255,255,0.5)'};
        transition: all 200ms ease;
        ${i === 0 ? 'width: 16px; box-shadow: 0 0 4px var(--color-primary, #fff);' : ''}
      `;
      dot.addEventListener('click', (e) => {
        e.stopPropagation();
        goToImage(i);
        resetAutoSlide();
      });
      dotsContainer.appendChild(dot);
      dots.push(dot);
    });

    gallery.appendChild(dotsContainer);

    const updateDots = () => {
      dots.forEach((dot, i) => {
        if (i === currentIndex) {
          dot.style.background = 'var(--color-primary, #fff)';
          dot.style.width = '16px';
          dot.style.boxShadow = '0 0 4px var(--color-primary, #fff)';
        } else {
          dot.style.background = 'rgba(255,255,255,0.5)';
          dot.style.width = '6px';
          dot.style.boxShadow = 'none';
        }
      });
    };

    const goToImage = (index) => {
      currentIndex = index;
      imgEl.style.opacity = '0.5';
      setTimeout(() => {
        imgEl.src = DOMPurify.sanitize(images[currentIndex].image_path);
        imgEl.style.opacity = '1';
        updateDots();
      }, 150);
    };

    const nextImage = () => {
      let nextIndex = currentIndex + 1;
      if (nextIndex >= images.length) nextIndex = 0;
      goToImage(nextIndex);
    };

    // Auto-slide every 3 seconds
    let autoSlideInterval = setInterval(nextImage, 3000);
    gallery.dataset.intervalId = autoSlideInterval.toString();

    const resetAutoSlide = () => {
      clearInterval(autoSlideInterval);
      autoSlideInterval = setInterval(nextImage, 3000);
      gallery.dataset.intervalId = autoSlideInterval.toString();
    };

    // Pause on hover
    gallery.addEventListener('mouseenter', () => clearInterval(autoSlideInterval));
    gallery.addEventListener('mouseleave', resetAutoSlide);
  }
}

/**
 * Show a toast notification.
 * 
 * @param {string} message - Notification message
 * @param {'success'|'error'|'warning'|'info'} [type='info'] - Toast type
 * @param {number} [duration=5000] - Auto-dismiss duration in ms
 */
function showToast(message, type = 'info', duration = 5000) {
  const container = document.getElementById('toast-container');
  if (container === null) { return; }

  const icons = {
    success: '✓',
    error: '✕',
    warning: '⚠',
    info: 'ℹ',
  };

  const toast = document.createElement('div');
  toast.className = `toast toast--${type}`;
  toast.innerHTML = `
    <span>${icons[type] || 'ℹ'}</span>
    <span>${DOMPurify.sanitize(message)}</span>
    <button class="toast__close" aria-label="Dismiss notification">✕</button>
  `;

  const closeBtn = toast.querySelector('.toast__close');
  closeBtn.addEventListener('click', () => removeToast(toast));

  container.appendChild(toast);

  setTimeout(() => removeToast(toast), duration);
}

/**
 * Remove a toast with animation.
 * 
 * @param {HTMLElement} toast - Toast element
 */
function removeToast(toast) {
  if (toast === null || toast.parentNode === null) { return; }

  toast.style.opacity = '0';
  toast.style.transform = 'translateX(120%)';
  toast.style.transition = 'all 200ms ease-in';

  setTimeout(() => {
    if (toast.parentNode !== null) {
      toast.parentNode.removeChild(toast);
    }
  }, 200);
}

/**
 * Update the legend panel based on currently visible layers.
 * 
 * @param {Object.<string, boolean>} visibleLayers - Map of layer key → visibility
 */
function updateLegend(visibleLayers) {
  const container = document.getElementById('legend-items');
  if (container === null) { return; }

  const legendEntries = [
    { key: 'flood-very-high', label: 'Very High Risk', color: '#ef4444', shape: 'square' },
    { key: 'flood-high', label: 'High Risk', color: '#f97316', shape: 'square' },
    { key: 'flood-moderate', label: 'Moderate Risk', color: '#f59e0b', shape: 'square' },
    { key: 'flood-low', label: 'Low Risk', color: '#10b981', shape: 'square' },
    { key: 'evacuation', label: 'Evacuation Center', color: '#10b981', shape: 'square' },
    { key: 'buffer-500m', label: 'Buffer 500m', color: '#3B82F6', shape: 'square' },
    { key: 'buffer-1km', label: 'Buffer 1km', color: '#06B6D4', shape: 'square' },
    { key: 'buffer-2km', label: 'Buffer 2km', color: '#6366F1', shape: 'square' },
    { key: 'isochrone-layers', label: 'Isochrone', color: '#22C55E', shape: 'square' },
    { key: 'network-analysis', label: 'Network Analysis', color: '#6366F1', shape: 'line' },
    { key: 'road-network', label: 'Road Network', color: '#01ff06', shape: 'line' },
    { key: 'population-density', label: 'Pop. Density', color: '#A855F7', shape: 'square' },
    { key: 'travel-time', label: 'Travel Routes', color: '#EC4899', shape: 'line' },
    { key: 'cabanatuan-boundary', label: 'City Boundary', color: '#8b5cf6', shape: 'square' },
  ];

  container.innerHTML = '';

  legendEntries.forEach(({ key, label, color, shape }) => {
    if (!visibleLayers[key]) { return; }

    const item = document.createElement('div');
    item.className = 'legend-item';

    let shapeClass = '';
    if (shape === 'circle') { shapeClass = 'legend-item__color--circle'; }
    else if (shape === 'line') { shapeClass = 'legend-item__color--line'; }

    item.innerHTML = `
      <div class="legend-item__color ${shapeClass}" style="background: ${color};"></div>
      <span class="legend-item__label">${label}</span>
    `;

    container.appendChild(item);
  });

  updateLegendHeightVar();
}

/**
 * Measure legend panel height and expose as CSS variable for mobile layout.
 */
function updateLegendHeightVar() {
  const panel = document.getElementById('legend-panel');
  if (panel === null) { return; }
  const height = panel.offsetHeight || 0;
  document.body.style.setProperty('--legend-height', `${height}px`);
}

/**
 * Toggle the layer control panel for mobile.
 * 
 * @param {boolean} [forceState] - Force open (true) or close (false)
 */
function toggleLayerPanel(forceState) {
  const panel = document.getElementById('layer-panel');
  const overlay = document.getElementById('drawer-overlay');
  if (panel === null) { return; }

  const isOpen = panel.classList.contains('mobile-open');
  const newState = forceState !== undefined ? forceState : !isOpen;

  panel.classList.toggle('mobile-open', newState);
  if (overlay !== null) { overlay.classList.toggle('active', newState); }

  const hamburger = document.getElementById('hamburger-btn');
  if (hamburger !== null) { hamburger.setAttribute('aria-expanded', String(newState)); }
}

/**
 * Open a fullscreen image lightbox with gallery support (Skill UI/UX ProMax).
 * 
 * @param {Array<Object>} images - Array of image objects
 * @param {number} startIndex - Index of the image to show first
 */
function openImageLightbox(images, startIndex = 0) {
  if (!images || images.length === 0) return;
  let currentIndex = startIndex;

  const existing = document.querySelector('.lightbox-overlay');
  if (existing !== null) { existing.remove(); }

  const lightbox = document.createElement('div');
  lightbox.className = 'lightbox-overlay';
  lightbox.style.cssText = `
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(10, 10, 15, 0.85); z-index: 9000;
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    animation: fadeIn 300ms cubic-bezier(0.175, 0.885, 0.32, 1.275);
    opacity: 0; transition: opacity 300ms ease;
  `;

  requestAnimationFrame(() => { lightbox.style.opacity = '1'; });

  // Top Bar (Filename and Close Button)
  const topBar = document.createElement('div');
  topBar.style.cssText = `
    position: absolute; top: 0; left: 0; right: 0;
    padding: var(--space-4) var(--space-6);
    display: flex; justify-content: space-between; align-items: center;
    z-index: 9010; pointer-events: none;
  `;

  const titleEl = document.createElement('div');
  titleEl.style.cssText = `
    color: #ffffff; font-family: var(--font-family-primary);
    font-size: var(--font-size-2xl); font-weight: 900; text-transform: uppercase;
    text-shadow: 0 4px 12px rgba(0,0,0,0.5); letter-spacing: -0.02em;
    pointer-events: auto;
  `;

  const closeBtn = document.createElement('button');
  closeBtn.innerHTML = `
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
  `;
  closeBtn.style.cssText = `
    width: 44px; height: 44px; border-radius: 50%;
    background: var(--color-status-error); color: white;
    border: 2px solid rgba(255,255,255,0.2); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px rgba(217, 48, 37, 0.4); pointer-events: auto;
    transition: transform 200ms ease, box-shadow 200ms ease;
  `;
  closeBtn.onmouseover = () => { closeBtn.style.transform = 'scale(1.1)'; closeBtn.style.boxShadow = '0 6px 16px rgba(217, 48, 37, 0.6)'; };
  closeBtn.onmouseout = () => { closeBtn.style.transform = 'scale(1)'; closeBtn.style.boxShadow = '0 4px 12px rgba(217, 48, 37, 0.4)'; };
  
  closeBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    closeLightbox();
  });

  topBar.appendChild(titleEl);
  topBar.appendChild(closeBtn);
  lightbox.appendChild(topBar);

  // Image Container
  const imgContainer = document.createElement('div');
  imgContainer.style.cssText = `
    position: relative; width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    padding: 80px 20px; overflow: hidden;
  `;
  
  const imgEl = document.createElement('img');
  imgEl.style.cssText = `
    max-width: 100%; max-height: 100%; object-fit: contain;
    border-radius: var(--radius-lg); box-shadow: 0 20px 40px rgba(0,0,0,0.5);
    transition: opacity 200ms ease, transform 200ms ease;
  `;
  imgContainer.appendChild(imgEl);
  lightbox.appendChild(imgContainer);

  // Navigation Arrows (if multiple)
  let prevBtn, nextBtn;
  if (images.length > 1) {
    const arrowStyle = `
      position: absolute; top: 50%; transform: translateY(-50%);
      width: 48px; height: 48px; border-radius: 50%;
      background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
      color: white; cursor: pointer; display: flex; align-items: center; justify-content: center;
      backdrop-filter: blur(8px); transition: all 200ms ease; z-index: 9010;
    `;
    
    prevBtn = document.createElement('button');
    prevBtn.innerHTML = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>`;
    prevBtn.style.cssText = arrowStyle + 'left: var(--space-4);';
    prevBtn.onmouseover = () => { prevBtn.style.background = 'rgba(255,255,255,0.25)'; prevBtn.style.transform = 'translateY(-50%) scale(1.1)'; };
    prevBtn.onmouseout = () => { prevBtn.style.background = 'rgba(255,255,255,0.15)'; prevBtn.style.transform = 'translateY(-50%) scale(1)'; };
    prevBtn.addEventListener('click', (e) => { e.stopPropagation(); navigate(-1); });

    nextBtn = document.createElement('button');
    nextBtn.innerHTML = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>`;
    nextBtn.style.cssText = arrowStyle + 'right: var(--space-4);';
    nextBtn.onmouseover = () => { nextBtn.style.background = 'rgba(255,255,255,0.25)'; nextBtn.style.transform = 'translateY(-50%) scale(1.1)'; };
    nextBtn.onmouseout = () => { nextBtn.style.background = 'rgba(255,255,255,0.15)'; nextBtn.style.transform = 'translateY(-50%) scale(1)'; };
    nextBtn.addEventListener('click', (e) => { e.stopPropagation(); navigate(1); });

    imgContainer.appendChild(prevBtn);
    imgContainer.appendChild(nextBtn);
  }

  // Dots (if multiple)
  const dotsContainer = document.createElement('div');
  if (images.length > 1) {
    dotsContainer.style.cssText = `
      position: absolute; bottom: var(--space-6); left: 50%; transform: translateX(-50%);
      display: flex; gap: 8px; z-index: 9010;
      background: rgba(0,0,0,0.3); padding: 8px 12px; border-radius: 20px;
      backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1);
    `;
    
    images.forEach((_, i) => {
      const dot = document.createElement('button');
      dot.style.cssText = `
        width: 8px; height: 8px; border-radius: 4px; border: none; cursor: pointer;
        background: rgba(255,255,255,0.4); transition: all 300ms ease; padding: 0;
      `;
      dot.addEventListener('click', (e) => { e.stopPropagation(); showImage(i); });
      dotsContainer.appendChild(dot);
    });
    lightbox.appendChild(dotsContainer);
  }

  function updateDots() {
    if (images.length <= 1) return;
    const dots = dotsContainer.children;
    for (let i = 0; i < dots.length; i++) {
      if (i === currentIndex) {
        dots[i].style.background = 'var(--color-primary)';
        dots[i].style.width = '24px';
        dots[i].style.boxShadow = '0 0 10px var(--color-primary)';
      } else {
        dots[i].style.background = 'rgba(255,255,255,0.4)';
        dots[i].style.width = '8px';
        dots[i].style.boxShadow = 'none';
      }
    }
  }

  function showImage(index) {
    currentIndex = index;
    const img = images[currentIndex];
    
    // Animate out
    imgEl.style.opacity = '0';
    imgEl.style.transform = 'scale(0.98)';
    
    setTimeout(() => {
      imgEl.src = DOMPurify.sanitize(img.image_path);
      titleEl.textContent = img.original_filename ? DOMPurify.sanitize(img.original_filename) : '';
      updateDots();
      
      // Animate in
      imgEl.onload = () => {
        imgEl.style.opacity = '1';
        imgEl.style.transform = 'scale(1)';
      };
    }, 150);
  }

  function navigate(direction) {
    let newIndex = currentIndex + direction;
    if (newIndex < 0) newIndex = images.length - 1;
    if (newIndex >= images.length) newIndex = 0;
    showImage(newIndex);
  }

  function closeLightbox() {
    lightbox.style.opacity = '0';
    setTimeout(() => lightbox.remove(), 300);
    document.removeEventListener('keydown', handleKeydown);
  }

  function handleKeydown(e) {
    if (e.key === 'Escape') closeLightbox();
    if (images.length > 1) {
      if (e.key === 'ArrowRight') navigate(1);
      if (e.key === 'ArrowLeft') navigate(-1);
    }
  }

  // Close when clicking outside image (the container itself)
  lightbox.addEventListener('click', (e) => {
    if (e.target === lightbox || e.target === imgContainer) {
      closeLightbox();
    }
  });

  document.addEventListener('keydown', handleKeydown);

  // Initial render
  showImage(currentIndex);
  document.body.appendChild(lightbox);
}

/**
 * Show a confirmation modal.
 * 
 * @param {string} title - Modal title
 * @param {string} message - Modal body message
 * @param {Function} onConfirm - Callback when confirm is clicked
 */
function showConfirmModal(title, message, onConfirm) {
  const existing = document.getElementById('confirm-modal');
  if (existing !== null) { existing.remove(); }

  const modal = document.createElement('div');
  modal.id = 'confirm-modal';
  modal.className = 'lightbox-overlay';
  modal.style.cssText = `
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 9500;
    backdrop-filter: blur(4px); display: flex;
    align-items: center; justify-content: center;
    animation: fadeIn 200ms ease-out;
  `;

  modal.innerHTML = `
    <div class="glass-panel" style="width: 100%; max-width: 400px; padding: var(--space-6); margin: var(--space-4);">
      <h3 style="font-size: var(--font-size-lg); font-weight: 700; margin-bottom: var(--space-2);">${DOMPurify.sanitize(title)}</h3>
      <p style="color: var(--color-text-secondary); margin-bottom: var(--space-6);">${DOMPurify.sanitize(message)}</p>
      <div style="display: flex; gap: var(--space-3); justify-content: flex-end;">
        <button class="btn btn--ghost" id="confirm-cancel">Cancel</button>
        <button class="btn btn--danger" id="confirm-ok">Confirm</button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  const close = () => {
    modal.style.opacity = '0';
    setTimeout(() => modal.remove(), 200);
  };

  document.getElementById('confirm-cancel').addEventListener('click', close);
  document.getElementById('confirm-ok').addEventListener('click', () => {
    close();
    onConfirm();
  });
}
