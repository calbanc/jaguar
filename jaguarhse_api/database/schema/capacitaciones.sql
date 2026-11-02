-- Complemento para el jaguarhse.sql proporcionado (MySQL 8.0).
-- Padre: capacitacion (una planilla). Hijos: capacitacion_participantes.
-- La relación por (empresa_id, capacitacion_id) ya existe y se conserva.
-- Aplicar una sola vez, sin registros concurrentes durante la actualización.
-- Alternativa a la migración 2026_10_24_012905_add_planilla_number_to_capacitacion.
-- Usar este SQL O la migración; no ejecutar ambos.

ALTER TABLE `capacitacion`
    ADD COLUMN `numero_planilla` BIGINT UNSIGNED NULL;

-- Numerar las planillas existentes por empresa e ID, incluidas las inactivas.
CREATE TEMPORARY TABLE `tmp_capacitacion_numeracion` AS
SELECT `id`, ROW_NUMBER() OVER (
    PARTITION BY `empresa_id` ORDER BY `id`
) AS `numero_planilla`
FROM `capacitacion`;

UPDATE `capacitacion` AS c
INNER JOIN `tmp_capacitacion_numeracion` AS n ON n.`id` = c.`id`
SET c.`numero_planilla` = n.`numero_planilla`;

DROP TEMPORARY TABLE `tmp_capacitacion_numeracion`;

ALTER TABLE `capacitacion`
    MODIFY COLUMN `numero_planilla` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uq_capacitacion_planilla_empresa` (`empresa_id`, `numero_planilla`);

-- Registrar la alternativa SQL para que Laravel no repita el ALTER TABLE.
SELECT COALESCE(MAX(`batch`), 0) + 1,
       COALESCE(SUM(`migration` = '2026_10_24_012905_add_planilla_number_to_capacitacion'), 0)
INTO @capacitacion_batch, @capacitacion_migracion_aplicada
FROM `migrations`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_24_012905_add_planilla_number_to_capacitacion', @capacitacion_batch
WHERE @capacitacion_migracion_aplicada = 0;

-- Los nuevos números los asigna la API dentro de una transacción con bloqueo
-- de la empresa: MAX(numero_planilla) + 1, incluyendo las planillas inactivas.
-- El cliente no debe enviar numero_planilla; siempre debe enviar empresa_id.
