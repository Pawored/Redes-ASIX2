-- Base de datos para la web PHP + MySQL (app.asir.local)
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS agenda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Usuario con permisos SOLO sobre esta BD y solo desde localhost (mínimo privilegio)
CREATE USER IF NOT EXISTS 'agenda_user'@'localhost' IDENTIFIED BY 'Asir2026!';
GRANT SELECT, INSERT, UPDATE, DELETE ON agenda.* TO 'agenda_user'@'localhost';
FLUSH PRIVILEGES;

USE agenda;

CREATE TABLE IF NOT EXISTS contactos (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  nombre    VARCHAR(100) NOT NULL,
  email     VARCHAR(150) NOT NULL,
  telefono  VARCHAR(20),
  creado    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO contactos (nombre, email, telefono) VALUES
  ('Ana García',   'ana@asir.local',   '600111222'),
  ('Luis Pérez',   'luis@asir.local',  '600333444'),
  ('Marta López',  'marta@asir.local', '600555666');
