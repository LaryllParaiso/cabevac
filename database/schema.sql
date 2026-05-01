-- ============================================
-- CabEvac Database Schema
-- Cabanatuan City Flood Evacuation Mapping System
-- ============================================
-- Run this file in phpMyAdmin or MySQL CLI:
--   mysql -u root < database/schema.sql
-- ============================================

CREATE DATABASE IF NOT EXISTS cabevac_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE cabevac_db;

-- ============================================
-- USERS TABLE (Admin Authentication)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  email         VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(100) NOT NULL,
  role          ENUM('admin') DEFAULT 'admin' NOT NULL,
  reset_token   VARCHAR(255) NULL,
  reset_expires DATETIME NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- CATEGORIES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS categories (
  id   INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) UNIQUE NOT NULL,
  slug VARCHAR(100) UNIQUE NOT NULL
) ENGINE=InnoDB;

INSERT INTO categories (name, slug) VALUES
  ('Evacuation Center', 'evacuation-center'),
  ('Barangay', 'barangay'),
  ('Flood Hazard Zone', 'flood-hazard-zone');

-- ============================================
-- BARANGAYS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS barangays (
  id                 INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name               VARCHAR(150) UNIQUE NOT NULL,
  pcode              VARCHAR(20) NULL,
  object_id          INT NULL,
  population         INT UNSIGNED NULL,
  population_density DECIMAL(12,4) NULL,
  area_sqkm          DECIMAL(10,6) NULL,
  flood_risk_level   ENUM('low', 'moderate', 'high', 'very_high') NULL,
  land_use           VARCHAR(150) NULL,
  description        TEXT NULL,
  boundary_geojson   JSON NULL,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_barangays_flood_risk (flood_risk_level)
) ENGINE=InnoDB;

-- ============================================
-- EVACUATION CENTERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS evacuation_centers (
  id           INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name         VARCHAR(255) NOT NULL,
  description  TEXT NULL,
  latitude     DECIMAL(10,8) NOT NULL,
  longitude    DECIMAL(11,8) NOT NULL,
  capacity     INT UNSIGNED NULL,
  barangay_id  INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED NOT NULL DEFAULT 1,
  status       ENUM('active', 'inactive') DEFAULT 'active' NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (barangay_id)  REFERENCES barangays(id) ON DELETE RESTRICT,
  FOREIGN KEY (category_id)  REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================
-- EVACUATION CENTER IMAGES TABLE (1:N)
-- ============================================
CREATE TABLE IF NOT EXISTS evacuation_center_images (
  id                    INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  evacuation_center_id  INT UNSIGNED NOT NULL,
  image_path            VARCHAR(500) NOT NULL,
  alt_text              VARCHAR(255) NULL,
  sort_order            TINYINT UNSIGNED DEFAULT 0,
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (evacuation_center_id) REFERENCES evacuation_centers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- FLOOD HAZARD ZONES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS flood_hazard_zones (
  id           INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  barangay_id  INT UNSIGNED NOT NULL,
  risk_level   ENUM('very_high', 'high', 'moderate') NOT NULL,
  area_sqkm    DECIMAL(10,6) NULL,
  geometry     JSON NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (barangay_id) REFERENCES barangays(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================
-- GIS LAYERS TABLE (static GeoJSON overlays)
-- ============================================
CREATE TABLE IF NOT EXISTS gis_layers (
  id           INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name         VARCHAR(150) NOT NULL,
  slug         VARCHAR(150) UNIQUE NOT NULL,
  layer_type   ENUM('buffer', 'isochrone', 'road', 'boundary', 'network_analysis', 'population_density', 'coverage') NOT NULL,
  geojson_data LONGTEXT NOT NULL,
  is_visible   TINYINT(1) DEFAULT 1,
  sort_order   TINYINT UNSIGNED DEFAULT 0,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- INDEXES
-- ============================================
CREATE INDEX idx_evac_centers_coords ON evacuation_centers(latitude, longitude);
CREATE INDEX idx_evac_centers_status ON evacuation_centers(status);
CREATE INDEX idx_evac_centers_barangay ON evacuation_centers(barangay_id);
CREATE INDEX idx_flood_zones_risk ON flood_hazard_zones(risk_level);
CREATE INDEX idx_flood_zones_barangay ON flood_hazard_zones(barangay_id);
CREATE INDEX idx_gis_layers_type ON gis_layers(layer_type);
CREATE INDEX idx_gis_layers_visible ON gis_layers(is_visible);

-- ============================================
-- DEFAULT ADMIN USER
-- Password: admin123 (CHANGE ON FIRST LOGIN)
-- Hash generated via: password_hash('admin123', PASSWORD_BCRYPT)
-- ============================================
INSERT INTO users (email, password_hash, display_name, role) VALUES
  ('admin@cabevac.local', '$2y$12$3pVr7/1E8PVI2.ij1S/giuCmrNCK4U7J7EnQXvkUrG0UdCFCS8PNa', 'Admin', 'admin');
