DROP USER IF EXISTS 'MEDAC'@'localhost';

DROP SCHEMA IF EXISTS videojuegos_bd;

CREATE USER 'MEDAC'@'localhost' IDENTIFIED BY 'MEDAC';

GRANT ALL PRIVILEGES ON *.* TO 'MEDAC'@'localhost';
FLUSH PRIVILEGES;

CREATE SCHEMA videojuegos_bd;
USE videojuegos_bd;

CREATE TABLE desarrolladoras (
    nombre_desarrolladora VARCHAR(50) PRIMARY KEY,
    ciudad VARCHAR(40),
    anno_fundacion NUMERIC(4,0)
);

CREATE TABLE videojuegos (
    id_videojuego INT PRIMARY KEY AUTO_INCREMENT,
    titulo VARCHAR(80) UNIQUE,
    nombre_desarrolladora VARCHAR(50),
    anno_lanzamiento NUMERIC(4,0),
    porcentaje_reseñas NUMERIC(3,1),
    horas_duracion INT,
    FOREIGN KEY (nombre_desarrolladora) REFERENCES desarrolladoras(nombre_desarrolladora)
);

-- Tabla usuarios modificada con email y telefono obligatorios
CREATE TABLE usuarios (
    id_usuario INT PRIMARY KEY AUTO_INCREMENT,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    admin TINYINT(1) DEFAULT 0
);

-- Insertar datos en la tabla desarrolladoras
INSERT INTO desarrolladoras VALUES ('CD Projekt Red', 'Varsovia', 1994);
INSERT INTO desarrolladoras VALUES ('Rockstar Games', 'Nueva York', 1998);
INSERT INTO desarrolladoras VALUES ('FromSoftware', 'Tokio', 1986);
INSERT INTO desarrolladoras VALUES ('Valve', 'Bellevue', 1996);
INSERT INTO desarrolladoras VALUES ('Nintendo', 'Kioto', 1889);
INSERT INTO desarrolladoras VALUES ('Square Enix', 'Tokio', 1986);
INSERT INTO desarrolladoras VALUES ('Riot Games', 'Los Angeles', 2011);

-- Insertar datos en la tabla videojuegos
INSERT INTO videojuegos (titulo, nombre_desarrolladora, anno_lanzamiento, porcentaje_reseñas, horas_duracion)
    VALUES ('The Witcher 3: Wild Hunt', 'CD Projekt Red', 2015, 97.5, 50);
INSERT INTO videojuegos (titulo, nombre_desarrolladora, anno_lanzamiento, porcentaje_reseñas, horas_duracion)
    VALUES ('Grand Theft Auto VI', 'Rockstar Games', 2026, 96.8, 110);
INSERT INTO videojuegos (titulo, nombre_desarrolladora, anno_lanzamiento, porcentaje_reseñas, horas_duracion)
    VALUES ('Dark Souls III', 'FromSoftware', 2016, 89.4, 40);
INSERT INTO videojuegos (titulo, nombre_desarrolladora, anno_lanzamiento, porcentaje_reseñas, horas_duracion)
    VALUES ('Sekiro', 'FromSoftware', 2020, 99.4, 40);
INSERT INTO videojuegos (titulo, nombre_desarrolladora, anno_lanzamiento, porcentaje_reseñas, horas_duracion)
    VALUES ('Half-Life 2', 'Valve', 2004, 98.1, 15);
INSERT INTO videojuegos (titulo, nombre_desarrolladora, anno_lanzamiento, porcentaje_reseñas, horas_duracion)
    VALUES ('The Legend of Zelda: Breath of the Wild', 'Nintendo', 2017, 97.0, 100);
INSERT INTO videojuegos (titulo, nombre_desarrolladora, anno_lanzamiento, porcentaje_reseñas, horas_duracion)
    VALUES ('Final Fantasy VII Remake', 'Square Enix', 2020, 88.3, 35);
INSERT INTO videojuegos (titulo, nombre_desarrolladora, anno_lanzamiento, porcentaje_reseñas, horas_duracion)
    VALUES ('Counter-Strike: Global Offensive', 'Valve', 2012, 94.6, -1);
INSERT INTO videojuegos (titulo, nombre_desarrolladora, anno_lanzamiento, porcentaje_reseñas, horas_duracion)
    VALUES ('Elden Ring', 'FromSoftware', 2022, 95.7, 80);
INSERT INTO videojuegos (titulo, nombre_desarrolladora, anno_lanzamiento, porcentaje_reseñas, horas_duracion)
    VALUES ('League of Legends', 'Riot Games', 2011, 71.4, -1);

-- Consultas ejemplo
USE videojuegos_bd;
SELECT titulo, nombre_desarrolladora FROM videojuegos ORDER BY titulo DESC;
SELECT * FROM videojuegos WHERE titulo = "Elden Ring";
SELECT * FROM videojuegos WHERE titulo LIKE "%Souls%";
SELECT * FROM videojuegos;
SELECT * FROM desarrolladoras;
-- Título, desarrolladora, ciudad
SELECT v.titulo, d.nombre_desarrolladora, d.ciudad
    FROM videojuegos v JOIN desarrolladoras d 
        ON v.nombre_desarrolladora = d.nombre_desarrolladora;

-- Nueva insercion
INSERT INTO videojuegos (titulo, nombre_desarrolladora, anno_lanzamiento, porcentaje_reseñas, horas_duracion)
VALUES ('Portal 2', 'Valve', 2011, 95.5, 10);