<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;

class WorkerValidation
{
    public function validator(array $values, bool $requireZone = true): ValidatorInstance
    {
        return Validator::make($values, [
            'zona_id' => $requireZone ? 'required|integer' : 'nullable|integer',
            'tipo_documento' => 'required|string|max:20',
            'numero_documento' => strtoupper(trim($values['tipo_documento'] ?? '')) === 'DNI' ? ['required', 'regex:/^[0-9]{8}$/'] : ['required', 'string', 'max:30'],
            'nombres' => 'required|string|max:150', 'apellidos' => 'required|string|max:150',
            'puesto' => 'required|string|max:150', 'fecha_ingreso' => 'required|date_format:Y-m-d',
            'fecha_cese' => 'nullable|date_format:Y-m-d|after_or_equal:fecha_ingreso',
        ], [
            'required' => 'El campo :attribute es obligatorio.', 'max' => 'El campo :attribute admite hasta :max caracteres.',
            'numero_documento.regex' => 'El DNI debe contener exactamente 8 dígitos, conservando los ceros iniciales.',
            'date_format' => 'El campo :attribute debe ser una fecha válida (AAAA-MM-DD).',
            'fecha_cese.after_or_equal' => 'La fecha de cese no puede ser anterior a la fecha de ingreso.',
        ], ['zona_id' => 'zona', 'numero_documento' => 'DNI / documento', 'fecha_ingreso' => 'fecha de ingreso', 'fecha_cese' => 'fecha de cese']);
    }
}
