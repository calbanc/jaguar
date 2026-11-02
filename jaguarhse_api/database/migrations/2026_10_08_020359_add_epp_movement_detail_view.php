<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE VIEW v_epp_movimiento_detalle AS
            SELECT m.*, e.nombre epp, e.unidad, a.nombre almacen,
                CASE WHEN m.compra_detalle_id IS NOT NULL THEN 'Compra'
                    WHEN m.tipo = 'entrega' THEN 'Salida por entrega'
                    WHEN m.tipo = 'devolucion' THEN 'Devolución'
                    WHEN m.tipo = 'reverso' THEN 'Anulación de entrega'
                    WHEN m.tipo = 'ajuste' THEN 'Ajuste' ELSE 'Entrada' END movimiento,
                CASE WHEN m.cantidad > 0 THEN m.cantidad ELSE 0 END entrada,
                CASE WHEN m.cantidad < 0 THEN -m.cantidad ELSE 0 END salida,
                w.nombres trabajador_nombres, w.apellidos trabajador_apellidos, w.numero_documento,
                c.proveedor, c.factura, c.moneda moneda_compra, c.tipo_cambio tipo_cambio_compra,
                cd.costo_unitario_original, cd.costo_total_original
            FROM epp_movimientos m
            JOIN epp_catalogo e ON e.empresa_id = m.empresa_id AND e.id = m.epp_id
            JOIN almacenes a ON a.empresa_id = m.empresa_id AND a.id = m.almacen_id
            LEFT JOIN epp_compra_detalles cd ON cd.empresa_id = m.empresa_id AND cd.id = m.compra_detalle_id
            LEFT JOIN epp_compras c ON c.empresa_id = cd.empresa_id AND c.id = cd.compra_id
            LEFT JOIN epp_entrega_detalles d ON d.empresa_id = m.empresa_id AND d.id = m.entrega_detalle_id
            LEFT JOIN epp_entregas t ON t.empresa_id = d.empresa_id AND t.id = d.entrega_id
            LEFT JOIN trabajadores w ON w.empresa_id = t.empresa_id AND w.id = t.trabajador_id");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_epp_movimiento_detalle');
    }
};
