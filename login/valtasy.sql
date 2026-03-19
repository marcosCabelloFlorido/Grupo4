-- Creamos la base de datos y la usamos
CREATE DATABASE IF NOT EXISTS FantasyEsports;
USE FantasyEsports;

-- ==========================================
-- 1. TABLAS INDEPENDIENTES (Sin Claves Foráneas)
-- ==========================================

CREATE TABLE Usuarios (
    id_usuario INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    contraseña VARCHAR(255) NOT NULL,
    rol VARCHAR(20) DEFAULT 'cliente'
);

CREATE TABLE Ligas (
    id_liga INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(50), 
    max_participantes INT DEFAULT 20
);

CREATE TABLE Equipos_Profesionales (
    id_equipo_profesional INT AUTO_INCREMENT PRIMARY KEY,
    nombre_equipo_profesional VARCHAR(100) NOT NULL,
    region VARCHAR(50),
    ranking INT
);

-- ==========================================
-- 2. TABLAS DE PRIMER NIVEL DE DEPENDENCIA
-- ==========================================

-- Resuelve la relación N:M entre Usuarios y Ligas
CREATE TABLE Participaciones (
    id_usuario INT,
    id_liga INT,
    posicion_actual INT DEFAULT 0,
    PRIMARY KEY (id_usuario, id_liga),
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_liga) REFERENCES Ligas(id_liga) ON DELETE CASCADE
);

CREATE TABLE Partidos (
    id_partido INT AUTO_INCREMENT PRIMARY KEY,
    id_equipo_local INT,
    id_equipo_visitante INT,
    fecha DATETIME,
    torneo VARCHAR(100),
    ganador INT, -- ID del equipo profesional ganador
    FOREIGN KEY (id_equipo_local) REFERENCES Equipos_Profesionales(id_equipo_profesional),
    FOREIGN KEY (id_equipo_visitante) REFERENCES Equipos_Profesionales(id_equipo_profesional)
);

-- ==========================================
-- 3. TABLAS DE SEGUNDO NIVEL DE DEPENDENCIA
-- ==========================================

CREATE TABLE Equipos_Fantasy (
    id_equipo_fantasy INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT,
    id_liga INT,
    nombre_equipo VARCHAR(100) NOT NULL,
    presupuesto_disponible DECIMAL(15, 2) DEFAULT 1000000.00,
    puntos_equipo INT DEFAULT 0,
    FOREIGN KEY (id_usuario, id_liga) REFERENCES Participaciones(id_usuario, id_liga) ON DELETE CASCADE
);

CREATE TABLE Jugadores (
    id_jugador INT AUTO_INCREMENT PRIMARY KEY,
    id_equipo_profesional INT, 
    id_equipo_fantasy INT,     
    nickname VARCHAR(50) NOT NULL,
    nombre_real VARCHAR(150),
    precio_mercado DECIMAL(15, 2),
    media_punto FLOAT DEFAULT 0.0,
    FOREIGN KEY (id_equipo_profesional) REFERENCES Equipos_Profesionales(id_equipo_profesional) ON DELETE SET NULL,
    FOREIGN KEY (id_equipo_fantasy) REFERENCES Equipos_Fantasy(id_equipo_fantasy) ON DELETE SET NULL
);

-- ==========================================
-- 4. TABLAS DE TERCER NIVEL (Estadísticas y Alineaciones)
-- ==========================================

-- Resuelve la relación "Hacen" entre Jugadores y Partidos
CREATE TABLE Estadisticas (
    id_estadistica INT AUTO_INCREMENT PRIMARY KEY,
    id_jugador INT,
    id_partido INT,
    kills INT DEFAULT 0,
    deaths INT DEFAULT 0,
    assist INT DEFAULT 0,
    ace INT DEFAULT 0,
    clutch INT DEFAULT 0,
    punto_fantasy FLOAT DEFAULT 0.0,
    FOREIGN KEY (id_jugador) REFERENCES Jugadores(id_jugador) ON DELETE CASCADE,
    FOREIGN KEY (id_partido) REFERENCES Partidos(id_partido) ON DELETE CASCADE
);

-- Conecta Equipos Fantasy con Jugadores por Jornada
CREATE TABLE Alineaciones (
    id_equipo_fantasy INT,
    id_jugador INT,
    jornada INT,
    puntos_jornada FLOAT DEFAULT 0.0,
    titular BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (id_equipo_fantasy, id_jugador, jornada),
    FOREIGN KEY (id_equipo_fantasy) REFERENCES Equipos_Fantasy(id_equipo_fantasy) ON DELETE CASCADE,
    FOREIGN KEY (id_jugador) REFERENCES Jugadores(id_jugador) ON DELETE CASCADE
);