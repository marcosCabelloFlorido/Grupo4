-- ==========================================
-- 0. LIMPIEZA TOTAL (DROP TABLES)
-- ==========================================
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS pujas;
DROP TABLE IF EXISTS mercado_liga;
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
    max_participantes INT DEFAULT 20,
    codigo_acceso VARCHAR(10) NULL COMMENT 'Código único para unirse a ligas privadas. NULL en ligas públicas.'
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

-- ==========================================
-- 5. MERCADO Y PUJAS (usadas en mercado.php)
-- ==========================================

CREATE TABLE mercado_liga (
    id_mercado INT AUTO_INCREMENT PRIMARY KEY,
    id_liga INT NOT NULL,
    id_jugador INT NOT NULL,
    fecha_expiracion DATETIME NOT NULL,
    FOREIGN KEY (id_liga) REFERENCES ligas(id_liga) ON DELETE CASCADE,
    FOREIGN KEY (id_jugador) REFERENCES jugadores(id_jugador) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pujas (
    id_puja INT AUTO_INCREMENT PRIMARY KEY,
    id_mercado INT NOT NULL,
    id_equipo_fantasy INT NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    fecha_puja DATETIME DEFAULT NOW(),
    FOREIGN KEY (id_mercado) REFERENCES mercado_liga(id_mercado) ON DELETE CASCADE,
    FOREIGN KEY (id_equipo_fantasy) REFERENCES equipos_fantasy(id_equipo_fantasy) ON DELETE CASCADE
) ENGINE=InnoDB;
-- ==========================================
-- 6. DATOS DE EJEMPLO: EQUIPOS Y JUGADORES
-- ==========================================

INSERT INTO equipos_profesionales (nombre_equipo_profesional, region, ranking) VALUES
('Team Liquid',          'EMEA',  1),
('Fnatic',               'EMEA',  2),
('NaVi',                 'EMEA',  3),
('Cloud9',               'NA',    4),
('Sentinels',            'NA',    5),
('100 Thieves',          'NA',    6),
('LOUD',                 'Americas', 7),
('Leviatán',             'Americas', 8),
('Paper Rex',            'Pacific', 9),
('NRG',                  'NA',    10);

-- DUELISTAS
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(1, 'Jamppi',    'Elias Olkkonen',    'Duelista',  950000,  42.5),
(2, 'Derke',     'Nikita Sirmitev',   'Duelista', 1200000,  47.8),
(3, 'cNed',      'Mehmet Yağız İpek', 'Duelista',  880000,  40.1),
(4, 'Leaf',      'Nathan Orf',        'Duelista',  820000,  38.6),
(5, 'TenZ',      'Tyson Ngo',         'Duelista', 1100000,  45.2),
(6, 'Asuna',     'Peter Mazuryk',     'Duelista',  790000,  37.4),
(7, 'aspas',     'Erick Santos',      'Duelista', 1350000,  51.0),
(8, 'nzr',       'Agustín Ibarra',    'Duelista',  760000,  35.9),
(9, 'f0rsakeN',  'Jason Susanto',     'Duelista',  990000,  43.3),
(10,'s0m',       'Sam Oh',            'Duelista',  710000,  33.2),
(1, 'Sayf',      'Saif Jibraeel',     'Duelista',  870000,  39.7),
(2, 'Alfajer',   'Emir Ali Beder',    'Duelista',  980000,  44.1);

-- INICIADORES
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(1, 'Nivera',    'Nabil Benrlitom',   'Iniciador', 900000,  41.0),
(2, 'Chronicle', 'Timofey Khromov',   'Iniciador', 850000,  38.8),
(3, 'ANGE1',     'Kyrylo Karasov',    'Iniciador', 780000,  35.5),
(4, 'vanity',    'Anthony Malaspina', 'Iniciador', 730000,  33.8),
(5, 'ShahZaM',   'Shahzeb Khan',      'Iniciador', 820000,  38.0),
(6, 'bang',      'Sean Bezerra',      'Iniciador', 690000,  31.4),
(7, 'saadhak',   'Matías Delipetro',  'Iniciador', 950000,  43.5),
(8, 'kiNgg',     'Francisco Aravena', 'Iniciador', 840000,  39.2),
(9, 'Jinggg',    'Wang Jing Jie',     'Iniciador', 910000,  41.8),
(10,'crashies',  'Austin Roberts',    'Iniciador', 770000,  35.1),
(1, 'soulcas',   'Dom Sulcas',        'Iniciador', 720000,  32.9),
(2, 'Boaster',   'Jake Howlett',      'Iniciador', 800000,  36.6);

-- CENTINELAS
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(1, 'Jamppi-C',  'Juhani Timonen',    'Centinela', 680000,  31.2),
(2, 'Mistic',    'James Orfila',      'Centinela', 740000,  34.0),
(3, 'Shao',      'Dmitry Petrov',     'Centinela', 650000,  29.8),
(4, 'xeta',      'Son Seon-ho',       'Centinela', 780000,  36.2),
(5, 'SicK',      'Hunter Mims',       'Centinela', 860000,  39.5),
(6, 'Zekken',    'Zachary Patrone',   'Centinela', 820000,  37.8),
(7, 'pANcada',   'Bryan Luna',        'Centinela', 930000,  42.8),
(8, 'Less',      'Felipe Basso',      'Centinela', 880000,  40.4),
(9, 'something', 'Ilya Petrov',       'Centinela', 710000,  32.5),
(10,'Victor',    'Victor Wong',       'Centinela', 790000,  36.9),
(1, 'Leo',       'Leo Jannesson',     'Centinela', 850000,  38.9),
(2, 'Zyppan',    'Pontus Eek',        'Centinela', 760000,  34.7),
(3, 'KOLDAMENTA','Jose Luis Aranguren','Centinela', 700000, 32.1),
(4, 'nAts',      'Ayaz Akhmetshin',   'Centinela', 980000,  44.6);

-- SMOKERS
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(1, 'Redgar',    'Igor Vlasov',       'Smoker',    850000,  38.3),
(2, 'mini',      'Minttu Kemppi',     'Smoker',    720000,  32.7),
(3, 'Lakia',     'Eimantas Nbratauskas','Smoker',  670000,  30.2),
(4, 'yay',       'Jaccob Whitelaw',   'Smoker',   1050000,  46.1),
(5, 'dapr',      'Michael Gulino',    'Smoker',    810000,  37.3),
(6, 'Ethan',     'Ethan Arnold',      'Smoker',    780000,  35.8),
(7, 'tuyz',      'Arthur Andrade',    'Smoker',    900000,  41.2),
(8, 'mwzera',    'Erick Britto',      'Smoker',    870000,  39.9),
(9, 'Benkai',    'Benedict Tan',      'Smoker',    830000,  38.1),
(10,'FNS',       'Pujan Mehta',       'Smoker',    760000,  34.5),
(1, 'ScreaM',    'Adil Benrlitom',    'Smoker',   1000000,  45.0),
(2, 'Marved',    'Jimmy Nguyen',      'Smoker',    830000,  38.0);