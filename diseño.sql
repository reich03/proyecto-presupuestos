
-- Tabla de roles
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    permisos JSON,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    rol_id INT NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    ultimo_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id)
);

-- Tabla de categorías padre (jerarquía)
CREATE TABLE categorias_padre (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    tipo ENUM('ingreso', 'egreso') NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de subcategorías
CREATE TABLE subcategorias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    categoria_padre_id INT NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_padre_id) REFERENCES categorias_padre(id)
);

-- Tabla de presupuestos
CREATE TABLE presupuestos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    monto_total DECIMAL(15,2) DEFAULT 0.00,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabla de items del presupuesto
CREATE TABLE presupuesto_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    presupuesto_id INT NOT NULL,
    subcategoria_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    monto_planificado DECIMAL(15,2) NOT NULL,
    monto_real DECIMAL(15,2) DEFAULT 0.00,
    tipo ENUM('ingreso', 'egreso') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (presupuesto_id) REFERENCES presupuestos(id),
    FOREIGN KEY (subcategoria_id) REFERENCES subcategorias(id)
);

-- Tabla de transacciones reales
CREATE TABLE transacciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    presupuesto_item_id INT NOT NULL,
    fecha DATE NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    descripcion TEXT,
    tipo ENUM('ingreso', 'egreso') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (presupuesto_item_id) REFERENCES presupuesto_items(id)
);

-- Tabla de metas personales
CREATE TABLE metas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    monto_objetivo DECIMAL(15,2) NOT NULL,
    monto_actual DECIMAL(15,2) DEFAULT 0.00,
    fecha_objetivo DATE NOT NULL,
    categoria_id INT,
    activo BOOLEAN DEFAULT TRUE,
    completado BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (categoria_id) REFERENCES categorias_padre(id)
);

-- Tabla de progreso de metas
CREATE TABLE metas_progreso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meta_id INT NOT NULL,
    fecha DATE NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meta_id) REFERENCES metas(id)
);

-- Insertar roles predeterminados
INSERT INTO roles (nombre, descripcion, permisos) VALUES
('Admin', 'Administrador del sistema', '["usuarios_crear", "usuarios_editar", "usuarios_eliminar", "roles_gestionar", "categorias_gestionar", "reportes_globales"]'),
('Usuario', 'Usuario estándar', '["presupuestos_crear", "presupuestos_editar", "metas_gestionar", "reportes_personales"]');

-- Insertar usuario administrador predeterminado
INSERT INTO usuarios (usuario, email, password_hash, nombre, apellido, rol_id) VALUES
('admin', 'admin@presupuesto.com', '$2y$10$.DF.f1W8peMaO.sRZj/DVOy/FqQ2RvYEwfZCzwf/x5pVGhfy9dX6W', 'Administrador', 'Sistema', 1);

-- Insertar categorías padre predeterminadas
INSERT INTO categorias_padre (nombre, descripcion, tipo) VALUES
('Salarios', 'Ingresos por trabajo', 'ingreso'),
('Inversiones', 'Ingresos por inversiones', 'ingreso'),
('Otros Ingresos', 'Ingresos diversos', 'ingreso'),
('Vivienda', 'Gastos de vivienda', 'egreso'),
('Alimentación', 'Gastos de comida', 'egreso'),
('Transporte', 'Gastos de transporte', 'egreso'),
('Salud', 'Gastos médicos', 'egreso'),
('Educación', 'Gastos educativos', 'egreso'),
('Entretenimiento', 'Gastos de ocio', 'egreso'),
('Otros Gastos', 'Gastos diversos', 'egreso');

-- Insertar subcategorías predeterminadas
INSERT INTO subcategorias (nombre, descripcion, categoria_padre_id) VALUES
-- Ingresos
('Sueldo Base', 'Salario básico mensual', 1),
('Bonificaciones', 'Bonos y comisiones', 1),
('Horas Extra', 'Pago por horas adicionales', 1),
('Dividendos', 'Ingresos por acciones', 2),
('Intereses', 'Intereses bancarios', 2),
('Rentas', 'Ingresos por alquiler', 2),
-- Egresos
('Alquiler/Hipoteca', 'Pago de vivienda', 4),
('Servicios Públicos', 'Luz, agua, gas', 4),
('Mantenimiento', 'Reparaciones del hogar', 4),
('Mercado', 'Compras de supermercado', 5),
('Restaurantes', 'Comidas fuera de casa', 5),
('Gasolina', 'Combustible', 6),
('Mantenimiento Vehículo', 'Reparaciones del carro', 6),
('Transporte Público', 'Buses, taxi, metro', 6),
('Medicina', 'Medicamentos', 7),
('Consultas Médicas', 'Citas médicas', 7),
('Cursos', 'Educación formal', 8),
('Libros', 'Material educativo', 8),
('Cine', 'Entretenimiento', 9),
('Deportes', 'Actividades deportivas', 9);

-- TRIGGERS

-- Trigger para actualizar monto_real en presupuesto_items
DELIMITER //
CREATE TRIGGER tr_actualizar_monto_real_item
AFTER INSERT ON transacciones
FOR EACH ROW
BEGIN
    UPDATE presupuesto_items 
    SET monto_real = (
        SELECT COALESCE(SUM(monto), 0) 
        FROM transacciones 
        WHERE presupuesto_item_id = NEW.presupuesto_item_id
    )
    WHERE id = NEW.presupuesto_item_id;
END//
DELIMITER ;

-- Trigger para actualizar monto_total en presupuestos
DELIMITER //
CREATE TRIGGER tr_actualizar_monto_total_presupuesto
AFTER UPDATE ON presupuesto_items
FOR EACH ROW
BEGIN
    UPDATE presupuestos 
    SET monto_total = (
        SELECT COALESCE(SUM(
            CASE 
                WHEN tipo = 'ingreso' THEN monto_real
                ELSE -monto_real
            END
        ), 0)
        FROM presupuesto_items 
        WHERE presupuesto_id = NEW.presupuesto_id
    )
    WHERE id = NEW.presupuesto_id;
END//
DELIMITER ;

-- Trigger para actualizar progreso de metas
DELIMITER //
CREATE TRIGGER tr_actualizar_progreso_meta
AFTER INSERT ON metas_progreso
FOR EACH ROW
BEGIN
    UPDATE metas 
    SET monto_actual = (
        SELECT COALESCE(SUM(monto), 0) 
        FROM metas_progreso 
        WHERE meta_id = NEW.meta_id
    ),
    completado = CASE 
        WHEN (SELECT SUM(monto) FROM metas_progreso WHERE meta_id = NEW.meta_id) >= monto_objetivo 
        THEN TRUE 
        ELSE FALSE 
    END
    WHERE id = NEW.meta_id;
END//
DELIMITER ;

-- VIEWS

-- Vista para resumen de presupuestos
CREATE VIEW v_resumen_presupuestos AS
SELECT 
    p.id,
    p.nombre,
    p.descripcion,
    p.fecha_inicio,
    p.fecha_fin,
    u.nombre as usuario_nombre,
    u.apellido as usuario_apellido,
    p.monto_total,
    COALESCE(SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_planificado ELSE 0 END), 0) as ingresos_planificados,
    COALESCE(SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_planificado ELSE 0 END), 0) as egresos_planificados,
    COALESCE(SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_real ELSE 0 END), 0) as ingresos_reales,
    COALESCE(SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_real ELSE 0 END), 0) as egresos_reales,
    (COALESCE(SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_planificado ELSE 0 END), 0) - 
     COALESCE(SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_planificado ELSE 0 END), 0)) as balance_planificado,
    (COALESCE(SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_real ELSE 0 END), 0) - 
     COALESCE(SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_real ELSE 0 END), 0)) as balance_real
FROM presupuestos p
LEFT JOIN usuarios u ON p.usuario_id = u.id
LEFT JOIN presupuesto_items pi ON p.id = pi.presupuesto_id
WHERE p.activo = TRUE
GROUP BY p.id, p.nombre, p.descripcion, p.fecha_inicio, p.fecha_fin, u.nombre, u.apellido, p.monto_total;

-- Vista para resumen de gastos por categoría
CREATE VIEW v_gastos_por_categoria AS
SELECT 
    cp.id as categoria_id,
    cp.nombre as categoria_nombre,
    cp.tipo,
    u.id as usuario_id,
    u.nombre as usuario_nombre,
    COALESCE(SUM(pi.monto_planificado), 0) as monto_planificado,
    COALESCE(SUM(pi.monto_real), 0) as monto_real,
    COUNT(pi.id) as cantidad_items
FROM categorias_padre cp
LEFT JOIN subcategorias sc ON cp.id = sc.categoria_padre_id
LEFT JOIN presupuesto_items pi ON sc.id = pi.subcategoria_id
LEFT JOIN presupuestos p ON pi.presupuesto_id = p.id
LEFT JOIN usuarios u ON p.usuario_id = u.id
WHERE cp.activo = TRUE
GROUP BY cp.id, cp.nombre, cp.tipo, u.id, u.nombre;

-- Vista para progreso de metas
CREATE VIEW v_progreso_metas AS
SELECT 
    m.id,
    m.nombre,
    m.descripcion,
    m.monto_objetivo,
    m.monto_actual,
    m.fecha_objetivo,
    m.completado,
    u.nombre as usuario_nombre,
    u.apellido as usuario_apellido,
    cp.nombre as categoria_nombre,
    ROUND((m.monto_actual / m.monto_objetivo) * 100, 2) as porcentaje_progreso,
    DATEDIFF(m.fecha_objetivo, CURDATE()) as dias_restantes
FROM metas m
LEFT JOIN usuarios u ON m.usuario_id = u.id
LEFT JOIN categorias_padre cp ON m.categoria_id = cp.id
WHERE m.activo = TRUE;