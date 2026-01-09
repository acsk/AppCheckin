-- Migration: Remove horarios table dependency from turmas
-- Desc: Add horario_inicio and horario_fim directly to turmas table
-- Remove horario_id foreign key
-- Date: 2026-01-09

-- Add new columns to turmas
ALTER TABLE turmas
ADD COLUMN horario_inicio TIME NOT NULL DEFAULT '06:00:00' AFTER dia_id,
ADD COLUMN horario_fim TIME NOT NULL DEFAULT '07:00:00' AFTER horario_inicio;

-- Drop the foreign key constraint first
ALTER TABLE turmas
DROP FOREIGN KEY turmas_ibfk_5;

