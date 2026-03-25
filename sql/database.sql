-- =============================================
-- OctaBank - Sistema Financiero 8vo Semestre
-- Base de datos MySQL
-- =============================================

CREATE DATABASE IF NOT EXISTS octabank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE octabank;

-- Tabla de usuarios/miembros
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    rol ENUM('admin', 'miembro') NOT NULL DEFAULT 'miembro',
    numero_cuenta VARCHAR(20) NOT NULL UNIQUE,
    avatar VARCHAR(10) DEFAULT '👤',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME DEFAULT NULL,
    INDEX idx_email (email),
    INDEX idx_numero_cuenta (numero_cuenta),
    INDEX idx_rol (rol)
) ENGINE=InnoDB;

-- Tabla de cuentas bancarias
CREATE TABLE IF NOT EXISTS cuentas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    numero_cuenta VARCHAR(20) NOT NULL UNIQUE,
    saldo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    saldo_retenido DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    estado ENUM('activa', 'suspendida', 'cerrada') NOT NULL DEFAULT 'activa',
    fecha_apertura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_estado (estado)
) ENGINE=InnoDB;

-- Tabla de transacciones
CREATE TABLE IF NOT EXISTS transacciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuenta_origen_id INT DEFAULT NULL,
    cuenta_destino_id INT DEFAULT NULL,
    tipo ENUM('deposito', 'retiro', 'transferencia_envio', 'transferencia_recibida') NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    saldo_anterior DECIMAL(12,2) DEFAULT NULL,
    saldo_posterior DECIMAL(12,2) DEFAULT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    referencia VARCHAR(30) NOT NULL UNIQUE,
    estado ENUM('pendiente', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente',
    aprobado_por INT DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion DATETIME DEFAULT NULL,
    FOREIGN KEY (cuenta_origen_id) REFERENCES cuentas(id) ON DELETE SET NULL,
    FOREIGN KEY (cuenta_destino_id) REFERENCES cuentas(id) ON DELETE SET NULL,
    FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_tipo (tipo),
    INDEX idx_estado (estado),
    INDEX idx_fecha (fecha_creacion),
    INDEX idx_referencia (referencia)
) ENGINE=InnoDB;

/* Tablas de cuotas eliminadas para simplificación del proyecto final */

-- Tabla de auditoría
CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT DEFAULT NULL,
    accion VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(50) DEFAULT NULL,
    detalle TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_fecha (fecha),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB;

-- =============================================
-- Insertar usuario administrador por defecto
-- Password: Admin123!
-- =============================================
INSERT INTO usuarios (nombre, apellido, email, password, telefono, rol, numero_cuenta, avatar) 
VALUES (
    'Administrador', 
    'OctaBank', 
    'admin@octabank.com', 
    '$2y$10$nM75vuVt8MUoP/k1cyguYu5sJNbDCPBhVv1sXSrhjb/Eyn5ARX4JG', 
    '0000000000', 
    'admin', 
    'OB-00000001',
    '🏦'
);

INSERT INTO cuentas (usuario_id, numero_cuenta, saldo, estado) 
VALUES (1, 'OB-00000001', 0.00, 'activa');






