-- Base de datos para WISDOM Importaciones (Sistem de Ventas, Inventario y Logística)
-- Motor InnoDB con soporte de transacciones y llaves foráneas

CREATE DATABASE IF NOT EXISTS BDwisdom CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE BDwisdom;


-- 1. Sucursales
CREATE TABLE IF NOT EXISTS sucursales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    telefono VARCHAR(50),
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Almacenes
CREATE TABLE IF NOT EXISTS almacenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sucursal_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    tipo ENUM('VENTA', 'DEFECTUOSO') DEFAULT 'VENTA',
    direccion VARCHAR(255),
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('ADMIN', 'VENDEDOR', 'AUTORIZADOR', 'CHOFER') NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    modificado_por INT DEFAULT NULL,
    modificado_en TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (modificado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4. Relación Usuario-Sucursal (Un usuario puede gestionar varias sucursales)
CREATE TABLE IF NOT EXISTS usuario_sucursal (
    usuario_id INT NOT NULL,
    sucursal_id INT NOT NULL,
    PRIMARY KEY (usuario_id, sucursal_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4.1 Relación Usuario-Almacenes (Asignación de almacenes de venta por vendedor)
CREATE TABLE IF NOT EXISTS usuario_almacen (
    usuario_id INT NOT NULL,
    almacen_id INT NOT NULL,
    PRIMARY KEY (usuario_id, almacen_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Clientes (General + Clasificación de Subtipos de Clientes)
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_cliente ENUM('COMERCIAL', 'CONSTRUCTORA', 'CLIENTE_FINAL') NOT NULL,
    nit_ci VARCHAR(30) DEFAULT NULL,

    email VARCHAR(100),
    ciudad VARCHAR(100) DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    
    -- Campos Tipo: COMERCIAL
    comercial_nombre_comercial VARCHAR(150),
    comercial_nombre_contacto VARCHAR(150),
    comercial_celular VARCHAR(50),
    comercial_direccion_principal VARCHAR(255),
    comercial_latitud DECIMAL(10, 8),
    comercial_longitud DECIMAL(11, 8),
    comercial_categoria ENUM('VIP', 'MAYORISTA', 'MINORISTA', 'NUEVO') DEFAULT 'NUEVO',

    -- Campos Tipo: CONSTRUCTORA
    constructora_nombre VARCHAR(150),
    constructora_representante VARCHAR(150),
    constructora_telefono VARCHAR(50),

    -- Campos Tipo: CLIENTE_FINAL
    final_nombre_completo VARCHAR(150),
    final_direccion VARCHAR(255),
    final_telefono VARCHAR(50)
) ENGINE=InnoDB;

-- Tabla Hija: Vendedores de Clientes Comerciales
CREATE TABLE IF NOT EXISTS cliente_comercial_vendedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    nombre_vendedor VARCHAR(150) NOT NULL,
    celular VARCHAR(50) NOT NULL,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla Hija: Obras / Direcciones de Entrega de Constructoras
CREATE TABLE IF NOT EXISTS constructora_obras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    nombre_obra VARCHAR(150) NOT NULL,
    direccion_obra VARCHAR(255) NOT NULL,
    latitud_gps DECIMAL(10, 8),
    longitud_gps DECIMAL(11, 8),
    contacto_residente_obra VARCHAR(150),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- 6. Anticipos de Clientes (Saldos a favor sin venta asociada inicialmente)
CREATE TABLE IF NOT EXISTS anticipos_cliente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    monto DECIMAL(12, 2) NOT NULL,
    saldo_disponible DECIMAL(12, 2) NOT NULL,
    fecha_pago DATE NOT NULL,
    metodo_pago VARCHAR(50) NOT NULL,
    notas TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Categorías
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 8. Productos (Porcelanatos y otros)
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    m2_por_caja DECIMAL(10, 4) NOT NULL,
    piezas_por_caja INT NOT NULL,
    peso_caja_kg DECIMAL(10, 2) NOT NULL,
    formato VARCHAR(50) NOT NULL, -- ej: 60x60, 60x120
    acabado VARCHAR(50) NOT NULL, -- ej: Vitrificado, Mate, Rústico
    origen VARCHAR(50) NOT NULL, -- ej: China, India, Brasil
    precio_base_bob DECIMAL(12, 2) DEFAULT 0.00,
    activo TINYINT DEFAULT 1,
    cajas_por_palet INT DEFAULT 0,
    imagen_url VARCHAR(255) DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tablas de Parámetros Dinámicos del SKU
CREATE TABLE IF NOT EXISTS sku_parametros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('PAIS', 'FABRICA', 'CLASE', 'ACABADO', 'FORMATO', 'COLOR', 'DISENO') NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    abreviacion VARCHAR(30) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;



-- 8.5 Configuración de Escalas Maestras de Precios
CREATE TABLE IF NOT EXISTS config_escalas_precios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    rango_min INT NOT NULL,
    rango_max INT NOT NULL,
    ordenado INT NOT NULL DEFAULT 0,
    precio_cliente_final TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 9. Precios Escalonados (Configuración de matriz normalizada por escala)
CREATE TABLE IF NOT EXISTS precios_escalonados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    escala_id INT NOT NULL,
    precio_caja_bob DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (escala_id) REFERENCES config_escalas_precios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_prod_escala (producto_id, escala_id)
) ENGINE=InnoDB;

-- 10. Inventario Almacén (Stock controlado estrictamente en Cajas)
CREATE TABLE IF NOT EXISTS inventario_almacen (
    almacen_id INT NOT NULL,
    producto_id INT NOT NULL,
    stock_cajas INT NOT NULL DEFAULT 0,
    PRIMARY KEY (almacen_id, producto_id),
    FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 11. Proformas (No reservan stock y duran 1 día hábil)
CREATE TABLE IF NOT EXISTS proformas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    usuario_id INT NOT NULL,
    sucursal_id INT NOT NULL,
    fecha_emision TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    validez_dias INT DEFAULT 1,
    subtotal DECIMAL(12, 2) NOT NULL,
    total DECIMAL(12, 2) NOT NULL,
    estado ENUM('ACTIVA', 'CONVERTIDA_A_VENTA', 'VENCIDA') DEFAULT 'ACTIVA',
    notas TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id)
) ENGINE=InnoDB;

-- 12. Detalles de Proformas
CREATE TABLE IF NOT EXISTS proforma_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proforma_id INT NOT NULL,
    producto_id INT NOT NULL,
    cajas_solicitadas INT NOT NULL,
    m2_reales DECIMAL(10, 4) NOT NULL,
    precio_unitario_caja DECIMAL(12, 2) NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (proforma_id) REFERENCES proformas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

-- 12.5 Cajas Sesiones (Apertura y Cierre de caja por usuario)
CREATE TABLE IF NOT EXISTS cajas_sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha_apertura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre TIMESTAMP NULL,
    monto_apertura DECIMAL(12,2) NOT NULL,
    monto_cierre DECIMAL(12,2) DEFAULT NULL,
    estado ENUM('ABIERTA', 'CERRADA') DEFAULT 'ABIERTA',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 13. Ventas
CREATE TABLE IF NOT EXISTS ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    usuario_id INT NOT NULL,
    sucursal_id INT NOT NULL,
    proforma_id INT, -- NULL si es venta directa
    caja_sesion_id INT NULL, -- Sesión de caja activa
    estado ENUM('PENDIENTE_AUTORIZACION', 'VENDIDO', 'DESPACHADO', 'ENTREGADO', 'RECHAZADO') DEFAULT 'PENDIENTE_AUTORIZACION',
    tipo_pago ENUM('EFECTIVO', 'CREDITO', 'ANTICIPO') DEFAULT 'EFECTIVO',
    costo_transporte DECIMAL(12, 2) DEFAULT 0.00,
    subtotal DECIMAL(12, 2) NOT NULL,
    total DECIMAL(12, 2) NOT NULL,
    fecha_venta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id),
    FOREIGN KEY (proforma_id) REFERENCES proformas(id) ON DELETE SET NULL,
    FOREIGN KEY (caja_sesion_id) REFERENCES cajas_sesiones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 14. Detalles de Ventas
CREATE TABLE IF NOT EXISTS venta_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    almacen_id INT NOT NULL, -- El almacén de donde sale el stock
    cajas_solicitadas INT NOT NULL,
    m2_reales DECIMAL(10, 4) NOT NULL,
    precio_unitario_caja DECIMAL(12, 2) NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (almacen_id) REFERENCES almacenes(id)
) ENGINE=InnoDB;

-- 15. Autorizaciones de Precios
CREATE TABLE IF NOT EXISTS autorizaciones_precio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    usuario_autorizador_id INT,
    estado ENUM('PENDIENTE', 'APROBADO', 'MODIFICADO', 'RECHAZADO') DEFAULT 'PENDIENTE',
    precio_propuesto_total DECIMAL(12, 2) NOT NULL,
    notas_solicitud TEXT,
    notas_resolucion TEXT,
    fecha_resolucion TIMESTAMP NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_autorizador_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- 16. Vehículos / Camiones de Reparto
CREATE TABLE IF NOT EXISTS vehiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    placa VARCHAR(20) NOT NULL UNIQUE,
    modelo VARCHAR(50),
    chofer_id INT NOT NULL,
    estado ENUM('DISPONIBLE', 'EN_RUTA', 'MANTENIMIENTO') DEFAULT 'DISPONIBLE',
    FOREIGN KEY (chofer_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- 17. Hojas de Ruta (Asignación de entregas/ventas a camiones)
CREATE TABLE IF NOT EXISTS hojas_ruta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT NOT NULL,
    fecha_ruta DATE NOT NULL,
    estado ENUM('PLANIFICADO', 'EN_TRANSITO', 'FINALIZADO') DEFAULT 'PLANIFICADO',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id)
) ENGINE=InnoDB;

-- Detalles de la Hoja de Ruta (Ventas asignadas)
CREATE TABLE IF NOT EXISTS hoja_ruta_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hoja_ruta_id INT NOT NULL,
    venta_id INT NOT NULL,
    orden_entrega INT NOT NULL,
    estado_despacho ENUM('PENDIENTE', 'SALIDA_DEPOSITO', 'LLEGADA_CLIENTE', 'ENTREGADO', 'NO_ENTREGADO') DEFAULT 'PENDIENTE',
    FOREIGN KEY (hoja_ruta_id) REFERENCES hojas_ruta(id) ON DELETE CASCADE,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 18. Marcaciones GPS de Logística
CREATE TABLE IF NOT EXISTS despachos_marcaciones_gps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hoja_ruta_detalle_id INT NOT NULL,
    tipo_marcacion ENUM('SALIDA_DEPOSITO', 'LLEGADA_CLIENTE', 'CONFIRMACION_ENTREGA') NOT NULL,
    latitud DECIMAL(10, 8) NOT NULL,
    longitud DECIMAL(11, 8) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hoja_ruta_detalle_id) REFERENCES hoja_ruta_detalles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 19. Cuentas por Cobrar (Ventas a Crédito)
CREATE TABLE IF NOT EXISTS cuentas_por_cobrar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    monto_total DECIMAL(12, 2) NOT NULL,
    saldo_pendiente DECIMAL(12, 2) NOT NULL,
    estado ENUM('PENDIENTE', 'PAGADO') DEFAULT 'PENDIENTE',
    fecha_limite DATE NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Abonos a Crédito
CREATE TABLE IF NOT EXISTS abonos_credito (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuenta_por_cobrar_id INT NOT NULL,
    monto_abonado DECIMAL(12, 2) NOT NULL,
    fecha_abono TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metodo_pago VARCHAR(50) NOT NULL,
    notas TEXT,
    FOREIGN KEY (cuenta_por_cobrar_id) REFERENCES cuentas_por_cobrar(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 20. Devoluciones a Merma/Defectuoso
CREATE TABLE IF NOT EXISTS devoluciones_merma (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    almacen_destino_id INT NOT NULL, -- Debe ser un almacén de tipo 'DEFECTUOSO'
    cajas_devueltas INT NOT NULL,
    motivo TEXT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venta_id) REFERENCES ventas(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (almacen_destino_id) REFERENCES almacenes(id)
) ENGINE=InnoDB;

-- 21. Auditoría de Precios: Documento Maestro
CREATE TABLE IF NOT EXISTS log_cambios_precios_documento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo_cambio VARCHAR(50) NOT NULL,
    modo_aplicado VARCHAR(50) NOT NULL,
    observacion TEXT NOT NULL,
    criterios_aplicados TEXT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 22. Auditoría de Precios: Detalle de Cambios por Producto y Escala
CREATE TABLE IF NOT EXISTS log_cambios_precios_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    producto_id INT NOT NULL,
    escala_id INT NOT NULL,
    precio_anterior DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    precio_nuevo DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (documento_id) REFERENCES log_cambios_precios_documento(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (escala_id) REFERENCES config_escalas_precios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ÍNDICES IMPORTANTES PARA OPTIMIZAR BÚSQUEDAS Y DESPACHOS
CREATE INDEX idx_ventas_estado ON ventas(estado);
CREATE INDEX idx_proformas_estado ON proformas(estado);
CREATE INDEX idx_inventario_stock ON inventario_almacen(stock_cajas);
CREATE INDEX idx_hoja_ruta_fecha ON hojas_ruta(fecha_ruta);

-- Insertar usuarios semilla por defecto para pruebas de desarrollo
INSERT INTO usuarios (nombre, email, password, rol) VALUES 
('Gerente Autorizador', 'gerente@wisdom.com', 'admin123', 'AUTORIZADOR'),
('Vendedor Cochabamba', 'vendedor@wisdom.com', 'vendedor123', 'VENDEDOR'),
('Chofer Camión Km 8', 'chofer@wisdom.com', 'chofer123', 'CHOFER'),
('Administrador General', 'admin@wisdom.com', 'admin123', 'ADMIN')
ON DUPLICATE KEY UPDATE id=id;

-- Insertar sucursales semilla
INSERT INTO sucursales (id, nombre, direccion, telefono) VALUES
(1, 'Cochabamba Centro', 'Av. Blanco Galindo Km 5', '4441234'),
(2, 'Santa Cruz Norte', 'Av. Banzer entre 3er y 4to Anillo', '3331234')
ON DUPLICATE KEY UPDATE id=id;

-- Insertar almacenes semilla
INSERT INTO almacenes (id, sucursal_id, nombre, tipo, direccion) VALUES
(1, 1, 'Galpón Colcapirhua (CBBA)', 'VENTA', 'Calle Santa Cruz Nro 12'),
(2, 1, 'Showroom Guisnai (CBBA)', 'VENTA', 'Av. Blanco Galindo Km 2'),
(3, 1, 'Almacén Merma Colcapirhua', 'DEFECTUOSO', 'Calle Santa Cruz Nro 12 - Area B'),
(4, 2, 'Galpón Km 8 (SCZ)', 'VENTA', 'Av. Banzer Km 8')
ON DUPLICATE KEY UPDATE id=id;

-- Insertar asociación usuarios a sucursales
INSERT INTO usuario_sucursal (usuario_id, sucursal_id) VALUES
(2, 1), -- Vendedor asignado a CBBA
(4, 1), -- Admin asignado a CBBA
(4, 2)  -- Admin asignado a SCZ
ON DUPLICATE KEY UPDATE usuario_id=usuario_id;

-- Insertar clientes semilla
INSERT INTO clientes (id, tipo_cliente, nit_ci, email, ciudad, constructora_nombre, constructora_representante, constructora_telefono, final_nombre_completo, final_direccion, final_telefono) VALUES
(1, 'CONSTRUCTORA', '1020304021', 'contacto@losandes.com', 'Cochabamba', 'Constructora Los Andes', 'Ing. Mario Silva', '77788899', NULL, NULL, NULL),
(2, 'CLIENTE_FINAL', '5432123', 'juan@gmail.com', 'Santa Cruz', NULL, NULL, NULL, 'Juan Perez (Cliente Particular)', 'Calle Las Flores Nro 42', '60011223')
ON DUPLICATE KEY UPDATE id=id;



-- Insertar categorías semilla
INSERT INTO categorias (id, nombre) VALUES
(1, 'Porcelanatos Importados'),
(2, 'Cerámicas Esmaltadas')
ON DUPLICATE KEY UPDATE id=id;

-- Insertar productos semilla (con conversión m2 vs cajas)
INSERT INTO productos (id, categoria_id, codigo, nombre, m2_por_caja, piezas_por_caja, peso_caja_kg, formato, acabado, origen) VALUES
(1, 1, 'POR-6060-VIT-CN', 'Porcelanato Vitrificado 60x60 Pulido', 1.4400, 4, 30.00, '60x60', 'Vitrificado', 'China'),
(2, 1, 'POR-6012-MAT-IN', 'Porcelanato Grís 60x120 Rústico', 1.4400, 2, 32.50, '60x120', 'Mate', 'India'),
(3, 1, 'POR-8080-VIT-BR', 'Porcelanato Super Glossy 80x80', 1.9200, 3, 42.00, '80x80', 'Vitrificado', 'Brasil')
ON DUPLICATE KEY UPDATE id=id;

-- Insertar escalas maestras
INSERT INTO config_escalas_precios (id, nombre, rango_min, rango_max, ordenado, precio_cliente_final) VALUES
(1, 'Escala 1 (1 a 40 cajas)', 1, 40, 1, 0),
(2, 'Escala 2 (41 a 80 cajas)', 41, 80, 2, 0),
(3, 'Escala 3 (+81 cajas)', 81, 99999, 3, 0)
ON DUPLICATE KEY UPDATE id=id;

-- Insertar matriz de precios escalonados (Precios en BOB por caja)
INSERT INTO precios_escalonados (producto_id, escala_id, precio_caja_bob) VALUES
(1, 1, 140.00),
(1, 2, 135.00),
(1, 3, 130.00),
(2, 1, 220.00),
(2, 2, 210.00),
(2, 3, 200.00)
ON DUPLICATE KEY UPDATE id=id;

-- 18. Configuración de Datos de Empresa y Facturación / Comprobantes
CREATE TABLE IF NOT EXISTS configuracion_empresa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    nit VARCHAR(50) NOT NULL,
    sucursal VARCHAR(150) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    telefono VARCHAR(50) NOT NULL,
    ciudad_defecto VARCHAR(100) DEFAULT NULL,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO configuracion_empresa (id, nombre, nit, sucursal, direccion, telefono, ciudad_defecto)
VALUES (1, 'IMPORTADORA MAMIER', '40291048013', 'EL OBRERITO COCHABAMBA', 'CALLE URUGUAY ENTRE OQUENDO Y BENJAMIN BLANCO NRO 123', '75900080', 'Cochabamba')
ON DUPLICATE KEY UPDATE id=id;

-- 19. Catálogo de Menús y Módulos del Sistema
CREATE TABLE IF NOT EXISTS menus (
    id VARCHAR(50) NOT NULL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) DEFAULT '',
    categoria VARCHAR(50) DEFAULT 'GENERAL',
    icono VARCHAR(50) DEFAULT 'Layout',
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 20. Permisos de Acceso a Menús por Rol (Ver y Editar)
CREATE TABLE IF NOT EXISTS rol_permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    menu_id VARCHAR(50) NOT NULL,
    puede_ver TINYINT(1) NOT NULL DEFAULT 0,
    puede_editar TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rol_menu (rol_id, menu_id),
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
) ENGINE=InnoDB;

