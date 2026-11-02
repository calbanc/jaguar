<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_epp_stock');
        Schema::create('epp_compras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->uuid('clave_operacion');
            $table->string('huella', 64);
            $table->string('proveedor', 180);
            $table->string('proveedor_ruc', 11)->nullable();
            $table->string('proveedor_clave', 180);
            $table->string('factura', 60);
            $table->date('fecha');
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('costo_total', 24, 6);
            $table->unsignedBigInteger('registrado_por');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'id']);
            $table->unique(['empresa_id', 'clave_operacion']);
            $table->unique(['empresa_id', 'proveedor_clave', 'factura'], 'uq_epp_compra_factura');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign(['empresa_id', 'registrado_por'])->references(['empresa_id', 'id'])->on('miembros_empresa');
        });
        Schema::create('epp_compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('compra_id');
            $table->unsignedBigInteger('epp_id');
            $table->unsignedBigInteger('almacen_id');
            $table->decimal('cantidad', 12, 3);
            $table->decimal('costo_unitario', 18, 6);
            $table->decimal('costo_total', 24, 6);
            $table->timestamps();
            $table->unique(['empresa_id', 'id']);
            foreach (['compra_id' => 'epp_compras', 'epp_id' => 'epp_catalogo', 'almacen_id' => 'almacenes'] as $field => $parent) {
                $table->foreign(['empresa_id', $field])->references(['empresa_id', 'id'])->on($parent);
            }
        });
        Schema::table('epp_movimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('compra_detalle_id')->nullable();
            $table->decimal('costo_unitario', 18, 6)->nullable();
            $table->decimal('costo_total', 24, 6)->nullable();
            $table->decimal('saldo_valor', 24, 6)->nullable();
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign(['empresa_id', 'compra_detalle_id'])->references(['empresa_id', 'id'])->on('epp_compra_detalles');
            }
        });
        Schema::table('epp_entrega_detalles', function (Blueprint $table) {
            $table->decimal('costo_unitario', 18, 6)->nullable();
            $table->decimal('costo_total', 24, 6)->nullable();
        });

        DB::statement("CREATE VIEW v_epp_stock AS
            SELECT m.empresa_id, m.almacen_id, m.epp_id, a.nombre almacen, e.nombre epp, e.unidad,
                SUM(m.cantidad) stock,
                CASE WHEN SUM(m.cantidad) = 0 THEN 0 ELSE ultimo.saldo_valor END valor_stock,
                CASE WHEN SUM(m.cantidad) = 0 THEN 0 ELSE (ultimo.saldo_valor * 1.0) / SUM(m.cantidad) END costo_promedio,
                'PEN' moneda
            FROM epp_movimientos m
            JOIN almacenes a ON a.empresa_id = m.empresa_id AND a.id = m.almacen_id
            JOIN epp_catalogo e ON e.empresa_id = m.empresa_id AND e.id = m.epp_id
            JOIN epp_movimientos ultimo ON ultimo.id = (SELECT MAX(u.id) FROM epp_movimientos u WHERE u.empresa_id = m.empresa_id AND u.almacen_id = m.almacen_id AND u.epp_id = m.epp_id)
            GROUP BY m.empresa_id, m.almacen_id, m.epp_id, a.nombre, e.nombre, e.unidad, ultimo.saldo_valor");
    }

    public function down(): void
    {
        throw new RuntimeException('Las compras y la valorización conservan historia; la reversión requiere un procedimiento manual.');
    }
};
