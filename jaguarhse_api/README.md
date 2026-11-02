# Jaguar HSE API

Backend Laravel 13 para web y móvil, basado en las 40 tablas y 4 vistas de la revisión 2 de GASSIP/HSE. Base de las rutas: `/api/v1`. Todas las respuestas de errores de API son JSON.

## Instalación

Requiere PHP 8.3+, extensiones PDO MySQL, BCMath, Fileinfo y Mbstring, Composer y MySQL 8.0.16+. El esquema original está en `database/schema/hse.sql`; sus metadatos de validación están en `database/schema/metadata.json`.

1. Ejecutar `composer install`.
2. Preparar `.env` a partir de `.env.example` **sin sobrescribir una configuración existente**. Configurar `DB_CONNECTION=mysql`, servidor, puerto, base vacía y credenciales. No importar el SQL manualmente si se usará la migración.
3. Ejecutar `php artisan key:generate` si todavía no existe una clave de aplicación.
4. Ejecutar `php artisan migrate`. La migración HSE rechaza tablas HSE preexistentes y no ofrece rollback destructivo. Las migraciones Laravel conservan sus tablas de infraestructura; la autenticación de la API utiliza `usuarios` y `sesiones`, no `users`.
5. Crear la primera empresa y el administrador:

```bash
php artisan hse:bootstrap EMPRESA "Mi empresa SAC" admin@example.com --identificacion=20123456789
```

El comando solicita la contraseña de forma oculta, no crea credenciales predeterminadas, habilita los seis módulos operativos y accesos web/móvil. Crea un contrato de prueba de 30 días. Para renovar desde consola administrativa:

```bash
php artisan hse:contrato EMPRESA CONTRATO-2026 2026-09-22 2027-09-21 --usuarios=50 --almacenamiento-mb=10240
```

Los contratos anteriores quedan terminados, conservados en el historial. Límites `NULL` significan sin límite. No se permite editar contratos ni módulos contratados desde la API del cliente.

6. Para desarrollo, ejecutar `php artisan serve`. En producción configurar HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, servidor con raíz `public/`, caché persistente para límites de peticiones y respaldos de MySQL y archivos privados.

## Autenticación

```http
POST /api/v1/auth/login
Content-Type: application/json
Accept: application/json

{
  "email": "admin@example.com",
  "password": "contraseña elegida",
  "empresa_codigo": "EMPRESA",
  "plataforma": "movil",
  "dispositivo": "Android"
}
```

Devuelve `token`, `token_type`, `expira_at` y contexto autenticado. En las siguientes peticiones enviar `Authorization: Bearer <token>`. La base conserva exclusivamente SHA-256 del token. Duración configurable por `HSE_TOKEN_HOURS` (24 por defecto). `GET auth/me` consulta la sesión; `POST auth/logout` la revoca. `GET auth/sesiones` lista los dispositivos propios de la empresa y `DELETE auth/sesiones/{id}` revoca uno. `POST auth/password` recibe `password_actual`, `password` y `password_confirmation`; cambia la contraseña global y revoca todas las sesiones de ese usuario. No enviar `empresa_id`: siempre deriva de la sesión.

Cada petición comprueba empresa, usuario, membresía, acceso, rol, contrato vigente y límite de usuarios. Cada operación verifica el permiso `<modulo>.<accion>` y la habilitación del módulo. Acciones: `ver`, `crear`, `editar`, `revisar`, `publicar`, `confirmar`, `gestionar`, `confidenciales`. `GET catalogos` devuelve empresa actual, plataformas, módulos y los permisos del rol.

## Recursos

Los nombres de ruta usan los nombres SQL, con guiones bajos. Los recursos habilitados están en `config/hse.php`.

| Método y ruta | Comportamiento |
|---|---|
| `GET /{recurso}` | Lista paginada, `per_page` entre 1 y 100, `page`, filtros exactos como `filter[estado]=borrador` |
| `GET /{recurso}/{id}` | Consulta solo dentro de la empresa autenticada |
| `POST /{recurso}` | Alta validada; estado y responsables generados por el servidor |
| `PATCH /{recurso}/{id}` | Modificación parcial; conserva relaciones y estados protegidos |
| `DELETE /{recurso}/{id}` | Desactiva si tiene `activo`; nunca elimina históricos |

Módulos: sedes, áreas y trabajadores; temas y requisitos documentales; versiones documentales; constructor de checklists; hallazgos, seguimientos y acciones correctivas; EPP y almacenes; indicadores y mediciones mensuales. Sesiones y usuarios globales no se exponen como recursos genéricos. Contratos, módulos contratados, auditoría, archivos, roles/permisos/accesos, respuestas de checklist y movimientos de inventario son de consulta por esta interfaz; sus escrituras usan los flujos específicos.

Campos desconocidos, `id`, `empresa_id`, marcas de auditoría y estados administrados por el servidor se rechazan. Relaciones compuestas se validan por empresa y entidad padre. Errores: 401 sesión inválida, 403 permiso/contrato, 404 registro no visible, 405 escritura no disponible, 409 conflicto de estado/idempotencia, 422 validación, 429 límite de peticiones.

## Flujos

Todas las escrituras operativas usan transacción y bloqueo de empresa para serializar mutaciones y evitar carreras de stock, publicación, numeración y cuotas. El bloqueo es deliberadamente conservador; puede reducirse a entidades específicas si el volumen lo requiere.

### Administración

- `POST administracion/roles`, `PATCH administracion/roles/{id}`: `nombre`, `descripcion`, `activo`, `permisos` (array de IDs). Solo se delegan permisos propios; no se modifica el rol de la sesión actual.
- `GET administracion/miembros` lista miembros con nombre y correo, sin contraseñas. `PATCH administracion/miembros/{id}` recibe `activo`, valida cuota y revoca sesiones al desactivar. No permite desactivar la propia membresía.
- `POST administracion/miembros`: `nombre`, `email`, `password` (mínimo 12 caracteres). Crea una identidad nueva y su membresía, sujeto a cuota. No altera contraseñas de identidades existentes.
- `POST administracion/accesos`: `miembro_id`, `rol_id`, `plataforma_id`, `activo`. Crea/actualiza el acceso y revoca sesiones anteriores al modificarlo.

### Archivos y documentos

- `POST archivos/subir`, multipart con `archivo` y `clasificacion` opcional (`interno`, `confidencial`, `restringido`). Acepta PDF, JPEG y PNG, límite `HSE_UPLOAD_MAX_KB` (10240), MIME detectado en servidor y cuota de almacenamiento.
- Los archivos están en `storage/app/hse-private`; no ejecutar `storage:link` sobre esta carpeta. Las respuestas omiten disco y ruta.
- `GET archivos/{id}/descargar`: requiere acceso a la empresa y `documentos.ver`; confidenciales y restringidos requieren además `documentos.confidenciales`.
- En producción es obligatorio configurar `HSE_CLAMSCAN_PATH` con el ejecutable ClamAV y mantener sus firmas actualizadas. Si falta o falla, las cargas quedan bloqueadas. En desarrollo/pruebas el escaneo puede omitirse.
- `POST documento_versiones`: `requisito_id`, `archivo_id`, fechas opcionales; el servidor asigna el siguiente número de versión y estado pendiente.
- `POST documento_versiones/{id}/revisar`: `resultado` (`aprobado`, `observado`, `rechazado`) y `comentario`. Escribe revisión y versión en una misma transacción. Una versión revisada no se sobrescribe: se crea otra.

### Checklists

1. Crear `checklist_plantillas`, `checklist_secciones`, `checklist_preguntas` y `checklist_pregunta_opciones` en borrador.
Consultar `GET checklist_plantillas/{id}/formulario` devuelve la plantilla completa, con secciones, preguntas y opciones ordenadas, para renderizarla en web/móvil. `GET checklist_registros/{id}/detalle` incluye respuestas, evidencias y puntaje.

2. `POST checklist_plantillas/{id}/publicar` valida estructura, tipos, reglas y opciones. Desde entonces la configuración es inmutable.
3. `POST checklist_plantillas/{id}/versionar` copia secciones, preguntas y opciones con nuevos IDs a la siguiente versión borrador. `.../retirar` retira una publicada.
4. Crear `checklist_registros` con `plantilla_id`, `uuid_cliente`, `fecha` UTC en formato `Y-m-d H:i:s` y, opcionalmente, área, observaciones y coordenadas.
5. `POST checklist_registros/{id}/respuestas` guarda/reemplaza atómicamente las respuestas incluidas (las preguntas omitidas permanecen). Para cada pregunta, enviar su respuesta completa; omitir opciones o evidencias en una respuesta las vacía.

```json
{
  "respuestas": [
    {"pregunta_id": 10, "valor_booleano": false},
    {"pregunta_id": 11, "opciones": [21], "observacion": "Revisado"},
    {"pregunta_id": 12, "evidencias": [{"archivo_id": 30, "tipo": "fotografia"}]}
  ]
}
```

6. `POST checklist_registros/{id}/enviar` verifica obligatoriedad, tipos, rangos, selecciones, observaciones exigidas y archivos. `false` y `0` son respuestas válidas. No aplica no se mezcla con opciones evaluables.
7. `GET checklist_registros/{id}/puntaje` calcula el porcentaje ponderado y devuelve `null` sin elementos evaluables. `POST .../revisar` marca enviado como revisado; `POST .../anular` admite borrador/enviado. Las respuestas enviadas/revisadas son inmutables.

### Hallazgos

Crear con UUID, título, descripción y fecha. Añadir seguimientos, evidencias y acciones correctivas. `POST hallazgos/{id}/transicion` recibe `estado` y `comentario`: abierto → en_proceso → por_validar → cerrado; por_validar puede volver a en_proceso; abierto/en_proceso pueden anularse. Cerrar requiere permiso de revisión y todas las acciones completadas o anuladas. El cierre registra miembro y fecha.

`POST acciones_correctivas/{id}/transicion` recibe los mismos campos: pendiente → en_proceso → por_validar → completada; permite devolución a en_proceso y anulación desde pendiente/en_proceso. No cierra automáticamente el hallazgo.

### EPP

- Crear catálogo, almacén, trabajador, entrega borrador y detalles.
- `POST inventario/movimientos`: `epp_id`, `almacen_id`, `cantidad`, `tipo` (`entrada` o `ajuste`), `clave_operacion`, `observacion` opcional. Cantidades con máximo tres decimales; no se permite stock negativo.
- `POST epp_entregas/{id}/confirmar`: verifica stock y genera salidas por detalle atómicamente. Reintentar no duplica salidas.
- `POST inventario/devoluciones`: `entrega_detalle_id`, `cantidad` positiva, `clave_operacion` y observación opcional. Limita devoluciones al saldo entregado.
- `POST epp_entregas/{id}/anular`: revierte solo el saldo no devuelto y conserva el historial. La repetición no duplica reversos.

### Indicadores y reportes

`indicador_mediciones` exige primer día del mes y valores acordes al tipo del indicador: `valor_manual` o `numerador`/`denominador`. El divisor cero produce sin datos en la vista.

`GET reportes/requisitos`, `reportes/temas`, `reportes/stock` y `reportes/indicadores` consultan las cuatro vistas filtradas por empresa. Los reportes documentales usan la fecha local de la empresa y restauran UTC al terminar. Stock muestra las combinaciones que tienen movimientos.

## Web y móvil

Configurar orígenes web permitidos en `HSE_CORS_ORIGINS` separados por coma. El móvil usa HTTPS y Bearer; guardar el token en almacenamiento seguro del sistema operativo. Las fechas de eventos viajan en UTC y las fechas calendario en `Y-m-d`.

Las altas de checklists, hallazgos y entregas usan UUID de cliente. Repetir el UUID con el mismo contenido devuelve el registro; cambiar el contenido devuelve 409. Esto cubre reintentos de creación, no un motor completo de sincronización offline ni fusión de ediciones concurrentes. Los movimientos usan claves de operación idempotentes.

## Verificación y alcance

```bash
php artisan test --compact tests/Feature/HseApiTest.php
php artisan route:list --path=api
vendor/bin/pint --format agent
```

Las pruebas funcionales usan una adaptación SQLite del esquema SQL con claves compuestas, índices únicos, CHECK y las cuatro vistas (con conversión explícita a real para reproducir la división decimal de MySQL). No sustituyen pruebas de despliegue ni de concurrencia bajo MySQL. La migración de producción ejecuta el SQL original y requiere una base vacía; ante un fallo DDL de MySQL, inspeccionar el estado parcial antes de reintentar.

No incluye frontend web/móvil, recuperación de contraseñas por correo, incorporación por invitación de usuarios globales existentes, notificaciones programadas de vencimiento ni los módulos que no tienen tablas operativas en el SQL (IPERC, EMO, capacitaciones, etc.). La habilitación comercial de módulos adicionales se administra fuera de la API del cliente.
