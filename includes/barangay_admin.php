<?php
/**
 * CabEvac — Admin Barangay Helpers
 *
 * Validation and normalization used by the protected barangay admin API.
 *
 * @package CabEvac
 */

declare(strict_types=1);

const BARANGAY_RISK_LEVELS = ['low', 'moderate', 'high', 'very_high'];

/**
 * Validate editable barangay payload fields.
 *
 * @param array<string, mixed> $data
 * @return array<string>
 */
function validateBarangayPayload(array $data): array
{
    $errors = [];

    if (empty($data['name']) || trim((string)$data['name']) === '') {
        $errors[] = 'Name is required';
    }

    if (isset($data['population']) && $data['population'] !== '') {
        $population = filter_var($data['population'], FILTER_VALIDATE_INT);
        if ($population === false || $population < 0) {
            $errors[] = 'Population must be a non-negative whole number';
        }
    }

    if (isset($data['population_density']) && $data['population_density'] !== '') {
        if (!is_numeric($data['population_density']) || (float)$data['population_density'] < 0) {
            $errors[] = 'Population density must be a non-negative number';
        }
    }

    if (isset($data['area_sqkm']) && $data['area_sqkm'] !== '') {
        if (!is_numeric($data['area_sqkm']) || (float)$data['area_sqkm'] < 0) {
            $errors[] = 'Area must be a non-negative number';
        }
    }

    if (isset($data['flood_risk_level']) && $data['flood_risk_level'] !== '') {
        if (!in_array((string)$data['flood_risk_level'], BARANGAY_RISK_LEVELS, true)) {
            $errors[] = 'Invalid flood risk level';
        }
    }

    return $errors;
}

/**
 * Normalize editable barangay payload fields for database writes.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function normalizeBarangayPayload(array $data): array
{
    return [
        'name' => trim((string)($data['name'] ?? '')),
        'pcode' => nullableTrim($data['pcode'] ?? null),
        'population' => nullableInt($data['population'] ?? null),
        'population_density' => nullableFloat($data['population_density'] ?? null),
        'area_sqkm' => nullableFloat($data['area_sqkm'] ?? null),
        'flood_risk_level' => nullableRiskLevel($data['flood_risk_level'] ?? null),
        'land_use' => nullableTrim($data['land_use'] ?? null),
        'description' => nullableTrim($data['description'] ?? null),
    ];
}

/**
 * Message shown when a barangay cannot be deleted safely.
 */
function barangayDeleteBlockedMessage(int $centerCount, int $floodZoneCount): string
{
    return 'Cannot delete barangay with linked evacuation centers or flood hazard zones.';
}

function nullableTrim(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $trimmed = trim((string)$value);
    return $trimmed === '' ? null : $trimmed;
}

function nullableInt(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    return (int)$value;
}

function nullableFloat(mixed $value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    return (float)$value;
}

function nullableRiskLevel(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return (string)$value;
}
