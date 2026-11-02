<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChecklistService
{
    public function __construct(private HseData $data) {}

    private function ensure(bool $condition, string $message, string $field = 'checklist'): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    public function validateQuestion(array $question): void
    {
        $type = $question['tipo'] ?? 'cumplimiento';
        $groups = ['longitud' => ['texto', 'texto_largo'], 'numero' => ['numero'], 'fecha' => ['fecha', 'fecha_hora'],
            'seleccion' => ['selector', 'seleccion_multiple']];
        foreach ($groups as $prefix => $types) {
            foreach (['min', 'max'] as $suffix) {
                $this->ensure(! isset($question[$prefix.'_'.$suffix]) || in_array($type, $types), 'La regla no corresponde al tipo.', $prefix.'_'.$suffix);
            }
        }
        if ($type === 'selector') {
            $this->ensure(($question['seleccion_min'] ?? 0) <= 1 && ($question['seleccion_max'] ?? 1) <= 1, 'El selector admite una opción.');
        }
        if (isset($question['mime_permitidos'])) {
            Validator::make($question, ['mime_permitidos' => 'array', 'mime_permitidos.*' => ['string', 'regex:~^[a-z0-9.+-]+/[a-z0-9.+-]+$~i']])->validate();
        }
    }

    public function publish(object $context, object $template): object
    {
        abort_unless($template->estado === 'borrador', 409, 'Solo se publica un borrador.');
        $sections = $this->data->query('checklist_secciones', $context)->where('plantilla_id', $template->id)->get();
        $questions = $this->data->query('checklist_preguntas', $context)->where('plantilla_id', $template->id)->get();
        $this->ensure(trim($template->nombre) !== '' && $sections->isNotEmpty() && $questions->isNotEmpty(), 'La plantilla necesita nombre, secciones y preguntas.');
        foreach ($questions as $question) {
            $values = (array) $question;
            $values['mime_permitidos'] = $question->mime_permitidos ? json_decode($question->mime_permitidos, true) : null;
            $this->validateQuestion($values);
            $count = $this->data->query('checklist_pregunta_opciones', $context)->where('pregunta_id', $question->id)->count();
            if (in_array($question->tipo, ['selector', 'seleccion_multiple'])) {
                $this->ensure($count >= 2 && ($question->seleccion_min ?? 0) <= $count && ($question->seleccion_max ?? 0) <= $count, 'Opciones insuficientes para la pregunta '.$question->codigo);
            } else {
                $this->ensure($count === 0, 'El tipo no admite opciones: '.$question->codigo);
            }
        }

        return $this->data->update('checklist_plantillas', $context, $template->id, ['estado' => 'publicado']);
    }

    public function version(object $context, object $template): object
    {
        $copy = (array) $template;
        unset($copy['id'], $copy['empresa_id'], $copy['created_at'], $copy['updated_at']);
        $copy['estado'] = 'borrador';
        $copy['version'] = 1 + $this->data->query('checklist_plantillas', $context)->where('codigo', $template->codigo)->max('version');
        $new = $this->data->insert('checklist_plantillas', $context, $copy);
        foreach ($this->data->query('checklist_secciones', $context)->where('plantilla_id', $template->id)->get() as $section) {
            $sectionCopy = (array) $section;
            unset($sectionCopy['id'], $sectionCopy['empresa_id'], $sectionCopy['created_at'], $sectionCopy['updated_at']);
            $sectionCopy['plantilla_id'] = $new->id;
            $newSection = $this->data->insert('checklist_secciones', $context, $sectionCopy);
            foreach ($this->data->query('checklist_preguntas', $context)->where('seccion_id', $section->id)->get() as $question) {
                $questionCopy = (array) $question;
                unset($questionCopy['id'], $questionCopy['empresa_id'], $questionCopy['created_at'], $questionCopy['updated_at']);
                $questionCopy['plantilla_id'] = $new->id;
                $questionCopy['seccion_id'] = $newSection->id;
                $newQuestion = $this->data->insert('checklist_preguntas', $context, $questionCopy);
                foreach ($this->data->query('checklist_pregunta_opciones', $context)->where('pregunta_id', $question->id)->get() as $option) {
                    $optionCopy = (array) $option;
                    unset($optionCopy['id'], $optionCopy['empresa_id'], $optionCopy['created_at'], $optionCopy['updated_at']);
                    $optionCopy['pregunta_id'] = $newQuestion->id;
                    $this->data->insert('checklist_pregunta_opciones', $context, $optionCopy);
                }
            }
        }

        return $new;
    }

    public function snapshot(object $context, object $record): object
    {
        $snapshot = clone $record;
        $snapshot->respuestas = $this->data->query('checklist_respuestas', $context)->where('registro_id', $record->id)->get()->map(function ($answer) use ($context) {
            $answer->opciones = $this->data->query('checklist_respuesta_opciones', $context)->where('respuesta_id', $answer->id)->pluck('opcion_id')->all();
            $answer->evidencias = $this->data->query('checklist_evidencias', $context)->where('respuesta_id', $answer->id)->get()->all();

            return $answer;
        })->all();

        return $snapshot;
    }

    public function saveAnswers(object $context, object $record, array $answers): object
    {
        abort_unless($record->estado === 'borrador', 409, 'El registro ya no admite respuestas.');
        abort_unless($this->data->find('checklist_plantillas', $context, $record->plantilla_id)->estado === 'publicado', 409, 'Plantilla no disponible.');
        Validator::make(['respuestas' => $answers], ['respuestas' => 'required|array|min:1|max:500',
            'respuestas.*' => 'array:pregunta_id,cumplimiento,valor_texto,valor_numero,valor_fecha,valor_fecha_hora,valor_booleano,observacion,opciones,evidencias',
            'respuestas.*.pregunta_id' => 'required|integer|distinct',
            'respuestas.*.cumplimiento' => 'nullable|in:cumple,no_cumple,no_aplica',
            'respuestas.*.valor_texto' => 'nullable|string', 'respuestas.*.valor_numero' => 'nullable|numeric',
            'respuestas.*.valor_fecha' => 'nullable|date_format:Y-m-d', 'respuestas.*.valor_fecha_hora' => 'nullable|date_format:Y-m-d H:i:s',
            'respuestas.*.valor_booleano' => 'nullable|boolean', 'respuestas.*.observacion' => 'nullable|string|max:10000',
            'respuestas.*.opciones' => 'sometimes|array', 'respuestas.*.opciones.*' => 'integer|distinct',
            'respuestas.*.evidencias' => 'sometimes|array', 'respuestas.*.evidencias.*' => 'array:archivo_id,tipo',
            'respuestas.*.evidencias.*.archivo_id' => 'required|integer',
            'respuestas.*.evidencias.*.tipo' => 'required|in:fotografia,archivo,firma'])->validate();
        foreach ($answers as $answer) {
            $question = $this->data->find('checklist_preguntas', $context, $answer['pregunta_id']);
            $this->ensure($question->plantilla_id === $record->plantilla_id, 'Pregunta de otra plantilla.');
            $options = collect($answer['opciones'] ?? [])->map(fn ($id) => $this->data->find('checklist_pregunta_opciones', $context, $id));
            $evidence = collect($answer['evidencias'] ?? [])->map(function ($item) use ($context) {
                $file = $this->data->find('archivos', $context, $item['archivo_id']);
                $this->ensure($file->eliminado_at === null, 'Archivo no disponible.');

                return (object) ['archivo' => $file, 'tipo' => $item['tipo']];
            });
            $this->validateAnswer($context, $question, (object) $answer, $options, $evidence, false);
            $values = array_fill_keys(['cumplimiento', 'valor_texto', 'valor_numero', 'valor_fecha', 'valor_fecha_hora', 'valor_booleano', 'observacion'], null);
            $values = array_merge($values, array_intersect_key($answer, $values), ['registro_id' => $record->id, 'plantilla_id' => $record->plantilla_id, 'pregunta_id' => $question->id]);
            $previous = $this->data->query('checklist_respuestas', $context)->where('registro_id', $record->id)->where('pregunta_id', $question->id)->first();
            $saved = $previous ? $this->data->update('checklist_respuestas', $context, $previous->id, $values) : $this->data->insert('checklist_respuestas', $context, $values);
            $this->data->query('checklist_respuesta_opciones', $context)->where('respuesta_id', $saved->id)->delete();
            $this->data->query('checklist_evidencias', $context)->where('respuesta_id', $saved->id)->delete();
            foreach ($options as $option) {
                $this->data->insert('checklist_respuesta_opciones', $context, ['respuesta_id' => $saved->id, 'pregunta_id' => $question->id, 'opcion_id' => $option->id]);
            }
            foreach ($evidence as $item) {
                $this->data->insert('checklist_evidencias', $context, ['respuesta_id' => $saved->id, 'archivo_id' => $item->archivo->id, 'tipo' => $item->tipo]);
            }
        }

        return $this->data->update('checklist_registros', $context, $record->id, []);
    }

    private function validateAnswer(object $context, object $question, ?object $answer, $options, $evidence, bool $final): ?string
    {
        $field = match ($question->tipo) {
            'texto', 'texto_largo' => 'valor_texto', 'numero' => 'valor_numero', 'fecha' => 'valor_fecha',
            'fecha_hora' => 'valor_fecha_hora', 'si_no' => 'valor_booleano', 'cumplimiento' => 'cumplimiento', default => null,
        };
        foreach (['valor_texto', 'valor_numero', 'valor_fecha', 'valor_fecha_hora', 'valor_booleano', 'cumplimiento'] as $candidate) {
            $this->ensure(! isset($answer->$candidate) || $candidate === $field, 'Valor incompatible con el tipo: '.$question->codigo);
        }
        $selection = in_array($question->tipo, ['selector', 'seleccion_multiple']);
        $attachment = in_array($question->tipo, ['fotografia', 'archivo', 'firma']);
        $this->ensure($selection || $options->isEmpty(), 'Este tipo no admite selección.');
        foreach ($options as $option) {
            $this->ensure($option->pregunta_id === $question->id, 'Opción de otra pregunta.');
        }
        $value = $field ? ($answer->$field ?? null) : null;
        $filled = $selection ? $options->isNotEmpty() : ($attachment ? $evidence->isNotEmpty() : ($value !== null && (! is_string($value) || trim($value) !== '')));
        if ($final && $question->obligatoria) {
            $this->ensure($filled, 'Respuesta obligatoria: '.$question->codigo);
        }
        if ($value !== null && in_array($question->tipo, ['texto', 'texto_largo'])) {
            $this->ensure(strlen($value) <= 65535, 'Texto excede 65535 bytes.');
            if ($filled) {
                $this->range(mb_strlen($value), $question->longitud_min, $question->longitud_max, $question->codigo);
            }
        }
        if ($value !== null && $question->tipo === 'numero') {
            $this->range($value, $question->numero_min, $question->numero_max, $question->codigo);
        }
        if ($value !== null && in_array($question->tipo, ['fecha', 'fecha_hora'])) {
            $date = $question->tipo === 'fecha_hora' ? CarbonImmutable::parse($value, 'UTC')->setTimezone($context->zona_horaria)->toDateString() : $value;
            $this->range($date, $question->fecha_min, $question->fecha_max, $question->codigo);
        }
        if ($selection && $filled) {
            $this->range($options->count(), $final ? ($question->seleccion_min ?? 1) : 0,
                $question->tipo === 'selector' ? 1 : $question->seleccion_max, $question->codigo);
        }
        if ($final && $filled && $question->archivos_min !== null) {
            $this->ensure($evidence->count() >= $question->archivos_min, 'Evidencias insuficientes: '.$question->codigo);
        }
        if ($evidence->isNotEmpty()) {
            $this->range($evidence->count(), $final ? $question->archivos_min : 0, $question->tipo === 'firma' ? 1 : $question->archivos_max, $question->codigo);
            foreach ($evidence as $item) {
                $file = $item->archivo;
                $this->ensure($file->eliminado_at === null, 'Evidencia eliminada.');
                $this->ensure(! $attachment || $item->tipo === $question->tipo, 'Tipo de evidencia incorrecto.');
                $this->ensure(! in_array($item->tipo, ['fotografia', 'firma']) || in_array($file->mime_type, ['image/jpeg', 'image/png']), 'La fotografía o firma debe ser una imagen.');
                $this->ensure($question->archivo_max_bytes === null || $file->bytes <= $question->archivo_max_bytes, 'Archivo demasiado grande.');
                $mimes = $question->mime_permitidos ? json_decode($question->mime_permitidos, true) : [];
                $this->ensure(! $mimes || in_array($file->mime_type, $mimes, true), 'MIME no permitido.');
            }
        }
        if ($final) {
            foreach ($options as $option) {
                $this->ensure(! $option->exige_observacion || trim($answer->observacion ?? '') !== '', 'La opción exige observación.');
                $this->ensure(! $option->exige_evidencia || $evidence->isNotEmpty(), 'La opción exige evidencia.');
            }
            $results = $options->pluck('resultado');
            $this->ensure(! ($results->contains('no_aplica') && ($results->contains('cumple') || $results->contains('no_cumple'))), 'No mezcle no_aplica con resultados evaluables.');
        }
        if ($question->tipo === 'cumplimiento') {
            return $value;
        }
        if ($selection) {
            if ($options->contains('resultado', 'no_cumple')) {
                return 'no_cumple';
            }
            if ($options->contains('resultado', 'cumple')) {
                return 'cumple';
            }
        }

        return null;
    }

    private function range(mixed $value, mixed $min, mixed $max, string $code): void
    {
        $this->ensure(($min === null || $value >= $min) && ($max === null || $value <= $max), 'Valor fuera de rango: '.$code);
    }

    public function score(object $context, object $record, bool $final = false): ?float
    {
        $total = 0;
        $passed = 0;
        foreach ($this->data->query('checklist_preguntas', $context)->where('plantilla_id', $record->plantilla_id)->get() as $question) {
            $answer = $this->data->query('checklist_respuestas', $context)->where('registro_id', $record->id)->where('pregunta_id', $question->id)->first();
            $options = collect();
            $evidence = collect();
            if ($answer) {
                $ids = $this->data->query('checklist_respuesta_opciones', $context)->where('respuesta_id', $answer->id)->pluck('opcion_id');
                $options = $this->data->query('checklist_pregunta_opciones', $context)->whereIn('id', $ids)->get();
                $evidence = $this->data->query('checklist_evidencias', $context)->where('respuesta_id', $answer->id)->get()->map(fn ($item) => (object) ['tipo' => $item->tipo, 'archivo' => $this->data->find('archivos', $context, $item->archivo_id)]);
            }
            $result = $this->validateAnswer($context, $question, $answer, $options, $evidence, $final);
            if (in_array($result, ['cumple', 'no_cumple'])) {
                $total += $question->peso;
                $passed += $result === 'cumple' ? $question->peso : 0;
            }
        }

        return $total > 0 ? round(100 * $passed / $total, 2) : null;
    }

    public function submit(object $context, object $record): object
    {
        abort_unless($record->estado === 'borrador', 409, 'Solo se envían borradores.');
        abort_unless($this->data->find('checklist_plantillas', $context, $record->plantilla_id)->estado === 'publicado', 409, 'Plantilla no publicada.');
        $this->score($context, $record, true);

        return $this->data->update('checklist_registros', $context, $record->id, ['estado' => 'enviado']);
    }
}
