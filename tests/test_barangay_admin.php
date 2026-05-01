<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/barangay_admin.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$valid = [
    'name' => 'Test Barangay',
    'pcode' => 'PH034903001',
    'population' => '1200',
    'population_density' => '456.789',
    'area_sqkm' => '12.345678',
    'flood_risk_level' => 'moderate',
    'land_use' => 'Residential',
    'description' => 'Sample description',
];

assertSameValue([], validateBarangayPayload($valid), 'Valid barangay payload should pass.');

$missingName = $valid;
$missingName['name'] = ' ';
assertSameValue(['Name is required'], validateBarangayPayload($missingName), 'Blank name should be rejected.');

$badRisk = $valid;
$badRisk['flood_risk_level'] = 'severe';
assertSameValue(['Invalid flood risk level'], validateBarangayPayload($badRisk), 'Unknown risk should be rejected.');

$badNumbers = $valid;
$badNumbers['population'] = '-1';
$badNumbers['population_density'] = 'many';
$badNumbers['area_sqkm'] = '-0.5';
assertSameValue(
    ['Population must be a non-negative whole number', 'Population density must be a non-negative number', 'Area must be a non-negative number'],
    validateBarangayPayload($badNumbers),
    'Invalid numeric fields should be rejected.'
);

$normalized = normalizeBarangayPayload([
    'name' => '  New Barangay  ',
    'pcode' => '',
    'population' => '',
    'population_density' => '100.50',
    'area_sqkm' => '',
    'flood_risk_level' => '',
    'land_use' => '  Mixed Use  ',
    'description' => '',
]);

assertSameValue('New Barangay', $normalized['name'], 'Name should be trimmed.');
assertSameValue(null, $normalized['pcode'], 'Blank pcode should normalize to null.');
assertSameValue(null, $normalized['population'], 'Blank population should normalize to null.');
assertSameValue(100.50, $normalized['population_density'], 'Density should normalize to float.');
assertSameValue(null, $normalized['area_sqkm'], 'Blank area should normalize to null.');
assertSameValue(null, $normalized['flood_risk_level'], 'Blank risk should normalize to null.');
assertSameValue('Mixed Use', $normalized['land_use'], 'Land use should be trimmed.');
assertSameValue(null, $normalized['description'], 'Blank description should normalize to null.');

assertSameValue(
    'Cannot delete barangay with linked evacuation centers or flood hazard zones.',
    barangayDeleteBlockedMessage(2, 1),
    'Linked barangay delete message should be clear.'
);

echo "Barangay admin helper tests passed." . PHP_EOL;
