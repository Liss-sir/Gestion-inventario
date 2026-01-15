-- ============================================
-- Tabla para relación FICHAS - ESTUDIANTES
-- ============================================
-- Esta tabla almacena la relación entre fichas
-- y estudiantes (usuarios con cargo 'Aprendiz')
-- ============================================

CREATE TABLE IF NOT EXISTS fichas_estudiantes (
    id_ficha_estudiante INT AUTO_INCREMENT PRIMARY KEY,
    id_ficha INT NOT NULL,
    id_estudiante INT NOT NULL,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (id_ficha) REFERENCES fichas(id_ficha) ON DELETE CASCADE,
    FOREIGN KEY (id_estudiante) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    
    -- Evitar duplicados
    UNIQUE KEY unique_ficha_estudiante (id_ficha, id_estudiante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices para mejorar rendimiento
CREATE INDEX idx_id_ficha ON fichas_estudiantes(id_ficha);
CREATE INDEX idx_id_estudiante ON fichas_estudiantes(id_estudiante);
