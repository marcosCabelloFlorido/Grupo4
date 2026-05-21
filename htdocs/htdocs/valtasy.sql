-- ==========================================
-- VALTASY — SQL COMPLETO CON SISTEMA PREMIUM
-- Base de datos: fantasyesports_v2
-- ==========================================

CREATE DATABASE IF NOT EXISTS `fantasyesports_v2`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `fantasyesports_v2`;

-- ==========================================
-- 1. LIMPIEZA TOTAL (orden inverso)
-- ==========================================
DROP TABLE IF EXISTS pujas;
DROP TABLE IF EXISTS mercado_liga;
DROP TABLE IF EXISTS alineaciones;
DROP TABLE IF EXISTS estadisticas;
DROP TABLE IF EXISTS equipos_fantasy;
DROP TABLE IF EXISTS participaciones;
DROP TABLE IF EXISTS partidos;
DROP TABLE IF EXISTS jugadores;
DROP TABLE IF EXISTS ligas;
DROP TABLE IF EXISTS pagos_premium;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS equipos_profesionales;

-- ==========================================
-- 2. CREACIÓN DE TABLAS
-- ==========================================

CREATE TABLE usuarios (
    id_usuario        INT PRIMARY KEY AUTO_INCREMENT,
    nombre            VARCHAR(50)  UNIQUE NOT NULL,
    email             VARCHAR(100) UNIQUE NOT NULL,
    telefono          VARCHAR(20)  NOT NULL,
    contrasena        VARCHAR(255) NOT NULL,
    rol               VARCHAR(20)  DEFAULT 'cliente',
    es_premium        TINYINT(1)   NOT NULL DEFAULT 0,
    premium_desde     DATETIME     NULL,
    premium_hasta     DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pagos_premium (
    id_pago       INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario    INT NOT NULL,
    monto         DECIMAL(8,2) NOT NULL DEFAULT 4.99,
    metodo_pago   VARCHAR(50)  NOT NULL DEFAULT 'tarjeta',
    referencia    VARCHAR(100) NULL COMMENT 'ID externo del proveedor de pagos',
    estado        ENUM('pendiente','completado','fallido','reembolsado') NOT NULL DEFAULT 'pendiente',
    fecha_pago    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    meses         INT          NOT NULL DEFAULT 1,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ligas (
    id_liga           INT AUTO_INCREMENT PRIMARY KEY,
    id_creador        INT NULL,
    nombre            VARCHAR(100) NOT NULL,
    tipo              VARCHAR(50),
    torneo            VARCHAR(100) DEFAULT 'VCT EMEA - Fase Regular',
    max_participantes INT          DEFAULT 20,
    codigo_acceso     VARCHAR(10)  NULL,
    FOREIGN KEY (id_creador) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE equipos_profesionales (
    id_equipo_profesional       INT AUTO_INCREMENT PRIMARY KEY,
    nombre_equipo_profesional   VARCHAR(100) NOT NULL,
    region                      VARCHAR(50),
    ranking                     INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE participaciones (
    id_usuario      INT,
    id_liga         INT,
    posicion_actual INT DEFAULT 0,
    PRIMARY KEY (id_usuario, id_liga),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)  ON DELETE CASCADE,
    FOREIGN KEY (id_liga)    REFERENCES ligas(id_liga)        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE partidos (
    id_partido          INT AUTO_INCREMENT PRIMARY KEY,
    id_equipo_local     INT,
    id_equipo_visitante INT,
    fecha               DATETIME,
    torneo              VARCHAR(100),
    ganador             INT NULL,
    FOREIGN KEY (id_equipo_local)     REFERENCES equipos_profesionales(id_equipo_profesional) ON DELETE CASCADE,
    FOREIGN KEY (id_equipo_visitante) REFERENCES equipos_profesionales(id_equipo_profesional) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE equipos_fantasy (
    id_equipo_fantasy       INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario              INT,
    id_liga                 INT,
    nombre_equipo           VARCHAR(100) NOT NULL,
    presupuesto_disponible  DECIMAL(15,2) DEFAULT 35000000.00,
    puntos_equipo           INT           DEFAULT 0,
    FOREIGN KEY (id_usuario, id_liga) REFERENCES participaciones(id_usuario, id_liga) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE jugadores (
    id_jugador              INT AUTO_INCREMENT PRIMARY KEY,
    id_equipo_profesional   INT NULL,
    nickname                VARCHAR(50)  NOT NULL,
    nombre_real             VARCHAR(150),
    rol                     VARCHAR(30)  NOT NULL,
    precio_mercado          DECIMAL(15,2),
    media_punto             FLOAT DEFAULT 0.0,
    FOREIGN KEY (id_equipo_profesional) REFERENCES equipos_profesionales(id_equipo_profesional) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE estadisticas (
    id_estadistica  INT AUTO_INCREMENT PRIMARY KEY,
    id_jugador      INT,
    id_partido      INT,
    kills           INT   DEFAULT 0,
    deaths          INT   DEFAULT 0,
    assist          INT   DEFAULT 0,
    ace             INT   DEFAULT 0,
    clutch          INT   DEFAULT 0,
    punto_fantasy   FLOAT DEFAULT 0.0,
    FOREIGN KEY (id_jugador) REFERENCES jugadores(id_jugador) ON DELETE CASCADE,
    FOREIGN KEY (id_partido) REFERENCES partidos(id_partido)  ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE alineaciones (
    id_equipo_fantasy   INT,
    id_jugador          INT,
    jornada             INT,
    puntos_jornada      FLOAT   DEFAULT 0.0,
    titular             BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (id_equipo_fantasy, id_jugador, jornada),
    FOREIGN KEY (id_equipo_fantasy) REFERENCES equipos_fantasy(id_equipo_fantasy) ON DELETE CASCADE,
    FOREIGN KEY (id_jugador)        REFERENCES jugadores(id_jugador)               ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE mercado_liga (
    id_mercado       INT AUTO_INCREMENT PRIMARY KEY,
    id_liga          INT NOT NULL,
    id_jugador       INT NOT NULL,
    fecha_expiracion DATETIME NOT NULL,
    FOREIGN KEY (id_liga)    REFERENCES ligas(id_liga)       ON DELETE CASCADE,
    FOREIGN KEY (id_jugador) REFERENCES jugadores(id_jugador) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pujas (
    id_puja           INT AUTO_INCREMENT PRIMARY KEY,
    id_mercado        INT            NOT NULL,
    id_equipo_fantasy INT            NOT NULL,
    monto             DECIMAL(15,2)  NOT NULL,
    fecha_puja        DATETIME       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_mercado)        REFERENCES mercado_liga(id_mercado)           ON DELETE CASCADE,
    FOREIGN KEY (id_equipo_fantasy) REFERENCES equipos_fantasy(id_equipo_fantasy) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- 3. DATOS DE EJEMPLO (VCT EMEA)
-- ==========================================

-- Equipos profesionales EMEA
INSERT INTO equipos_profesionales (id_equipo_profesional, nombre_equipo_profesional, region, ranking) VALUES
(1,  'Fnatic',        'EMEA', 1),
(2,  'Team Liquid',   'EMEA', 2),
(3,  'Natus Vincere', 'EMEA', 3),
(4,  'Team Heretics', 'EMEA', 4),
(5,  'KOI',           'EMEA', 5),
(6,  'GIANTX',        'EMEA', 6),
(7,  'Karmine Corp',  'EMEA', 7),
(8,  'Team Vitality', 'EMEA', 8),
(9,  'FUT Esports',   'EMEA', 9),
(10, 'BBL Esports',   'EMEA', 10);

-- FNATIC (1)
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(1, 'Derke',     'Nikita Sirmitev',   'Duelista',  1200000, 47.8),
(1, 'Leo',       'Leo Jannesson',     'Iniciador', 1150000, 46.2),
(1, 'Chronicle', 'Timofey Khromov',   'Iniciador', 1050000, 41.5),
(1, 'Alfajer',   'Emir Ali Beder',    'Centinela', 1100000, 45.1),
(1, 'Boaster',   'Jake Howlett',      'Smoker',     800000, 36.6),
(1, 'hiro',      'Muhanad Ali',       'Centinela',  700000, 30.5);

-- TEAM LIQUID (2)
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(2, 'Keiko',  'Georgio Sanassy',    'Duelista',   950000, 42.5),
(2, 'Jamppi', 'Elias Olkkonen',     'Iniciador',  920000, 40.1),
(2, 'Enzo',   'Enzo Mestari',       'Iniciador',  850000, 38.0),
(2, 'nAts',   'Ayaz Akhmetshin',    'Centinela', 1050000, 44.2),
(2, 'Mistic', 'James Orfila',       'Smoker',     780000, 35.1),
(2, 'Ayumik', 'Ayumi K.',           'Centinela',  600000, 25.0);

-- NATUS VINCERE (3)
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(3, 'ardiis',   'Ardis Svarenieks', 'Duelista',   980000, 41.8),
(3, 'Shao',     'Dmitry Petrov',    'Iniciador',  950000, 42.0),
(3, 'Zyppan',   'Pontus Eek',       'Iniciador',  890000, 39.5),
(3, 'SUYGETSU', 'Dmitry Ilyushin',  'Centinela', 1020000, 43.1),
(3, 'ANGE1',    'Kyrylo Karasov',   'Smoker',     750000, 32.5),
(3, 'Dplus',    'Danil P.',         'Centinela',  550000, 22.0);

-- TEAM HERETICS (4)
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(4, 'MiniBoo',    'Dominykas Lukaševičius', 'Duelista',  1100000, 45.2),
(4, 'Wo0t',       'Mert Alkan',             'Iniciador', 1150000, 46.5),
(4, 'RieNs',      'Enes Ecirli',            'Iniciador',  850000, 37.5),
(4, 'benjyfishy', 'Benjy David Fish',       'Centinela',  980000, 44.6),
(4, 'Boo',        'Ričardas Lukaševičius',  'Smoker',     820000, 36.1),
(4, 'paTiTek',    'Patryk Fabrowski',       'Centinela',  750000, 35.0);

-- KOI (5)
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(5, 'kamo',     'Kamil Frąckowiak',   'Duelista',  850000, 38.0),
(5, 'trexx',    'Ilya Maksimchuk',    'Iniciador', 780000, 35.5),
(5, 'sheydos',  'Bogdan Naumov',      'Iniciador', 820000, 37.1),
(5, 'starxo',   'Patryk Kopczyński',  'Centinela', 790000, 36.0),
(5, 'grubinho', 'Grzegorz Ryczko',    'Smoker',    710000, 32.0),
(5, 'ShadoW',   'Tobias Flodström',   'Centinela', 650000, 29.5);

-- GIANTX (6)
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(6, 'Fit1nho', 'Adolfo Gallego',              'Duelista',  880000, 39.5),
(6, 'purp0',   'Semen Borchev',               'Duelista',  820000, 37.0),
(6, 'Cloud',   'Kirill Nehozhin',             'Iniciador', 850000, 38.5),
(6, 'hoody',   'Aaro Peltokangas',            'Centinela', 780000, 35.0),
(6, 'nukkye',  'Žygimantas Chmieliauskas',    'Centinela', 800000, 36.5),
(6, 'Redgar',  'Igor Vlasov',                 'Smoker',    850000, 38.3);

-- KARMINE CORP (7)
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(7, 'marteen', 'Martin Pátek',    'Duelista',   920000, 41.0),
(7, 'N4RRATE', 'Marshall Massey', 'Iniciador', 1050000, 45.0),
(7, 'tomaszy', 'Tomasz Machado',  'Centinela',  880000, 39.0),
(7, 'MAGNUM',  'Cem Burgaz',      'Centinela',  690000, 31.0),
(7, 'sh1n',    'Ryad Ensaad',     'Smoker',     750000, 34.5),
(7, 'kuron',   'Kuron T.',        'Centinela',  600000, 26.0);

-- TEAM VITALITY (8)
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(8, 'Sayf',     'Saif Jibraeel',   'Duelista',  1080000, 44.5),
(8, 'runneR',   'Kaleb Willis',    'Duelista',   850000, 38.0),
(8, 'Kicks',    'Kicks L.',        'Iniciador',  790000, 36.5),
(8, 'ceNder',   'Jokūbas Labutis', 'Centinela',  820000, 37.0),
(8, 'BONECOLD', 'Santeri Sassi',   'Centinela',  700000, 32.0),
(8, 'Destrian', 'Tomas Linikas',   'Smoker',     740000, 33.5);

-- FUT ESPORTS (9)
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(9, 'cNed',      'Mehmet Yağız İpek', 'Duelista',  950000, 43.0),
(9, 'qRaxs',     'Doğukan Balaban',   'Iniciador', 800000, 36.0),
(9, 'MrFaliN',   'Furkan Yeğen',      'Iniciador', 820000, 37.5),
(9, 'yetujey',   'Eray Budak',        'Centinela', 860000, 38.5),
(9, 'Muj',       'Muj T.',            'Centinela', 650000, 28.0),
(9, 'AtaKaptan', 'Ata Tan',           'Smoker',    780000, 35.0);

-- BBL ESPORTS (10)
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(10, 'QutionerX',     'Doğukan Dural',     'Duelista',  890000, 39.5),
(10, 'reazy',         'Kaan Ürpek',        'Iniciador', 750000, 33.5),
(10, 'pAura',         'Melih Karaduman',   'Centinela', 720000, 32.0),
(10, 'Elite',         'Efe Teber',         'Centinela', 780000, 35.0),
(10, 'AsLanM4shadoW', 'Ali Osman Balta',   'Centinela', 700000, 31.0),
(10, 'Brave',         'Eren Kasırga',      'Smoker',    820000, 36.5);

-- AGENTES LIBRES (id_equipo_profesional NULL)
INSERT INTO jugadores (id_equipo_profesional, nickname, nombre_real, rol, precio_mercado, media_punto) VALUES
(NULL, 'ScreaM',      'Adil Benrlitom',       'Duelista',  950000, 42.0),
(NULL, 'keloqz',      'Cista Wassim',         'Duelista',  750000, 33.0),
(NULL, 'zeek',        'Aleksander Zygmunt',   'Iniciador', 720000, 32.0),
(NULL, 'AvovA',       'Auni Chahade',         'Smoker',    780000, 35.0),
(NULL, 'koldamenta',  'Jose Luis Aranguren',  'Smoker',    700000, 31.0),
(NULL, 'mixwell',     'Oscar Cañellas',       'Centinela', 800000, 36.0),
(NULL, 'Kiles',       'Vlad Shvets',          'Centinela', 650000, 29.0),
(NULL, 'vakk',        'Vakaris Bebravičius',  'Iniciador', 680000, 30.0),
(NULL, 'BONECOLD_FA', 'Santeri Sassi',        'Smoker',    690000, 30.5),
(NULL, 'Twisten_FA',  'Karel Ašenbrener',     'Duelista',  700000, 31.5),
(NULL, 'L1NK',        'Travis Mendoza',       'Smoker',    670000, 29.5),
(NULL, 'ec1s',        'Adam Eccles',          'Iniciador', 620000, 27.0),
(NULL, 'Fearoth',     'Thiago',               'Centinela', 550000, 25.0),
(NULL, 'GatsH',       'Guillaume',            'Centinela', 580000, 26.0),
(NULL, 'Moe40',       'Moe',                  'Centinela', 600000, 27.5),
(NULL, 'TakaS',       'Jonathan',             'Duelista',  680000, 30.0),
(NULL, 'AKUMAAAAA',   'Alex',                 'Iniciador', 650000, 28.5),
(NULL, 'LogaN',       'Logan',                'Centinela', 620000, 27.0),
(NULL, 'KONEQT',      'Konstantin',           'Centinela', 590000, 26.5),
(NULL, 'Mikes',       'Mike',                 'Smoker',    610000, 27.0);