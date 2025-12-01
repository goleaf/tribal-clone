-- Migration: rename legacy *_pl columns to English equivalents
-- Run the section that matches your database engine.

-- =========================
-- MySQL / MariaDB
-- =========================
ALTER TABLE building_types CHANGE name_pl name VARCHAR(100) NOT NULL;
ALTER TABLE building_types CHANGE description_pl description TEXT;

ALTER TABLE unit_types CHANGE name_pl name VARCHAR(100) NOT NULL;
ALTER TABLE unit_types CHANGE description_pl description TEXT;

ALTER TABLE research_types CHANGE name_pl name VARCHAR(100) NOT NULL;

-- =========================
-- SQLite (3.25+ supports RENAME COLUMN)
-- =========================
ALTER TABLE building_types RENAME COLUMN name_pl TO name;
ALTER TABLE building_types RENAME COLUMN description_pl TO description;

ALTER TABLE unit_types RENAME COLUMN name_pl TO name;
ALTER TABLE unit_types RENAME COLUMN description_pl TO description;

ALTER TABLE research_types RENAME COLUMN name_pl TO name;
