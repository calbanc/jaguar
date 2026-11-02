<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TrainingReport
{
    public const COLUMNS = ['numero' => 'N.º', 'numero_documento' => 'DNI / documento', 'nombres' => 'Nombres', 'apellidos' => 'Apellidos', 'puesto' => 'Puesto', 'firma' => 'Firma', 'firma_fecha' => 'Fecha de firma'];

    public function fields(): array
    {
        $groups = [
            'empresa' => ['label' => 'Empresa', 'fields' => ['razon_social' => 'Razón social', 'nombre_comercial' => 'Nombre comercial', 'identificacion_fiscal' => 'RUC / identificación fiscal', 'pais' => 'País']],
            'planilla' => ['label' => 'Planilla', 'fields' => ['id' => 'ID', 'numero_planilla' => 'Número de planilla', 'fecha' => 'Fecha', 'hora_inicio' => 'Hora de inicio', 'hora_termino' => 'Hora de término', 'observaciones' => 'Observaciones', 'activo' => 'Estado', 'created_at' => 'Fecha de registro', 'updated_at' => 'Última actualización']],
            'responsable' => ['label' => 'Responsable del registro', 'fields' => ['nombre' => 'Nombre', 'email' => 'Correo']],
        ];
        foreach (['tema' => 'Tema', 'lugar' => 'Lugar', 'expositor' => 'Expositor', 'empresa_expositora' => 'Empresa expositora'] as $key => $label) {
            $fields = ['nombre' => 'Nombre', 'descripcion' => 'Descripción'];
            if ($key === 'lugar') {
                $fields['direccion'] = 'Dirección';
            }
            if (in_array($key, ['expositor', 'empresa_expositora'], true)) {
                $fields += ['numero_documento' => 'Documento / RUC', 'email' => 'Correo', 'telefono' => 'Teléfono'];
            }
            $groups[$key] = ['label' => $label, 'fields' => $fields];
        }
        $groups['participantes'] = ['label' => 'Participantes', 'fields' => ['total' => 'Total de participantes', 'firmados' => 'Total con firma', 'tabla' => 'Detalle de participantes y firmas']];

        return $groups;
    }

    public function clean(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $document->getElementsByTagName('body')->item(0);
        $clean = '';
        foreach ($body->childNodes as $node) {
            $clean .= $this->cleanNode($node);
        }
        $allowed = [];
        foreach ($this->fields() as $group => $definition) {
            foreach ($definition['fields'] as $key => $label) {
                $allowed[] = $group.'.'.$key;
            }
        }
        preg_match_all('/\{\{([^{}]+)\}\}/u', html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $matches);
        preg_match_all('/\{\{([^{}]+)\}\}/u', $clean, $htmlMatches);
        if (count($matches[1]) !== count($htmlMatches[1]) || $matches[1] !== $htmlMatches[1]) {
            throw ValidationException::withMessages(['diseno.html' => 'Un campo quedó dividido por el formato de texto. Elimínalo e insértalo nuevamente.']);
        }
        foreach ($matches[1] as $key) {
            if (! in_array(trim($key), $allowed, true)) {
                throw ValidationException::withMessages(['diseno.html' => 'Campo desconocido: '.$key]);
            }
        }

        return $clean;
    }

    private function cleanNode(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return e($node->textContent);
        }
        if (! $node instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg', 'math', 'form', 'input', 'img', 'link', 'meta'], true)) {
            return '';
        }
        $inside = '';
        foreach ($node->childNodes as $child) {
            $inside .= $this->cleanNode($child);
        }
        if (! in_array($tag, ['p', 'div', 'span', 'br', 'hr', 'h1', 'h2', 'h3', 'b', 'strong', 'i', 'em', 'u', 's', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'blockquote'], true)) {
            return $inside;
        }
        $styles = [];
        foreach (explode(';', $node->getAttribute('style')) as $style) {
            $parts = explode(':', $style, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$name, $value] = array_map('trim', $parts);
            if (($name === 'text-align' && in_array($value, ['left', 'center', 'right', 'justify'], true)) || ($name === 'font-weight' && in_array($value, ['bold', 'normal', '700'], true)) || ($name === 'font-style' && $value === 'italic') || ($name === 'text-decoration' && $value === 'underline') || ($name === 'break-before' && $value === 'page')) {
                $styles[] = $name.':'.$value;
            }
        }
        $attrs = $styles ? ' style="'.e(implode(';', $styles)).'"' : '';
        foreach (['colspan', 'rowspan'] as $attr) {
            if (in_array($tag, ['td', 'th'], true) && ctype_digit($node->getAttribute($attr))) {
                $attrs .= ' '.$attr.'="'.min(20, max(1, (int) $node->getAttribute($attr))).'"';
            }
        }

        return '<'.$tag.$attrs.'>'.(in_array($tag, ['br', 'hr'], true) ? '' : $inside.'</'.$tag.'>');
    }

    public function render(object $context, array $design, int $number): string
    {
        $planilla = DB::table('capacitacion')->where('empresa_id', $context->empresa_id)->where('numero_planilla', $number)->first();
        abort_unless($planilla, 404, 'No existe esa planilla en la empresa.');
        $values = ['planilla' => (array) $planilla, 'empresa' => (array) DB::table('empresas')->where('id', $context->empresa_id)->first()];
        $values['planilla']['activo'] = $planilla->activo ? 'Activa' : 'Anulada';
        $values['responsable'] = (array) DB::table('usuarios as u')->join('miembros_empresa as m', 'm.usuario_id', '=', 'u.id')->where('m.empresa_id', $context->empresa_id)->where('u.id', $planilla->responsable_registro_id)->first(['u.nombre', 'u.email']);
        foreach (['tema' => 'tema_capacitacion', 'lugar' => 'lugar_capacitacion', 'expositor' => 'capacitador', 'empresa_expositora' => 'empresa_capacitacion'] as $key => $table) {
            $values[$key] = (array) DB::table($table)->where('empresa_id', $context->empresa_id)->where('id', $planilla->{$table.'_id'})->first();
        }
        $participants = DB::table('capacitacion_participantes as p')->join('trabajadores as t', function ($join) {
            $join->on('t.id', '=', 'p.trabajador_id')->on('t.empresa_id', '=', 'p.empresa_id');
        })->where('p.empresa_id', $context->empresa_id)->where('p.capacitacion_id', $planilla->id)->where('p.activo', true)->orderBy('t.apellidos')->orderBy('t.nombres')
            ->get(['t.numero_documento', 't.nombres', 't.apellidos', 't.puesto', 'p.firma_fecha', 'p.firma_ruta']);
        $values['participantes'] = ['total' => $participants->count(), 'firmados' => $participants->whereNotNull('firma_fecha')->count()];
        $columns = $design['columnas'];
        $list = ($design['detalle'] ?? 'tabla') === 'listado';
        $table = $list ? '<div class="participants-list">' : '<table><thead><tr>';
        foreach ($columns as $column) {
            if (! $list) {
                $table .= '<th>'.e(self::COLUMNS[$column]).'</th>';
            }
        }
        if (! $list) {
            $table .= '</tr></thead><tbody>';
        }
        foreach ($participants as $index => $participant) {
            $table .= $list ? '<section class="participant-card">' : '<tr>';
            foreach ($columns as $column) {
                $cell = $column === 'numero' ? (string) ($index + 1) : e($participant->{$column} ?? '');
                if ($column === 'firma') {
                    $cell = 'Sin firma';
                    $path = $participant->firma_ruta;
                    if ($path && str_starts_with($path, $context->empresa_id.'/') && Storage::disk('firmas_capacitaciones')->exists($path)) {
                        $bytes = Storage::disk('firmas_capacitaciones')->get($path);
                        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                        if (in_array($mime, ['image/png', 'image/jpeg'], true)) {
                            $cell = '<img alt="Firma" src="data:'.$mime.';base64,'.base64_encode($bytes).'" style="max-width:120px;max-height:56px">';
                        }
                    }
                }
                $table .= $list ? '<div><strong>'.e(self::COLUMNS[$column]).': </strong>'.$cell.'</div>' : '<td>'.$cell.'</td>';
            }
            $table .= $list ? '</section>' : '</tr>';
        }
        if ($participants->isEmpty()) {
            $table .= $list ? '<p>Sin participantes registrados.</p>' : '<tr><td colspan="'.count($columns).'">Sin participantes registrados.</td></tr>';
        }
        $table .= $list ? '</div>' : '</tbody></table>';
        $html = $this->clean($design['html']);
        $html = preg_replace_callback('/\{\{\s*([a-z_]+)\.([a-z_]+)\s*\}\}/', function ($match) use ($values, $table): string {
            if ($match[1] === 'participantes' && $match[2] === 'tabla') {
                return $table;
            }

            return nl2br(e($values[$match[1]][$match[2]] ?? ''));
        }, $html);
        $landscape = $design['orientacion'] === 'horizontal';
        $width = $landscape ? 297 : 210;
        $orientation = $landscape ? 'landscape' : 'portrait';
        $margin = (int) $design['margen'];
        $size = (int) $design['fuente'];

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Capacitación '.$number.'</title><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'"><style>@page{size:A4 '.$orientation.';margin:'.$margin.'mm}*{box-sizing:border-box}body{font:'.$size.'pt Arial,sans-serif;color:#172b22;line-height:1.45;margin:0}main{max-width:'.$width.'mm;padding:'.$margin.'mm;margin:auto;background:white}h1{font-size:1.8em}h2{font-size:1.4em}h3{font-size:1.15em}table{width:100%;border-collapse:collapse;margin:12px 0;font-size:.9em;table-layout:fixed}td,th{border:1px solid #aab7af;padding:7px;overflow-wrap:anywhere}th{background:#eef3ef;text-align:left}thead{display:table-header-group}tr,img,.participant-card{break-inside:avoid}.participant-card{border:1px solid #aab7af;padding:12px;margin:12px 0}.participant-card>div{padding:3px 0}p{min-height:1em}blockquote{border-left:3px solid #bccbc2;margin-left:0;padding-left:15px}@media print{main{max-width:none;padding:0}body{background:white}}</style></head><body><main>'.$html.'</main></body></html>';
    }
}
