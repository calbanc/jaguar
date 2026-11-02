-- Ejecutar en la base de datos existente de Jaguar HSE.
-- Equivalente a 2026_10_24_205617_create_capacitacion_formatos_table.
CREATE TABLE IF NOT EXISTS `capacitacion_formatos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `empresa_id` BIGINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(200) NOT NULL,
    `diseno` JSON NOT NULL,
    `version` INT UNSIGNED NOT NULL DEFAULT 1,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `capacitacion_formatos_empresa_id_activo_index` (`empresa_id`, `activo`),
    CONSTRAINT `capacitacion_formatos_empresa_id_foreign`
        FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registrar la migración para que Laravel no intente crear nuevamente la tabla.
-- La tabla migrations ya debe existir en esta instalación de Laravel.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_24_205617_create_capacitacion_formatos_table',
       COALESCE(MAX(`batch`), 0) + 1
FROM `migrations`
HAVING NOT EXISTS (
    SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_10_24_205617_create_capacitacion_formatos_table'
);
