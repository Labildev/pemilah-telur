-- database/schema.sql
-- =========================================================
-- Jalankan file SQL ini di phpMyAdmin untuk membuat tabel
-- =========================================================

CREATE DATABASE IF NOT EXISTS egg_sorter;
USE egg_sorter;

CREATE TABLE IF NOT EXISTS egg_sort_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    weight DECIMAL(5, 2) NOT NULL, -- Berat dalam gram (contoh: 54.20)
    gas_value INT NOT NULL,        -- Nilai gas MQ-135 raw ADC
    category ENUM('ringan', 'sedang', 'berat', 'busuk') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index untuk mempercepat query filter riwayat & statistik
CREATE INDEX idx_category ON egg_sort_results(category);
CREATE INDEX idx_created_at ON egg_sort_results(created_at);
