/**
 * CabEvac — Mobile Bottom Sheet
 * Three snap states matching the design images:
 *
 *   peek  (~120px) — compact card: thumbnail + name + coverage pill
 *                    map fully visible above  (image 3)
 *
 *   mid   (~56vh)  — map peek on top, gallery + coverage chips + Get Directions
 *                    body is scrollable       (image 2)
 *
 *   full  (~92vh)  — entire screen, scrollable detail  (image 1)
 *
 * Interactions:
 *   open            → peek
 *   tap peek-bar    → mid
 *   drag handle up  → mid → full
 *   drag handle dn  → full → mid → peek → close
 *   tap close ✕     → close
 *
 * @module mobileSheet
 */

/** @type {Object|null} Currently displayed center data */
let mobileSheetCenter = null;

/** @type {'hidden'|'peek'|'mid'|'full'} Current sheet state */
let mobileSheetState = 'hidden';

/** Drag tracking */
const drag = { active: false, startY: 0 };

/** State → CSS class map */
const STATE_CLASS = {
  peek: 'mobile-sheet--peek',
  mid:  'mobile-sheet--mid',
  full: 'mobile-sheet--full',
};

/**
 * Returns true when the viewport is mobile (≤768px).
 * @returns {boolean}
 */
function isMobileViewport() {
  return window.innerWidth <= 768;
}

// ─── Public API ───────────────────────────────────────────────────────────────

/**
 * Open the sheet in peek state, populate data, and show coverage polygon.
 * @param {Object} centerData
 */
function openMobileSheet(centerData) {
  if (!isMobileViewport()) { return; }

  mobileSheetCenter = centerData;

  const sheet = document.getElementById('mobile-sheet');
  if (sheet === null) { return; }

  _populateSheet(centerData);
  sheet.setAttribute('aria-hidden', 'false');
  _setState('peek');

  // Trigger coverage polygon immediately — no marker tap needed
  if (typeof showCoverageForCenter === 'function') {
    showCoverageForCenter(centerData.name || '', (barangays) => {
      _renderCoveragePill(barangays);
      _renderCoverageList(barangays);
    });
  }
}

/**
 * Advance sheet to mid (expanded) state.
 */
function expandToMid() {
  if (mobileSheetState === 'hidden') { return; }
  _setState('mid');
}

/**
 * Advance sheet to full state.
 */
function expandToFull() {
  if (mobileSheetState === 'hidden') { return; }
  _setState('full');
}

/**
 * Collapse sheet back to peek state.
 */
function collapseToPeek() {
  if (mobileSheetState === 'hidden') { return; }
  _setState('peek');
}

/**
 * Close and hide the sheet entirely.
 */
function closeMobileSheet() {
  const sheet = document.getElementById('mobile-sheet');
  if (sheet === null) { return; }

  const gallery = document.getElementById('mobile-sheet-gallery');
  if (gallery && gallery.dataset.intervalId) {
    clearInterval(parseInt(gallery.dataset.intervalId));
    gallery.removeAttribute('data-interval-id');
  }

  sheet.classList.remove(...Object.values(STATE_CLASS));
  sheet.setAttribute('aria-hidden', 'true');
  mobileSheetState = 'hidden';
  mobileSheetCenter = null;

  if (typeof hideCoverageHighlight === 'function') { hideCoverageHighlight(); }
  if (typeof clearEvacuationMarkerHighlights === 'function') { clearEvacuationMarkerHighlights(); }
}

// ─── Internal helpers ─────────────────────────────────────────────────────────

/**
 * Apply a state class and update mobileSheetState.
 * @param {'peek'|'mid'|'full'} state
 */
function _setState(state) {
  const sheet = document.getElementById('mobile-sheet');
  if (sheet === null) { return; }
  sheet.classList.remove(...Object.values(STATE_CLASS));
  sheet.classList.add(STATE_CLASS[state]);
  mobileSheetState = state;
}

/**
 * Populate all sheet DOM fields with center data.
 * @param {Object} c
 */
function _populateSheet(c) {
  const images = c.images || [];

  // Peek-bar: thumbnail
  const thumb = document.getElementById('mobile-sheet-thumb');
  if (thumb !== null) {
    if (images.length > 0) {
      thumb.innerHTML = `<img src="${DOMPurify.sanitize(images[0].image_path)}" alt="${DOMPurify.sanitize(images[0].alt_text || c.name || '')}" loading="lazy">`;
    } else {
      thumb.innerHTML = '<div class="mobile-sheet__thumb-placeholder">🏫</div>';
    }
  }

  // Peek-bar: name
  const peekName = document.getElementById('mobile-sheet-peek-name');
  if (peekName !== null) { peekName.textContent = c.name || '—'; }

  // Peek-bar: status badge (kept in its own child so coverage pill appends after)
  const peekMeta = document.getElementById('mobile-sheet-peek-meta');
  if (peekMeta !== null) {
    const isActive = c.status === 'active';
    peekMeta.innerHTML = `<span id="mobile-sheet-peek-badge" class="badge badge--${isActive ? 'active' : 'inactive'}"><span class="badge__dot"></span> ${isActive ? 'Active' : 'Inactive'}</span>`;
  }

  // Clear stale coverage pill
  const oldPill = document.getElementById('mobile-sheet-coverage-pill');
  if (oldPill !== null) { oldPill.remove(); }

  // Body: full gallery
  _renderGallery(images, c.name || '');

  // Body: info fields
  const fullName = document.getElementById('mobile-sheet-full-name');
  if (fullName !== null) { fullName.textContent = c.name || '—'; }

  const fullStatus = document.getElementById('mobile-sheet-full-status');
  if (fullStatus !== null) {
    const isActive = c.status === 'active';
    fullStatus.className = `badge badge--${isActive ? 'active' : 'inactive'}`;
    fullStatus.innerHTML = `<span class="badge__dot"></span> ${isActive ? 'Active' : 'Inactive'}`;
  }

  const barangayEl = document.getElementById('mobile-sheet-barangay');
  if (barangayEl !== null) { barangayEl.textContent = c.barangay || '—'; }

  const capacityEl = document.getElementById('mobile-sheet-capacity');
  if (capacityEl !== null) {
    capacityEl.textContent = c.capacity
      ? `${Number(c.capacity).toLocaleString()} persons`
      : 'Not specified';
  }

  // Reset directions link
  const directionsEl = document.getElementById('mobile-sheet-directions');
  if (directionsEl !== null) {
    directionsEl.href = `https://www.google.com/maps/dir/?api=1&destination=${c.latitude},${c.longitude}`;
  }

  // Reset copy-coords button (clone to remove old listeners)
  const copyBtn = document.getElementById('mobile-sheet-copy-coords');
  if (copyBtn !== null) {
    const freshBtn = copyBtn.cloneNode(true);
    copyBtn.parentNode.replaceChild(freshBtn, copyBtn);
    freshBtn.addEventListener('click', () => {
      navigator.clipboard.writeText(`${c.latitude}, ${c.longitude}`).then(() => {
        if (typeof showToast === 'function') { showToast('Coordinates copied', 'success'); }
      }).catch(() => {
        if (typeof showToast === 'function') { showToast('Failed to copy', 'error'); }
      });
    });
  }

  // Coverage: reset to loading
  const covWrapper = document.getElementById('mobile-sheet-coverage-wrapper');
  const covList    = document.getElementById('mobile-sheet-coverage-list');
  if (covWrapper !== null) { covWrapper.style.display = 'none'; }
  if (covList    !== null) { covList.innerHTML = ''; }

  // Scroll body back to top
  const body = document.getElementById('mobile-sheet-body');
  if (body !== null) { body.scrollTop = 0; }
}

/**
 * Build swipeable image gallery in the body panel.
 * @param {Array}  images
 * @param {string} centerName
 */
function _renderGallery(images, centerName) {
  const gallery = document.getElementById('mobile-sheet-gallery');
  if (gallery === null) { return; }

  gallery.innerHTML = '';

  // Remove stale dots row
  const oldDots = document.getElementById('mobile-sheet-gallery-dots');
  if (oldDots !== null) { oldDots.remove(); }

  if (images.length === 0) {
    gallery.innerHTML = '<div class="mobile-sheet__gallery-placeholder">🏫</div>';
    return;
  }

  images.forEach((img, index) => {
    const el = document.createElement('img');
    el.src     = DOMPurify.sanitize(img.image_path);
    el.alt     = DOMPurify.sanitize(img.alt_text || centerName);
    el.loading = 'lazy';
    el.addEventListener('click', () => {
      if (typeof openImageLightbox === 'function') { openImageLightbox(images, index); }
    });
    gallery.appendChild(el);
  });

  // Dot indicators (only when multiple images)
  if (images.length > 1) {
    const dotsEl = document.createElement('div');
    dotsEl.className = 'mobile-sheet__gallery-dots';
    dotsEl.id = 'mobile-sheet-gallery-dots';

    images.forEach((_, i) => {
      const dot = document.createElement('div');
      dot.className = `mobile-sheet__gallery-dot${i === 0 ? ' mobile-sheet__gallery-dot--active' : ''}`;
      dotsEl.appendChild(dot);
    });

    gallery.parentNode.insertBefore(dotsEl, gallery.nextSibling);

    gallery.addEventListener('scroll', () => {
      const active = Math.round(gallery.scrollLeft / gallery.offsetWidth);
      dotsEl.querySelectorAll('.mobile-sheet__gallery-dot').forEach((d, i) => {
        d.classList.toggle('mobile-sheet__gallery-dot--active', i === active);
      });
    }, { passive: true });

    // Auto-slide logic
    if (gallery.dataset.intervalId) {
      clearInterval(parseInt(gallery.dataset.intervalId));
    }
    
    let autoSlideInterval = setInterval(() => {
      let active = Math.round(gallery.scrollLeft / gallery.offsetWidth);
      let nextIndex = active + 1;
      if (nextIndex >= images.length) nextIndex = 0;
      gallery.scrollTo({ left: nextIndex * gallery.offsetWidth, behavior: 'smooth' });
    }, 3000);
    
    gallery.dataset.intervalId = autoSlideInterval.toString();

    const resetAutoSlide = () => {
      clearInterval(autoSlideInterval);
      autoSlideInterval = setInterval(() => {
        let active = Math.round(gallery.scrollLeft / gallery.offsetWidth);
        let nextIndex = active + 1;
        if (nextIndex >= images.length) nextIndex = 0;
        gallery.scrollTo({ left: nextIndex * gallery.offsetWidth, behavior: 'smooth' });
      }, 3000);
      gallery.dataset.intervalId = autoSlideInterval.toString();
    };

    gallery.addEventListener('touchstart', () => clearInterval(autoSlideInterval), { passive: true });
    gallery.addEventListener('touchend', resetAutoSlide, { passive: true });
  }
}

/**
 * Render coverage pill inside the peek-bar meta section.
 * @param {Array} barangays
 */
function _renderCoveragePill(barangays) {
  const peekMeta = document.getElementById('mobile-sheet-peek-meta');
  if (peekMeta === null) { return; }

  // Remove stale pill only — badge stays
  const old = document.getElementById('mobile-sheet-coverage-pill');
  if (old !== null) { old.remove(); }

  if (barangays.length === 0) { return; }

  const pill = document.createElement('div');
  pill.id        = 'mobile-sheet-coverage-pill';
  pill.className = 'mobile-sheet__coverage-pill';
  const names = barangays.slice(0, 2).map((b) => b.name).join(', ');
  const extra = barangays.length > 2 ? ` +${barangays.length - 2}` : '';
  pill.textContent = `Coverage: ${names}${extra}`;
  // Append after the badge (not replacing it)
  peekMeta.appendChild(pill);
}

/**
 * Render coverage chips inside the body panel.
 * @param {Array} barangays
 */
function _renderCoverageList(barangays) {
  const covWrapper = document.getElementById('mobile-sheet-coverage-wrapper');
  const covList    = document.getElementById('mobile-sheet-coverage-list');
  if (covWrapper === null || covList === null) { return; }

  if (barangays.length === 0) {
    covWrapper.style.display = 'none';
    return;
  }

  covWrapper.style.display = 'block';
  covList.innerHTML = '';

  barangays.forEach((b) => {
    const chip = document.createElement('span');
    chip.className = 'detail-coverage-chip';
    chip.title = b.area ? `${b.name} · ${b.area} km²` : b.name;
    chip.innerHTML = `
      <span class="detail-coverage-chip__dot"></span>
      ${DOMPurify.sanitize(b.name)}${b.area ? `<span class="detail-coverage-chip__area">${b.area} km²</span>` : ''}`;
    covList.appendChild(chip);
  });
}

// ─── Gesture / event wiring ───────────────────────────────────────────────────

function initMobileSheet() {
  const sheet   = document.getElementById('mobile-sheet');
  const handle  = document.getElementById('mobile-sheet-handle');
  const closeBtn = document.getElementById('mobile-sheet-close');
  const peekBar = document.getElementById('mobile-sheet-peek-bar');

  if (sheet === null) { return; }

  // ✕ close button
  if (closeBtn !== null) {
    closeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      closeMobileSheet();
      if (typeof clearSearchInput === 'function') { clearSearchInput(); }
    });
  }

  // Tap peek-bar → toggle between peek ↔ mid
  if (peekBar !== null) {
    peekBar.addEventListener('click', (e) => {
      if (e.target.closest('.mobile-sheet__close-btn')) { return; }
      if (mobileSheetState === 'peek') { expandToMid(); }
      else if (mobileSheetState === 'mid') { _setState('peek'); }
    });
  }

  // Drag handle — touch events
  if (handle !== null) {
    handle.addEventListener('touchstart', _dragStart, { passive: true });
    handle.addEventListener('touchmove',  _dragMove,  { passive: false });
    handle.addEventListener('touchend',   _dragEnd,   { passive: true });
  }
}

function _dragStart(e) {
  drag.active = true;
  drag.startY = e.touches[0].clientY;
  const sheet = document.getElementById('mobile-sheet');
  if (sheet !== null) { sheet.style.transition = 'none'; }
}

function _dragMove(e) {
  if (!drag.active) { return; }
  const dy = e.touches[0].clientY - drag.startY;
  if (dy < 0) { return; } // don't allow pulling up via drag
  e.preventDefault();
  const sheet = document.getElementById('mobile-sheet');
  if (sheet !== null) {
    sheet.style.transform = `translateY(${dy}px)`;
  }
}

function _dragEnd(e) {
  if (!drag.active) { return; }
  drag.active = false;

  const sheet = document.getElementById('mobile-sheet');
  if (sheet === null) { return; }
  sheet.style.transition = '';
  sheet.style.transform  = '';

  const dy        = e.changedTouches[0].clientY - drag.startY;
  const THRESHOLD = 70;

  if (dy > THRESHOLD) {
    // Dragged downward — step back
    if (mobileSheetState === 'full') {
      _setState('mid');
    } else if (mobileSheetState === 'mid') {
      _setState('peek');
    } else if (mobileSheetState === 'peek') {
      closeMobileSheet();
      if (typeof clearSearchInput === 'function') { clearSearchInput(); }
    }
  } else if (dy < -THRESHOLD) {
    // Dragged upward — step forward
    if (mobileSheetState === 'mid') {
      _setState('full');
    } else if (mobileSheetState === 'peek') {
      _setState('mid');
    }
  }
}
