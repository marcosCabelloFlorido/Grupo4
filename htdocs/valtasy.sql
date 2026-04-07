-- ==========================================
-- 0. LIMPIEZA TOTAL (DROP TABLES)
-- ==========================================
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS alineaciones;
DROP TABLE IF EXISTS estadisticas;
DROP TABLE IF EXISTS jugadores;
DROP TABLE IF EXISTS equipos_fantasy;
DROP TABLE IF EXISTS partidos;
DROP TABLE IF EXISTS participaciones;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS ligas;
DROP TABLE IF EXISTS equipos_profesionales;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- 1. TABLAS INDEPENDIENTES
-- ==========================================

CREATE TABLE usuarios (
    id_usuario INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    rol VARCHAR(20) DEFAULT 'cliente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ligas (
    id_liga INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(50), 
    max_participantes INT DEFAULT 20
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE equipos_profesionales (
    id_equipo_profesional INT AUTO_INCREMENT PRIMARY KEY,
    nombre_equipo_profesional VARCHAR(100) NOT NULL,
    region VARCHAR(50),
    ranking INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 2. TABLAS DE RELACIÓN (PRIMER NIVEL)
-- ==========================================

CREATE TABLE participaciones (
    id_usuario INT,
    id_liga INT,
    posicion_actual INT DEFAULT 0,
    PRIMARY KEY (id_usuario, id_liga),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_liga) REFERENCES ligas(id_liga) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE partidos (
    id_partido INT AUTO_INCREMENT PRIMARY KEY,
    id_equipo_local INT,
    id_equipo_visitante INT,
    fecha DATETIME,
    torneo VARCHAR(100),
    ganador INT NULL,
    FOREIGN KEY (id_equipo_local) REFERENCES equipos_profesionales(id_equipo_profesional) ON DELETE CASCADE,
    FOREIGN KEY (id_equipo_visitante) REFERENCES equipos_profesionales(id_equipo_profesional) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- 3. TABLAS DE SEGUNDO NIVEL
-- ==========================================

CREATE TABLE equipos_fantasy (
    id_equipo_fantasy INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT,
    id_liga INT,
    nombre_equipo VARCHAR(100) NOT NULL,
    presupuesto_disponible DECIMAL(15, 2) DEFAULT 1000000.00,
    puntos_equipo INT DEFAULT 0,
    FOREIGN KEY (id_usuario, id_liga) REFERENCES participaciones(id_usuario, id_liga) ON DELETE CASCADE
) ENGINE=InnoDB;

-- CORRECCIÓN: Quitamos id_equipo_fantasy de aquí para permitir MULTILIGA
-- Añadimos columna ROL para el reparto táctico
CREATE TABLE jugadores (
    id_jugador INT AUTO_INCREMENT PRIMARY KEY,
    id_equipo_profesional INT NULL, 
    nickname VARCHAR(50) NOT NULL,
    nombre_real VARCHAR(150),
    rol VARCHAR(30) NOT NULL, -- Duelista, Iniciador, Centinela, Smoker
    precio_mercado DECIMAL(15, 2),
    media_punto FLOAT DEFAULT 0.0,
    FOREIGN KEY (id_equipo_profesional) REFERENCES equipos_profesionales(id_equipo_profesional) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 4. TABLAS DE TERCER NIVEL
-- ==========================================

CREATE TABLE estadisticas (
    id_estadistica INT AUTO_INCREMENT PRIMARY KEY,
    id_jugador INT,
    id_partido INT,
    kills INT DEFAULT 0,
    deaths INT DEFAULT 0,
    assist INT DEFAULT 0,
    ace INT DEFAULT 0,
    clutch INT DEFAULT 0,
    punto_fantasy FLOAT DEFAULT 0.0,
    FOREIGN KEY (id_jugador) REFERENCES jugadores(id_jugador) ON DELETE CASCADE,
    FOREIGN KEY (id_partido) REFERENCES partidos(id_partido) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE alineaciones (
    id_equipo_fantasy INT,
    id_jugador INT,
    jornada INT,
    puntos_jornada FLOAT DEFAULT 0.0,
    titular BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (id_equipo_fantasy, id_jugador, jornada),
    FOREIGN KEY (id_equipo_fantasy) REFERENCES equipos_fantasy(id_equipo_fantasy) ON DELETE CASCADE,
    FOREIGN KEY (id_jugador) REFERENCES jugadores(id_jugador) ON DELETE CASCADE
) ENGINE=InnoDB;