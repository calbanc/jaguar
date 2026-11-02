<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HseAccess;
use App\Services\HseData;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EppPurchaseController extends Controller
{
    public function __construct(private HseData $data, private HseAccess $access, private InventoryService $inventory) {}

    public function store(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'almacenes', 'crear');
        $input = $request->validate([
            'moneda' => 'sometimes|in:PEN,USD',
            'tipo_cambio' => 'required_if:moneda,USD|nullable|numeric|decimal:0,6|gt:0|max:1000',
            'clave_operacion' => 'required|uuid', 'proveedor' => 'required|string|max:180',
            'proveedor_ruc' => 'nullable|regex:/^[0-9]{11}$/', 'factura' => 'required|string|max:60',
            'fecha' => 'required|date_format:Y-m-d', 'observacion' => 'nullable|string|max:10000',
            'detalles' => 'required|array|min:1|max:100', 'detalles.*.epp_id' => 'required|integer|min:1',
            'detalles.*.almacen_id' => 'required|integer|min:1',
            'detalles.*.cantidad' => 'required|numeric|decimal:0,3|gt:0|max:1000000',
            'detalles.*.costo_unitario' => 'required|numeric|decimal:0,6|gt:0|max:1000000000',
        ]);
        $currency = $input['moneda'] ?? 'PEN';
        $rate = $currency === 'USD' ? bcadd((string) $input['tipo_cambio'], '0', 6) : '1.000000';
        if ($currency === 'PEN' && isset($input['tipo_cambio']) && bccomp((string) $input['tipo_cambio'], '1', 6) !== 0) {
            throw ValidationException::withMessages(['tipo_cambio' => 'El tipo de cambio para soles debe ser 1.']);
        }
        if (isset($input['tipo_cambio'])) {
            $input['tipo_cambio'] = $rate;
        }
        $input['proveedor'] = trim($input['proveedor']);
        $input['factura'] = mb_strtoupper(trim($input['factura']));
        foreach ($input['detalles'] as &$line) {
            $line = ['epp_id' => (int) $line['epp_id'], 'almacen_id' => (int) $line['almacen_id'],
                'cantidad' => bcadd((string) $line['cantidad'], '0', 3), 'costo_unitario' => bcadd((string) $line['costo_unitario'], '0', 6)];
        }
        unset($line);
        $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($request, $context, $input, $hash, $currency, $rate) {
            $this->data->lock($context);
            $prior = $this->data->query('epp_compras', $context)->where('clave_operacion', $input['clave_operacion'])->first();
            if ($prior) {
                abort_unless(hash_equals($prior->huella, $hash), 409, 'La operación ya se registró con datos distintos.');

                return response()->json(['data' => $this->data->present('epp_compras', $prior)]);
            }
            $supplierKey = $input['proveedor_ruc'] ?? mb_strtoupper($input['proveedor']);
            if ($this->data->query('epp_compras', $context)->where('proveedor_clave', $supplierKey)->where('factura', $input['factura'])->exists()) {
                throw ValidationException::withMessages(['factura' => 'Esta factura ya está registrada para el proveedor.']);
            }
            $total = '0';
            $originalTotal = '0';
            foreach ($input['detalles'] as $line) {
                foreach (['epp_id' => 'epp_catalogo', 'almacen_id' => 'almacenes'] as $field => $table) {
                    $record = $this->data->find($table, $context, $line[$field]);
                    abort_unless($record->activo, 422, 'El EPP y el almacén deben estar activos.');
                }
                $unitPen = bcmul($line['costo_unitario'], $rate, 6);
                if (bccomp($unitPen, '0', 6) <= 0 || bccomp($unitPen, '999999999999.999999', 6) > 0) {
                    throw ValidationException::withMessages(['tipo_cambio' => 'El costo convertido está fuera del rango permitido.']);
                }
                $originalTotal = bcadd($originalTotal, bcmul($line['cantidad'], $line['costo_unitario'], 6), 6);
                $total = bcadd($total, bcmul($line['cantidad'], $unitPen, 6), 6);
            }
            $purchase = $this->data->insert('epp_compras', $context, [
                'clave_operacion' => $input['clave_operacion'], 'huella' => $hash,
                'proveedor' => $input['proveedor'], 'proveedor_ruc' => $input['proveedor_ruc'] ?? null,
                'proveedor_clave' => $supplierKey, 'factura' => $input['factura'], 'fecha' => $input['fecha'],
                'moneda' => $currency, 'tipo_cambio' => $rate, 'costo_total_original' => $originalTotal, 'costo_total' => $total, 'registrado_por' => $context->miembro_id,
                'observacion' => $input['observacion'] ?? null,
            ]);
            foreach ($input['detalles'] as $index => $line) {
                $originalUnit = $line['costo_unitario'];
                $line['costo_unitario'] = bcmul($originalUnit, $rate, 6);
                $detail = $this->data->insert('epp_compra_detalles', $context, $line + [
                    'costo_unitario_original' => $originalUnit, 'costo_total_original' => bcmul($line['cantidad'], $originalUnit, 6),
                    'compra_id' => $purchase->id, 'costo_total' => bcmul($line['cantidad'], $line['costo_unitario'], 6),
                ]);
                $movement = $this->inventory->movement($context, $line + ['tipo' => 'entrada',
                    'compra_detalle_id' => $detail->id, 'clave_operacion' => 'compra:'.$purchase->id.':'.$index,
                    'observacion' => 'Compra '.$purchase->factura.' · '.$purchase->proveedor]);
                $this->data->audit($request, 'epp_movimientos', null, $movement, 'compra');
            }
            $this->data->audit($request, 'epp_compras', null, $purchase, 'registrar_compra');

            return response()->json(['data' => $this->data->present('epp_compras', $purchase)], 201);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'almacenes', 'ver');
        $purchase = $this->data->present('epp_compras', $this->data->find('epp_compras', $context, $id));
        $lines = DB::table('epp_compra_detalles as d')
            ->join('epp_catalogo as e', 'e.id', '=', 'd.epp_id')->join('almacenes as a', 'a.id', '=', 'd.almacen_id')
            ->where('d.empresa_id', $context->empresa_id)->where('d.compra_id', $id)
            ->select('d.*', 'e.nombre as epp', 'e.unidad', 'a.nombre as almacen')->get();

        return response()->json(['data' => $purchase, 'detalles' => $lines]);
    }
}
