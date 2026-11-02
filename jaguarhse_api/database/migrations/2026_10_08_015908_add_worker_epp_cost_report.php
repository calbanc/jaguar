<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE VIEW v_epp_trabajador_detalle AS
            SELECT d.id, d.empresa_id, t.trabajador_id, t.id entrega_id, t.fecha,
                e.nombre epp, e.unidad, a.nombre almacen,
                d.cantidad cantidad_entregada, COALESCE(r.cantidad, 0) cantidad_devuelta,
                d.cantidad - COALESCE(r.cantidad, 0) cantidad_neta,
                d.costo_unitario, d.costo_total costo_entregado,
                CASE WHEN COALESCE(r.sin_costo, 0) > 0 THEN NULL ELSE COALESCE(r.costo, 0) END costo_devuelto,
                CASE WHEN d.cantidad - COALESCE(r.cantidad, 0) = 0 THEN 0
                    WHEN d.costo_total IS NULL OR COALESCE(r.sin_costo, 0) > 0 THEN NULL
                    ELSE d.costo_total - COALESCE(r.costo, 0) END costo_neto, 'PEN' moneda
            FROM epp_entrega_detalles d
            JOIN epp_entregas t ON t.id = d.entrega_id AND t.empresa_id = d.empresa_id
            JOIN epp_catalogo e ON e.id = d.epp_id AND e.empresa_id = d.empresa_id
            JOIN almacenes a ON a.id = d.almacen_id AND a.empresa_id = d.empresa_id
            LEFT JOIN (SELECT empresa_id, entrega_detalle_id, SUM(cantidad) cantidad, SUM(costo_total) costo,
                SUM(CASE WHEN costo_total IS NULL THEN 1 ELSE 0 END) sin_costo
                FROM epp_movimientos WHERE tipo = 'devolucion' GROUP BY empresa_id, entrega_detalle_id) r
                ON r.empresa_id = d.empresa_id AND r.entrega_detalle_id = d.id
            WHERE t.estado = 'confirmada'");
        DB::statement("CREATE VIEW v_epp_costo_trabajador AS
            SELECT w.id, w.empresa_id, w.numero_documento, w.nombres, w.apellidos, w.puesto,
                COUNT(DISTINCT d.entrega_id) entregas_confirmadas,
                CASE WHEN SUM(CASE WHEN d.costo_entregado IS NULL THEN 1 ELSE 0 END) > 0 THEN NULL ELSE SUM(d.costo_entregado) END costo_entregado,
                CASE WHEN SUM(CASE WHEN d.costo_devuelto IS NULL THEN 1 ELSE 0 END) > 0 THEN NULL ELSE SUM(d.costo_devuelto) END costo_devuelto,
                CASE WHEN SUM(CASE WHEN d.costo_neto IS NULL THEN 1 ELSE 0 END) > 0 THEN NULL ELSE SUM(d.costo_neto) END costo_neto,
                SUM(CASE WHEN d.costo_neto IS NULL THEN 1 ELSE 0 END) detalles_sin_costo, 'PEN' moneda
            FROM trabajadores w JOIN v_epp_trabajador_detalle d ON d.trabajador_id = w.id AND d.empresa_id = w.empresa_id
            GROUP BY w.id, w.empresa_id, w.numero_documento, w.nombres, w.apellidos, w.puesto");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_epp_costo_trabajador');
        DB::statement('DROP VIEW IF EXISTS v_epp_trabajador_detalle');
    }
};
