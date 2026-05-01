(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.AdminTableControls = factory();
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  function normalizeValue(value) {
    if (value === null || value === undefined) { return ''; }
    return String(value).toLowerCase();
  }

  function compareValues(a, b, direction) {
    const dir = direction === 'desc' ? -1 : 1;
    const aEmpty = a === null || a === undefined || a === '';
    const bEmpty = b === null || b === undefined || b === '';

    if (aEmpty && bEmpty) { return 0; }
    if (aEmpty) { return 1; }
    if (bEmpty) { return -1; }

    const aNum = Number(a);
    const bNum = Number(b);
    if (!Number.isNaN(aNum) && !Number.isNaN(bNum)) {
      return aNum === bNum ? 0 : (aNum > bNum ? dir : -dir);
    }

    return String(a).localeCompare(String(b), undefined, { sensitivity: 'base' }) * dir;
  }

  function getAdminTableView(rows, options) {
    const query = normalizeValue(options.query || '').trim();
    const searchableKeys = options.searchableKeys || [];
    const sortKey = options.sortKey || '';
    const sortDirection = options.sortDirection === 'desc' ? 'desc' : 'asc';
    const pageSize = Math.max(1, parseInt(options.pageSize || 10, 10));

    let filtered = rows.slice();
    if (query !== '') {
      filtered = filtered.filter((row) =>
        searchableKeys.some((key) => normalizeValue(row[key]).includes(query))
      );
    }

    if (sortKey !== '') {
      filtered.sort((a, b) => compareValues(a[sortKey], b[sortKey], sortDirection));
    }

    const totalItems = filtered.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
    const requestedPage = parseInt(options.page || 1, 10);
    const page = Math.min(Math.max(Number.isNaN(requestedPage) ? 1 : requestedPage, 1), totalPages);
    const start = (page - 1) * pageSize;

    return {
      items: filtered.slice(start, start + pageSize),
      totalItems,
      totalPages,
      page,
      pageSize,
    };
  }

  function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function initAdminDataTable(config) {
    const searchInput = document.getElementById(config.searchInputId);
    const pageSizeSelect = document.getElementById(config.pageSizeSelectId);
    const tbody = document.getElementById(config.tbodyId);
    const summary = document.getElementById(config.summaryId);
    const pagination = document.getElementById(config.paginationId);
    const sortableHeaders = document.querySelectorAll(`[data-admin-table="${config.tableId}"][data-sort-key]`);

    if (!searchInput || !pageSizeSelect || !tbody || !summary || !pagination) { return; }

    const state = {
      query: '',
      sortKey: config.defaultSortKey || '',
      sortDirection: config.defaultSortDirection || 'asc',
      page: 1,
      pageSize: parseInt(pageSizeSelect.value || config.defaultPageSize || 10, 10),
    };

    function render() {
      const view = getAdminTableView(config.rows, {
        query: state.query,
        sortKey: state.sortKey,
        sortDirection: state.sortDirection,
        page: state.page,
        pageSize: state.pageSize,
        searchableKeys: config.searchableKeys,
      });

      state.page = view.page;
      tbody.innerHTML = view.items.length > 0
        ? view.items.map(config.renderRow).join('')
        : config.renderEmptyRow();

      const start = view.totalItems === 0 ? 0 : ((view.page - 1) * view.pageSize) + 1;
      const end = Math.min(view.page * view.pageSize, view.totalItems);
      summary.textContent = `Showing ${start}-${end} of ${view.totalItems}`;

      renderPagination(view);
      renderSortHeaders();
    }

    function renderPagination(view) {
      const buttons = [];
      buttons.push(`<button type="button" class="admin-pagination__btn" data-page="${view.page - 1}" ${view.page <= 1 ? 'disabled' : ''}>Prev</button>`);

      for (let i = 1; i <= view.totalPages; i += 1) {
        if (i === 1 || i === view.totalPages || Math.abs(i - view.page) <= 1) {
          buttons.push(`<button type="button" class="admin-pagination__btn ${i === view.page ? 'is-active' : ''}" data-page="${i}">${i}</button>`);
        } else if (buttons[buttons.length - 1] !== '<span class="admin-pagination__ellipsis">...</span>') {
          buttons.push('<span class="admin-pagination__ellipsis">...</span>');
        }
      }

      buttons.push(`<button type="button" class="admin-pagination__btn" data-page="${view.page + 1}" ${view.page >= view.totalPages ? 'disabled' : ''}>Next</button>`);
      pagination.innerHTML = buttons.join('');

      pagination.querySelectorAll('[data-page]').forEach((button) => {
        button.addEventListener('click', () => {
          state.page = parseInt(button.dataset.page || '1', 10);
          render();
        });
      });
    }

    function renderSortHeaders() {
      sortableHeaders.forEach((header) => {
        const key = header.dataset.sortKey || '';
        header.classList.toggle('is-sorted', key === state.sortKey);
        header.dataset.sortDirection = key === state.sortKey ? state.sortDirection : '';
      });
    }

    searchInput.addEventListener('input', () => {
      state.query = searchInput.value;
      state.page = 1;
      render();
    });

    pageSizeSelect.addEventListener('change', () => {
      state.pageSize = parseInt(pageSizeSelect.value, 10);
      state.page = 1;
      render();
    });

    sortableHeaders.forEach((header) => {
      header.addEventListener('click', () => {
        const key = header.dataset.sortKey || '';
        if (state.sortKey === key) {
          state.sortDirection = state.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
          state.sortKey = key;
          state.sortDirection = 'asc';
        }
        state.page = 1;
        render();
      });
    });

    render();
  }

  return {
    escapeHtml,
    getAdminTableView,
    initAdminDataTable,
  };
}));
