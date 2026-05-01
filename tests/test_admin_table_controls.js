const assert = require('assert');
const { getAdminTableView } = require('../js/adminTableControls');

const rows = [
  { id: 1, name: 'Lazaro School', barangay: 'Vijandre', capacity: 120, status: 'active' },
  { id: 2, name: 'Araullo University', barangay: 'Bitas', capacity: 500, status: 'active' },
  { id: 3, name: 'San Josef NHS', barangay: 'San Josef Sur', capacity: null, status: 'inactive' },
  { id: 4, name: 'Midway Colleges', barangay: 'Bitas', capacity: 300, status: 'active' },
];

let view = getAdminTableView(rows, {
  query: 'bitas',
  sortKey: 'name',
  sortDirection: 'asc',
  page: 1,
  pageSize: 10,
  searchableKeys: ['name', 'barangay', 'status'],
});

assert.deepStrictEqual(
  view.items.map((row) => row.name),
  ['Araullo University', 'Midway Colleges'],
  'filters by searchable fields and sorts by name'
);
assert.strictEqual(view.totalItems, 2);
assert.strictEqual(view.totalPages, 1);

view = getAdminTableView(rows, {
  query: '',
  sortKey: 'capacity',
  sortDirection: 'desc',
  page: 1,
  pageSize: 2,
  searchableKeys: ['name', 'barangay', 'status'],
});

assert.deepStrictEqual(
  view.items.map((row) => row.name),
  ['Araullo University', 'Midway Colleges'],
  'sorts numeric values descending and returns the requested page slice'
);
assert.strictEqual(view.totalItems, 4);
assert.strictEqual(view.totalPages, 2);
assert.strictEqual(view.page, 1);

view = getAdminTableView(rows, {
  query: 'zzzz',
  sortKey: 'name',
  sortDirection: 'asc',
  page: 5,
  pageSize: 2,
  searchableKeys: ['name', 'barangay', 'status'],
});

assert.deepStrictEqual(view.items, [], 'returns no rows for empty search results');
assert.strictEqual(view.page, 1, 'clamps empty results to page 1');
assert.strictEqual(view.totalPages, 1);

console.log('Admin table control tests passed.');
