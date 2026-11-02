import { Row } from './api.service';
export const DEMO: Record<string, Row[]> = {
 hallazgos:[
 {id:104,titulo:'Protección incompleta en zona de mantenimiento',descripcion:'Verificar el uso de protección facial durante las actividades de mantenimiento.',severidad:'alta',estado:'abierto',fecha_compromiso:'2026-09-26',area:'Mantenimiento'},
 {id:103,titulo:'Señalización de ruta de evacuación',descripcion:'Reponer la señalización de la salida del almacén.',severidad:'media',estado:'en_proceso',fecha_compromiso:'2026-09-28',area:'Almacén principal'},
 {id:102,titulo:'Actualización de hojas de seguridad',descripcion:'Actualizar las fichas disponibles en el punto de almacenamiento.',severidad:'media',estado:'por_validar',fecha_compromiso:'2026-09-30',area:'Operaciones'},
 {id:101,titulo:'Orden y limpieza en área de trabajo',descripcion:'Retirar materiales fuera de su ubicación asignada.',severidad:'baja',estado:'cerrado',fecha_compromiso:'2026-09-24',area:'Producción'}],
 checklist_registros:[{id:38,fecha:'2026-09-25 09:00:00',nombre:'Inspección de seguridad · Planta',estado:'enviado'},{id:37,fecha:'2026-09-24 14:30:00',nombre:'Verificación de equipos de protección',estado:'revisado'},{id:36,fecha:'2026-09-24 08:00:00',nombre:'Inspección de almacén',estado:'borrador'}],
 documento_versiones:[{id:12,nombre:'Política de seguridad y salud',version:2,estado:'aprobado',fecha_vencimiento:'2027-01-15'},{id:11,nombre:'Plan de respuesta ante emergencias',version:1,estado:'pendiente',fecha_vencimiento:'2026-10-02'},{id:10,nombre:'Procedimiento de trabajo seguro',version:3,estado:'observado',fecha_vencimiento:'2026-10-10'}],
 temas_hse:[{id:1,nombre:'Seguridad y salud en el trabajo',codigo:'SST',aplica:1},{id:2,nombre:'Gestión ambiental',codigo:'AMB',aplica:1}],
 trabajadores:[{id:1,nombres:'Carlos',apellidos:'Mendoza',numero_documento:'00000001',puesto:'Supervisor HSE',activo:1}],
 sedes:[{id:1,codigo:'SEDE-01',nombre:'Sede principal',direccion:'Lima, Perú',activo:1}],
 epp_catalogo:[{id:1,codigo:'EPP-001',nombre:'Casco de seguridad',unidad:'unidad',activo:1},{id:2,codigo:'EPP-002',nombre:'Guantes de protección',unidad:'par',activo:1}],
 indicadores:[{id:1,codigo:'INS-01',nombre:'Inspecciones ejecutadas',unidad:'%',tipo_calculo:'razon',sentido:'mayor_mejor',activo:1}],
 acciones_correctivas:[{id:1,descripcion:'Reponer señalización de evacuación',estado:'en_proceso',fecha_compromiso:'2026-09-28'}],
 checklist_plantillas:[{id:1,nombre:'Inspección general de seguridad',codigo:'INS-GEN',version:1,estado:'publicado'}]
};
