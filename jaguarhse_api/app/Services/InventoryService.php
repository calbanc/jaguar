<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private HseData $data) {}

    public function transition(object $context, object $delivery, bool $cancel): object
    {
        $target = $cancel ? 'anulada' : 'confirmada';
        if ($delivery->estado === $target) {
            return $delivery;
        }
        abort_unless($delivery->estado === 'borrador' || ($cancel && $delivery->estado === 'confirmada'), 409, 'Transición inválida.');
        if (! $cancel) {
            abort_unless($this->data->find('trabajadores', $context, $delivery->trabajador_id)->activo, 422, 'Trabajador inactivo.');
        }
        $details = $this->data->query('epp_entrega_detalles', $context)->where('entrega_id', $delivery->id)->orderBy('almacen_id')->get();
        abort_unless($cancel || $details->isNotEmpty(), 422, 'La entrega necesita detalles.');
        if (! $cancel || $delivery->estado === 'confirmada') {
            foreach ($details as $detail) {
                $this->data->query('almacenes', $context)->where('id', $detail->almacen_id)->lockForUpdate()->first();
                $returned = (string) $this->data->query('epp_movimientos', $context)->where('entrega_detalle_id', $detail->id)->where('tipo', 'devolucion')->sum('cantidad');
                $quantity = $cancel ? bcsub($detail->cantidad, $returned, 3) : bcmul($detail->cantidad, '-1', 3);
                if (bccomp($quantity, '0', 3) === 0) {
                    continue;
                }
                $movement = $this->movement($context, ['epp_id' => $detail->epp_id, 'almacen_id' => $detail->almacen_id,
                    'entrega_detalle_id' => $detail->id, 'cantidad' => $quantity, 'tipo' => $cancel ? 'reverso' : 'entrega',
                    'clave_operacion' => 'entrega:'.$delivery->id.':'.$detail->id.':'.$target]);
                if (! $cancel) {
                    $this->data->update('epp_entrega_detalles', $context, $detail->id, [
                        'costo_unitario' => $movement->costo_unitario,
                        'costo_total' => $movement->costo_total === null ? null : bcmul((string) $movement->costo_total, '-1', 6),
                    ]);
                }
            }
        }

        return $this->data->update('epp_entregas', $context, $delivery->id, ['estado' => $target]);
    }

    public function returnItems(object $context, array $input): object
    {
        $detail = $this->data->find('epp_entrega_detalles', $context, $input['entrega_detalle_id']);
        $delivery = $this->data->find('epp_entregas', $context, $detail->entrega_id);
        abort_unless($delivery->estado === 'confirmada', 409, 'Solo se devuelve una entrega confirmada.');
        $prior = $this->data->query('epp_movimientos', $context)->where('clave_operacion', $input['clave_operacion'])->first();
        if ($prior) {
            abort_unless($prior->tipo === 'devolucion' && $prior->entrega_detalle_id === $detail->id && bccomp($prior->cantidad, (string) $input['cantidad'], 3) === 0, 409, 'Clave de operación reutilizada.');

            return $prior;
        }
        $returned = (string) $this->data->query('epp_movimientos', $context)->where('entrega_detalle_id', $detail->id)->where('tipo', 'devolucion')->sum('cantidad');
        abort_if(bccomp(bcadd($returned, (string) $input['cantidad'], 3), $detail->cantidad, 3) > 0, 422, 'La devolución excede la cantidad entregada pendiente.');

        return $this->movement($context, $input + ['epp_id' => $detail->epp_id, 'almacen_id' => $detail->almacen_id, 'tipo' => 'devolucion']);
    }

    public function movement(object $context, array $input): object
    {
        $prior = $this->data->query('epp_movimientos', $context)->where('clave_operacion', $input['clave_operacion'])->first();
        if ($prior) {
            foreach ($input as $field => $value) {
                abort_unless($value == $prior->$field, 409, 'Clave de operación reutilizada con datos diferentes.');
            }

            return $prior;
        }
        $item = $this->data->find('epp_catalogo', $context, $input['epp_id']);
        $warehouse = $this->data->find('almacenes', $context, $input['almacen_id']);
        if (! in_array($input['tipo'], ['reverso', 'devolucion'])) {
            abort_unless($item->activo && $warehouse->activo, 422, 'EPP o almacén inactivo.');
        }
        $this->data->query('almacenes', $context)->where('id', $input['almacen_id'])->lockForUpdate()->first();
        $stock = (string) $this->data->query('epp_movimientos', $context)->where('epp_id', $input['epp_id'])->where('almacen_id', $input['almacen_id'])->sum('cantidad');
        if (bccomp(bcadd($stock, (string) $input['cantidad'], 3), '0', 3) < 0) {
            throw ValidationException::withMessages(['cantidad' => 'Stock insuficiente.']);
        }

        $last = $this->data->query('epp_movimientos', $context)->where('epp_id', $input['epp_id'])->where('almacen_id', $input['almacen_id'])->orderByDesc('id')->first();
        $balance = bccomp($stock, '0', 3) === 0 ? '0' : ($last->saldo_valor ?? null);
        $unitCost = $input['costo_unitario'] ?? null;
        if (in_array($input['tipo'], ['devolucion', 'reverso'])) {
            $detail = $this->data->find('epp_entrega_detalles', $context, $input['entrega_detalle_id']);
            $unitCost = $detail->costo_unitario;
        } elseif (bccomp((string) $input['cantidad'], '0', 3) < 0) {
            $unitCost = $balance !== null && bccomp($stock, '0', 3) > 0 ? bcdiv((string) $balance, $stock, 6) : null;
        }
        $value = $unitCost === null ? null : bcmul((string) $input['cantidad'], (string) $unitCost, 6);
        $remaining = bcadd($stock, (string) $input['cantidad'], 3);
        if (bccomp($remaining, '0', 3) === 0 && $balance !== null && bccomp((string) $input['cantidad'], '0', 3) < 0) {
            $value = bcmul((string) $balance, '-1', 6);
        }
        if (in_array($input['tipo'], ['devolucion', 'reverso']) && $value !== null && $detail->costo_total !== null) {
            $returns = $this->data->query('epp_movimientos', $context)->where('entrega_detalle_id', $detail->id)->whereIn('tipo', ['devolucion', 'reverso']);
            $returned = (string) (clone $returns)->sum('cantidad');
            if (bccomp(bcadd($returned, (string) $input['cantidad'], 3), (string) $detail->cantidad, 3) === 0) {
                $value = bcsub((string) $detail->costo_total, (string) $returns->sum('costo_total'), 6);
            }
        }
        $nextBalance = bccomp($remaining, '0', 3) === 0 ? '0' : ($balance === null || $value === null ? null : bcadd((string) $balance, $value, 6));

        return $this->data->insert('epp_movimientos', $context, array_merge($input, [
            'costo_unitario' => $unitCost, 'costo_total' => $value, 'saldo_valor' => $nextBalance,
            'registrado_por' => $context->miembro_id, 'fecha' => now(),
        ]));
    }
}
