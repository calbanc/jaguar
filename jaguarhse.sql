-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:8889
-- Tiempo de generación: 02-11-2026 a las 14:40:27
-- Versión del servidor: 8.0.40
-- Versión de PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `jaguarhse`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `accesos_aplicacion`
--

CREATE TABLE `accesos_aplicacion` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `miembro_id` bigint UNSIGNED NOT NULL,
  `plataforma_id` bigint UNSIGNED NOT NULL,
  `rol_id` bigint UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `accesos_aplicacion`
--

INSERT INTO `accesos_aplicacion` (`id`, `empresa_id`, `miembro_id`, `plataforma_id`, `rol_id`, `activo`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, 1, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(2, 1, 1, 2, 1, 1, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(3, 1, 2, 1, 2, 1, '2026-10-04 03:13:20', '2026-10-04 03:13:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acciones_correctivas`
--

CREATE TABLE `acciones_correctivas` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `hallazgo_id` bigint UNSIGNED NOT NULL,
  `responsable_id` bigint UNSIGNED NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_limite` date NOT NULL,
  `estado` enum('pendiente','en_proceso','por_validar','completada','anulada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `completada_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `almacenes`
--

CREATE TABLE `almacenes` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `sede_id` bigint UNSIGNED NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `almacenes`
--

INSERT INTO `almacenes` (`id`, `empresa_id`, `sede_id`, `nombre`, `activo`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Almacen General', 1, '2026-10-08 01:45:02', '2026-10-08 01:45:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `archivos`
--

CREATE TABLE `archivos` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `subido_por` bigint UNSIGNED NOT NULL,
  `nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `disco` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bytes` bigint UNSIGNED NOT NULL,
  `sha256` char(64) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `clasificacion` enum('interno','confidencial','restringido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'interno',
  `eliminado_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas`
--

CREATE TABLE `areas` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `zona_id` bigint UNSIGNED NOT NULL,
  `coordenadas` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `areas`
--

INSERT INTO `areas` (`id`, `empresa_id`, `nombre`, `activo`, `created_at`, `updated_at`, `zona_id`, `coordenadas`) VALUES
(1, 1, 'ADMINISTRACION', 1, '2026-09-26 17:32:45', '2026-10-26 21:16:58', 1, '{\"type\": \"Polygon\", \"coordinates\": [[[-80.59764020956794, -5.067486468355867], [-80.59722714937939, -5.067566620108067], [-80.59717082298984, -5.067262043400859], [-80.59755169667018, -5.067192578518387], [-80.59764020956794, -5.067486468355867]]]}');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria_eventos`
--

CREATE TABLE `auditoria_eventos` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `actor_id` bigint UNSIGNED DEFAULT NULL,
  `accion` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registro_id` bigint UNSIGNED DEFAULT NULL,
  `datos_antes` json DEFAULT NULL,
  `datos_despues` json DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `auditoria_eventos`
--

INSERT INTO `auditoria_eventos` (`id`, `empresa_id`, `actor_id`, `accion`, `entidad`, `registro_id`, `datos_antes`, `datos_despues`, `ip`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'crear', 'sedes', 1, NULL, '{\"id\": 1, \"activo\": 1, \"codigo\": \"SEDE-001\", \"nombre\": \"Sede principal (configuración inicial)\", \"direccion\": null, \"created_at\": \"2026-09-26 11:44:37\", \"empresa_id\": 1, \"updated_at\": \"2026-09-26 11:44:37\"}', '127.0.0.1', '2026-09-26 11:44:37', '2026-09-26 11:44:37'),
(2, 1, 1, 'crear', 'areas', 1, NULL, '{\"id\": 1, \"activo\": 1, \"nombre\": \"ADMINISTRACION\", \"sede_id\": 1, \"created_at\": \"2026-09-26 17:32:45\", \"empresa_id\": 1, \"updated_at\": \"2026-09-26 17:32:45\"}', '127.0.0.1', '2026-09-26 17:32:45', '2026-09-26 17:32:45'),
(3, 1, 1, 'crear', 'trabajadores', 1, NULL, '{\"id\": 1, \"activo\": 1, \"puesto\": \"PROGRAMADOR\", \"area_id\": 1, \"nombres\": \"JEANCARLOS\", \"apellidos\": \"ALBAN ATO\", \"created_at\": \"2026-09-26 17:36:30\", \"empresa_id\": 1, \"fecha_cese\": null, \"miembro_id\": null, \"updated_at\": \"2026-09-26 17:36:30\", \"fecha_ingreso\": \"2026-09-22\", \"tipo_documento\": \"DNI\", \"numero_documento\": \"71462409\"}', '127.0.0.1', '2026-09-26 17:36:30', '2026-09-26 17:36:30'),
(4, 1, 1, 'configurar_rol', 'roles', 2, NULL, '{\"id\": 2, \"activo\": 1, \"nombre\": \"Gestion Organizacion\", \"permisos\": [309, 308, 307, 523, 524], \"created_at\": \"2026-10-04 03:12:21\", \"empresa_id\": 1, \"updated_at\": \"2026-10-04 03:12:21\", \"descripcion\": \"Usuario podra realizar todas las acciones para el modulo de organizacion\"}', '127.0.0.1', '2026-10-04 03:12:21', '2026-10-04 03:12:21'),
(5, 1, 1, 'crear_usuario', 'miembros_empresa', 2, NULL, '{\"id\": 2, \"activo\": 1, \"created_at\": \"2026-10-04 03:13:20\", \"empresa_id\": 1, \"updated_at\": \"2026-10-04 03:13:20\", \"usuario_id\": 2}', '127.0.0.1', '2026-10-04 03:13:20', '2026-10-04 03:13:20'),
(6, 1, 1, 'crear', 'almacenes', 1, NULL, '{\"id\": 1, \"activo\": 1, \"nombre\": \"Almacen General\", \"sede_id\": 1, \"created_at\": \"2026-10-08 01:45:02\", \"empresa_id\": 1, \"updated_at\": \"2026-10-08 01:45:02\"}', '127.0.0.1', '2026-10-08 01:45:02', '2026-10-08 01:45:02'),
(7, 1, 1, 'crear', 'epp_catalogo', 1, NULL, '{\"id\": 1, \"talla\": \"UNICA\", \"activo\": 1, \"codigo\": \"L01\", \"nombre\": \"LENTES NEGRO\", \"unidad\": \"par\", \"created_at\": \"2026-10-08 02:09:54\", \"empresa_id\": 1, \"updated_at\": \"2026-10-08 02:09:54\", \"vida_util_dias\": 30, \"ficha_archivo_id\": null}', '127.0.0.1', '2026-10-08 02:09:54', '2026-10-08 02:09:54'),
(8, 1, 1, 'compra', 'epp_movimientos', 1, NULL, '{\"id\": 1, \"tipo\": \"entrada\", \"fecha\": \"2026-10-08 02:11:02\", \"epp_id\": 1, \"cantidad\": \"300.000\", \"almacen_id\": 1, \"created_at\": \"2026-10-08 02:11:02\", \"empresa_id\": 1, \"updated_at\": \"2026-10-08 02:11:02\", \"costo_total\": \"450.000000\", \"observacion\": \"Compra 23 · PEPITO\", \"saldo_valor\": \"450.000000\", \"costo_unitario\": \"1.500000\", \"registrado_por\": 1, \"clave_operacion\": \"compra:1:0\", \"compra_detalle_id\": 1, \"entrega_detalle_id\": null}', '127.0.0.1', '2026-10-08 02:11:02', '2026-10-08 02:11:02'),
(9, 1, 1, 'registrar_compra', 'epp_compras', 1, NULL, '{\"id\": 1, \"fecha\": \"2026-10-07\", \"huella\": \"d6f13b644741e944a45698d2d8f931779ef6bc81c92cafc2e55fdb018456000b\", \"moneda\": \"PEN\", \"factura\": \"23\", \"proveedor\": \"PEPITO\", \"created_at\": \"2026-10-08 02:11:02\", \"empresa_id\": 1, \"updated_at\": \"2026-10-08 02:11:02\", \"costo_total\": \"450.000000\", \"observacion\": null, \"tipo_cambio\": \"1.000000\", \"proveedor_ruc\": \"12123123898\", \"registrado_por\": 1, \"clave_operacion\": \"e4a7a1d0-43fc-4e94-a11c-20a041e75386\", \"proveedor_clave\": \"12123123898\", \"costo_total_original\": \"450.000000\"}', '127.0.0.1', '2026-10-08 02:11:02', '2026-10-08 02:11:02'),
(10, 1, 1, 'entregar', 'epp_entregas', 1, NULL, '{\"id\": 1, \"fecha\": \"2026-10-10 16:17:00\", \"estado\": \"confirmada\", \"motivo\": \"nuevo\", \"created_at\": \"2026-10-10 16:17:51\", \"empresa_id\": 1, \"updated_at\": \"2026-10-10 16:17:51\", \"observacion\": null, \"recibido_at\": null, \"uuid_cliente\": \"4c56f569-7726-42df-a21f-d28962fc3c3c\", \"entregado_por\": 1, \"trabajador_id\": 1, \"firma_archivo_id\": null}', '127.0.0.1', '2026-10-10 16:17:51', '2026-10-10 16:17:51'),
(11, 1, 1, 'crear', 'epp_entrega_detalles', 1, NULL, '{\"id\": 1, \"epp_id\": 1, \"cantidad\": \"20.000\", \"almacen_id\": 1, \"created_at\": \"2026-10-10 16:17:51\", \"empresa_id\": 1, \"entrega_id\": 1, \"updated_at\": \"2026-10-10 16:17:51\", \"costo_total\": \"30.000000\", \"costo_unitario\": \"1.500000\", \"fecha_reposicion\": null}', '127.0.0.1', '2026-10-10 16:17:51', '2026-10-10 16:17:51'),
(12, 1, 1, 'crear', 'tema_capacitacion', 1, NULL, '{\"id\": 1, \"activo\": 1, \"nombre\": \"MANEJO CONTRA INCENDIOS\", \"created_at\": \"2026-10-24 02:50:35\", \"empresa_id\": 1, \"updated_at\": \"2026-10-24 02:50:35\", \"descripcion\": \"CAPACITACION CONTRA INCENDIOS\"}', '127.0.0.1', '2026-10-24 02:50:35', '2026-10-24 02:50:35'),
(13, 1, 1, 'crear', 'lugar_capacitacion', 1, NULL, '{\"id\": 1, \"activo\": 1, \"nombre\": \"ADMINISTRACION\", \"direccion\": \"AVANTI\", \"created_at\": \"2026-10-24 02:50:49\", \"empresa_id\": 1, \"updated_at\": \"2026-10-24 02:50:49\", \"descripcion\": \"ADMINISTRACION\"}', '127.0.0.1', '2026-10-24 02:50:49', '2026-10-24 02:50:49'),
(14, 1, 1, 'crear', 'capacitador', 1, NULL, '{\"id\": 1, \"email\": \"REYES@GMAIL.COM\", \"activo\": 1, \"nombre\": \"JOSE REYES\", \"telefono\": \"98766788\", \"created_at\": \"2026-10-24 02:51:18\", \"empresa_id\": 1, \"updated_at\": \"2026-10-24 02:51:18\", \"descripcion\": \"CAPACITADOR\", \"numero_documento\": \"71462098\"}', '127.0.0.1', '2026-10-24 02:51:18', '2026-10-24 02:51:18'),
(15, 1, 1, 'crear', 'empresa_capacitacion', 1, NULL, '{\"id\": 1, \"email\": \"GASSI@GMAIL.COM\", \"activo\": 1, \"nombre\": \"GASSI PERU\", \"telefono\": \"1239182\", \"created_at\": \"2026-10-24 02:51:38\", \"empresa_id\": 1, \"updated_at\": \"2026-10-24 02:51:38\", \"descripcion\": null, \"numero_documento\": \"1230\'9\"}', '127.0.0.1', '2026-10-24 02:51:38', '2026-10-24 02:51:38'),
(16, 1, 1, 'crear', 'capacitacion', 1, NULL, '{\"id\": 1, \"fecha\": \"2026-10-23\", \"activo\": 1, \"created_at\": \"2026-10-24 02:52:09\", \"empresa_id\": 1, \"updated_at\": \"2026-10-24 02:52:09\", \"hora_inicio\": \"10:00:00\", \"hora_termino\": \"12:00:00\", \"trabajadores\": [], \"observaciones\": null, \"capacitador_id\": 1, \"numero_planilla\": 1, \"tema_capacitacion_id\": 1, \"lugar_capacitacion_id\": 1, \"empresa_capacitacion_id\": 1, \"responsable_registro_id\": 1}', '127.0.0.1', '2026-10-24 02:52:09', '2026-10-24 02:52:09'),
(17, 1, 1, 'crear', 'capacitacion_participantes', 1, NULL, '{\"id\": 1, \"activo\": 1, \"created_at\": \"2026-10-24 02:52:18\", \"empresa_id\": 1, \"firma_ruta\": null, \"updated_at\": \"2026-10-24 02:52:18\", \"firma_bytes\": 0, \"firma_fecha\": null, \"trabajador_id\": 1, \"capacitacion_id\": 1}', '127.0.0.1', '2026-10-24 02:52:18', '2026-10-24 02:52:18'),
(18, 1, 1, 'guardar_firma', 'capacitacion_participantes', 1, '{\"id\": 1, \"activo\": 1, \"created_at\": \"2026-10-24 02:52:18\", \"empresa_id\": 1, \"firma_ruta\": null, \"updated_at\": \"2026-10-24 02:52:18\", \"firma_bytes\": 0, \"firma_fecha\": null, \"trabajador_id\": 1, \"capacitacion_id\": 1}', '{\"id\": 1, \"activo\": 1, \"created_at\": \"2026-10-24 02:52:18\", \"empresa_id\": 1, \"firma_ruta\": \"1/1/1/230837cf-c935-4a26-957a-e631c16e8608/71462409.png\", \"updated_at\": \"2026-10-24 02:52:28\", \"firma_bytes\": 11859, \"firma_fecha\": \"2026-10-24 02:52:28\", \"trabajador_id\": 1, \"capacitacion_id\": 1}', '127.0.0.1', '2026-10-24 02:52:28', '2026-10-24 02:52:28'),
(19, 1, 1, 'editar', 'areas', 1, '{\"id\": 1, \"activo\": 1, \"nombre\": \"ADMINISTRACION\", \"zona_id\": 1, \"created_at\": \"2026-09-26 17:32:45\", \"empresa_id\": 1, \"updated_at\": \"2026-10-09 17:38:22\", \"coordenadas\": null}', '{\"id\": 1, \"activo\": 1, \"nombre\": \"ADMINISTRACION\", \"zona_id\": 1, \"created_at\": \"2026-09-26 17:32:45\", \"empresa_id\": 1, \"updated_at\": \"2026-10-26 21:16:58\", \"coordenadas\": \"{\\\"type\\\": \\\"Polygon\\\", \\\"coordinates\\\": [[[-80.59764020956794, -5.067486468355867], [-80.59722714937939, -5.067566620108067], [-80.59717082298984, -5.067262043400859], [-80.59755169667018, -5.067192578518387], [-80.59764020956794, -5.067486468355867]]]}\"}', '127.0.0.1', '2026-10-26 21:16:58', '2026-10-26 21:16:58'),
(20, 1, 1, 'crear', 'implementos_categorias', 1, NULL, '{\"id\": 1, \"activo\": 1, \"nombre\": \"Extintor\", \"created_at\": \"2026-10-27 15:21:40\", \"empresa_id\": 1, \"updated_at\": \"2026-10-27 15:21:40\", \"descripcion\": null}', '127.0.0.1', '2026-10-27 15:21:40', '2026-10-27 15:21:40'),
(21, 1, 1, 'editar', 'implementos_categorias', 1, '{\"id\": 1, \"activo\": 1, \"nombre\": \"Extintor\", \"created_at\": \"2026-10-27 15:21:40\", \"empresa_id\": 1, \"updated_at\": \"2026-10-27 15:21:40\", \"descripcion\": null}', '{\"id\": 1, \"activo\": 1, \"nombre\": \"Extintor\", \"created_at\": \"2026-10-27 15:21:40\", \"empresa_id\": 1, \"updated_at\": \"2026-10-27 15:21:51\", \"descripcion\": \"Extintores\"}', '127.0.0.1', '2026-10-27 15:21:51', '2026-10-27 15:21:51'),
(22, 1, 1, 'crear', 'implementos_catalogo', 1, NULL, '{\"id\": 1, \"activo\": 1, \"codigo\": \"1\", \"nombre\": \"Tipo A\", \"categoria\": \"Extintor\", \"created_at\": \"2026-10-27 15:26:55\", \"empresa_id\": 1, \"updated_at\": \"2026-10-27 15:26:55\", \"descripcion\": \"Extintor para fuegos de tipo A, solidos combustibles\", \"categoria_id\": 1, \"periodicidad_unidad\": \"meses\", \"periodicidad_cantidad\": 1}', '127.0.0.1', '2026-10-27 15:26:55', '2026-10-27 15:26:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('laravel-cache-0202867243fcf8d1bee13589bec2bfc0', 'i:3;', 1791424659),
('laravel-cache-0202867243fcf8d1bee13589bec2bfc0:timer', 'i:1791424659;', 1791424659),
('laravel-cache-12760546334bc68fa7064651665b755e', 'i:2;', 1791651196),
('laravel-cache-12760546334bc68fa7064651665b755e:timer', 'i:1791651196;', 1791651196),
('laravel-cache-183b74e06ad8f48cbbc649877f2f3e40', 'i:2;', 1791081771),
('laravel-cache-183b74e06ad8f48cbbc649877f2f3e40:timer', 'i:1791081771;', 1791081771),
('laravel-cache-232b6da48bd3fb7ed7495c40510c16c8', 'i:1;', 1791083713),
('laravel-cache-232b6da48bd3fb7ed7495c40510c16c8:timer', 'i:1791083713;', 1791083713),
('laravel-cache-275e0d8f6facbcacec65057a0fca40b9', 'i:3;', 1791995760),
('laravel-cache-275e0d8f6facbcacec65057a0fca40b9:timer', 'i:1791995760;', 1791995760),
('laravel-cache-297e712f0ae5c7c3d27eed00d78aac56', 'i:4;', 1791471382),
('laravel-cache-297e712f0ae5c7c3d27eed00d78aac56:timer', 'i:1791471382;', 1791471382),
('laravel-cache-42f1c73084a5f7b5cde0b57d8d549f96', 'i:1;', 1791169030),
('laravel-cache-42f1c73084a5f7b5cde0b57d8d549f96:timer', 'i:1791169030;', 1791169030),
('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba', 'i:2;', 1791215334),
('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba:timer', 'i:1791215334;', 1791215334),
('laravel-cache-5d39eb9ea312abcc1135008d7237ef6a', 'i:10;', 1791421866),
('laravel-cache-5d39eb9ea312abcc1135008d7237ef6a:timer', 'i:1791421866;', 1791421866),
('laravel-cache-6a190ce541b3c2f4f267ed84d4210959', 'i:2;', 1790443749),
('laravel-cache-6a190ce541b3c2f4f267ed84d4210959:timer', 'i:1790443749;', 1790443749),
('laravel-cache-6c545cc5756d1484620afa5f54d5c9b6', 'i:8;', 1791083659),
('laravel-cache-6c545cc5756d1484620afa5f54d5c9b6:timer', 'i:1791083659;', 1791083659),
('laravel-cache-7530cdd1e3d8df5b99713f5665cf3e49', 'i:4;', 1790444376),
('laravel-cache-7530cdd1e3d8df5b99713f5665cf3e49:timer', 'i:1790444376;', 1790444376),
('laravel-cache-a3717aa20bd0e4e9d6e1f95dbeacf33b', 'i:18;', 1791083409),
('laravel-cache-a3717aa20bd0e4e9d6e1f95dbeacf33b:timer', 'i:1791083409;', 1791083409),
('laravel-cache-aff9f7e1dfe00f113ec5526eafe14f99', 'i:3;', 1793129211),
('laravel-cache-aff9f7e1dfe00f113ec5526eafe14f99:timer', 'i:1793129211;', 1793129211),
('laravel-cache-b92eed9b9fe60c15e5f59aa389959c08', 'i:3;', 1791220050),
('laravel-cache-b92eed9b9fe60c15e5f59aa389959c08:timer', 'i:1791220050;', 1791220050),
('laravel-cache-cd0ff8c26734c2b3830d399a69f70c44', 'i:3;', 1792097983),
('laravel-cache-cd0ff8c26734c2b3830d399a69f70c44:timer', 'i:1792097983;', 1792097983),
('laravel-cache-d5ea1a40d8061db2abf2f07bd34ca610', 'i:8;', 1791079827),
('laravel-cache-d5ea1a40d8061db2abf2f07bd34ca610:timer', 'i:1791079827;', 1791079827),
('laravel-cache-d81c051dd42e8b5cd6ccbd2afc86c89f', 'i:3;', 1791216041),
('laravel-cache-d81c051dd42e8b5cd6ccbd2afc86c89f:timer', 'i:1791216041;', 1791216041),
('laravel-cache-e15dce64691cc53fae3e4cfe6fbe33ef', 'i:3;', 1793114517),
('laravel-cache-e15dce64691cc53fae3e4cfe6fbe33ef:timer', 'i:1793114517;', 1793114517),
('laravel-cache-e32e2c8c993682743bec73f932c45870', 'i:8;', 1791080300),
('laravel-cache-e32e2c8c993682743bec73f932c45870:timer', 'i:1791080300;', 1791080300),
('laravel-cache-e72e7eca2d8a99f6ee058e228be01745', 'i:3;', 1793108977),
('laravel-cache-e72e7eca2d8a99f6ee058e228be01745:timer', 'i:1793108977;', 1793108977),
('laravel-cache-ef3ddcf0b9fcfc5ba83eb68f3697ff87', 'i:8;', 1790443781),
('laravel-cache-ef3ddcf0b9fcfc5ba83eb68f3697ff87:timer', 'i:1790443781;', 1790443781),
('laravel-cache-email-verification:1:ca4fa20ae19afd6e6ec7cc2111828f2de970dfc3', 'i:1;', 1791214994),
('laravel-cache-email-verification:1:ca4fa20ae19afd6e6ec7cc2111828f2de970dfc3:timer', 'i:1791214994;', 1791214994),
('laravel-cache-f1350280739b5003043a5d9057350719', 'i:14;', 1791083714),
('laravel-cache-f1350280739b5003043a5d9057350719:timer', 'i:1791083714;', 1791083714),
('laravel-cache-f399cf683eb63f8105758107c28d3c95', 'i:3;', 1792879488),
('laravel-cache-f399cf683eb63f8105758107c28d3c95:timer', 'i:1792879488;', 1792879488),
('laravel-cache-fe0f06f5033afda5cacceb931296cf0b', 'i:3;', 1793114517),
('laravel-cache-fe0f06f5033afda5cacceb931296cf0b:timer', 'i:1793114517;', 1793114517);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `capacitacion`
--

CREATE TABLE `capacitacion` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tema_capacitacion_id` bigint UNSIGNED NOT NULL,
  `lugar_capacitacion_id` bigint UNSIGNED NOT NULL,
  `capacitador_id` bigint UNSIGNED NOT NULL,
  `empresa_capacitacion_id` bigint UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_termino` time NOT NULL,
  `responsable_registro_id` bigint UNSIGNED NOT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `numero_planilla` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `capacitacion`
--

INSERT INTO `capacitacion` (`id`, `empresa_id`, `activo`, `created_at`, `updated_at`, `tema_capacitacion_id`, `lugar_capacitacion_id`, `capacitador_id`, `empresa_capacitacion_id`, `fecha`, `hora_inicio`, `hora_termino`, `responsable_registro_id`, `observaciones`, `numero_planilla`) VALUES
(1, 1, 1, '2026-10-24 02:52:09', '2026-10-24 02:52:09', 1, 1, 1, 1, '2026-10-23', '10:00:00', '12:00:00', 1, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `capacitacion_formatos`
--

CREATE TABLE `capacitacion_formatos` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `diseno` json NOT NULL,
  `version` int UNSIGNED NOT NULL DEFAULT '1',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `capacitacion_formatos`
--

INSERT INTO `capacitacion_formatos` (`id`, `empresa_id`, `nombre`, `diseno`, `version`, `activo`, `created_at`, `updated_at`) VALUES
(1, 1, 'informa capacitaciones jaguar', '{\"html\": \"<h1 style=\\\"text-align:center\\\">INFORME DE CAPACITACIONES JAGUAR</h1>\\n<p style=\\\"text-align:center\\\"><b>Registro de capacitación y asistencia de trabajadores</b></p>\\n<h2>1. Datos generales de la capacitación</h2>\\n<table><tbody>\\n<tr><th>Empresa</th><td>{{empresa.razon_social}}</td><th>RUC</th><td>{{empresa.identificacion_fiscal}}</td></tr>\\n<tr><th>N.º de planilla</th><td>{{planilla.numero_planilla}}</td><th>Fecha</th><td>{{planilla.fecha}}</td></tr>\\n<tr><th>Hora de inicio</th><td>{{planilla.hora_inicio}}</td><th>Hora de término</th><td>{{planilla.hora_termino}}</td></tr>\\n<tr><th>Lugar</th><td>{{lugar.nombre}}</td><th>Dirección</th><td>{{lugar.direccion}}</td></tr>\\n<tr><th>Tema</th><td colspan=\\\"3\\\">{{tema.nombre}}</td></tr>\\n<tr><th>Expositor</th><td>{{expositor.nombre}}</td><th>Documento</th><td>{{expositor.numero_documento}}</td></tr>\\n<tr><th>Empresa expositora</th><td>{{empresa_expositora.nombre}}</td><th>RUC / documento</th><td>{{empresa_expositora.numero_documento}}</td></tr>\\n<tr><th>Responsable del registro</th><td>{{responsable.nombre}}</td><th>Correo</th><td>{{responsable.email}}</td></tr>\\n<tr><th>Estado de la planilla</th><td colspan=\\\"3\\\">{{planilla.activo}}</td></tr>\\n</tbody></table>\\n<h2>2. Objetivo y contenido</h2>\\n<p><b>Objetivo general:</b> Fortalecer los conocimientos de los trabajadores sobre el tema registrado y su aplicación en las actividades de trabajo.</p>\\n<p><b>Descripción del tema y contenido:</b></p><p>{{tema.descripcion}}</p>\\n<h2>3. Asistencia y firmas de los trabajadores</h2>\\n<p><b>Total de participantes:</b> {{participantes.total}} · <b>Firmas registradas:</b> {{participantes.firmados}}</p>\\n<p>La relación siguiente corresponde a los participantes activos de esta planilla. Se muestran las firmas registradas para esta capacitación; los registros pendientes se identifican como «Sin firma».</p>\\n<div>{{participantes.tabla}}</div>\\n<p>El registro de asistencia no constituye por sí solo una evaluación de aprendizaje.</p>\\n<h2>4. Desarrollo, observaciones y seguimiento</h2>\\n<p>{{planilla.observaciones}}</p>\\n<p>Registrar en las observaciones de la planilla la metodología utilizada, las incidencias, los resultados de evaluación cuando correspondan y los compromisos con sus responsables y fechas.</p>\\n<h2>5. Revisión del informe</h2>\\n<p><b>Elaborado por:</b> {{responsable.nombre}}</p>\\n<table><tbody><tr><th>Expositor / capacitador</th><th>Responsable de revisión</th></tr><tr><td>{{expositor.nombre}}<br><br>Firma: ____________________<br>Fecha: ____________________</td><td>Nombre: ____________________<br><br>Firma: ____________________<br>Fecha: ____________________</td></tr></tbody></table>\", \"fuente\": 10, \"margen\": 15, \"detalle\": \"tabla\", \"columnas\": [\"numero\", \"numero_documento\", \"apellidos\", \"nombres\", \"puesto\", \"firma\", \"firma_fecha\"], \"orientacion\": \"horizontal\"}', 1, 1, '2026-10-26 14:53:32', '2026-10-26 14:53:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `capacitacion_participantes`
--

CREATE TABLE `capacitacion_participantes` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `capacitacion_id` bigint UNSIGNED NOT NULL,
  `trabajador_id` bigint UNSIGNED NOT NULL,
  `firma_ruta` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firma_bytes` bigint UNSIGNED NOT NULL DEFAULT '0',
  `firma_fecha` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `capacitacion_participantes`
--

INSERT INTO `capacitacion_participantes` (`id`, `empresa_id`, `activo`, `created_at`, `updated_at`, `capacitacion_id`, `trabajador_id`, `firma_ruta`, `firma_bytes`, `firma_fecha`) VALUES
(1, 1, 1, '2026-10-24 02:52:18', '2026-10-24 02:52:28', 1, 1, '1/1/1/230837cf-c935-4a26-957a-e631c16e8608/71462409.png', 11859, '2026-10-24 02:52:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `capacitador`
--

CREATE TABLE `capacitador` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `numero_documento` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `capacitador`
--

INSERT INTO `capacitador` (`id`, `empresa_id`, `activo`, `created_at`, `updated_at`, `nombre`, `descripcion`, `numero_documento`, `email`, `telefono`) VALUES
(1, 1, 1, '2026-10-24 02:51:18', '2026-10-24 02:51:18', 'JOSE REYES', 'CAPACITADOR', '71462098', 'REYES@GMAIL.COM', '98766788');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklist_evidencias`
--

CREATE TABLE `checklist_evidencias` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `respuesta_id` bigint UNSIGNED NOT NULL,
  `archivo_id` bigint UNSIGNED NOT NULL,
  `tipo` enum('fotografia','archivo','firma') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fotografia',
  `orden` int UNSIGNED NOT NULL DEFAULT '1',
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklist_plantillas`
--

CREATE TABLE `checklist_plantillas` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `instrucciones` text COLLATE utf8mb4_unicode_ci,
  `version` int UNSIGNED NOT NULL DEFAULT '1',
  `estado` enum('borrador','publicado','retirado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklist_preguntas`
--

CREATE TABLE `checklist_preguntas` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `plantilla_id` bigint UNSIGNED NOT NULL,
  `seccion_id` bigint UNSIGNED NOT NULL,
  `codigo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ayuda` text COLLATE utf8mb4_unicode_ci,
  `placeholder` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pregunta` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('cumplimiento','texto','texto_largo','numero','fecha','fecha_hora','selector','seleccion_multiple','si_no','fotografia','archivo','firma') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cumplimiento',
  `obligatoria` tinyint(1) NOT NULL DEFAULT '1',
  `orden` int UNSIGNED NOT NULL DEFAULT '1',
  `longitud_min` int UNSIGNED DEFAULT NULL,
  `longitud_max` int UNSIGNED DEFAULT NULL,
  `numero_min` decimal(18,4) DEFAULT NULL,
  `numero_max` decimal(18,4) DEFAULT NULL,
  `fecha_min` date DEFAULT NULL,
  `fecha_max` date DEFAULT NULL,
  `seleccion_min` int UNSIGNED DEFAULT NULL,
  `seleccion_max` int UNSIGNED DEFAULT NULL,
  `archivos_min` int UNSIGNED DEFAULT NULL,
  `archivos_max` int UNSIGNED DEFAULT NULL,
  `archivo_max_bytes` bigint UNSIGNED DEFAULT NULL,
  `mime_permitidos` json DEFAULT NULL,
  `peso` decimal(10,2) NOT NULL DEFAULT '1.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklist_pregunta_opciones`
--

CREATE TABLE `checklist_pregunta_opciones` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `pregunta_id` bigint UNSIGNED NOT NULL,
  `valor` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `etiqueta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` int UNSIGNED NOT NULL DEFAULT '1',
  `resultado` enum('informativo','cumple','no_cumple','no_aplica') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'informativo',
  `exige_observacion` tinyint(1) NOT NULL DEFAULT '0',
  `exige_evidencia` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklist_registros`
--

CREATE TABLE `checklist_registros` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `plantilla_id` bigint UNSIGNED NOT NULL,
  `area_id` bigint UNSIGNED DEFAULT NULL,
  `realizado_por` bigint UNSIGNED NOT NULL,
  `uuid_cliente` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `fecha` datetime NOT NULL,
  `estado` enum('borrador','enviado','revisado','anulado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklist_respuestas`
--

CREATE TABLE `checklist_respuestas` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `registro_id` bigint UNSIGNED NOT NULL,
  `plantilla_id` bigint UNSIGNED NOT NULL,
  `pregunta_id` bigint UNSIGNED NOT NULL,
  `cumplimiento` enum('cumple','no_cumple','no_aplica') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_texto` text COLLATE utf8mb4_unicode_ci,
  `valor_numero` decimal(18,4) DEFAULT NULL,
  `valor_fecha` date DEFAULT NULL,
  `valor_fecha_hora` datetime DEFAULT NULL,
  `valor_booleano` tinyint(1) DEFAULT NULL,
  `observacion` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklist_respuesta_opciones`
--

CREATE TABLE `checklist_respuesta_opciones` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `respuesta_id` bigint UNSIGNED NOT NULL,
  `pregunta_id` bigint UNSIGNED NOT NULL,
  `opcion_id` bigint UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklist_secciones`
--

CREATE TABLE `checklist_secciones` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `plantilla_id` bigint UNSIGNED NOT NULL,
  `titulo` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `orden` int UNSIGNED NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contratos`
--

CREATE TABLE `contratos` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `numero` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('prueba','activo','suspendido','terminado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'prueba',
  `limite_usuarios` int UNSIGNED DEFAULT NULL,
  `limite_almacenamiento_mb` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Volcado de datos para la tabla `contratos`
--

INSERT INTO `contratos` (`id`, `empresa_id`, `numero`, `fecha_inicio`, `fecha_fin`, `estado`, `limite_usuarios`, `limite_almacenamiento_mb`, `created_at`, `updated_at`) VALUES
(1, 1, 'INICIAL', '2026-09-25', '2027-10-20', 'prueba', NULL, NULL, '2026-09-25 21:07:43', '2026-10-26 09:37:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documento_revisiones`
--

CREATE TABLE `documento_revisiones` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `documento_version_id` bigint UNSIGNED NOT NULL,
  `revisor_id` bigint UNSIGNED NOT NULL,
  `resultado` enum('aprobado','observado','rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'observado',
  `comentario` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documento_versiones`
--

CREATE TABLE `documento_versiones` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `requisito_id` bigint UNSIGNED NOT NULL,
  `archivo_id` bigint UNSIGNED NOT NULL,
  `version` int UNSIGNED NOT NULL,
  `fecha_emision` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `estado` enum('pendiente','aprobado','observado','rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `revisado_por` bigint UNSIGNED DEFAULT NULL,
  `revisado_at` datetime DEFAULT NULL,
  `comentario_revision` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas`
--

CREATE TABLE `empresas` (
  `id` bigint UNSIGNED NOT NULL,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `razon_social` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_comercial` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pais` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identificacion_fiscal` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zona_horaria` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'America/Lima',
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `empresas`
--

INSERT INTO `empresas` (`id`, `codigo`, `razon_social`, `nombre_comercial`, `pais`, `identificacion_fiscal`, `zona_horaria`, `direccion`, `contacto_email`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'Avanti', 'SOCIEDAD AGRICOLA AVANTI', 'Agricola Avanti', 'PE', 'DEMO-JAGUAR-001', 'America/Lima', NULL, 'contacto@avanti.com', 1, '2026-09-25 21:07:43', '2026-10-03 21:01:17');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresa_capacitacion`
--

CREATE TABLE `empresa_capacitacion` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `numero_documento` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `empresa_capacitacion`
--

INSERT INTO `empresa_capacitacion` (`id`, `empresa_id`, `activo`, `created_at`, `updated_at`, `nombre`, `descripcion`, `numero_documento`, `email`, `telefono`) VALUES
(1, 1, 1, '2026-10-24 02:51:38', '2026-10-24 02:51:38', 'GASSI PERU', NULL, '1230\'9', 'GASSI@GMAIL.COM', '1239182');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresa_modulos`
--

CREATE TABLE `empresa_modulos` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `modulo_id` bigint UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `empresa_modulos`
--

INSERT INTO `empresa_modulos` (`id`, `empresa_id`, `modulo_id`, `activo`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(3, 1, 2, 1, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(4, 1, 4, 1, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(5, 1, 5, 1, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(6, 1, 6, 1, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(7, 1, 25, 1, '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(8, 1, 24, 1, '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(9, 1, 26, 1, '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(10, 1, 27, 1, '2026-10-04 02:20:34', '2026-10-04 02:20:34'),
(11, 1, 28, 1, '2026-10-08 01:34:51', '2026-10-08 01:34:51'),
(12, 1, 29, 1, '2026-10-26 15:29:32', '2026-10-26 15:29:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `epp_catalogo`
--

CREATE TABLE `epp_catalogo` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `talla` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UNICA',
  `unidad` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unidad',
  `vida_util_dias` int UNSIGNED DEFAULT NULL,
  `ficha_archivo_id` bigint UNSIGNED DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `epp_catalogo`
--

INSERT INTO `epp_catalogo` (`id`, `empresa_id`, `codigo`, `nombre`, `talla`, `unidad`, `vida_util_dias`, `ficha_archivo_id`, `activo`, `created_at`, `updated_at`) VALUES
(1, 1, 'L01', 'LENTES NEGRO', 'UNICA', 'par', 30, NULL, 1, '2026-10-08 02:09:54', '2026-10-08 02:09:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `epp_compras`
--

CREATE TABLE `epp_compras` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `clave_operacion` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `huella` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proveedor` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proveedor_ruc` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proveedor_clave` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `factura` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `moneda` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PEN',
  `costo_total` decimal(24,6) NOT NULL,
  `registrado_por` bigint UNSIGNED NOT NULL,
  `observacion` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tipo_cambio` decimal(12,6) NOT NULL DEFAULT '1.000000',
  `costo_total_original` decimal(24,6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `epp_compras`
--

INSERT INTO `epp_compras` (`id`, `empresa_id`, `clave_operacion`, `huella`, `proveedor`, `proveedor_ruc`, `proveedor_clave`, `factura`, `fecha`, `moneda`, `costo_total`, `registrado_por`, `observacion`, `created_at`, `updated_at`, `tipo_cambio`, `costo_total_original`) VALUES
(1, 1, 'e4a7a1d0-43fc-4e94-a11c-20a041e75386', 'd6f13b644741e944a45698d2d8f931779ef6bc81c92cafc2e55fdb018456000b', 'PEPITO', '12123123898', '12123123898', '23', '2026-10-07', 'PEN', 450.000000, 1, NULL, '2026-10-08 02:11:02', '2026-10-08 02:11:02', 1.000000, 450.000000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `epp_compra_detalles`
--

CREATE TABLE `epp_compra_detalles` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `compra_id` bigint UNSIGNED NOT NULL,
  `epp_id` bigint UNSIGNED NOT NULL,
  `almacen_id` bigint UNSIGNED NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `costo_unitario` decimal(18,6) NOT NULL,
  `costo_total` decimal(24,6) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `costo_unitario_original` decimal(18,6) DEFAULT NULL,
  `costo_total_original` decimal(24,6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `epp_compra_detalles`
--

INSERT INTO `epp_compra_detalles` (`id`, `empresa_id`, `compra_id`, `epp_id`, `almacen_id`, `cantidad`, `costo_unitario`, `costo_total`, `created_at`, `updated_at`, `costo_unitario_original`, `costo_total_original`) VALUES
(1, 1, 1, 1, 1, 300.000, 1.500000, 450.000000, '2026-10-08 02:11:02', '2026-10-08 02:11:02', 1.500000, 450.000000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `epp_entregas`
--

CREATE TABLE `epp_entregas` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `uuid_cliente` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `trabajador_id` bigint UNSIGNED NOT NULL,
  `entregado_por` bigint UNSIGNED NOT NULL,
  `fecha` datetime NOT NULL,
  `estado` enum('borrador','confirmada','anulada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `firma_archivo_id` bigint UNSIGNED DEFAULT NULL,
  `recibido_at` datetime DEFAULT NULL,
  `observacion` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `motivo` enum('nuevo','reposicion') COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `epp_entregas`
--

INSERT INTO `epp_entregas` (`id`, `empresa_id`, `uuid_cliente`, `trabajador_id`, `entregado_por`, `fecha`, `estado`, `firma_archivo_id`, `recibido_at`, `observacion`, `created_at`, `updated_at`, `motivo`) VALUES
(1, 1, '4c56f569-7726-42df-a21f-d28962fc3c3c', 1, 1, '2026-10-10 16:17:00', 'confirmada', NULL, NULL, NULL, '2026-10-10 16:17:51', '2026-10-10 16:17:51', 'nuevo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `epp_entrega_detalles`
--

CREATE TABLE `epp_entrega_detalles` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `entrega_id` bigint UNSIGNED NOT NULL,
  `epp_id` bigint UNSIGNED NOT NULL,
  `almacen_id` bigint UNSIGNED NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `fecha_reposicion` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `costo_unitario` decimal(18,6) DEFAULT NULL,
  `costo_total` decimal(24,6) DEFAULT NULL
) ;

--
-- Volcado de datos para la tabla `epp_entrega_detalles`
--

INSERT INTO `epp_entrega_detalles` (`id`, `empresa_id`, `entrega_id`, `epp_id`, `almacen_id`, `cantidad`, `fecha_reposicion`, `created_at`, `updated_at`, `costo_unitario`, `costo_total`) VALUES
(1, 1, 1, 1, 1, 20.000, NULL, '2026-10-10 16:17:51', '2026-10-10 16:17:51', 1.500000, 30.000000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `epp_entrega_lotes`
--

CREATE TABLE `epp_entrega_lotes` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `uuid_cliente` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `huella` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` int UNSIGNED NOT NULL,
  `entregas` json NOT NULL,
  `created_at` timestamp NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `epp_movimientos`
--

CREATE TABLE `epp_movimientos` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `epp_id` bigint UNSIGNED NOT NULL,
  `almacen_id` bigint UNSIGNED NOT NULL,
  `entrega_detalle_id` bigint UNSIGNED DEFAULT NULL,
  `registrado_por` bigint UNSIGNED NOT NULL,
  `fecha` datetime NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `tipo` enum('entrada','entrega','devolucion','ajuste','reverso') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'entrada',
  `clave_operacion` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacion` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `compra_detalle_id` bigint UNSIGNED DEFAULT NULL,
  `costo_unitario` decimal(18,6) DEFAULT NULL,
  `costo_total` decimal(24,6) DEFAULT NULL,
  `saldo_valor` decimal(24,6) DEFAULT NULL
) ;

--
-- Volcado de datos para la tabla `epp_movimientos`
--

INSERT INTO `epp_movimientos` (`id`, `empresa_id`, `epp_id`, `almacen_id`, `entrega_detalle_id`, `registrado_por`, `fecha`, `cantidad`, `tipo`, `clave_operacion`, `observacion`, `created_at`, `updated_at`, `compra_detalle_id`, `costo_unitario`, `costo_total`, `saldo_valor`) VALUES
(1, 1, 1, 1, NULL, 1, '2026-10-08 02:11:02', 300.000, 'entrada', 'compra:1:0', 'Compra 23 · PEPITO', '2026-10-08 02:11:02', '2026-10-08 02:11:02', 1, 1.500000, 450.000000, 450.000000),
(2, 1, 1, 1, 1, 1, '2026-10-10 16:17:51', -20.000, 'entrega', 'entrega:1:1:confirmada', NULL, '2026-10-10 16:17:51', '2026-10-10 16:17:51', NULL, 1.500000, -30.000000, 420.000000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `epp_planillas`
--

CREATE TABLE `epp_planillas` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `numero` bigint UNSIGNED NOT NULL,
  `uuid_cliente` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `zona` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `registrado_por` bigint UNSIGNED DEFAULT NULL,
  `responsable` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trabajadores` int UNSIGNED NOT NULL,
  `entregas` json NOT NULL,
  `created_at` timestamp NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `epp_planillas`
--

INSERT INTO `epp_planillas` (`id`, `empresa_id`, `numero`, `uuid_cliente`, `fecha`, `zona`, `registrado_por`, `responsable`, `trabajadores`, `entregas`, `created_at`) VALUES
(1, 1, 1, '4c56f569-7726-42df-a21f-d28962fc3c3c', '2026-10-10', 'Zona general', 1, 'Administrador', 1, '[1]', '2026-10-14 13:58:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hallazgos`
--

CREATE TABLE `hallazgos` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `uuid_cliente` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `area_id` bigint UNSIGNED DEFAULT NULL,
  `respuesta_id` bigint UNSIGNED DEFAULT NULL,
  `reportado_por` bigint UNSIGNED NOT NULL,
  `responsable_id` bigint UNSIGNED DEFAULT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` datetime NOT NULL,
  `severidad` enum('baja','media','alta','critica') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'media',
  `estado` enum('abierto','en_proceso','por_validar','cerrado','anulado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierto',
  `fecha_limite` date DEFAULT NULL,
  `cerrado_at` datetime DEFAULT NULL,
  `cerrado_por` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hallazgo_evidencias`
--

CREATE TABLE `hallazgo_evidencias` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `hallazgo_id` bigint UNSIGNED NOT NULL,
  `archivo_id` bigint UNSIGNED NOT NULL,
  `tipo` enum('deteccion','levantamiento','cierre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'deteccion',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hallazgo_seguimientos`
--

CREATE TABLE `hallazgo_seguimientos` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `hallazgo_id` bigint UNSIGNED NOT NULL,
  `autor_id` bigint UNSIGNED NOT NULL,
  `comentario` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado_resultante` enum('abierto','en_proceso','por_validar','cerrado','anulado') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `implementos`
--

CREATE TABLE `implementos` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `catalogo_id` bigint UNSIGNED NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `marca` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serie` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_adquisicion` date DEFAULT NULL,
  `estado` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disponible',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `proveedor_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `implementos_catalogo`
--

CREATE TABLE `implementos_catalogo` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `periodicidad_cantidad` int UNSIGNED NOT NULL,
  `periodicidad_unidad` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `categoria_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `implementos_catalogo`
--

INSERT INTO `implementos_catalogo` (`id`, `empresa_id`, `codigo`, `nombre`, `categoria`, `descripcion`, `periodicidad_cantidad`, `periodicidad_unidad`, `activo`, `created_at`, `updated_at`, `categoria_id`) VALUES
(1, 1, '1', 'Tipo A', 'Extintor', 'Extintor para fuegos de tipo A, solidos combustibles', 1, 'meses', 1, '2026-10-27 15:26:55', '2026-10-27 15:26:55', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `implementos_categorias`
--

CREATE TABLE `implementos_categorias` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `implementos_categorias`
--

INSERT INTO `implementos_categorias` (`id`, `empresa_id`, `nombre`, `descripcion`, `activo`, `created_at`, `updated_at`) VALUES
(1, 1, 'Extintor', 'Extintores', 1, '2026-10-27 15:21:40', '2026-10-27 15:21:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `implementos_proveedores`
--

CREATE TABLE `implementos_proveedores` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `nombre` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `implemento_archivos`
--

CREATE TABLE `implemento_archivos` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `implemento_id` bigint UNSIGNED NOT NULL,
  `inspeccion_id` bigint UNSIGNED DEFAULT NULL,
  `archivo_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `implemento_costos`
--

CREATE TABLE `implemento_costos` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `implemento_id` bigint UNSIGNED NOT NULL,
  `proveedor_id` bigint UNSIGNED NOT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `comprobante` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moneda` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `importe` decimal(16,2) NOT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `implemento_inspecciones`
--

CREATE TABLE `implemento_inspecciones` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `implemento_id` bigint UNSIGNED NOT NULL,
  `instalacion_id` bigint UNSIGNED NOT NULL,
  `fecha_programada` date NOT NULL,
  `fecha_realizada` date DEFAULT NULL,
  `responsable_id` bigint UNSIGNED DEFAULT NULL,
  `realizado_por` bigint UNSIGNED DEFAULT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `resultado` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `periodicidad_cantidad` int UNSIGNED NOT NULL,
  `periodicidad_unidad` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `implemento_instalaciones`
--

CREATE TABLE `implemento_instalaciones` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `implemento_id` bigint UNSIGNED NOT NULL,
  `area_id` bigint UNSIGNED NOT NULL,
  `ubicacion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `piso` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `fecha_instalacion` date NOT NULL,
  `fecha_renovacion` date DEFAULT NULL,
  `fecha_retiro` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `indicadores`
--

CREATE TABLE `indicadores` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `tema_id` bigint UNSIGNED DEFAULT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `unidad` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_calculo` enum('manual','razon') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `factor` decimal(16,4) NOT NULL DEFAULT '100.0000',
  `sentido` enum('mayor_mejor','menor_mejor') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mayor_mejor',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `indicador_mediciones`
--

CREATE TABLE `indicador_mediciones` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `indicador_id` bigint UNSIGNED NOT NULL,
  `periodo` date NOT NULL,
  `numerador` decimal(18,4) DEFAULT NULL,
  `denominador` decimal(18,4) DEFAULT NULL,
  `valor_manual` decimal(18,4) DEFAULT NULL,
  `meta` decimal(18,4) NOT NULL,
  `registrado_por` bigint UNSIGNED NOT NULL,
  `evidencia_archivo_id` bigint UNSIGNED DEFAULT NULL,
  `comentario` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lugar_capacitacion`
--

CREATE TABLE `lugar_capacitacion` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `lugar_capacitacion`
--

INSERT INTO `lugar_capacitacion` (`id`, `empresa_id`, `activo`, `created_at`, `updated_at`, `nombre`, `descripcion`, `direccion`) VALUES
(1, 1, 1, '2026-10-24 02:50:49', '2026-10-24 02:50:49', 'ADMINISTRACION', 'ADMINISTRACION', 'AVANTI');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `miembros_empresa`
--

CREATE TABLE `miembros_empresa` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `usuario_id` bigint UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `miembros_empresa`
--

INSERT INTO `miembros_empresa` (`id`, `empresa_id`, `usuario_id`, `activo`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(2, 1, 2, 1, '2026-10-04 03:13:20', '2026-10-04 03:13:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000001_create_cache_table', 1),
(2, '2026_10_04_021648_consolidate_hse_modules', 2),
(3, '2026_10_04_021951_add_capacitaciones_module', 3),
(4, '2026_10_04_022135_synchronize_hse_permissions_and_roles', 4),
(5, '2026_10_04_030326_simplify_hse_permission_actions', 5),
(6, '2026_10_08_013329_add_almacenes_module', 6),
(7, '2026_10_08_015026_add_epp_purchases_and_valuation', 7),
(8, '2026_10_08_015720_add_currency_to_epp_purchases', 8),
(9, '2026_10_08_015908_add_worker_epp_cost_report', 9),
(10, '2026_10_08_020359_add_epp_movement_detail_view', 10),
(11, '2026_10_09_173241_add_zones_and_site_countries', 11),
(12, '2026_10_09_174218_add_zone_to_workers', 12),
(13, '2026_10_09_174837_create_worker_assignments', 13),
(16, '2026_10_09_200812_add_signature_to_workers', 15),
(17, '2026_10_09_201511_add_reason_to_epp_deliveries', 16),
(18, '2026_10_09_201923_create_epp_delivery_batches_table', 17),
(19, '2026_10_14_135511_create_epp_planillas_table', 18),
(20, '2026_10_24_011304_create_training_tables', 19),
(21, '2026_10_24_012905_add_planilla_number_to_capacitacion', 20),
(22, '2026_10_24_013635_add_signature_to_training_participants', 21),
(23, '2026_10_24_205617_create_capacitacion_formatos_table', 22),
(24, '2026_10_26_152256_create_safety_implements_module', 23),
(25, '2026_10_27_151909_create_safety_implement_categories', 24),
(26, '2026_10_27_152826_add_supplier_to_safety_implements', 25);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modulos`
--

CREATE TABLE `modulos` (
  `id` bigint UNSIGNED NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `modulos`
--

INSERT INTO `modulos` (`id`, `codigo`, `nombre`, `created_at`, `updated_at`) VALUES
(1, 'administracion', 'Administración', '2026-09-21 21:52:43', '2026-10-04 02:19:09'),
(2, 'documentos', 'Documentos', '2026-09-21 21:52:43', '2026-10-04 02:19:09'),
(4, 'epp', 'EPP', '2026-09-21 21:52:43', '2026-10-04 02:19:09'),
(5, 'hallazgos', 'Hallazgos', '2026-09-21 21:52:43', '2026-10-04 02:19:09'),
(6, 'indicadores', 'Indicadores', '2026-09-21 21:52:43', '2026-10-04 02:19:09'),
(24, 'gestion_sst', 'Gestión SST', '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(25, 'organizacion', 'Organización', '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(26, 'inspecciones', 'Inspecciones', '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(27, 'capacitaciones', 'Capacitaciones', '2026-10-04 02:20:34', '2026-10-04 02:20:34'),
(28, 'almacenes', 'Almacenes', '2026-10-08 01:34:51', '2026-10-08 01:34:51'),
(29, 'implementos', 'Gestión de implementos de seguridad', '2026-10-26 15:29:32', '2026-10-26 15:29:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id` bigint UNSIGNED NOT NULL,
  `modulo_id` bigint UNSIGNED NOT NULL,
  `codigo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `descripcion`, `created_at`, `updated_at`) VALUES
(4, 1, 'administracion.editar', 'Editar · Administración', '2026-09-21 21:52:43', '2026-10-04 02:23:00'),
(5, 1, 'administracion.crear', 'Crear · Administración', '2026-09-21 21:52:43', '2026-10-04 02:23:00'),
(6, 1, 'administracion.ver', 'Consultar · Administración', '2026-09-21 21:52:43', '2026-10-04 03:08:53'),
(10, 2, 'documentos.editar', 'Editar · Documentos', '2026-09-21 21:52:43', '2026-10-04 02:23:00'),
(11, 2, 'documentos.crear', 'Crear · Documentos', '2026-09-21 21:52:43', '2026-10-04 02:23:00'),
(12, 2, 'documentos.ver', 'Consultar · Documentos', '2026-09-21 21:52:43', '2026-10-04 03:08:53'),
(22, 4, 'epp.editar', 'Editar · EPP', '2026-09-21 21:52:43', '2026-10-04 02:23:00'),
(23, 4, 'epp.crear', 'Crear · EPP', '2026-09-21 21:52:43', '2026-10-04 02:23:00'),
(24, 4, 'epp.ver', 'Consultar · EPP', '2026-09-21 21:52:43', '2026-10-04 03:08:53'),
(28, 5, 'hallazgos.editar', 'Editar · Hallazgos', '2026-09-21 21:52:43', '2026-10-04 02:23:00'),
(29, 5, 'hallazgos.crear', 'Crear · Hallazgos', '2026-09-21 21:52:43', '2026-10-04 02:23:00'),
(30, 5, 'hallazgos.ver', 'Consultar · Hallazgos', '2026-09-21 21:52:43', '2026-10-04 03:08:53'),
(34, 6, 'indicadores.editar', 'Editar · Indicadores', '2026-09-21 21:52:43', '2026-10-04 02:23:00'),
(35, 6, 'indicadores.crear', 'Crear · Indicadores', '2026-09-21 21:52:43', '2026-10-04 02:23:00'),
(36, 6, 'indicadores.ver', 'Consultar · Indicadores', '2026-09-21 21:52:43', '2026-10-04 03:08:53'),
(279, 2, 'documentos.confidenciales', 'Acceder a documentos confidenciales · Documentos', '2026-09-25 21:07:44', '2026-10-04 03:08:53'),
(307, 25, 'organizacion.editar', 'Editar · Organización', '2026-10-04 02:19:09', '2026-10-04 02:23:00'),
(308, 25, 'organizacion.crear', 'Crear · Organización', '2026-10-04 02:19:09', '2026-10-04 02:23:00'),
(309, 25, 'organizacion.ver', 'Consultar · Organización', '2026-10-04 02:19:09', '2026-10-04 03:08:53'),
(318, 24, 'gestion_sst.editar', 'Editar · Gestión SST', '2026-10-04 02:19:09', '2026-10-04 02:23:00'),
(319, 24, 'gestion_sst.crear', 'Crear · Gestión SST', '2026-10-04 02:19:09', '2026-10-04 02:23:00'),
(320, 24, 'gestion_sst.ver', 'Consultar · Gestión SST', '2026-10-04 02:19:09', '2026-10-04 03:08:53'),
(329, 26, 'inspecciones.editar', 'Editar · Inspecciones', '2026-10-04 02:19:09', '2026-10-04 02:23:00'),
(330, 26, 'inspecciones.crear', 'Crear · Inspecciones', '2026-10-04 02:19:09', '2026-10-04 02:23:00'),
(331, 26, 'inspecciones.ver', 'Consultar · Inspecciones', '2026-10-04 02:19:09', '2026-10-04 03:08:53'),
(503, 27, 'capacitaciones.ver', 'Consultar · Capacitaciones', '2026-10-04 02:20:34', '2026-10-04 03:08:53'),
(504, 27, 'capacitaciones.crear', 'Crear · Capacitaciones', '2026-10-04 02:20:34', '2026-10-04 02:23:00'),
(505, 27, 'capacitaciones.editar', 'Editar · Capacitaciones', '2026-10-04 02:20:34', '2026-10-04 02:23:00'),
(511, 1, 'administracion.eliminar', 'Eliminar · Administración', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(512, 1, 'administracion.descargar', 'Descargar informes · Administración', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(513, 2, 'documentos.eliminar', 'Eliminar · Documentos', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(514, 2, 'documentos.descargar', 'Descargar informes · Documentos', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(515, 6, 'indicadores.eliminar', 'Eliminar · Indicadores', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(516, 6, 'indicadores.descargar', 'Descargar informes · Indicadores', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(517, 4, 'epp.eliminar', 'Eliminar · EPP', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(518, 4, 'epp.descargar', 'Descargar informes · EPP', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(519, 5, 'hallazgos.eliminar', 'Eliminar · Hallazgos', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(520, 5, 'hallazgos.descargar', 'Descargar informes · Hallazgos', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(521, 24, 'gestion_sst.eliminar', 'Eliminar · Gestión SST', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(522, 24, 'gestion_sst.descargar', 'Descargar informes · Gestión SST', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(523, 25, 'organizacion.eliminar', 'Eliminar · Organización', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(524, 25, 'organizacion.descargar', 'Descargar informes · Organización', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(525, 26, 'inspecciones.eliminar', 'Eliminar · Inspecciones', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(526, 26, 'inspecciones.descargar', 'Descargar informes · Inspecciones', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(527, 27, 'capacitaciones.eliminar', 'Eliminar · Capacitaciones', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(528, 27, 'capacitaciones.descargar', 'Descargar informes · Capacitaciones', '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(529, 28, 'almacenes.ver', 'Consultar · Almacenes', '2026-10-08 01:34:51', '2026-10-08 01:34:51'),
(530, 28, 'almacenes.crear', 'Crear · Almacenes', '2026-10-08 01:34:51', '2026-10-08 01:34:51'),
(531, 28, 'almacenes.editar', 'Editar · Almacenes', '2026-10-08 01:34:51', '2026-10-08 01:34:51'),
(532, 28, 'almacenes.eliminar', 'Eliminar · Almacenes', '2026-10-08 01:34:51', '2026-10-08 01:34:51'),
(533, 28, 'almacenes.descargar', 'Descargar informes · Almacenes', '2026-10-08 01:34:51', '2026-10-08 01:34:51'),
(534, 29, 'implementos.ver', 'ver implementos de seguridad', '2026-10-26 15:29:32', '2026-10-26 15:29:32'),
(535, 29, 'implementos.crear', 'crear implementos de seguridad', '2026-10-26 15:29:32', '2026-10-26 15:29:32'),
(536, 29, 'implementos.editar', 'editar implementos de seguridad', '2026-10-26 15:29:32', '2026-10-26 15:29:32'),
(537, 29, 'implementos.eliminar', 'eliminar implementos de seguridad', '2026-10-26 15:29:32', '2026-10-26 15:29:32'),
(538, 29, 'implementos.descargar', 'descargar implementos de seguridad', '2026-10-26 15:29:32', '2026-10-26 15:29:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plataformas`
--

CREATE TABLE `plataformas` (
  `id` bigint UNSIGNED NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `plataformas`
--

INSERT INTO `plataformas` (`id`, `codigo`, `nombre`, `created_at`, `updated_at`) VALUES
(1, 'web', 'Aplicacion web', '2026-09-21 21:52:43', '2026-09-21 21:52:43'),
(2, 'movil', 'Aplicacion movil', '2026-09-21 21:52:43', '2026-09-21 21:52:43');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `requisitos_documentales`
--

CREATE TABLE `requisitos_documentales` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `tema_id` bigint UNSIGNED NOT NULL,
  `nombre` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `obligatorio` tinyint(1) NOT NULL DEFAULT '1',
  `requiere_vencimiento` tinyint(1) NOT NULL DEFAULT '0',
  `peso` decimal(10,2) NOT NULL DEFAULT '1.00',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `empresa_id`, `nombre`, `descripcion`, `activo`, `created_at`, `updated_at`) VALUES
(1, 1, 'Administrador', 'Administración de los módulos habilitados para la empresa.', 1, '2026-09-25 21:07:44', '2026-10-04 02:23:00'),
(2, 1, 'Gestion Organizacion', 'Usuario podra realizar todas las acciones para el modulo de organizacion', 1, '2026-10-04 03:12:21', '2026-10-04 03:12:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_permisos`
--

CREATE TABLE `rol_permisos` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `rol_id` bigint UNSIGNED NOT NULL,
  `permiso_id` bigint UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `rol_permisos`
--

INSERT INTO `rol_permisos` (`id`, `empresa_id`, `rol_id`, `permiso_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 6, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(2, 1, 1, 5, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(3, 1, 1, 4, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(17, 1, 1, 12, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(18, 1, 1, 11, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(19, 1, 1, 10, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(24, 1, 1, 279, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(25, 1, 1, 24, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(26, 1, 1, 23, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(27, 1, 1, 22, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(33, 1, 1, 30, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(34, 1, 1, 29, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(35, 1, 1, 28, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(41, 1, 1, 36, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(42, 1, 1, 35, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(43, 1, 1, 34, '2026-09-25 21:07:44', '2026-09-25 21:07:44'),
(49, 1, 1, 307, '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(50, 1, 1, 308, '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(51, 1, 1, 309, '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(57, 1, 1, 318, '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(58, 1, 1, 319, '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(59, 1, 1, 320, '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(65, 1, 1, 329, '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(66, 1, 1, 330, '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(67, 1, 1, 331, '2026-10-04 02:19:09', '2026-10-04 02:19:09'),
(73, 1, 1, 503, '2026-10-04 02:20:34', '2026-10-04 02:20:34'),
(74, 1, 1, 504, '2026-10-04 02:20:34', '2026-10-04 02:20:34'),
(75, 1, 1, 505, '2026-10-04 02:20:34', '2026-10-04 02:20:34'),
(155, 1, 1, 511, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(159, 1, 1, 517, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(163, 1, 1, 525, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(168, 1, 1, 512, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(173, 1, 1, 513, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(174, 1, 1, 514, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(179, 1, 1, 518, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(183, 1, 1, 519, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(184, 1, 1, 520, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(188, 1, 1, 515, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(189, 1, 1, 516, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(193, 1, 1, 523, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(194, 1, 1, 524, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(198, 1, 1, 521, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(199, 1, 1, 522, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(204, 1, 1, 526, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(208, 1, 1, 527, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(209, 1, 1, 528, '2026-10-04 03:08:53', '2026-10-04 03:08:53'),
(228, 1, 2, 309, '2026-10-04 03:12:21', '2026-10-04 03:12:21'),
(229, 1, 2, 308, '2026-10-04 03:12:21', '2026-10-04 03:12:21'),
(230, 1, 2, 307, '2026-10-04 03:12:21', '2026-10-04 03:12:21'),
(231, 1, 2, 523, '2026-10-04 03:12:21', '2026-10-04 03:12:21'),
(232, 1, 2, 524, '2026-10-04 03:12:21', '2026-10-04 03:12:21'),
(233, 1, 1, 529, '2026-10-08 01:34:51', '2026-10-08 01:34:51'),
(234, 1, 1, 530, '2026-10-08 01:34:51', '2026-10-08 01:34:51'),
(235, 1, 1, 531, '2026-10-08 01:34:51', '2026-10-08 01:34:51'),
(236, 1, 1, 532, '2026-10-08 01:34:51', '2026-10-08 01:34:51'),
(237, 1, 1, 533, '2026-10-08 01:34:51', '2026-10-08 01:34:51'),
(238, 1, 1, 534, '2026-10-26 15:29:32', '2026-10-26 15:29:32'),
(239, 1, 1, 535, '2026-10-26 15:29:32', '2026-10-26 15:29:32'),
(240, 1, 1, 536, '2026-10-26 15:29:32', '2026-10-26 15:29:32'),
(241, 1, 1, 537, '2026-10-26 15:29:32', '2026-10-26 15:29:32'),
(242, 1, 1, 538, '2026-10-26 15:29:32', '2026-10-26 15:29:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sedes`
--

CREATE TABLE `sedes` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `pais` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sedes`
--

INSERT INTO `sedes` (`id`, `empresa_id`, `codigo`, `nombre`, `direccion`, `activo`, `created_at`, `updated_at`, `pais`) VALUES
(1, 1, 'SEDE-001', 'Sede principal (configuración inicial)', NULL, 1, '2026-09-26 11:44:37', '2026-10-09 17:38:22', 'PE');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesiones`
--

CREATE TABLE `sesiones` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `acceso_id` bigint UNSIGNED NOT NULL,
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `dispositivo` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expira_at` datetime NOT NULL,
  `revocado_at` datetime DEFAULT NULL,
  `ultimo_uso_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sesiones`
--

INSERT INTO `sesiones` (`id`, `empresa_id`, `acceso_id`, `token_hash`, `dispositivo`, `ip`, `expira_at`, `revocado_at`, `ultimo_uso_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'f9a2c9fc4d6d92c867e23bc62d256af835d1adad220fb01a99a1a20c312c4397', 'Jaguar HSE · Web', '127.0.0.1', '2026-09-27 11:44:00', '2026-10-04 02:02:28', '2026-09-26 17:28:09', '2026-09-26 11:44:00', '2026-10-04 02:02:28'),
(2, 1, 1, '37949d5ecaf5e445403442fc7f6c1f3ac1aad2fa9875ff86948437fe1a785c09', 'Jaguar HSE · Web', '127.0.0.1', '2026-09-27 17:28:40', '2026-09-26 17:28:52', '2026-09-26 17:28:52', '2026-09-26 17:28:40', '2026-09-26 17:28:52'),
(3, 1, 1, '2fe45b544a89aade749505356f57df725eddb2e306d5a1539d92ea9c638b4e69', 'Jaguar HSE · Web', '127.0.0.1', '2026-09-27 17:30:45', '2026-10-04 02:02:28', '2026-09-26 17:38:48', '2026-09-26 17:30:45', '2026-10-04 02:02:28'),
(4, 1, 1, '1ae2b5c6ec77c8b3c7fbf91d690ca932484d56c9c2c5cf3b6d6586068b20bb28', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-05 02:09:27', '2026-10-04 02:09:34', '2026-10-04 02:09:34', '2026-10-04 02:09:27', '2026-10-04 02:09:34'),
(5, 1, 1, 'bac05ede48adeb5dddb32885e852e9a64bc62cfc5181ebd4ad528f8081f70d14', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-05 02:10:31', '2026-10-05 15:42:14', NULL, '2026-10-04 02:10:31', '2026-10-05 15:42:14'),
(6, 1, 1, '0c97b726224aec6c19df26bb1fe0db22922eb4d7d379f2157bdc03e2723e6216', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-05 02:10:46', '2026-10-05 15:42:14', '2026-10-04 02:18:14', '2026-10-04 02:10:46', '2026-10-05 15:42:14'),
(7, 1, 1, '1bc7430fc1caa4baad6b08282bf1fc4b114940a7944032d8f9f5536b57242b59', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-05 02:21:25', '2026-10-04 03:13:55', '2026-10-04 03:13:55', '2026-10-04 02:21:25', '2026-10-04 03:13:55'),
(8, 1, 1, '3091b2fdddbd54231d2a300f14e2eebf2209534d77d277be9663788215eba1f2', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-05 02:40:45', '2026-10-05 15:42:14', '2026-10-04 02:42:43', '2026-10-04 02:40:45', '2026-10-05 15:42:14'),
(9, 1, 1, '4eccbba61dd96341c85375739a074a921962a9fa838d643b4692a3e44962490a', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-05 03:09:09', '2026-10-05 15:42:14', '2026-10-04 03:09:56', '2026-10-04 03:09:09', '2026-10-05 15:42:14'),
(10, 1, 3, '5df7722ca1f86ff88f550c43bd62a75509a2f6e6804479750e8ac753887021ef', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-05 03:14:14', '2026-10-04 03:14:36', '2026-10-04 03:14:36', '2026-10-04 03:14:14', '2026-10-04 03:14:36'),
(11, 1, 1, '87d16f219ce760469d8252ec046d48ed7e6a2c6c7f0a695f4d4801aed23e8a21', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-06 15:48:38', NULL, '2026-10-05 17:06:31', '2026-10-05 15:48:38', '2026-10-05 17:06:31'),
(12, 1, 1, '2467b938eddc73cbf4980c20b36f88e52a19706cab54b32535a4d2c592597445', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-06 15:58:22', NULL, '2026-10-05 15:59:41', '2026-10-05 15:58:22', '2026-10-05 15:59:41'),
(13, 1, 1, 'c9453c9e7f45ec5344c8ba110d9c189b7735357f3af13b25514984ce6d3a4a66', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-09 00:58:45', NULL, '2026-10-08 14:55:29', '2026-10-08 00:58:45', '2026-10-08 14:55:29'),
(14, 1, 1, '3933e578356e3260640331bb62fb8c96ea3a6d34171916b4fc1c60f08a5f16a1', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-09 01:10:06', NULL, '2026-10-08 01:10:19', '2026-10-08 01:10:06', '2026-10-08 01:10:19'),
(15, 1, 1, '9d62d5507b180d18b265e212d4c93b4c679d3be1ba5d39b76004361ce52d4618', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-09 01:55:31', NULL, '2026-10-08 01:56:46', '2026-10-08 01:55:31', '2026-10-08 01:56:46'),
(16, 1, 1, '0ee3a741281bc263d02cb27d989f1a23d5483e59f1eda7cd8aed0d983a960af8', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-10 17:14:16', NULL, '2026-10-10 16:52:19', '2026-10-09 17:14:16', '2026-10-10 16:52:19'),
(17, 1, 1, '6ad978503a199442fc9342ccb4e74188052b25c67f26a0e3314326901062723b', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-15 13:47:54', NULL, '2026-10-14 16:35:00', '2026-10-14 13:47:54', '2026-10-14 16:35:00'),
(18, 1, 1, '359f5063838238f69a325f0ca94d259a3bc7cf165945199e7c716e017d8a1a44', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-16 18:05:13', NULL, '2026-10-15 20:58:43', '2026-10-15 18:05:13', '2026-10-15 20:58:43'),
(19, 1, 1, '55c9b80c8be136be7b7e2f0d1a05818136211557636e12f80c043da752f841e5', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-25 01:02:32', NULL, '2026-10-24 22:03:48', '2026-10-24 01:02:32', '2026-10-24 22:03:48'),
(20, 1, 1, 'd243eb37b8e9474f06ae5f14a7d43a339542a6771ecfa7724b78d20661f1ed56', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-27 14:38:27', NULL, '2026-10-27 13:48:37', '2026-10-26 14:38:27', '2026-10-27 13:48:37'),
(21, 1, 1, '1073ae6b6658bb537b19706e23e40f822c3e05d9aaabf31b83aa6eb5928199de', 'Jaguar HSE · Web', '127.0.0.1', '2026-10-28 15:21:26', NULL, '2026-10-27 19:25:51', '2026-10-27 15:21:26', '2026-10-27 19:25:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `temas_hse`
--

CREATE TABLE `temas_hse` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `modulo_id` bigint UNSIGNED NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `responsable_id` bigint UNSIGNED DEFAULT NULL,
  `aplica` tinyint(1) NOT NULL DEFAULT '1',
  `motivo_no_aplica` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tema_capacitacion`
--

CREATE TABLE `tema_capacitacion` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tema_capacitacion`
--

INSERT INTO `tema_capacitacion` (`id`, `empresa_id`, `activo`, `created_at`, `updated_at`, `nombre`, `descripcion`) VALUES
(1, 1, 1, '2026-10-24 02:50:35', '2026-10-24 02:50:35', 'MANEJO CONTRA INCENDIOS', 'CAPACITACION CONTRA INCENDIOS');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trabajadores`
--

CREATE TABLE `trabajadores` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `area_id` bigint UNSIGNED DEFAULT NULL,
  `miembro_id` bigint UNSIGNED DEFAULT NULL,
  `tipo_documento` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_documento` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombres` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `puesto` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `fecha_cese` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `zona_id` bigint UNSIGNED DEFAULT NULL,
  `firma_almacenada` tinyint(1) NOT NULL DEFAULT '0',
  `firma_ruta` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firma_bytes` bigint UNSIGNED NOT NULL DEFAULT '0',
  `firma_actualizada_at` timestamp NULL DEFAULT NULL
) ;

--
-- Volcado de datos para la tabla `trabajadores`
--

INSERT INTO `trabajadores` (`id`, `empresa_id`, `area_id`, `miembro_id`, `tipo_documento`, `numero_documento`, `nombres`, `apellidos`, `puesto`, `fecha_ingreso`, `fecha_cese`, `activo`, `created_at`, `updated_at`, `zona_id`, `firma_almacenada`, `firma_ruta`, `firma_bytes`, `firma_actualizada_at`) VALUES
(1, 1, 1, NULL, 'DNI', '71462409', 'JEANCARLOS', 'ALBAN ATO', 'PROGRAMADOR', '2026-09-22', NULL, 1, '2026-09-26 17:36:30', '2026-10-09 17:46:25', 1, 0, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trabajador_asignaciones`
--

CREATE TABLE `trabajador_asignaciones` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `trabajador_id` bigint UNSIGNED NOT NULL,
  `sede_id` bigint UNSIGNED NOT NULL,
  `zona_id` bigint UNSIGNED NOT NULL,
  `area_id` bigint UNSIGNED DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `observaciones` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `trabajador_asignaciones`
--

INSERT INTO `trabajador_asignaciones` (`id`, `empresa_id`, `trabajador_id`, `sede_id`, `zona_id`, `area_id`, `fecha_inicio`, `fecha_fin`, `observaciones`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, 1, '2026-09-22', NULL, 'Asignación inicial migrada desde la ficha del trabajador. Revisar las fechas si hubo traslados anteriores.', '2026-10-09 17:55:57', '2026-10-09 17:55:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` bigint UNSIGNED NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password_hash`, `email_verified_at`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'Administrador', 'calbanc@gmail.com', '$2y$12$cTFS4DuD5UAW1aTeYa4bu.Bje8MV3VIuRrNmYVznQwUqmECCTZfN2', '2026-10-05 15:47:56', 1, '2026-09-25 21:07:44', '2026-10-05 15:47:56'),
(2, 'Usuario organizacion', 'organizacion@avanti.com', '$2y$12$uaMbC9bWeSgTB8MV5BE1UeD3SFkKKi8fc/s3qg6TpFig9uflr3Iyu', NULL, 1, '2026-10-04 03:13:20', '2026-10-04 03:13:20');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_epp_costo_trabajador`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_epp_costo_trabajador` (
`apellidos` varchar(150)
,`costo_devuelto` decimal(65,6)
,`costo_entregado` decimal(46,6)
,`costo_neto` decimal(65,6)
,`detalles_sin_costo` decimal(23,0)
,`empresa_id` bigint unsigned
,`entregas_confirmadas` bigint
,`id` bigint unsigned
,`moneda` varchar(3)
,`nombres` varchar(150)
,`numero_documento` varchar(30)
,`puesto` varchar(150)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_epp_movimiento_detalle`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_epp_movimiento_detalle` (
`almacen` varchar(150)
,`almacen_id` bigint unsigned
,`cantidad` decimal(12,3)
,`clave_operacion` varchar(100)
,`compra_detalle_id` bigint unsigned
,`costo_total` decimal(24,6)
,`costo_total_original` decimal(24,6)
,`costo_unitario` decimal(18,6)
,`costo_unitario_original` decimal(18,6)
,`created_at` datetime
,`empresa_id` bigint unsigned
,`entrada` decimal(12,3)
,`entrega_detalle_id` bigint unsigned
,`epp` varchar(180)
,`epp_id` bigint unsigned
,`factura` varchar(60)
,`fecha` datetime
,`id` bigint unsigned
,`moneda_compra` varchar(3)
,`movimiento` varchar(20)
,`numero_documento` varchar(30)
,`observacion` text
,`proveedor` varchar(180)
,`registrado_por` bigint unsigned
,`saldo_valor` decimal(24,6)
,`salida` decimal(12,3)
,`tipo` enum('entrada','entrega','devolucion','ajuste','reverso')
,`tipo_cambio_compra` decimal(12,6)
,`trabajador_apellidos` varchar(150)
,`trabajador_nombres` varchar(150)
,`unidad` varchar(30)
,`updated_at` datetime
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_epp_stock`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_epp_stock` (
`almacen` varchar(150)
,`almacen_id` bigint unsigned
,`costo_promedio` decimal(33,11)
,`empresa_id` bigint unsigned
,`epp` varchar(180)
,`epp_id` bigint unsigned
,`moneda` varchar(3)
,`stock` decimal(34,3)
,`unidad` varchar(30)
,`valor_stock` decimal(24,6)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_epp_trabajador_detalle`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_epp_trabajador_detalle` (
`almacen` varchar(150)
,`cantidad_devuelta` decimal(34,3)
,`cantidad_entregada` decimal(12,3)
,`cantidad_neta` decimal(35,3)
,`costo_devuelto` decimal(46,6)
,`costo_entregado` decimal(24,6)
,`costo_neto` decimal(47,6)
,`costo_unitario` decimal(18,6)
,`empresa_id` bigint unsigned
,`entrega_id` bigint unsigned
,`epp` varchar(180)
,`fecha` datetime
,`id` bigint unsigned
,`moneda` varchar(3)
,`trabajador_id` bigint unsigned
,`unidad` varchar(30)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_estado_requisitos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_estado_requisitos` (
`documento_version_id` bigint unsigned
,`empresa_id` bigint unsigned
,`estado` varchar(13)
,`fecha_vencimiento` date
,`nombre` varchar(180)
,`obligatorio` tinyint(1)
,`peso` decimal(10,2)
,`requisito_id` bigint unsigned
,`tema_id` bigint unsigned
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_estado_temas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_estado_temas` (
`empresa_id` bigint unsigned
,`estado` varchar(14)
,`nombre` varchar(180)
,`porcentaje_cumplimiento` decimal(38,2)
,`requisitos_obligatorios` bigint
,`requisitos_vigentes` decimal(23,0)
,`tema_id` bigint unsigned
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_indicador_resultados`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_indicador_resultados` (
`codigo` varchar(50)
,`comentario` text
,`created_at` datetime
,`denominador` decimal(18,4)
,`empresa_id` bigint unsigned
,`estado` varchar(9)
,`evidencia_archivo_id` bigint unsigned
,`id` bigint unsigned
,`indicador_id` bigint unsigned
,`meta` decimal(18,4)
,`nombre` varchar(180)
,`numerador` decimal(18,4)
,`periodo` date
,`registrado_por` bigint unsigned
,`sentido` enum('mayor_mejor','menor_mejor')
,`unidad` varchar(30)
,`updated_at` datetime
,`valor` decimal(42,12)
,`valor_manual` decimal(18,4)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `zonas`
--

CREATE TABLE `zonas` (
  `id` bigint UNSIGNED NOT NULL,
  `empresa_id` bigint UNSIGNED NOT NULL,
  `sede_id` bigint UNSIGNED NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `zonas`
--

INSERT INTO `zonas` (`id`, `empresa_id`, `sede_id`, `nombre`, `activo`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Zona general', 1, '2026-10-09 17:38:22', '2026-10-09 17:38:22');

-- --------------------------------------------------------

--
-- Estructura para la vista `v_epp_costo_trabajador`
--
DROP TABLE IF EXISTS `v_epp_costo_trabajador`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_epp_costo_trabajador`  AS SELECT `w`.`id` AS `id`, `w`.`empresa_id` AS `empresa_id`, `w`.`numero_documento` AS `numero_documento`, `w`.`nombres` AS `nombres`, `w`.`apellidos` AS `apellidos`, `w`.`puesto` AS `puesto`, count(distinct `d`.`entrega_id`) AS `entregas_confirmadas`, (case when (sum((case when (`d`.`costo_entregado` is null) then 1 else 0 end)) > 0) then NULL else sum(`d`.`costo_entregado`) end) AS `costo_entregado`, (case when (sum((case when (`d`.`costo_devuelto` is null) then 1 else 0 end)) > 0) then NULL else sum(`d`.`costo_devuelto`) end) AS `costo_devuelto`, (case when (sum((case when (`d`.`costo_neto` is null) then 1 else 0 end)) > 0) then NULL else sum(`d`.`costo_neto`) end) AS `costo_neto`, sum((case when (`d`.`costo_neto` is null) then 1 else 0 end)) AS `detalles_sin_costo`, 'PEN' AS `moneda` FROM (`trabajadores` `w` join `v_epp_trabajador_detalle` `d` on(((`d`.`trabajador_id` = `w`.`id`) and (`d`.`empresa_id` = `w`.`empresa_id`)))) GROUP BY `w`.`id`, `w`.`empresa_id`, `w`.`numero_documento`, `w`.`nombres`, `w`.`apellidos`, `w`.`puesto` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_epp_movimiento_detalle`
--
DROP TABLE IF EXISTS `v_epp_movimiento_detalle`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_epp_movimiento_detalle`  AS SELECT `m`.`id` AS `id`, `m`.`empresa_id` AS `empresa_id`, `m`.`epp_id` AS `epp_id`, `m`.`almacen_id` AS `almacen_id`, `m`.`entrega_detalle_id` AS `entrega_detalle_id`, `m`.`registrado_por` AS `registrado_por`, `m`.`fecha` AS `fecha`, `m`.`cantidad` AS `cantidad`, `m`.`tipo` AS `tipo`, `m`.`clave_operacion` AS `clave_operacion`, `m`.`observacion` AS `observacion`, `m`.`created_at` AS `created_at`, `m`.`updated_at` AS `updated_at`, `m`.`compra_detalle_id` AS `compra_detalle_id`, `m`.`costo_unitario` AS `costo_unitario`, `m`.`costo_total` AS `costo_total`, `m`.`saldo_valor` AS `saldo_valor`, `e`.`nombre` AS `epp`, `e`.`unidad` AS `unidad`, `a`.`nombre` AS `almacen`, (case when (`m`.`compra_detalle_id` is not null) then 'Compra' when (`m`.`tipo` = 'entrega') then 'Salida por entrega' when (`m`.`tipo` = 'devolucion') then 'Devolución' when (`m`.`tipo` = 'reverso') then 'Anulación de entrega' when (`m`.`tipo` = 'ajuste') then 'Ajuste' else 'Entrada' end) AS `movimiento`, (case when (`m`.`cantidad` > 0) then `m`.`cantidad` else 0 end) AS `entrada`, (case when (`m`.`cantidad` < 0) then -(`m`.`cantidad`) else 0 end) AS `salida`, `w`.`nombres` AS `trabajador_nombres`, `w`.`apellidos` AS `trabajador_apellidos`, `w`.`numero_documento` AS `numero_documento`, `c`.`proveedor` AS `proveedor`, `c`.`factura` AS `factura`, `c`.`moneda` AS `moneda_compra`, `c`.`tipo_cambio` AS `tipo_cambio_compra`, `cd`.`costo_unitario_original` AS `costo_unitario_original`, `cd`.`costo_total_original` AS `costo_total_original` FROM (((((((`epp_movimientos` `m` join `epp_catalogo` `e` on(((`e`.`empresa_id` = `m`.`empresa_id`) and (`e`.`id` = `m`.`epp_id`)))) join `almacenes` `a` on(((`a`.`empresa_id` = `m`.`empresa_id`) and (`a`.`id` = `m`.`almacen_id`)))) left join `epp_compra_detalles` `cd` on(((`cd`.`empresa_id` = `m`.`empresa_id`) and (`cd`.`id` = `m`.`compra_detalle_id`)))) left join `epp_compras` `c` on(((`c`.`empresa_id` = `cd`.`empresa_id`) and (`c`.`id` = `cd`.`compra_id`)))) left join `epp_entrega_detalles` `d` on(((`d`.`empresa_id` = `m`.`empresa_id`) and (`d`.`id` = `m`.`entrega_detalle_id`)))) left join `epp_entregas` `t` on(((`t`.`empresa_id` = `d`.`empresa_id`) and (`t`.`id` = `d`.`entrega_id`)))) left join `trabajadores` `w` on(((`w`.`empresa_id` = `t`.`empresa_id`) and (`w`.`id` = `t`.`trabajador_id`)))) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_epp_stock`
--
DROP TABLE IF EXISTS `v_epp_stock`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_epp_stock`  AS SELECT `m`.`empresa_id` AS `empresa_id`, `m`.`almacen_id` AS `almacen_id`, `m`.`epp_id` AS `epp_id`, `a`.`nombre` AS `almacen`, `e`.`nombre` AS `epp`, `e`.`unidad` AS `unidad`, sum(`m`.`cantidad`) AS `stock`, (case when (sum(`m`.`cantidad`) = 0) then 0 else `ultimo`.`saldo_valor` end) AS `valor_stock`, (case when (sum(`m`.`cantidad`) = 0) then 0 else ((`ultimo`.`saldo_valor` * 1.0) / sum(`m`.`cantidad`)) end) AS `costo_promedio`, 'PEN' AS `moneda` FROM (((`epp_movimientos` `m` join `almacenes` `a` on(((`a`.`empresa_id` = `m`.`empresa_id`) and (`a`.`id` = `m`.`almacen_id`)))) join `epp_catalogo` `e` on(((`e`.`empresa_id` = `m`.`empresa_id`) and (`e`.`id` = `m`.`epp_id`)))) join `epp_movimientos` `ultimo` on((`ultimo`.`id` = (select max(`u`.`id`) from `epp_movimientos` `u` where ((`u`.`empresa_id` = `m`.`empresa_id`) and (`u`.`almacen_id` = `m`.`almacen_id`) and (`u`.`epp_id` = `m`.`epp_id`)))))) GROUP BY `m`.`empresa_id`, `m`.`almacen_id`, `m`.`epp_id`, `a`.`nombre`, `e`.`nombre`, `e`.`unidad`, `ultimo`.`saldo_valor` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_epp_trabajador_detalle`
--
DROP TABLE IF EXISTS `v_epp_trabajador_detalle`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_epp_trabajador_detalle`  AS SELECT `d`.`id` AS `id`, `d`.`empresa_id` AS `empresa_id`, `t`.`trabajador_id` AS `trabajador_id`, `t`.`id` AS `entrega_id`, `t`.`fecha` AS `fecha`, `e`.`nombre` AS `epp`, `e`.`unidad` AS `unidad`, `a`.`nombre` AS `almacen`, `d`.`cantidad` AS `cantidad_entregada`, coalesce(`r`.`cantidad`,0) AS `cantidad_devuelta`, (`d`.`cantidad` - coalesce(`r`.`cantidad`,0)) AS `cantidad_neta`, `d`.`costo_unitario` AS `costo_unitario`, `d`.`costo_total` AS `costo_entregado`, (case when (coalesce(`r`.`sin_costo`,0) > 0) then NULL else coalesce(`r`.`costo`,0) end) AS `costo_devuelto`, (case when ((`d`.`cantidad` - coalesce(`r`.`cantidad`,0)) = 0) then 0 when ((`d`.`costo_total` is null) or (coalesce(`r`.`sin_costo`,0) > 0)) then NULL else (`d`.`costo_total` - coalesce(`r`.`costo`,0)) end) AS `costo_neto`, 'PEN' AS `moneda` FROM ((((`epp_entrega_detalles` `d` join `epp_entregas` `t` on(((`t`.`id` = `d`.`entrega_id`) and (`t`.`empresa_id` = `d`.`empresa_id`)))) join `epp_catalogo` `e` on(((`e`.`id` = `d`.`epp_id`) and (`e`.`empresa_id` = `d`.`empresa_id`)))) join `almacenes` `a` on(((`a`.`id` = `d`.`almacen_id`) and (`a`.`empresa_id` = `d`.`empresa_id`)))) left join (select `epp_movimientos`.`empresa_id` AS `empresa_id`,`epp_movimientos`.`entrega_detalle_id` AS `entrega_detalle_id`,sum(`epp_movimientos`.`cantidad`) AS `cantidad`,sum(`epp_movimientos`.`costo_total`) AS `costo`,sum((case when (`epp_movimientos`.`costo_total` is null) then 1 else 0 end)) AS `sin_costo` from `epp_movimientos` where (`epp_movimientos`.`tipo` = 'devolucion') group by `epp_movimientos`.`empresa_id`,`epp_movimientos`.`entrega_detalle_id`) `r` on(((`r`.`empresa_id` = `d`.`empresa_id`) and (`r`.`entrega_detalle_id` = `d`.`id`)))) WHERE (`t`.`estado` = 'confirmada') ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_estado_requisitos`
--
DROP TABLE IF EXISTS `v_estado_requisitos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `v_estado_requisitos`  AS SELECT `r`.`empresa_id` AS `empresa_id`, `r`.`id` AS `requisito_id`, `r`.`tema_id` AS `tema_id`, `r`.`nombre` AS `nombre`, `r`.`obligatorio` AS `obligatorio`, `r`.`peso` AS `peso`, `d`.`id` AS `documento_version_id`, `d`.`fecha_vencimiento` AS `fecha_vencimiento`, (case when (`d`.`id` is null) then 'sin_documento' when (`d`.`estado` <> 'aprobado') then `d`.`estado` when ((`r`.`requiere_vencimiento` = 1) and (`d`.`fecha_vencimiento` is null)) then 'incompleto' when (`d`.`fecha_vencimiento` < curdate()) then 'vencido' when (`a`.`eliminado_at` is not null) then 'sin_documento' else 'vigente' end) AS `estado` FROM ((`requisitos_documentales` `r` left join `documento_versiones` `d` on(((`d`.`empresa_id` = `r`.`empresa_id`) and (`d`.`requisito_id` = `r`.`id`) and exists(select 1 from `documento_versiones` `n` where ((`n`.`empresa_id` = `d`.`empresa_id`) and (`n`.`requisito_id` = `d`.`requisito_id`) and (`n`.`version` > `d`.`version`))) is false))) left join `archivos` `a` on(((`a`.`empresa_id` = `d`.`empresa_id`) and (`a`.`id` = `d`.`archivo_id`)))) WHERE (`r`.`activo` = 1) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_estado_temas`
--
DROP TABLE IF EXISTS `v_estado_temas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `v_estado_temas`  AS SELECT `t`.`empresa_id` AS `empresa_id`, `t`.`id` AS `tema_id`, `t`.`nombre` AS `nombre`, count(`r`.`requisito_id`) AS `requisitos_obligatorios`, sum((case when (`r`.`estado` = 'vigente') then 1 else 0 end)) AS `requisitos_vigentes`, (case when (`t`.`aplica` = 0) then NULL else round(((100 * sum((case when (`r`.`estado` = 'vigente') then `r`.`peso` else 0 end))) / nullif(sum(`r`.`peso`),0)),2) end) AS `porcentaje_cumplimiento`, (case when (`t`.`aplica` = 0) then 'no_aplica' when (count(`r`.`requisito_id`) = 0) then 'sin_configurar' when (sum((`r`.`estado` = 'vigente')) = count(`r`.`requisito_id`)) then 'cumplido' when (sum((`r`.`estado` = 'vencido')) > 0) then 'vencido' when (sum((`r`.`estado` in ('observado','rechazado','incompleto'))) > 0) then 'observado' else 'pendiente' end) AS `estado` FROM (`temas_hse` `t` left join `v_estado_requisitos` `r` on(((`r`.`empresa_id` = `t`.`empresa_id`) and (`r`.`tema_id` = `t`.`id`) and (`r`.`obligatorio` = 1)))) WHERE (`t`.`activo` = 1) GROUP BY `t`.`empresa_id`, `t`.`id`, `t`.`nombre`, `t`.`aplica` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_indicador_resultados`
--
DROP TABLE IF EXISTS `v_indicador_resultados`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `v_indicador_resultados`  AS SELECT `m`.`id` AS `id`, `m`.`empresa_id` AS `empresa_id`, `m`.`indicador_id` AS `indicador_id`, `m`.`periodo` AS `periodo`, `m`.`numerador` AS `numerador`, `m`.`denominador` AS `denominador`, `m`.`valor_manual` AS `valor_manual`, `m`.`meta` AS `meta`, `m`.`registrado_por` AS `registrado_por`, `m`.`evidencia_archivo_id` AS `evidencia_archivo_id`, `m`.`comentario` AS `comentario`, `m`.`created_at` AS `created_at`, `m`.`updated_at` AS `updated_at`, `i`.`codigo` AS `codigo`, `i`.`nombre` AS `nombre`, `i`.`unidad` AS `unidad`, `i`.`sentido` AS `sentido`, (case when (`i`.`tipo_calculo` = 'manual') then `m`.`valor_manual` else ((`m`.`numerador` / nullif(`m`.`denominador`,0)) * `i`.`factor`) end) AS `valor`, (case when ((`i`.`tipo_calculo` = 'manual') and (`m`.`valor_manual` is null)) then 'sin_datos' when ((`i`.`tipo_calculo` = 'razon') and ((`m`.`numerador` is null) or (`m`.`denominador` is null) or (`m`.`denominador` = 0))) then 'sin_datos' when (((`i`.`sentido` = 'mayor_mejor') and (coalesce(`m`.`valor_manual`,((`m`.`numerador` / nullif(`m`.`denominador`,0)) * `i`.`factor`)) >= `m`.`meta`)) or ((`i`.`sentido` = 'menor_mejor') and (coalesce(`m`.`valor_manual`,((`m`.`numerador` / nullif(`m`.`denominador`,0)) * `i`.`factor`)) <= `m`.`meta`))) then 'cumple' else 'no_cumple' end) AS `estado` FROM (`indicador_mediciones` `m` join `indicadores` `i` on(((`i`.`empresa_id` = `m`.`empresa_id`) and (`i`.`id` = `m`.`indicador_id`)))) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `accesos_aplicacion`
--
ALTER TABLE `accesos_aplicacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_accesos_aplicacion_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_acceso` (`empresa_id`,`miembro_id`,`plataforma_id`),
  ADD KEY `empresa_id` (`empresa_id`,`rol_id`),
  ADD KEY `plataforma_id` (`plataforma_id`);

--
-- Indices de la tabla `acciones_correctivas`
--
ALTER TABLE `acciones_correctivas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_acciones_correctivas_empresa` (`empresa_id`,`id`),
  ADD KEY `empresa_id` (`empresa_id`,`hallazgo_id`),
  ADD KEY `empresa_id_2` (`empresa_id`,`responsable_id`),
  ADD KEY `ix_accion_plazo` (`empresa_id`,`estado`,`fecha_limite`);

--
-- Indices de la tabla `almacenes`
--
ALTER TABLE `almacenes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_almacenes_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_almacen` (`empresa_id`,`sede_id`,`nombre`);

--
-- Indices de la tabla `archivos`
--
ALTER TABLE `archivos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_archivos_empresa` (`empresa_id`,`id`),
  ADD KEY `empresa_id` (`empresa_id`,`subido_por`);

--
-- Indices de la tabla `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_areas_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_area_zona` (`empresa_id`,`zona_id`,`nombre`),
  ADD UNIQUE KEY `uq_area_zona_id` (`empresa_id`,`zona_id`,`id`);

--
-- Indices de la tabla `auditoria_eventos`
--
ALTER TABLE `auditoria_eventos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_auditoria_eventos_empresa` (`empresa_id`,`id`),
  ADD KEY `empresa_id` (`empresa_id`,`actor_id`),
  ADD KEY `ix_auditoria_registro` (`empresa_id`,`entidad`,`registro_id`),
  ADD KEY `ix_auditoria_fecha` (`empresa_id`,`created_at`);

--
-- Indices de la tabla `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indices de la tabla `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indices de la tabla `capacitacion`
--
ALTER TABLE `capacitacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_capacitacion_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_capacitacion_planilla_empresa` (`empresa_id`,`numero_planilla`),
  ADD KEY `fk_cap_tema_capacitacion` (`empresa_id`,`tema_capacitacion_id`),
  ADD KEY `fk_cap_lugar_capacitacion` (`empresa_id`,`lugar_capacitacion_id`),
  ADD KEY `fk_cap_capacitador` (`empresa_id`,`capacitador_id`),
  ADD KEY `fk_cap_empresa_capacitacion` (`empresa_id`,`empresa_capacitacion_id`),
  ADD KEY `fk_cap_responsable` (`empresa_id`,`responsable_registro_id`),
  ADD KEY `capacitacion_empresa_id_fecha_index` (`empresa_id`,`fecha`);

--
-- Indices de la tabla `capacitacion_formatos`
--
ALTER TABLE `capacitacion_formatos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `capacitacion_formatos_empresa_id_activo_index` (`empresa_id`,`activo`);

--
-- Indices de la tabla `capacitacion_participantes`
--
ALTER TABLE `capacitacion_participantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_capacitacion_participantes_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_cap_participante` (`empresa_id`,`capacitacion_id`,`trabajador_id`),
  ADD KEY `fk_cap_part_trabajador` (`empresa_id`,`trabajador_id`);

--
-- Indices de la tabla `capacitador`
--
ALTER TABLE `capacitador`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_capacitador_empresa` (`empresa_id`,`id`);

--
-- Indices de la tabla `checklist_evidencias`
--
ALTER TABLE `checklist_evidencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_checklist_evidencias_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_check_evidencia` (`empresa_id`,`respuesta_id`,`archivo_id`),
  ADD KEY `empresa_id` (`empresa_id`,`archivo_id`);

--
-- Indices de la tabla `checklist_plantillas`
--
ALTER TABLE `checklist_plantillas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_checklist_plantillas_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_plantilla` (`empresa_id`,`codigo`,`version`);

--
-- Indices de la tabla `checklist_preguntas`
--
ALTER TABLE `checklist_preguntas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_checklist_preguntas_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_pregunta_plantilla` (`empresa_id`,`plantilla_id`,`id`),
  ADD UNIQUE KEY `uq_pregunta_codigo` (`empresa_id`,`plantilla_id`,`codigo`),
  ADD KEY `ix_pregunta_orden` (`empresa_id`,`plantilla_id`,`seccion_id`,`orden`);

--
-- Indices de la tabla `checklist_pregunta_opciones`
--
ALTER TABLE `checklist_pregunta_opciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_checklist_pregunta_opciones_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_opcion_pregunta` (`empresa_id`,`pregunta_id`,`id`),
  ADD UNIQUE KEY `uq_opcion_valor` (`empresa_id`,`pregunta_id`,`valor`),
  ADD KEY `ix_opcion_orden` (`empresa_id`,`pregunta_id`,`orden`);

--
-- Indices de la tabla `checklist_registros`
--
ALTER TABLE `checklist_registros`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_checklist_registros_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_check_uuid` (`empresa_id`,`uuid_cliente`),
  ADD UNIQUE KEY `uq_check_plantilla` (`empresa_id`,`plantilla_id`,`id`),
  ADD KEY `empresa_id` (`empresa_id`,`area_id`),
  ADD KEY `empresa_id_2` (`empresa_id`,`realizado_por`),
  ADD KEY `ix_check_fecha` (`empresa_id`,`fecha`);

--
-- Indices de la tabla `checklist_respuestas`
--
ALTER TABLE `checklist_respuestas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_checklist_respuestas_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_respuesta` (`empresa_id`,`registro_id`,`pregunta_id`),
  ADD UNIQUE KEY `uq_respuesta_pregunta` (`empresa_id`,`pregunta_id`,`id`),
  ADD KEY `empresa_id` (`empresa_id`,`plantilla_id`,`registro_id`),
  ADD KEY `empresa_id_2` (`empresa_id`,`plantilla_id`,`pregunta_id`);

--
-- Indices de la tabla `checklist_respuesta_opciones`
--
ALTER TABLE `checklist_respuesta_opciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_checklist_respuesta_opciones_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_respuesta_opcion` (`empresa_id`,`respuesta_id`,`opcion_id`),
  ADD KEY `empresa_id` (`empresa_id`,`pregunta_id`,`respuesta_id`),
  ADD KEY `empresa_id_2` (`empresa_id`,`pregunta_id`,`opcion_id`);

--
-- Indices de la tabla `checklist_secciones`
--
ALTER TABLE `checklist_secciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_checklist_secciones_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_seccion_plantilla` (`empresa_id`,`plantilla_id`,`id`),
  ADD KEY `ix_seccion_orden` (`empresa_id`,`plantilla_id`,`orden`);

--
-- Indices de la tabla `contratos`
--
ALTER TABLE `contratos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_contratos_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_contrato` (`empresa_id`,`numero`);

--
-- Indices de la tabla `documento_revisiones`
--
ALTER TABLE `documento_revisiones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_documento_revisiones_empresa` (`empresa_id`,`id`),
  ADD KEY `empresa_id` (`empresa_id`,`documento_version_id`),
  ADD KEY `empresa_id_2` (`empresa_id`,`revisor_id`);

--
-- Indices de la tabla `documento_versiones`
--
ALTER TABLE `documento_versiones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_documento_versiones_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_doc_version` (`empresa_id`,`requisito_id`,`version`),
  ADD KEY `empresa_id` (`empresa_id`,`archivo_id`),
  ADD KEY `empresa_id_2` (`empresa_id`,`revisado_por`);

--
-- Indices de la tabla `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_empresa_codigo` (`codigo`),
  ADD UNIQUE KEY `uq_empresa_fiscal` (`pais`,`identificacion_fiscal`);

--
-- Indices de la tabla `empresa_capacitacion`
--
ALTER TABLE `empresa_capacitacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_empresa_capacitacion_empresa` (`empresa_id`,`id`);

--
-- Indices de la tabla `empresa_modulos`
--
ALTER TABLE `empresa_modulos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_empresa_modulos_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_empresa_modulo` (`empresa_id`,`modulo_id`),
  ADD KEY `modulo_id` (`modulo_id`);

--
-- Indices de la tabla `epp_catalogo`
--
ALTER TABLE `epp_catalogo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_epp_catalogo_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_epp` (`empresa_id`,`codigo`),
  ADD KEY `empresa_id` (`empresa_id`,`ficha_archivo_id`);

--
-- Indices de la tabla `epp_compras`
--
ALTER TABLE `epp_compras`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `epp_compras_empresa_id_id_unique` (`empresa_id`,`id`),
  ADD UNIQUE KEY `epp_compras_empresa_id_clave_operacion_unique` (`empresa_id`,`clave_operacion`),
  ADD UNIQUE KEY `uq_epp_compra_factura` (`empresa_id`,`proveedor_clave`,`factura`),
  ADD KEY `epp_compras_empresa_id_registrado_por_foreign` (`empresa_id`,`registrado_por`);

--
-- Indices de la tabla `epp_compra_detalles`
--
ALTER TABLE `epp_compra_detalles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `epp_compra_detalles_empresa_id_id_unique` (`empresa_id`,`id`),
  ADD KEY `epp_compra_detalles_empresa_id_compra_id_foreign` (`empresa_id`,`compra_id`),
  ADD KEY `epp_compra_detalles_empresa_id_epp_id_foreign` (`empresa_id`,`epp_id`),
  ADD KEY `epp_compra_detalles_empresa_id_almacen_id_foreign` (`empresa_id`,`almacen_id`);

--
-- Indices de la tabla `epp_entregas`
--
ALTER TABLE `epp_entregas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_epp_entregas_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_entrega_uuid` (`empresa_id`,`uuid_cliente`),
  ADD KEY `empresa_id` (`empresa_id`,`entregado_por`),
  ADD KEY `empresa_id_2` (`empresa_id`,`firma_archivo_id`),
  ADD KEY `ix_entrega_trabajador` (`empresa_id`,`trabajador_id`,`fecha`);

--
-- Indices de la tabla `epp_entrega_detalles`
--
ALTER TABLE `epp_entrega_detalles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_epp_entrega_detalles_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_detalle_stock` (`empresa_id`,`epp_id`,`almacen_id`,`id`),
  ADD KEY `empresa_id` (`empresa_id`,`entrega_id`),
  ADD KEY `empresa_id_2` (`empresa_id`,`almacen_id`);

--
-- Indices de la tabla `epp_entrega_lotes`
--
ALTER TABLE `epp_entrega_lotes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `epp_entrega_lotes_empresa_id_uuid_cliente_unique` (`empresa_id`,`uuid_cliente`);

--
-- Indices de la tabla `epp_movimientos`
--
ALTER TABLE `epp_movimientos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_epp_movimientos_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_mov_operacion` (`empresa_id`,`clave_operacion`),
  ADD KEY `empresa_id` (`empresa_id`,`registrado_por`),
  ADD KEY `empresa_id_2` (`empresa_id`,`epp_id`,`almacen_id`,`entrega_detalle_id`),
  ADD KEY `ix_stock` (`empresa_id`,`almacen_id`,`epp_id`),
  ADD KEY `epp_movimientos_empresa_id_compra_detalle_id_foreign` (`empresa_id`,`compra_detalle_id`);

--
-- Indices de la tabla `epp_planillas`
--
ALTER TABLE `epp_planillas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `epp_planillas_empresa_id_numero_unique` (`empresa_id`,`numero`),
  ADD UNIQUE KEY `epp_planillas_empresa_id_uuid_cliente_unique` (`empresa_id`,`uuid_cliente`),
  ADD KEY `epp_planillas_empresa_id_registrado_por_foreign` (`empresa_id`,`registrado_por`);

--
-- Indices de la tabla `hallazgos`
--
ALTER TABLE `hallazgos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hallazgos_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_hallazgo_uuid` (`empresa_id`,`uuid_cliente`),
  ADD KEY `empresa_id` (`empresa_id`,`area_id`),
  ADD KEY `empresa_id_2` (`empresa_id`,`respuesta_id`),
  ADD KEY `empresa_id_3` (`empresa_id`,`reportado_por`),
  ADD KEY `empresa_id_4` (`empresa_id`,`responsable_id`),
  ADD KEY `empresa_id_5` (`empresa_id`,`cerrado_por`),
  ADD KEY `ix_hallazgo_estado` (`empresa_id`,`estado`,`fecha_limite`);

--
-- Indices de la tabla `hallazgo_evidencias`
--
ALTER TABLE `hallazgo_evidencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hallazgo_evidencias_empresa` (`empresa_id`,`id`),
  ADD KEY `empresa_id` (`empresa_id`,`hallazgo_id`),
  ADD KEY `empresa_id_2` (`empresa_id`,`archivo_id`);

--
-- Indices de la tabla `hallazgo_seguimientos`
--
ALTER TABLE `hallazgo_seguimientos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hallazgo_seguimientos_empresa` (`empresa_id`,`id`),
  ADD KEY `empresa_id` (`empresa_id`,`hallazgo_id`),
  ADD KEY `empresa_id_2` (`empresa_id`,`autor_id`);

--
-- Indices de la tabla `implementos`
--
ALTER TABLE `implementos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `implementos_empresa_id_codigo_unique` (`empresa_id`,`codigo`),
  ADD KEY `implementos_catalogo_id_foreign` (`catalogo_id`),
  ADD KEY `implementos_proveedor_id_foreign` (`proveedor_id`);

--
-- Indices de la tabla `implementos_catalogo`
--
ALTER TABLE `implementos_catalogo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `implementos_catalogo_empresa_id_codigo_unique` (`empresa_id`,`codigo`),
  ADD KEY `implementos_catalogo_categoria_id_foreign` (`categoria_id`);

--
-- Indices de la tabla `implementos_categorias`
--
ALTER TABLE `implementos_categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `implementos_categorias_empresa_id_nombre_unique` (`empresa_id`,`nombre`);

--
-- Indices de la tabla `implementos_proveedores`
--
ALTER TABLE `implementos_proveedores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `implementos_proveedores_empresa_id_foreign` (`empresa_id`);

--
-- Indices de la tabla `implemento_archivos`
--
ALTER TABLE `implemento_archivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `implemento_archivos_empresa_id_foreign` (`empresa_id`),
  ADD KEY `implemento_archivos_implemento_id_foreign` (`implemento_id`),
  ADD KEY `implemento_archivos_inspeccion_id_foreign` (`inspeccion_id`),
  ADD KEY `implemento_archivos_archivo_id_foreign` (`archivo_id`);

--
-- Indices de la tabla `implemento_costos`
--
ALTER TABLE `implemento_costos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `implemento_costos_empresa_id_foreign` (`empresa_id`),
  ADD KEY `implemento_costos_implemento_id_foreign` (`implemento_id`),
  ADD KEY `implemento_costos_proveedor_id_foreign` (`proveedor_id`);

--
-- Indices de la tabla `implemento_inspecciones`
--
ALTER TABLE `implemento_inspecciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `implemento_inspecciones_implemento_id_foreign` (`implemento_id`),
  ADD KEY `implemento_inspecciones_instalacion_id_foreign` (`instalacion_id`),
  ADD KEY `implemento_inspecciones_responsable_id_foreign` (`responsable_id`),
  ADD KEY `implemento_inspecciones_realizado_por_foreign` (`realizado_por`),
  ADD KEY `implemento_agenda` (`empresa_id`,`estado`,`fecha_programada`);

--
-- Indices de la tabla `implemento_instalaciones`
--
ALTER TABLE `implemento_instalaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `implemento_instalaciones_implemento_id_foreign` (`implemento_id`),
  ADD KEY `implemento_instalaciones_area_id_foreign` (`area_id`),
  ADD KEY `implemento_instalacion_actual` (`empresa_id`,`implemento_id`,`fecha_retiro`);

--
-- Indices de la tabla `indicadores`
--
ALTER TABLE `indicadores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_indicadores_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_indicador` (`empresa_id`,`codigo`),
  ADD KEY `empresa_id` (`empresa_id`,`tema_id`);

--
-- Indices de la tabla `indicador_mediciones`
--
ALTER TABLE `indicador_mediciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_indicador_mediciones_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_indicador_periodo` (`empresa_id`,`indicador_id`,`periodo`),
  ADD KEY `empresa_id` (`empresa_id`,`registrado_por`),
  ADD KEY `empresa_id_2` (`empresa_id`,`evidencia_archivo_id`);

--
-- Indices de la tabla `lugar_capacitacion`
--
ALTER TABLE `lugar_capacitacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_lugar_capacitacion_empresa` (`empresa_id`,`id`);

--
-- Indices de la tabla `miembros_empresa`
--
ALTER TABLE `miembros_empresa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_miembros_empresa_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_membresia` (`empresa_id`,`usuario_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `modulos`
--
ALTER TABLE `modulos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_modulo` (`codigo`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permiso` (`codigo`),
  ADD KEY `modulo_id` (`modulo_id`);

--
-- Indices de la tabla `plataformas`
--
ALTER TABLE `plataformas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_plataforma` (`codigo`);

--
-- Indices de la tabla `requisitos_documentales`
--
ALTER TABLE `requisitos_documentales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_requisitos_documentales_empresa` (`empresa_id`,`id`),
  ADD KEY `empresa_id` (`empresa_id`,`tema_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_rol` (`empresa_id`,`nombre`);

--
-- Indices de la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rol_permisos_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_rol_permiso` (`empresa_id`,`rol_id`,`permiso_id`),
  ADD KEY `permiso_id` (`permiso_id`);

--
-- Indices de la tabla `sedes`
--
ALTER TABLE `sedes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sedes_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_sede` (`empresa_id`,`codigo`);

--
-- Indices de la tabla `sesiones`
--
ALTER TABLE `sesiones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sesiones_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_token` (`token_hash`),
  ADD KEY `empresa_id` (`empresa_id`,`acceso_id`);

--
-- Indices de la tabla `temas_hse`
--
ALTER TABLE `temas_hse`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_temas_hse_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_tema` (`empresa_id`,`codigo`),
  ADD KEY `empresa_id` (`empresa_id`,`responsable_id`),
  ADD KEY `modulo_id` (`modulo_id`);

--
-- Indices de la tabla `tema_capacitacion`
--
ALTER TABLE `tema_capacitacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tema_capacitacion_empresa` (`empresa_id`,`id`);

--
-- Indices de la tabla `trabajadores`
--
ALTER TABLE `trabajadores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_trabajadores_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_trabajador_doc` (`empresa_id`,`tipo_documento`,`numero_documento`),
  ADD UNIQUE KEY `uq_trabajador_miembro` (`empresa_id`,`miembro_id`),
  ADD KEY `empresa_id` (`empresa_id`,`area_id`),
  ADD KEY `fk_trabajador_area_zona` (`empresa_id`,`zona_id`,`area_id`);

--
-- Indices de la tabla `trabajador_asignaciones`
--
ALTER TABLE `trabajador_asignaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_asignacion_empresa` (`empresa_id`,`id`),
  ADD KEY `ix_asignacion_periodo` (`empresa_id`,`trabajador_id`,`fecha_inicio`,`fecha_fin`),
  ADD KEY `fk_asignacion_zona` (`empresa_id`,`sede_id`,`zona_id`),
  ADD KEY `fk_asignacion_area` (`empresa_id`,`zona_id`,`area_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuario_email` (`email`);

--
-- Indices de la tabla `zonas`
--
ALTER TABLE `zonas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_zonas_empresa` (`empresa_id`,`id`),
  ADD UNIQUE KEY `uq_zona` (`empresa_id`,`sede_id`,`nombre`),
  ADD UNIQUE KEY `uq_zona_sede_id` (`empresa_id`,`sede_id`,`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `accesos_aplicacion`
--
ALTER TABLE `accesos_aplicacion`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `acciones_correctivas`
--
ALTER TABLE `acciones_correctivas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `almacenes`
--
ALTER TABLE `almacenes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `archivos`
--
ALTER TABLE `archivos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `areas`
--
ALTER TABLE `areas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `auditoria_eventos`
--
ALTER TABLE `auditoria_eventos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `capacitacion`
--
ALTER TABLE `capacitacion`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `capacitacion_formatos`
--
ALTER TABLE `capacitacion_formatos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `capacitacion_participantes`
--
ALTER TABLE `capacitacion_participantes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `capacitador`
--
ALTER TABLE `capacitador`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `checklist_evidencias`
--
ALTER TABLE `checklist_evidencias`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `checklist_plantillas`
--
ALTER TABLE `checklist_plantillas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `checklist_preguntas`
--
ALTER TABLE `checklist_preguntas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `checklist_pregunta_opciones`
--
ALTER TABLE `checklist_pregunta_opciones`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `checklist_registros`
--
ALTER TABLE `checklist_registros`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `checklist_respuestas`
--
ALTER TABLE `checklist_respuestas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `checklist_respuesta_opciones`
--
ALTER TABLE `checklist_respuesta_opciones`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `checklist_secciones`
--
ALTER TABLE `checklist_secciones`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `contratos`
--
ALTER TABLE `contratos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `documento_revisiones`
--
ALTER TABLE `documento_revisiones`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `documento_versiones`
--
ALTER TABLE `documento_versiones`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `empresa_capacitacion`
--
ALTER TABLE `empresa_capacitacion`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `empresa_modulos`
--
ALTER TABLE `empresa_modulos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `epp_catalogo`
--
ALTER TABLE `epp_catalogo`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `epp_compras`
--
ALTER TABLE `epp_compras`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `epp_compra_detalles`
--
ALTER TABLE `epp_compra_detalles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `epp_entregas`
--
ALTER TABLE `epp_entregas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `epp_entrega_detalles`
--
ALTER TABLE `epp_entrega_detalles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `epp_entrega_lotes`
--
ALTER TABLE `epp_entrega_lotes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `epp_movimientos`
--
ALTER TABLE `epp_movimientos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `epp_planillas`
--
ALTER TABLE `epp_planillas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `hallazgos`
--
ALTER TABLE `hallazgos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hallazgo_evidencias`
--
ALTER TABLE `hallazgo_evidencias`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hallazgo_seguimientos`
--
ALTER TABLE `hallazgo_seguimientos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `implementos`
--
ALTER TABLE `implementos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `implementos_catalogo`
--
ALTER TABLE `implementos_catalogo`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `implementos_categorias`
--
ALTER TABLE `implementos_categorias`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `implementos_proveedores`
--
ALTER TABLE `implementos_proveedores`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `implemento_archivos`
--
ALTER TABLE `implemento_archivos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `implemento_costos`
--
ALTER TABLE `implemento_costos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `implemento_inspecciones`
--
ALTER TABLE `implemento_inspecciones`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `implemento_instalaciones`
--
ALTER TABLE `implemento_instalaciones`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `indicadores`
--
ALTER TABLE `indicadores`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `indicador_mediciones`
--
ALTER TABLE `indicador_mediciones`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lugar_capacitacion`
--
ALTER TABLE `lugar_capacitacion`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `miembros_empresa`
--
ALTER TABLE `miembros_empresa`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de la tabla `modulos`
--
ALTER TABLE `modulos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=539;

--
-- AUTO_INCREMENT de la tabla `plataformas`
--
ALTER TABLE `plataformas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `requisitos_documentales`
--
ALTER TABLE `requisitos_documentales`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=248;

--
-- AUTO_INCREMENT de la tabla `sedes`
--
ALTER TABLE `sedes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `sesiones`
--
ALTER TABLE `sesiones`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `temas_hse`
--
ALTER TABLE `temas_hse`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tema_capacitacion`
--
ALTER TABLE `tema_capacitacion`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `trabajadores`
--
ALTER TABLE `trabajadores`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `trabajador_asignaciones`
--
ALTER TABLE `trabajador_asignaciones`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `zonas`
--
ALTER TABLE `zonas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `accesos_aplicacion`
--
ALTER TABLE `accesos_aplicacion`
  ADD CONSTRAINT `accesos_aplicacion_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `accesos_aplicacion_ibfk_2` FOREIGN KEY (`empresa_id`,`miembro_id`) REFERENCES `miembros_empresa` (`empresa_id`, `id`),
  ADD CONSTRAINT `accesos_aplicacion_ibfk_3` FOREIGN KEY (`empresa_id`,`rol_id`) REFERENCES `roles` (`empresa_id`, `id`),
  ADD CONSTRAINT `accesos_aplicacion_ibfk_4` FOREIGN KEY (`plataforma_id`) REFERENCES `plataformas` (`id`);

--
-- Filtros para la tabla `acciones_correctivas`
--
ALTER TABLE `acciones_correctivas`
  ADD CONSTRAINT `acciones_correctivas_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `acciones_correctivas_ibfk_2` FOREIGN KEY (`empresa_id`,`hallazgo_id`) REFERENCES `hallazgos` (`empresa_id`, `id`),
  ADD CONSTRAINT `acciones_correctivas_ibfk_3` FOREIGN KEY (`empresa_id`,`responsable_id`) REFERENCES `miembros_empresa` (`empresa_id`, `id`);

--
-- Filtros para la tabla `almacenes`
--
ALTER TABLE `almacenes`
  ADD CONSTRAINT `almacenes_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `almacenes_ibfk_2` FOREIGN KEY (`empresa_id`,`sede_id`) REFERENCES `sedes` (`empresa_id`, `id`);

--
-- Filtros para la tabla `archivos`
--
ALTER TABLE `archivos`
  ADD CONSTRAINT `archivos_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `archivos_ibfk_2` FOREIGN KEY (`empresa_id`,`subido_por`) REFERENCES `miembros_empresa` (`empresa_id`, `id`);

--
-- Filtros para la tabla `areas`
--
ALTER TABLE `areas`
  ADD CONSTRAINT `areas_empresa_id_zona_id_foreign` FOREIGN KEY (`empresa_id`,`zona_id`) REFERENCES `zonas` (`empresa_id`, `id`),
  ADD CONSTRAINT `areas_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `auditoria_eventos`
--
ALTER TABLE `auditoria_eventos`
  ADD CONSTRAINT `auditoria_eventos_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `auditoria_eventos_ibfk_2` FOREIGN KEY (`empresa_id`,`actor_id`) REFERENCES `miembros_empresa` (`empresa_id`, `id`);

--
-- Filtros para la tabla `capacitacion`
--
ALTER TABLE `capacitacion`
  ADD CONSTRAINT `capacitacion_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `fk_cap_capacitador` FOREIGN KEY (`empresa_id`,`capacitador_id`) REFERENCES `capacitador` (`empresa_id`, `id`),
  ADD CONSTRAINT `fk_cap_empresa_capacitacion` FOREIGN KEY (`empresa_id`,`empresa_capacitacion_id`) REFERENCES `empresa_capacitacion` (`empresa_id`, `id`),
  ADD CONSTRAINT `fk_cap_lugar_capacitacion` FOREIGN KEY (`empresa_id`,`lugar_capacitacion_id`) REFERENCES `lugar_capacitacion` (`empresa_id`, `id`),
  ADD CONSTRAINT `fk_cap_responsable` FOREIGN KEY (`empresa_id`,`responsable_registro_id`) REFERENCES `miembros_empresa` (`empresa_id`, `usuario_id`),
  ADD CONSTRAINT `fk_cap_tema_capacitacion` FOREIGN KEY (`empresa_id`,`tema_capacitacion_id`) REFERENCES `tema_capacitacion` (`empresa_id`, `id`);

--
-- Filtros para la tabla `capacitacion_formatos`
--
ALTER TABLE `capacitacion_formatos`
  ADD CONSTRAINT `capacitacion_formatos_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `capacitacion_participantes`
--
ALTER TABLE `capacitacion_participantes`
  ADD CONSTRAINT `capacitacion_participantes_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `fk_cap_part_capacitacion` FOREIGN KEY (`empresa_id`,`capacitacion_id`) REFERENCES `capacitacion` (`empresa_id`, `id`),
  ADD CONSTRAINT `fk_cap_part_trabajador` FOREIGN KEY (`empresa_id`,`trabajador_id`) REFERENCES `trabajadores` (`empresa_id`, `id`);

--
-- Filtros para la tabla `capacitador`
--
ALTER TABLE `capacitador`
  ADD CONSTRAINT `capacitador_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `checklist_evidencias`
--
ALTER TABLE `checklist_evidencias`
  ADD CONSTRAINT `checklist_evidencias_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `checklist_evidencias_ibfk_2` FOREIGN KEY (`empresa_id`,`respuesta_id`) REFERENCES `checklist_respuestas` (`empresa_id`, `id`),
  ADD CONSTRAINT `checklist_evidencias_ibfk_3` FOREIGN KEY (`empresa_id`,`archivo_id`) REFERENCES `archivos` (`empresa_id`, `id`);

--
-- Filtros para la tabla `checklist_plantillas`
--
ALTER TABLE `checklist_plantillas`
  ADD CONSTRAINT `checklist_plantillas_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `checklist_preguntas`
--
ALTER TABLE `checklist_preguntas`
  ADD CONSTRAINT `checklist_preguntas_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `checklist_preguntas_ibfk_2` FOREIGN KEY (`empresa_id`,`plantilla_id`) REFERENCES `checklist_plantillas` (`empresa_id`, `id`),
  ADD CONSTRAINT `checklist_preguntas_ibfk_3` FOREIGN KEY (`empresa_id`,`plantilla_id`,`seccion_id`) REFERENCES `checklist_secciones` (`empresa_id`, `plantilla_id`, `id`);

--
-- Filtros para la tabla `checklist_pregunta_opciones`
--
ALTER TABLE `checklist_pregunta_opciones`
  ADD CONSTRAINT `checklist_pregunta_opciones_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `checklist_pregunta_opciones_ibfk_2` FOREIGN KEY (`empresa_id`,`pregunta_id`) REFERENCES `checklist_preguntas` (`empresa_id`, `id`);

--
-- Filtros para la tabla `checklist_registros`
--
ALTER TABLE `checklist_registros`
  ADD CONSTRAINT `checklist_registros_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `checklist_registros_ibfk_2` FOREIGN KEY (`empresa_id`,`plantilla_id`) REFERENCES `checklist_plantillas` (`empresa_id`, `id`),
  ADD CONSTRAINT `checklist_registros_ibfk_3` FOREIGN KEY (`empresa_id`,`area_id`) REFERENCES `areas` (`empresa_id`, `id`),
  ADD CONSTRAINT `checklist_registros_ibfk_4` FOREIGN KEY (`empresa_id`,`realizado_por`) REFERENCES `miembros_empresa` (`empresa_id`, `id`);

--
-- Filtros para la tabla `checklist_respuestas`
--
ALTER TABLE `checklist_respuestas`
  ADD CONSTRAINT `checklist_respuestas_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `checklist_respuestas_ibfk_2` FOREIGN KEY (`empresa_id`,`plantilla_id`,`registro_id`) REFERENCES `checklist_registros` (`empresa_id`, `plantilla_id`, `id`),
  ADD CONSTRAINT `checklist_respuestas_ibfk_3` FOREIGN KEY (`empresa_id`,`plantilla_id`,`pregunta_id`) REFERENCES `checklist_preguntas` (`empresa_id`, `plantilla_id`, `id`);

--
-- Filtros para la tabla `checklist_respuesta_opciones`
--
ALTER TABLE `checklist_respuesta_opciones`
  ADD CONSTRAINT `checklist_respuesta_opciones_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `checklist_respuesta_opciones_ibfk_2` FOREIGN KEY (`empresa_id`,`pregunta_id`,`respuesta_id`) REFERENCES `checklist_respuestas` (`empresa_id`, `pregunta_id`, `id`),
  ADD CONSTRAINT `checklist_respuesta_opciones_ibfk_3` FOREIGN KEY (`empresa_id`,`pregunta_id`,`opcion_id`) REFERENCES `checklist_pregunta_opciones` (`empresa_id`, `pregunta_id`, `id`);

--
-- Filtros para la tabla `checklist_secciones`
--
ALTER TABLE `checklist_secciones`
  ADD CONSTRAINT `checklist_secciones_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `checklist_secciones_ibfk_2` FOREIGN KEY (`empresa_id`,`plantilla_id`) REFERENCES `checklist_plantillas` (`empresa_id`, `id`);

--
-- Filtros para la tabla `contratos`
--
ALTER TABLE `contratos`
  ADD CONSTRAINT `contratos_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `documento_revisiones`
--
ALTER TABLE `documento_revisiones`
  ADD CONSTRAINT `documento_revisiones_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `documento_revisiones_ibfk_2` FOREIGN KEY (`empresa_id`,`documento_version_id`) REFERENCES `documento_versiones` (`empresa_id`, `id`),
  ADD CONSTRAINT `documento_revisiones_ibfk_3` FOREIGN KEY (`empresa_id`,`revisor_id`) REFERENCES `miembros_empresa` (`empresa_id`, `id`);

--
-- Filtros para la tabla `documento_versiones`
--
ALTER TABLE `documento_versiones`
  ADD CONSTRAINT `documento_versiones_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `documento_versiones_ibfk_2` FOREIGN KEY (`empresa_id`,`requisito_id`) REFERENCES `requisitos_documentales` (`empresa_id`, `id`),
  ADD CONSTRAINT `documento_versiones_ibfk_3` FOREIGN KEY (`empresa_id`,`archivo_id`) REFERENCES `archivos` (`empresa_id`, `id`),
  ADD CONSTRAINT `documento_versiones_ibfk_4` FOREIGN KEY (`empresa_id`,`revisado_por`) REFERENCES `miembros_empresa` (`empresa_id`, `id`);

--
-- Filtros para la tabla `empresa_capacitacion`
--
ALTER TABLE `empresa_capacitacion`
  ADD CONSTRAINT `empresa_capacitacion_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `empresa_modulos`
--
ALTER TABLE `empresa_modulos`
  ADD CONSTRAINT `empresa_modulos_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `empresa_modulos_ibfk_2` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`);

--
-- Filtros para la tabla `epp_catalogo`
--
ALTER TABLE `epp_catalogo`
  ADD CONSTRAINT `epp_catalogo_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `epp_catalogo_ibfk_2` FOREIGN KEY (`empresa_id`,`ficha_archivo_id`) REFERENCES `archivos` (`empresa_id`, `id`);

--
-- Filtros para la tabla `epp_compras`
--
ALTER TABLE `epp_compras`
  ADD CONSTRAINT `epp_compras_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `epp_compras_empresa_id_registrado_por_foreign` FOREIGN KEY (`empresa_id`,`registrado_por`) REFERENCES `miembros_empresa` (`empresa_id`, `id`);

--
-- Filtros para la tabla `epp_compra_detalles`
--
ALTER TABLE `epp_compra_detalles`
  ADD CONSTRAINT `epp_compra_detalles_empresa_id_almacen_id_foreign` FOREIGN KEY (`empresa_id`,`almacen_id`) REFERENCES `almacenes` (`empresa_id`, `id`),
  ADD CONSTRAINT `epp_compra_detalles_empresa_id_compra_id_foreign` FOREIGN KEY (`empresa_id`,`compra_id`) REFERENCES `epp_compras` (`empresa_id`, `id`),
  ADD CONSTRAINT `epp_compra_detalles_empresa_id_epp_id_foreign` FOREIGN KEY (`empresa_id`,`epp_id`) REFERENCES `epp_catalogo` (`empresa_id`, `id`);

--
-- Filtros para la tabla `epp_entregas`
--
ALTER TABLE `epp_entregas`
  ADD CONSTRAINT `epp_entregas_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `epp_entregas_ibfk_2` FOREIGN KEY (`empresa_id`,`trabajador_id`) REFERENCES `trabajadores` (`empresa_id`, `id`),
  ADD CONSTRAINT `epp_entregas_ibfk_3` FOREIGN KEY (`empresa_id`,`entregado_por`) REFERENCES `miembros_empresa` (`empresa_id`, `id`),
  ADD CONSTRAINT `epp_entregas_ibfk_4` FOREIGN KEY (`empresa_id`,`firma_archivo_id`) REFERENCES `archivos` (`empresa_id`, `id`);

--
-- Filtros para la tabla `epp_entrega_detalles`
--
ALTER TABLE `epp_entrega_detalles`
  ADD CONSTRAINT `epp_entrega_detalles_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `epp_entrega_detalles_ibfk_2` FOREIGN KEY (`empresa_id`,`entrega_id`) REFERENCES `epp_entregas` (`empresa_id`, `id`),
  ADD CONSTRAINT `epp_entrega_detalles_ibfk_3` FOREIGN KEY (`empresa_id`,`epp_id`) REFERENCES `epp_catalogo` (`empresa_id`, `id`),
  ADD CONSTRAINT `epp_entrega_detalles_ibfk_4` FOREIGN KEY (`empresa_id`,`almacen_id`) REFERENCES `almacenes` (`empresa_id`, `id`);

--
-- Filtros para la tabla `epp_entrega_lotes`
--
ALTER TABLE `epp_entrega_lotes`
  ADD CONSTRAINT `epp_entrega_lotes_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `epp_movimientos`
--
ALTER TABLE `epp_movimientos`
  ADD CONSTRAINT `epp_movimientos_empresa_id_compra_detalle_id_foreign` FOREIGN KEY (`empresa_id`,`compra_detalle_id`) REFERENCES `epp_compra_detalles` (`empresa_id`, `id`),
  ADD CONSTRAINT `epp_movimientos_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `epp_movimientos_ibfk_2` FOREIGN KEY (`empresa_id`,`epp_id`) REFERENCES `epp_catalogo` (`empresa_id`, `id`),
  ADD CONSTRAINT `epp_movimientos_ibfk_3` FOREIGN KEY (`empresa_id`,`almacen_id`) REFERENCES `almacenes` (`empresa_id`, `id`),
  ADD CONSTRAINT `epp_movimientos_ibfk_4` FOREIGN KEY (`empresa_id`,`registrado_por`) REFERENCES `miembros_empresa` (`empresa_id`, `id`),
  ADD CONSTRAINT `epp_movimientos_ibfk_5` FOREIGN KEY (`empresa_id`,`epp_id`,`almacen_id`,`entrega_detalle_id`) REFERENCES `epp_entrega_detalles` (`empresa_id`, `epp_id`, `almacen_id`, `id`);

--
-- Filtros para la tabla `epp_planillas`
--
ALTER TABLE `epp_planillas`
  ADD CONSTRAINT `epp_planillas_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `epp_planillas_empresa_id_registrado_por_foreign` FOREIGN KEY (`empresa_id`,`registrado_por`) REFERENCES `miembros_empresa` (`empresa_id`, `id`);

--
-- Filtros para la tabla `hallazgos`
--
ALTER TABLE `hallazgos`
  ADD CONSTRAINT `hallazgos_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `hallazgos_ibfk_2` FOREIGN KEY (`empresa_id`,`area_id`) REFERENCES `areas` (`empresa_id`, `id`),
  ADD CONSTRAINT `hallazgos_ibfk_3` FOREIGN KEY (`empresa_id`,`respuesta_id`) REFERENCES `checklist_respuestas` (`empresa_id`, `id`),
  ADD CONSTRAINT `hallazgos_ibfk_4` FOREIGN KEY (`empresa_id`,`reportado_por`) REFERENCES `miembros_empresa` (`empresa_id`, `id`),
  ADD CONSTRAINT `hallazgos_ibfk_5` FOREIGN KEY (`empresa_id`,`responsable_id`) REFERENCES `miembros_empresa` (`empresa_id`, `id`),
  ADD CONSTRAINT `hallazgos_ibfk_6` FOREIGN KEY (`empresa_id`,`cerrado_por`) REFERENCES `miembros_empresa` (`empresa_id`, `id`);

--
-- Filtros para la tabla `hallazgo_evidencias`
--
ALTER TABLE `hallazgo_evidencias`
  ADD CONSTRAINT `hallazgo_evidencias_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `hallazgo_evidencias_ibfk_2` FOREIGN KEY (`empresa_id`,`hallazgo_id`) REFERENCES `hallazgos` (`empresa_id`, `id`),
  ADD CONSTRAINT `hallazgo_evidencias_ibfk_3` FOREIGN KEY (`empresa_id`,`archivo_id`) REFERENCES `archivos` (`empresa_id`, `id`);

--
-- Filtros para la tabla `hallazgo_seguimientos`
--
ALTER TABLE `hallazgo_seguimientos`
  ADD CONSTRAINT `hallazgo_seguimientos_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `hallazgo_seguimientos_ibfk_2` FOREIGN KEY (`empresa_id`,`hallazgo_id`) REFERENCES `hallazgos` (`empresa_id`, `id`),
  ADD CONSTRAINT `hallazgo_seguimientos_ibfk_3` FOREIGN KEY (`empresa_id`,`autor_id`) REFERENCES `miembros_empresa` (`empresa_id`, `id`);

--
-- Filtros para la tabla `implementos`
--
ALTER TABLE `implementos`
  ADD CONSTRAINT `implementos_catalogo_id_foreign` FOREIGN KEY (`catalogo_id`) REFERENCES `implementos_catalogo` (`id`),
  ADD CONSTRAINT `implementos_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `implementos_proveedor_id_foreign` FOREIGN KEY (`proveedor_id`) REFERENCES `implementos_proveedores` (`id`);

--
-- Filtros para la tabla `implementos_catalogo`
--
ALTER TABLE `implementos_catalogo`
  ADD CONSTRAINT `implementos_catalogo_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `implementos_categorias` (`id`),
  ADD CONSTRAINT `implementos_catalogo_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `implementos_categorias`
--
ALTER TABLE `implementos_categorias`
  ADD CONSTRAINT `implementos_categorias_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `implementos_proveedores`
--
ALTER TABLE `implementos_proveedores`
  ADD CONSTRAINT `implementos_proveedores_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `implemento_archivos`
--
ALTER TABLE `implemento_archivos`
  ADD CONSTRAINT `implemento_archivos_archivo_id_foreign` FOREIGN KEY (`archivo_id`) REFERENCES `archivos` (`id`),
  ADD CONSTRAINT `implemento_archivos_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `implemento_archivos_implemento_id_foreign` FOREIGN KEY (`implemento_id`) REFERENCES `implementos` (`id`),
  ADD CONSTRAINT `implemento_archivos_inspeccion_id_foreign` FOREIGN KEY (`inspeccion_id`) REFERENCES `implemento_inspecciones` (`id`);

--
-- Filtros para la tabla `implemento_costos`
--
ALTER TABLE `implemento_costos`
  ADD CONSTRAINT `implemento_costos_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `implemento_costos_implemento_id_foreign` FOREIGN KEY (`implemento_id`) REFERENCES `implementos` (`id`),
  ADD CONSTRAINT `implemento_costos_proveedor_id_foreign` FOREIGN KEY (`proveedor_id`) REFERENCES `implementos_proveedores` (`id`);

--
-- Filtros para la tabla `implemento_inspecciones`
--
ALTER TABLE `implemento_inspecciones`
  ADD CONSTRAINT `implemento_inspecciones_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `implemento_inspecciones_implemento_id_foreign` FOREIGN KEY (`implemento_id`) REFERENCES `implementos` (`id`),
  ADD CONSTRAINT `implemento_inspecciones_instalacion_id_foreign` FOREIGN KEY (`instalacion_id`) REFERENCES `implemento_instalaciones` (`id`),
  ADD CONSTRAINT `implemento_inspecciones_realizado_por_foreign` FOREIGN KEY (`realizado_por`) REFERENCES `miembros_empresa` (`id`),
  ADD CONSTRAINT `implemento_inspecciones_responsable_id_foreign` FOREIGN KEY (`responsable_id`) REFERENCES `miembros_empresa` (`id`);

--
-- Filtros para la tabla `implemento_instalaciones`
--
ALTER TABLE `implemento_instalaciones`
  ADD CONSTRAINT `implemento_instalaciones_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`),
  ADD CONSTRAINT `implemento_instalaciones_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `implemento_instalaciones_implemento_id_foreign` FOREIGN KEY (`implemento_id`) REFERENCES `implementos` (`id`);

--
-- Filtros para la tabla `indicadores`
--
ALTER TABLE `indicadores`
  ADD CONSTRAINT `indicadores_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `indicadores_ibfk_2` FOREIGN KEY (`empresa_id`,`tema_id`) REFERENCES `temas_hse` (`empresa_id`, `id`);

--
-- Filtros para la tabla `indicador_mediciones`
--
ALTER TABLE `indicador_mediciones`
  ADD CONSTRAINT `indicador_mediciones_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `indicador_mediciones_ibfk_2` FOREIGN KEY (`empresa_id`,`indicador_id`) REFERENCES `indicadores` (`empresa_id`, `id`),
  ADD CONSTRAINT `indicador_mediciones_ibfk_3` FOREIGN KEY (`empresa_id`,`registrado_por`) REFERENCES `miembros_empresa` (`empresa_id`, `id`),
  ADD CONSTRAINT `indicador_mediciones_ibfk_4` FOREIGN KEY (`empresa_id`,`evidencia_archivo_id`) REFERENCES `archivos` (`empresa_id`, `id`);

--
-- Filtros para la tabla `lugar_capacitacion`
--
ALTER TABLE `lugar_capacitacion`
  ADD CONSTRAINT `lugar_capacitacion_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `miembros_empresa`
--
ALTER TABLE `miembros_empresa`
  ADD CONSTRAINT `miembros_empresa_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `miembros_empresa_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD CONSTRAINT `permisos_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`);

--
-- Filtros para la tabla `requisitos_documentales`
--
ALTER TABLE `requisitos_documentales`
  ADD CONSTRAINT `requisitos_documentales_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `requisitos_documentales_ibfk_2` FOREIGN KEY (`empresa_id`,`tema_id`) REFERENCES `temas_hse` (`empresa_id`, `id`);

--
-- Filtros para la tabla `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  ADD CONSTRAINT `rol_permisos_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `rol_permisos_ibfk_2` FOREIGN KEY (`empresa_id`,`rol_id`) REFERENCES `roles` (`empresa_id`, `id`),
  ADD CONSTRAINT `rol_permisos_ibfk_3` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`);

--
-- Filtros para la tabla `sedes`
--
ALTER TABLE `sedes`
  ADD CONSTRAINT `sedes_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `sesiones`
--
ALTER TABLE `sesiones`
  ADD CONSTRAINT `sesiones_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `sesiones_ibfk_2` FOREIGN KEY (`empresa_id`,`acceso_id`) REFERENCES `accesos_aplicacion` (`empresa_id`, `id`);

--
-- Filtros para la tabla `temas_hse`
--
ALTER TABLE `temas_hse`
  ADD CONSTRAINT `temas_hse_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `temas_hse_ibfk_2` FOREIGN KEY (`empresa_id`,`responsable_id`) REFERENCES `miembros_empresa` (`empresa_id`, `id`),
  ADD CONSTRAINT `temas_hse_ibfk_3` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`);

--
-- Filtros para la tabla `tema_capacitacion`
--
ALTER TABLE `tema_capacitacion`
  ADD CONSTRAINT `tema_capacitacion_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `trabajadores`
--
ALTER TABLE `trabajadores`
  ADD CONSTRAINT `fk_trabajador_area_zona` FOREIGN KEY (`empresa_id`,`zona_id`,`area_id`) REFERENCES `areas` (`empresa_id`, `zona_id`, `id`),
  ADD CONSTRAINT `fk_trabajador_zona` FOREIGN KEY (`empresa_id`,`zona_id`) REFERENCES `zonas` (`empresa_id`, `id`),
  ADD CONSTRAINT `trabajadores_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `trabajadores_ibfk_2` FOREIGN KEY (`empresa_id`,`area_id`) REFERENCES `areas` (`empresa_id`, `id`),
  ADD CONSTRAINT `trabajadores_ibfk_3` FOREIGN KEY (`empresa_id`,`miembro_id`) REFERENCES `miembros_empresa` (`empresa_id`, `id`);

--
-- Filtros para la tabla `trabajador_asignaciones`
--
ALTER TABLE `trabajador_asignaciones`
  ADD CONSTRAINT `fk_asignacion_area` FOREIGN KEY (`empresa_id`,`zona_id`,`area_id`) REFERENCES `areas` (`empresa_id`, `zona_id`, `id`),
  ADD CONSTRAINT `fk_asignacion_zona` FOREIGN KEY (`empresa_id`,`sede_id`,`zona_id`) REFERENCES `zonas` (`empresa_id`, `sede_id`, `id`),
  ADD CONSTRAINT `trabajador_asignaciones_empresa_id_trabajador_id_foreign` FOREIGN KEY (`empresa_id`,`trabajador_id`) REFERENCES `trabajadores` (`empresa_id`, `id`);

--
-- Filtros para la tabla `zonas`
--
ALTER TABLE `zonas`
  ADD CONSTRAINT `zonas_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `zonas_empresa_id_sede_id_foreign` FOREIGN KEY (`empresa_id`,`sede_id`) REFERENCES `sedes` (`empresa_id`, `id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
