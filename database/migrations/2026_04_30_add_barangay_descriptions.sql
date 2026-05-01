-- ============================================
-- CabEvac — Barangay Descriptions Migration
-- Adds flood risk level, land use, and description
-- columns to the barangays table.
-- Safe to re-run.
-- ============================================

ALTER TABLE barangays
  ADD COLUMN IF NOT EXISTS flood_risk_level
    ENUM('low', 'moderate', 'high', 'very_high') NULL
    AFTER area_sqkm,
  ADD COLUMN IF NOT EXISTS land_use
    VARCHAR(150) NULL
    AFTER flood_risk_level,
  ADD COLUMN IF NOT EXISTS description
    TEXT NULL
    AFTER land_use;

-- Helpful index for filtering by risk
CREATE INDEX IF NOT EXISTS idx_barangays_flood_risk
  ON barangays (flood_risk_level);
