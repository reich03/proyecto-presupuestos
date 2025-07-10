-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: db:3306
-- Generation Time: Jul 10, 2025 at 11:28 PM
-- Server version: 8.0.42
-- PHP Version: 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `presupuesto_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `categorias_padre`
--

CREATE TABLE `categorias_padre` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `tipo` enum('ingreso','egreso') NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categorias_padre`
--

INSERT INTO `categorias_padre` (`id`, `nombre`, `descripcion`, `tipo`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'Salarios', 'Ingresos por trabajo', 'ingreso', 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(2, 'Inversiones', 'Ingresos por inversionessss', 'ingreso', 1, '2025-07-10 16:59:27', '2025-07-10 23:13:41'),
(3, 'Otros Ingresos', 'Ingresos diversos', 'ingreso', 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(4, 'Vivienda', 'Gastos de vivienda', 'egreso', 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(5, 'Alimentación', 'Gastos de comida', 'egreso', 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(6, 'Transporte', 'Gastos de transporte', 'egreso', 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(7, 'Salud', 'Gastos médicos', 'egreso', 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(8, 'Educación', 'Gastos educativos', 'egreso', 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(9, 'Entretenimiento', 'Gastos de ocio', 'egreso', 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(10, 'Otros Gastos', 'Gastos diversos', 'egreso', 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(11, 'deudas', '', 'egreso', 1, '2025-07-10 20:19:29', '2025-07-10 20:19:29');

-- --------------------------------------------------------

--
-- Table structure for table `metas`
--

CREATE TABLE `metas` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `monto_objetivo` decimal(15,2) NOT NULL,
  `monto_actual` decimal(15,2) DEFAULT '0.00',
  `fecha_objetivo` date NOT NULL,
  `categoria_id` int DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `completado` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `metas`
--

INSERT INTO `metas` (`id`, `usuario_id`, `nombre`, `descripcion`, `monto_objetivo`, `monto_actual`, `fecha_objetivo`, `categoria_id`, `activo`, `completado`, `created_at`, `updated_at`) VALUES
(1, 1, 'Comprar casa', '', 3000000.00, 0.00, '2025-10-30', 1, 1, 0, '2025-07-10 18:10:15', '2025-07-10 18:10:15'),
(2, 1, 'Comprar casa', '', 3000000.00, 0.00, '2025-10-30', 1, 1, 0, '2025-07-10 18:43:46', '2025-07-10 18:43:46'),
(3, 1, 'nose', 'djsjd', 450000.00, 0.00, '2025-10-07', 4, 1, 0, '2025-07-10 18:47:03', '2025-07-10 18:47:03'),
(4, 1, 'pagar casa', 'ejemplo', 25000.00, 20000.00, '2025-07-31', 11, 1, 0, '2025-07-10 20:23:03', '2025-07-10 23:13:30'),
(5, 2, 'Metas de Jhojan', '', 500000.00, 12000.00, '2025-10-02', 8, 1, 0, '2025-07-10 21:17:47', '2025-07-10 21:18:06');

-- --------------------------------------------------------

--
-- Table structure for table `metas_progreso`
--

CREATE TABLE `metas_progreso` (
  `id` int NOT NULL,
  `meta_id` int NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `descripcion` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `metas_progreso`
--

INSERT INTO `metas_progreso` (`id`, `meta_id`, `fecha`, `monto`, `descripcion`, `created_at`) VALUES
(1, 4, '2025-07-10', 20000.00, '', '2025-07-10 20:57:51'),
(2, 5, '2025-07-10', 12000.00, 'Ingresos familiares', '2025-07-10 21:18:06');

--
-- Triggers `metas_progreso`
--
DELIMITER $$
CREATE TRIGGER `tr_actualizar_progreso_meta` AFTER INSERT ON `metas_progreso` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `presupuestos`
--

CREATE TABLE `presupuestos` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `monto_total` decimal(15,2) DEFAULT '0.00',
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `presupuestos`
--

INSERT INTO `presupuestos` (`id`, `usuario_id`, `nombre`, `descripcion`, `fecha_inicio`, `fecha_fin`, `monto_total`, `activo`, `created_at`, `updated_at`) VALUES
(1, 2, 'Compra carro', '', '2025-07-10', '2025-07-31', 0.00, 1, '2025-07-10 17:52:23', '2025-07-10 17:52:23'),
(2, 2, 'Compra carro', '', '2025-07-10', '2025-07-31', 0.00, 1, '2025-07-10 17:52:26', '2025-07-10 17:52:26'),
(3, 1, 'Compras', '', '2025-07-08', '2025-07-30', 1994677.00, 1, '2025-07-10 17:55:05', '2025-07-10 23:24:39'),
(4, 1, 'Compras', '', '2025-07-08', '2025-07-30', 0.00, 1, '2025-07-10 17:55:07', '2025-07-10 17:55:07'),
(5, 1, 'pago casa', 'ejemplo', '2025-07-09', '2025-07-31', 20000.00, 1, '2025-07-10 20:23:20', '2025-07-10 18:12:50'),
(6, 1, 'pago casa', '', '2025-07-09', '2025-07-31', 0.00, 1, '2025-07-10 20:23:53', '2025-07-10 20:23:53'),
(7, 2, 'Presupuesto de julio', '', '2025-07-01', '2025-07-31', 2000000.00, 1, '2025-07-10 21:00:34', '2025-07-10 21:01:35'),
(8, 1, 'ejemplo guardado', '', '2025-07-09', '2025-08-06', 0.00, 1, '2025-07-10 21:12:10', '2025-07-10 21:12:10');

-- --------------------------------------------------------

--
-- Table structure for table `presupuesto_items`
--

CREATE TABLE `presupuesto_items` (
  `id` int NOT NULL,
  `presupuesto_id` int NOT NULL,
  `subcategoria_id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `monto_planificado` decimal(15,2) NOT NULL,
  `monto_real` decimal(15,2) DEFAULT '0.00',
  `tipo` enum('ingreso','egreso') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `presupuesto_items`
--

INSERT INTO `presupuesto_items` (`id`, `presupuesto_id`, `subcategoria_id`, `nombre`, `descripcion`, `monto_planificado`, `monto_real`, `tipo`, `created_at`, `updated_at`) VALUES
(1, 3, 4, 'nose', '', 20000.00, 2000000.00, 'ingreso', '2025-07-10 19:03:59', '2025-07-10 19:04:55'),
(2, 3, 18, 'gasto de ejemplo', '', 10000.00, 323.00, 'egreso', '2025-07-10 19:40:25', '2025-07-10 23:24:39'),
(3, 3, 21, 'pago mes de agosto', '', 20000.00, 5000.00, 'egreso', '2025-07-10 20:21:02', '2025-07-10 20:22:01'),
(4, 5, 2, 'pago con sueldo', 'hjkhj', 25000.00, 20000.00, 'ingreso', '2025-07-10 20:24:44', '2025-07-10 23:12:17'),
(5, 7, 1, 'Sueldo sasoftco', '', 2000000.00, 2000000.00, 'ingreso', '2025-07-10 21:01:18', '2025-07-10 21:01:35'),
(6, 8, 5, 'ejemplo para guardado', '', 200000.00, 0.00, 'ingreso', '2025-07-10 21:12:33', '2025-07-10 21:12:33');

--
-- Triggers `presupuesto_items`
--
DELIMITER $$
CREATE TRIGGER `tr_actualizar_monto_total_presupuesto` AFTER UPDATE ON `presupuesto_items` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text,
  `permisos` json DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `permisos`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'Administrador del sistema', '[\"usuarios_crear\", \"usuarios_editar\", \"usuarios_eliminar\", \"roles_gestionar\", \"categorias_gestionar\", \"presupuestos_editar\", \"reportes_personales\", \"reportes_globales\"]', 1, '2025-07-10 16:59:27', '2025-07-10 23:14:00'),
(2, 'Usuario', 'Usuario estándar', '[\"presupuestos_crear\", \"presupuestos_editar\", \"metas_gestionar\", \"reportes_personales\"]', 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27');

-- --------------------------------------------------------

--
-- Table structure for table `subcategorias`
--

CREATE TABLE `subcategorias` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `categoria_padre_id` int NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `subcategorias`
--

INSERT INTO `subcategorias` (`id`, `nombre`, `descripcion`, `categoria_padre_id`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'Sueldo Base', 'Salario básico mensual', 1, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(2, 'Bonificaciones', 'Bonos y comisiones', 1, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(3, 'Horas Extra', 'Pago por horas adicionales', 1, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(4, 'Dividendos', 'Ingresos por acciones', 2, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(5, 'Intereses', 'Intereses bancarios', 2, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(6, 'Rentas', 'Ingresos por alquiler', 2, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(7, 'Alquiler/Hipoteca', 'Pago de vivienda', 4, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(8, 'Servicios Públicos', 'Luz, agua, gas', 4, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(9, 'Mantenimiento', 'Reparaciones del hogar', 4, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(10, 'Mercado', 'Compras de supermercado', 5, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(11, 'Restaurantes', 'Comidas fuera de casa', 5, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(12, 'Gasolina', 'Combustible', 6, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(13, 'Mantenimiento Vehículo', 'Reparaciones del carro', 6, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(14, 'Transporte Público', 'Buses, taxi, metro', 6, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(15, 'Medicina', 'Medicamentos', 7, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(16, 'Consultas Médicas', 'Citas médicas', 7, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(17, 'Cursos', 'Educación formal', 8, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(18, 'Libros', 'Material educativo', 8, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(19, 'Cine', 'Entretenimiento', 9, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(20, 'Deportes', 'Actividades deportivas', 9, 1, '2025-07-10 16:59:27', '2025-07-10 16:59:27'),
(21, 'deudas - arriendo', '', 11, 1, '2025-07-10 20:20:22', '2025-07-10 20:20:22');

-- --------------------------------------------------------

--
-- Table structure for table `transacciones`
--

CREATE TABLE `transacciones` (
  `id` int NOT NULL,
  `presupuesto_item_id` int NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `descripcion` text,
  `tipo` enum('ingreso','egreso') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transacciones`
--

INSERT INTO `transacciones` (`id`, `presupuesto_item_id`, `fecha`, `monto`, `descripcion`, `tipo`, `created_at`) VALUES
(1, 1, '2025-07-10', 2000000.00, '', 'ingreso', '2025-07-10 19:04:55'),
(2, 3, '2025-07-10', 5000.00, 'aja', 'egreso', '2025-07-10 20:22:01'),
(3, 4, '2025-07-10', 20000.00, 'ejemplo', 'ingreso', '2025-07-10 20:25:15'),
(4, 5, '2025-07-02', 2000000.00, '', 'ingreso', '2025-07-10 21:01:35'),
(5, 2, '2025-07-10', 323.00, 'fdsfds', 'egreso', '2025-07-10 23:24:39');

--
-- Triggers `transacciones`
--
DELIMITER $$
CREATE TRIGGER `tr_actualizar_monto_real_item` AFTER INSERT ON `transacciones` FOR EACH ROW BEGIN
    UPDATE presupuesto_items 
    SET monto_real = (
        SELECT COALESCE(SUM(monto), 0) 
        FROM transacciones 
        WHERE presupuesto_item_id = NEW.presupuesto_item_id
    )
    WHERE id = NEW.presupuesto_item_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `rol_id` int NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `email`, `password_hash`, `nombre`, `apellido`, `rol_id`, `activo`, `ultimo_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@presupuesto.com', '$2y$10$.DF.f1W8peMaO.sRZj/DVOy/FqQ2RvYEwfZCzwf/x5pVGhfy9dX6W', 'Administrador', 'Sistema', 1, 1, NULL, '2025-07-10 16:59:27', '2025-07-10 17:48:19'),
(2, 'reich', 'redjhojan0319@gmail.com', '$2y$10$JaMJ78xBgJFLRQGOGzKxOOgNv2REbhOKiiLqmb56ygpw8dqdYNdxu', 'jhojan', 'grisales', 2, 1, NULL, '2025-07-10 17:50:37', '2025-07-10 17:50:37'),
(3, 'ejemplo', 'jhojan.grisales@sasoftco.com', '$2y$10$gFQDguZTutDoBF5p27.ciell39wnUh0oadpIbxaBhlxc0UO5plEX.', 'Carlos Arturo', 'Gomez Jimenez', 1, 1, NULL, '2025-07-10 18:48:29', '2025-07-10 18:08:25');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_gastos_por_categoria`
-- (See below for the actual view)
--
CREATE TABLE `v_gastos_por_categoria` (
`categoria_id` int
,`categoria_nombre` varchar(100)
,`tipo` enum('ingreso','egreso')
,`usuario_id` int
,`usuario_nombre` varchar(100)
,`monto_planificado` decimal(37,2)
,`monto_real` decimal(37,2)
,`cantidad_items` bigint
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_progreso_metas`
-- (See below for the actual view)
--
CREATE TABLE `v_progreso_metas` (
`id` int
,`nombre` varchar(100)
,`descripcion` text
,`monto_objetivo` decimal(15,2)
,`monto_actual` decimal(15,2)
,`fecha_objetivo` date
,`completado` tinyint(1)
,`usuario_nombre` varchar(100)
,`usuario_apellido` varchar(100)
,`categoria_nombre` varchar(100)
,`porcentaje_progreso` decimal(21,2)
,`dias_restantes` int
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_resumen_presupuestos`
-- (See below for the actual view)
--
CREATE TABLE `v_resumen_presupuestos` (
`id` int
,`nombre` varchar(100)
,`descripcion` text
,`fecha_inicio` date
,`fecha_fin` date
,`usuario_nombre` varchar(100)
,`usuario_apellido` varchar(100)
,`monto_total` decimal(15,2)
,`ingresos_planificados` decimal(37,2)
,`egresos_planificados` decimal(37,2)
,`ingresos_reales` decimal(37,2)
,`egresos_reales` decimal(37,2)
,`balance_planificado` decimal(38,2)
,`balance_real` decimal(38,2)
);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categorias_padre`
--
ALTER TABLE `categorias_padre`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `metas`
--
ALTER TABLE `metas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `categoria_id` (`categoria_id`);

--
-- Indexes for table `metas_progreso`
--
ALTER TABLE `metas_progreso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meta_id` (`meta_id`);

--
-- Indexes for table `presupuestos`
--
ALTER TABLE `presupuestos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indexes for table `presupuesto_items`
--
ALTER TABLE `presupuesto_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `presupuesto_id` (`presupuesto_id`),
  ADD KEY `subcategoria_id` (`subcategoria_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `subcategorias`
--
ALTER TABLE `subcategorias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoria_padre_id` (`categoria_padre_id`);

--
-- Indexes for table `transacciones`
--
ALTER TABLE `transacciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `presupuesto_item_id` (`presupuesto_item_id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `rol_id` (`rol_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categorias_padre`
--
ALTER TABLE `categorias_padre`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `metas`
--
ALTER TABLE `metas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `metas_progreso`
--
ALTER TABLE `metas_progreso`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `presupuestos`
--
ALTER TABLE `presupuestos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `presupuesto_items`
--
ALTER TABLE `presupuesto_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subcategorias`
--
ALTER TABLE `subcategorias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `transacciones`
--
ALTER TABLE `transacciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

-- --------------------------------------------------------

--
-- Structure for view `v_gastos_por_categoria`
--
DROP TABLE IF EXISTS `v_gastos_por_categoria`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_gastos_por_categoria`  AS SELECT `cp`.`id` AS `categoria_id`, `cp`.`nombre` AS `categoria_nombre`, `cp`.`tipo` AS `tipo`, `u`.`id` AS `usuario_id`, `u`.`nombre` AS `usuario_nombre`, coalesce(sum(`pi`.`monto_planificado`),0) AS `monto_planificado`, coalesce(sum(`pi`.`monto_real`),0) AS `monto_real`, count(`pi`.`id`) AS `cantidad_items` FROM ((((`categorias_padre` `cp` left join `subcategorias` `sc` on((`cp`.`id` = `sc`.`categoria_padre_id`))) left join `presupuesto_items` `pi` on((`sc`.`id` = `pi`.`subcategoria_id`))) left join `presupuestos` `p` on((`pi`.`presupuesto_id` = `p`.`id`))) left join `usuarios` `u` on((`p`.`usuario_id` = `u`.`id`))) WHERE (`cp`.`activo` = true) GROUP BY `cp`.`id`, `cp`.`nombre`, `cp`.`tipo`, `u`.`id`, `u`.`nombre` ;

-- --------------------------------------------------------

--
-- Structure for view `v_progreso_metas`
--
DROP TABLE IF EXISTS `v_progreso_metas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_progreso_metas`  AS SELECT `m`.`id` AS `id`, `m`.`nombre` AS `nombre`, `m`.`descripcion` AS `descripcion`, `m`.`monto_objetivo` AS `monto_objetivo`, `m`.`monto_actual` AS `monto_actual`, `m`.`fecha_objetivo` AS `fecha_objetivo`, `m`.`completado` AS `completado`, `u`.`nombre` AS `usuario_nombre`, `u`.`apellido` AS `usuario_apellido`, `cp`.`nombre` AS `categoria_nombre`, round(((`m`.`monto_actual` / `m`.`monto_objetivo`) * 100),2) AS `porcentaje_progreso`, (to_days(`m`.`fecha_objetivo`) - to_days(curdate())) AS `dias_restantes` FROM ((`metas` `m` left join `usuarios` `u` on((`m`.`usuario_id` = `u`.`id`))) left join `categorias_padre` `cp` on((`m`.`categoria_id` = `cp`.`id`))) WHERE (`m`.`activo` = true) ;

-- --------------------------------------------------------

--
-- Structure for view `v_resumen_presupuestos`
--
DROP TABLE IF EXISTS `v_resumen_presupuestos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_resumen_presupuestos`  AS SELECT `p`.`id` AS `id`, `p`.`nombre` AS `nombre`, `p`.`descripcion` AS `descripcion`, `p`.`fecha_inicio` AS `fecha_inicio`, `p`.`fecha_fin` AS `fecha_fin`, `u`.`nombre` AS `usuario_nombre`, `u`.`apellido` AS `usuario_apellido`, `p`.`monto_total` AS `monto_total`, coalesce(sum((case when (`pi`.`tipo` = 'ingreso') then `pi`.`monto_planificado` else 0 end)),0) AS `ingresos_planificados`, coalesce(sum((case when (`pi`.`tipo` = 'egreso') then `pi`.`monto_planificado` else 0 end)),0) AS `egresos_planificados`, coalesce(sum((case when (`pi`.`tipo` = 'ingreso') then `pi`.`monto_real` else 0 end)),0) AS `ingresos_reales`, coalesce(sum((case when (`pi`.`tipo` = 'egreso') then `pi`.`monto_real` else 0 end)),0) AS `egresos_reales`, (coalesce(sum((case when (`pi`.`tipo` = 'ingreso') then `pi`.`monto_planificado` else 0 end)),0) - coalesce(sum((case when (`pi`.`tipo` = 'egreso') then `pi`.`monto_planificado` else 0 end)),0)) AS `balance_planificado`, (coalesce(sum((case when (`pi`.`tipo` = 'ingreso') then `pi`.`monto_real` else 0 end)),0) - coalesce(sum((case when (`pi`.`tipo` = 'egreso') then `pi`.`monto_real` else 0 end)),0)) AS `balance_real` FROM ((`presupuestos` `p` left join `usuarios` `u` on((`p`.`usuario_id` = `u`.`id`))) left join `presupuesto_items` `pi` on((`p`.`id` = `pi`.`presupuesto_id`))) WHERE (`p`.`activo` = true) GROUP BY `p`.`id`, `p`.`nombre`, `p`.`descripcion`, `p`.`fecha_inicio`, `p`.`fecha_fin`, `u`.`nombre`, `u`.`apellido`, `p`.`monto_total` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `metas`
--
ALTER TABLE `metas`
  ADD CONSTRAINT `metas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `metas_ibfk_2` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_padre` (`id`);

--
-- Constraints for table `metas_progreso`
--
ALTER TABLE `metas_progreso`
  ADD CONSTRAINT `metas_progreso_ibfk_1` FOREIGN KEY (`meta_id`) REFERENCES `metas` (`id`);

--
-- Constraints for table `presupuestos`
--
ALTER TABLE `presupuestos`
  ADD CONSTRAINT `presupuestos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `presupuesto_items`
--
ALTER TABLE `presupuesto_items`
  ADD CONSTRAINT `presupuesto_items_ibfk_1` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`),
  ADD CONSTRAINT `presupuesto_items_ibfk_2` FOREIGN KEY (`subcategoria_id`) REFERENCES `subcategorias` (`id`);

--
-- Constraints for table `subcategorias`
--
ALTER TABLE `subcategorias`
  ADD CONSTRAINT `subcategorias_ibfk_1` FOREIGN KEY (`categoria_padre_id`) REFERENCES `categorias_padre` (`id`);

--
-- Constraints for table `transacciones`
--
ALTER TABLE `transacciones`
  ADD CONSTRAINT `transacciones_ibfk_1` FOREIGN KEY (`presupuesto_item_id`) REFERENCES `presupuesto_items` (`id`);

--
-- Constraints for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
