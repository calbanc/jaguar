# Detalle explicativo de la base de datos HSE

Documento basado en `gassip_hse_mysql.sql`, revisión 2, con **40 tablas, 97 claves foráneas y 4 vistas**. Describe la estructura existente y las reglas que debe implementar la aplicación; no modifica el SQL.

## Cómo interpretar el modelo

Una tabla contiene registros de una misma clase. Una clave primaria (`id`) identifica una fila. Una clave foránea (FK) conecta una fila con otra tabla. Una clave única evita duplicados. `NULL` significa que el dato puede faltar; no equivale a cero ni a texto vacío.

Las tablas globales son empresas, usuarios, plataformas, modulos y permisos. Las otras 35 incluyen empresa_id. Las FK compuestas incluyen la empresa y evitan cruces entre clientes, pero no reemplazan el filtro y la autorización de cada petición.

Todas las tablas incluyen id, created_at y updated_at. El estado de negocio puede coexistir con activo: no deben confundirse. Las relaciones no tienen borrado en cascada; conservar históricos y desactivar cuando el modelo lo permita.

La web y el móvil consumen la API Laravel. Las reglas descritas como responsabilidad de la API no son ejecutadas automáticamente por MySQL.

## Inventario de tablas

| Nº | Tabla | Finalidad |
|---|---|---|
| 1 | `empresas` | Registra cada organización que contrata o implementa la plataforma. Es la raíz de la separación de datos entre clientes. |
| 2 | `contratos` | Guarda los periodos comerciales de servicio, su estado y límites contratados por empresa. |
| 3 | `usuarios` | Almacena la identidad global de quien inicia sesión; la misma cuenta puede pertenecer a varias empresas. |
| 4 | `miembros_empresa` | Relaciona una cuenta global con una empresa y permite activar o desactivar esa pertenencia. |
| 5 | `plataformas` | Catálogo global de aplicaciones desde las que se concede acceso. |
| 6 | `modulos` | Catálogo global de funcionalidades comerciales y operativas. |
| 7 | `empresa_modulos` | Determina qué módulos están habilitados para cada empresa. |
| 8 | `roles` | Define perfiles de trabajo propios de cada empresa. |
| 9 | `permisos` | Catálogo global de acciones identificadas por módulo y operación. |
| 10 | `rol_permisos` | Tabla puente que concede acciones a un rol de empresa. |
| 11 | `accesos_aplicacion` | Autoriza a un miembro a usar una plataforma con un rol específico. |
| 12 | `sesiones` | Registra credenciales de sesión asociadas a un acceso concreto, su expiración y revocación. |
| 13 | `sedes` | Representa ubicaciones o centros de trabajo de una empresa. |
| 14 | `areas` | Divide una sede en unidades operativas. |
| 15 | `trabajadores` | Registra al personal operativo, tenga o no usuario de acceso. |
| 16 | `archivos` | Guarda metadatos y localización de archivos utilizados como documentos, fotos, firmas o evidencias. |
| 17 | `temas_hse` | Agrupa requisitos documentales de un asunto HSE dentro de un módulo. |
| 18 | `requisitos_documentales` | Define qué documento debe existir para evaluar un tema. |
| 19 | `documento_versiones` | Conserva cada presentación de un documento y el resultado actual de su revisión. |
| 20 | `documento_revisiones` | Registra decisiones de revisión y comentarios de una versión documental. |
| 21 | `checklist_plantillas` | Cabecera del formulario configurable y de cada una de sus versiones. |
| 22 | `checklist_secciones` | Organiza un formulario en apartados ordenados. |
| 23 | `checklist_preguntas` | Configura cada campo del formulario, su tipo, instrucciones y reglas de validación. |
| 24 | `checklist_pregunta_opciones` | Define las alternativas disponibles en selectores simples y múltiples. |
| 25 | `checklist_registros` | Representa una ejecución de un formulario por un usuario en un momento y área. |
| 26 | `checklist_respuestas` | Guarda una respuesta por pregunta de cada inspección. |
| 27 | `checklist_respuesta_opciones` | Guarda las alternativas seleccionadas en una respuesta. |
| 28 | `checklist_evidencias` | Adjunta fotos, archivos o firmas a una respuesta concreta. |
| 29 | `hallazgos` | Registra desviaciones o condiciones que requieren seguimiento, con responsables y fechas. |
| 30 | `hallazgo_seguimientos` | Conserva comentarios y avances del tratamiento del hallazgo. |
| 31 | `hallazgo_evidencias` | Relaciona archivos con detección, levantamiento o cierre del hallazgo. |
| 32 | `acciones_correctivas` | Divide el tratamiento de un hallazgo en tareas con responsable y plazo. |
| 33 | `epp_catalogo` | Define los artículos de protección que se entregan, incluyendo talla y ficha técnica. |
| 34 | `almacenes` | Identifica depósitos de EPP dentro de una sede. |
| 35 | `epp_entregas` | Cabecera del acto de entrega a un trabajador. |
| 36 | `epp_entrega_detalles` | Lista los artículos, cantidades y almacenes de una entrega. |
| 37 | `epp_movimientos` | Lleva el historial de variaciones de inventario mediante cantidades con signo. |
| 38 | `indicadores` | Define una métrica, su forma de cálculo y sentido de cumplimiento. |
| 39 | `indicador_mediciones` | Guarda el dato y la meta de un indicador para un mes. |
| 40 | `auditoria_eventos` | Registra quién realizó una acción y qué datos cambiaron. |

## Diccionario detallado

### 1. empresas

Registra cada organización que contrata o implementa la plataforma. Es la raíz de la separación de datos entre clientes.

**Ejemplo:** Empresa Agroindustrial A y Empresa Industrial B tienen trabajadores, documentos e inspecciones independientes.

**Regla de uso:** Desactivar una empresa no bloquea por sí solo las consultas: la API debe comprobar activo y filtrar por empresa autenticada.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `codigo` | `VARCHAR(40) NOT NULL` | Código identificador funcional. |
| `razon_social` | `VARCHAR(200) NOT NULL` | Denominación legal de la organización. |
| `nombre_comercial` | `VARCHAR(200) NULL` | Nombre comercial. |
| `pais` | `CHAR(2) NOT NULL` | Código de país de dos caracteres. |
| `identificacion_fiscal` | `VARCHAR(30) NOT NULL` | Identificación tributaria, como RUC o RUT. |
| `zona_horaria` | `VARCHAR(64) NOT NULL DEFAULT 'America/Lima'` | Zona horaria para interpretar fechas locales. |
| `direccion` | `VARCHAR(255) NULL` | Dirección física. |
| `contacto_email` | `VARCHAR(190) NULL` | Correo de contacto de la empresa. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

No declara claves foráneas; actúa como catálogo global o entidad raíz.

**Referenciada desde:** `contratos` mediante `empresa_id`; `miembros_empresa` mediante `empresa_id`; `empresa_modulos` mediante `empresa_id`; `roles` mediante `empresa_id`; `rol_permisos` mediante `empresa_id`; `accesos_aplicacion` mediante `empresa_id`; `sesiones` mediante `empresa_id`; `sedes` mediante `empresa_id`; `areas` mediante `empresa_id`; `trabajadores` mediante `empresa_id`; `archivos` mediante `empresa_id`; `temas_hse` mediante `empresa_id`; `requisitos_documentales` mediante `empresa_id`; `documento_versiones` mediante `empresa_id`; `documento_revisiones` mediante `empresa_id`; `checklist_plantillas` mediante `empresa_id`; `checklist_secciones` mediante `empresa_id`; `checklist_preguntas` mediante `empresa_id`; `checklist_pregunta_opciones` mediante `empresa_id`; `checklist_registros` mediante `empresa_id`; `checklist_respuestas` mediante `empresa_id`; `checklist_respuesta_opciones` mediante `empresa_id`; `checklist_evidencias` mediante `empresa_id`; `hallazgos` mediante `empresa_id`; `hallazgo_seguimientos` mediante `empresa_id`; `hallazgo_evidencias` mediante `empresa_id`; `acciones_correctivas` mediante `empresa_id`; `epp_catalogo` mediante `empresa_id`; `almacenes` mediante `empresa_id`; `epp_entregas` mediante `empresa_id`; `epp_entrega_detalles` mediante `empresa_id`; `epp_movimientos` mediante `empresa_id`; `indicadores` mediante `empresa_id`; `indicador_mediciones` mediante `empresa_id`; `auditoria_eventos` mediante `empresa_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_empresa_codigo (codigo)
UNIQUE KEY uq_empresa_fiscal (pais, identificacion_fiscal)
```

### 2. contratos

Guarda los periodos comerciales de servicio, su estado y límites contratados por empresa.

**Ejemplo:** Un contrato anual permite 30 usuarios y un límite de almacenamiento.

**Regla de uso:** La API debe controlar vigencia, consumo de límites y contratos solapados. NULL en límites necesita una política explícita; no existe facturación ni cobro automático.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `numero` | `VARCHAR(60) NOT NULL` | Número del contrato. |
| `fecha_inicio` | `DATE NOT NULL` | Inicio de vigencia contractual. |
| `fecha_fin` | `DATE NULL` | Fin de vigencia; puede quedar sin definir. |
| `estado` | `ENUM('prueba','activo','suspendido','terminado') NOT NULL DEFAULT 'prueba'` | Estado actual; valores y valor inicial indicados en la definición SQL. |
| `limite_usuarios` | `INT UNSIGNED NULL` | Límite de usuarios del contrato, aplicado por backend. |
| `limite_almacenamiento_mb` | `BIGINT UNSIGNED NULL` | Capacidad contratada en MB, controlada por backend. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |

**Referenciada desde:** ninguna tabla mediante FK en esta revisión.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_contratos_empresa (empresa_id, id)
UNIQUE KEY uq_contrato (empresa_id, numero)
CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
```

### 3. usuarios

Almacena la identidad global de quien inicia sesión; la misma cuenta puede pertenecer a varias empresas.

**Ejemplo:** Jeancarlos utiliza su correo para acceder a dos empresas.

**Regla de uso:** Guardar hash de contraseña, nunca texto plano. El correo es único globalmente; pertenecer a una empresa se configura en miembros_empresa.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `nombre` | `VARCHAR(150) NOT NULL` | Nombre visible del registro. |
| `email` | `VARCHAR(190) NOT NULL` | Correo de acceso único globalmente. |
| `password_hash` | `VARCHAR(255) NOT NULL` | Hash de contraseña generado por backend. |
| `email_verified_at` | `DATETIME NULL` | Fecha/hora en que se verificó el correo. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

No declara claves foráneas; actúa como catálogo global o entidad raíz.

**Referenciada desde:** `miembros_empresa` mediante `usuario_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_usuario_email (email)
```

### 4. miembros_empresa

Relaciona una cuenta global con una empresa y permite activar o desactivar esa pertenencia.

**Ejemplo:** El usuario 7 pertenece a las empresas A y B mediante dos membresías.

**Regla de uso:** Solo puede existir una membresía por pareja empresa/usuario. Los responsables operativos apuntan a esta membresía, no directamente a usuarios.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `usuario_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `usuarios` que identifica usuario. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `usuario_id` | `usuarios` | `id` |

**Referenciada desde:** `accesos_aplicacion` mediante `empresa_id, miembro_id`; `trabajadores` mediante `empresa_id, miembro_id`; `archivos` mediante `empresa_id, subido_por`; `temas_hse` mediante `empresa_id, responsable_id`; `documento_versiones` mediante `empresa_id, revisado_por`; `documento_revisiones` mediante `empresa_id, revisor_id`; `checklist_registros` mediante `empresa_id, realizado_por`; `hallazgos` mediante `empresa_id, reportado_por`; `hallazgos` mediante `empresa_id, responsable_id`; `hallazgos` mediante `empresa_id, cerrado_por`; `hallazgo_seguimientos` mediante `empresa_id, autor_id`; `acciones_correctivas` mediante `empresa_id, responsable_id`; `epp_entregas` mediante `empresa_id, entregado_por`; `epp_movimientos` mediante `empresa_id, registrado_por`; `indicador_mediciones` mediante `empresa_id, registrado_por`; `auditoria_eventos` mediante `empresa_id, actor_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_miembros_empresa_empresa (empresa_id, id)
UNIQUE KEY uq_membresia (empresa_id, usuario_id)
```

### 5. plataformas

Catálogo global de aplicaciones desde las que se concede acceso.

**Ejemplo:** web para Angular y movil para Flutter, tanto Android como iOS.

**Regla de uso:** El SQL inserta web y movil. La plataforma declarada por el cliente no es por sí sola una garantía de seguridad; la API debe validar el acceso y sus permisos.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `codigo` | `VARCHAR(20) NOT NULL` | Código identificador funcional. |
| `nombre` | `VARCHAR(80) NOT NULL` | Nombre visible del registro. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

No declara claves foráneas; actúa como catálogo global o entidad raíz.

**Referenciada desde:** `accesos_aplicacion` mediante `plataforma_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_plataforma (codigo)
```

### 6. modulos

Catálogo global de funcionalidades comerciales y operativas.

**Ejemplo:** checklists, epp, documentos y hallazgos.

**Regla de uso:** Un módulo registrado no implica que su funcionalidad esté implementada. El script incluye también nombres de módulos futuros.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `codigo` | `VARCHAR(50) NOT NULL` | Código identificador funcional. |
| `nombre` | `VARCHAR(120) NOT NULL` | Nombre visible del registro. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

No declara claves foráneas; actúa como catálogo global o entidad raíz.

**Referenciada desde:** `empresa_modulos` mediante `modulo_id`; `permisos` mediante `modulo_id`; `temas_hse` mediante `modulo_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_modulo (codigo)
```

### 7. empresa_modulos

Determina qué módulos están habilitados para cada empresa.

**Ejemplo:** Empresa A dispone de EPP y Checklists; Empresa B también tiene Hallazgos.

**Regla de uso:** La API debe comprobar esta habilitación además de los permisos del usuario.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `modulo_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `modulos` que identifica modulo. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `modulo_id` | `modulos` | `id` |

**Referenciada desde:** ninguna tabla mediante FK en esta revisión.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_empresa_modulos_empresa (empresa_id, id)
UNIQUE KEY uq_empresa_modulo (empresa_id, modulo_id)
```

### 8. roles

Define perfiles de trabajo propios de cada empresa.

**Ejemplo:** Administrador, Supervisor HSE e Inspector.

**Regla de uso:** No hay permisos implícitos por el nombre del rol; se asignan en rol_permisos.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `nombre` | `VARCHAR(80) NOT NULL` | Nombre visible del registro. |
| `descripcion` | `VARCHAR(255) NULL` | Descripción explicativa. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |

**Referenciada desde:** `rol_permisos` mediante `empresa_id, rol_id`; `accesos_aplicacion` mediante `empresa_id, rol_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_roles_empresa (empresa_id, id)
UNIQUE KEY uq_rol (empresa_id, nombre)
```

### 9. permisos

Catálogo global de acciones identificadas por módulo y operación.

**Ejemplo:** checklists.crear y hallazgos.aprobar.

**Regla de uso:** El SQL genera ver, crear, editar, aprobar, anular y exportar para cada módulo. Esto no crea endpoints ni asigna automáticamente derechos a roles.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `modulo_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `modulos` que identifica modulo. |
| `codigo` | `VARCHAR(100) NOT NULL` | Código identificador funcional. |
| `descripcion` | `VARCHAR(200) NOT NULL` | Descripción explicativa. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `modulo_id` | `modulos` | `id` |

**Referenciada desde:** `rol_permisos` mediante `permiso_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_permiso (codigo)
```

### 10. rol_permisos

Tabla puente que concede acciones a un rol de empresa.

**Ejemplo:** Inspector recibe checklists.ver y checklists.crear.

**Regla de uso:** Evita duplicar el mismo permiso en el mismo rol. Revocar esta asignación debe reflejarse en la autorización de la API y sus cachés.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `rol_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `roles` que identifica rol. |
| `permiso_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `permisos` que identifica permiso. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, rol_id` | `roles` | `empresa_id, id` |
| `permiso_id` | `permisos` | `id` |

**Referenciada desde:** ninguna tabla mediante FK en esta revisión.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_rol_permisos_empresa (empresa_id, id)
UNIQUE KEY uq_rol_permiso (empresa_id, rol_id, permiso_id)
```

### 11. accesos_aplicacion

Autoriza a un miembro a usar una plataforma con un rol específico.

**Ejemplo:** Jeancarlos es administrador en web e inspector en móvil.

**Regla de uso:** El esquema admite un rol por empresa, miembro y plataforma. Para múltiples roles simultáneos habría que ampliar el modelo.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `miembro_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `miembros_empresa` que identifica miembro. |
| `plataforma_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `plataformas` que identifica plataforma. |
| `rol_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `roles` que identifica rol. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, miembro_id` | `miembros_empresa` | `empresa_id, id` |
| `empresa_id, rol_id` | `roles` | `empresa_id, id` |
| `plataforma_id` | `plataformas` | `id` |

**Referenciada desde:** `sesiones` mediante `empresa_id, acceso_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_accesos_aplicacion_empresa (empresa_id, id)
UNIQUE KEY uq_acceso (empresa_id, miembro_id, plataforma_id)
```

### 12. sesiones

Registra credenciales de sesión asociadas a un acceso concreto, su expiración y revocación.

**Ejemplo:** La sesión del teléfono se revoca sin revocar necesariamente la del navegador.

**Regla de uso:** Conservar solo el digest del token. La API debe aplicar expiración, revocación y desactivación de accesos. No es una implementación automática de JWT/Sanctum.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `acceso_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `accesos_aplicacion` que identifica acceso. |
| `token_hash` | `CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL` | Digest del token; no guardar el token en texto plano. |
| `dispositivo` | `VARCHAR(200) NULL` | Descripción del dispositivo o cliente. |
| `ip` | `VARCHAR(45) NULL` | Dirección IP asociada al evento o sesión. |
| `expira_at` | `DATETIME NOT NULL` | Fecha/hora límite de la sesión. |
| `revocado_at` | `DATETIME NULL` | Fecha/hora de revocación, si existe. |
| `ultimo_uso_at` | `DATETIME NULL` | Fecha/hora del último uso, mantenida por backend. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, acceso_id` | `accesos_aplicacion` | `empresa_id, id` |

**Referenciada desde:** ninguna tabla mediante FK en esta revisión.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_sesiones_empresa (empresa_id, id)
UNIQUE KEY uq_token (token_hash)
```

### 13. sedes

Representa ubicaciones o centros de trabajo de una empresa.

**Ejemplo:** Planta Piura y Planta Olmos.

**Regla de uso:** Su código es único por empresa. Es la tabla padre de áreas y almacenes.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `codigo` | `VARCHAR(40) NOT NULL` | Código identificador funcional. |
| `nombre` | `VARCHAR(150) NOT NULL` | Nombre visible del registro. |
| `direccion` | `VARCHAR(255) NULL` | Dirección física. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |

**Referenciada desde:** `areas` mediante `empresa_id, sede_id`; `almacenes` mediante `empresa_id, sede_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_sedes_empresa (empresa_id, id)
UNIQUE KEY uq_sede (empresa_id, codigo)
```

### 14. areas

Divide una sede en unidades operativas.

**Ejemplo:** Mantenimiento dentro de Planta Piura.

**Regla de uso:** El nombre es único dentro de la sede. El modelo almacena la asignación actual de trabajadores, no un historial de traslados.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `sede_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `sedes` que identifica sede. |
| `nombre` | `VARCHAR(150) NOT NULL` | Nombre visible del registro. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, sede_id` | `sedes` | `empresa_id, id` |

**Referenciada desde:** `trabajadores` mediante `empresa_id, area_id`; `checklist_registros` mediante `empresa_id, area_id`; `hallazgos` mediante `empresa_id, area_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_areas_empresa (empresa_id, id)
UNIQUE KEY uq_area (empresa_id, sede_id, nombre)
```

### 15. trabajadores

Registra al personal operativo, tenga o no usuario de acceso.

**Ejemplo:** Juan Pérez recibe EPP aunque no tenga cuenta.

**Regla de uso:** Puede vincularse opcionalmente a una membresía. puesto es texto libre; todavía no hay catálogo de puestos ni matriz de EPP por puesto.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `area_id` | `BIGINT UNSIGNED NULL` | Referencia a `areas` que identifica area. |
| `miembro_id` | `BIGINT UNSIGNED NULL` | Referencia a `miembros_empresa` que identifica miembro. |
| `tipo_documento` | `VARCHAR(20) NOT NULL` | Tipo de identidad personal. |
| `numero_documento` | `VARCHAR(30) NOT NULL` | Número de documento del trabajador. |
| `nombres` | `VARCHAR(150) NOT NULL` | Nombres del trabajador. |
| `apellidos` | `VARCHAR(150) NOT NULL` | Apellidos del trabajador. |
| `puesto` | `VARCHAR(150) NULL` | Puesto de trabajo como texto libre. |
| `fecha_ingreso` | `DATE NULL` | Fecha de ingreso laboral. |
| `fecha_cese` | `DATE NULL` | Fecha de término laboral. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, area_id` | `areas` | `empresa_id, id` |
| `empresa_id, miembro_id` | `miembros_empresa` | `empresa_id, id` |

**Referenciada desde:** `epp_entregas` mediante `empresa_id, trabajador_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_trabajadores_empresa (empresa_id, id)
UNIQUE KEY uq_trabajador_doc (empresa_id, tipo_documento, numero_documento)
UNIQUE KEY uq_trabajador_miembro (empresa_id, miembro_id)
CHECK (fecha_cese IS NULL OR fecha_ingreso IS NULL OR fecha_cese >= fecha_ingreso)
```

### 16. archivos

Guarda metadatos y localización de archivos utilizados como documentos, fotos, firmas o evidencias.

**Ejemplo:** Una fotografía se almacena de forma privada y su registro se referencia desde un hallazgo.

**Regla de uso:** No contiene el binario. eliminado_at es una marca; borrar el objeto físico y autorizar descargas corresponde al backend. sha256 sirve como huella, no impone deduplicación.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `subido_por` | `BIGINT UNSIGNED NOT NULL` | Referencia a `miembros_empresa` que identifica subido por. |
| `nombre_original` | `VARCHAR(255) NOT NULL` | Nombre recibido al subir el archivo. |
| `disco` | `VARCHAR(40) NOT NULL` | Identificador del almacenamiento utilizado. |
| `ruta` | `VARCHAR(500) NOT NULL` | Ruta o clave privada del objeto almacenado. |
| `mime_type` | `VARCHAR(120) NOT NULL` | Tipo MIME declarado y a verificar. |
| `bytes` | `BIGINT UNSIGNED NOT NULL` | Tamaño del archivo en bytes. |
| `sha256` | `CHAR(64) CHARACTER SET ascii NULL` | Huella del contenido del archivo. |
| `clasificacion` | `ENUM('interno','confidencial','restringido') NOT NULL DEFAULT 'interno'` | Nivel de sensibilidad para políticas de acceso. |
| `eliminado_at` | `DATETIME NULL` | Marca temporal de eliminación lógica. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, subido_por` | `miembros_empresa` | `empresa_id, id` |

**Referenciada desde:** `documento_versiones` mediante `empresa_id, archivo_id`; `checklist_evidencias` mediante `empresa_id, archivo_id`; `hallazgo_evidencias` mediante `empresa_id, archivo_id`; `epp_catalogo` mediante `empresa_id, ficha_archivo_id`; `epp_entregas` mediante `empresa_id, firma_archivo_id`; `indicador_mediciones` mediante `empresa_id, evidencia_archivo_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_archivos_empresa (empresa_id, id)
```

### 17. temas_hse

Agrupa requisitos documentales de un asunto HSE dentro de un módulo.

**Ejemplo:** El tema Política SST tiene un responsable y documentos requeridos.

**Regla de uso:** aplica permite excluir un tema con motivo. El estado y porcentaje se consultan en v_estado_temas, no son columnas almacenadas aquí.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `modulo_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `modulos` que identifica modulo. |
| `codigo` | `VARCHAR(50) NOT NULL` | Código identificador funcional. |
| `nombre` | `VARCHAR(180) NOT NULL` | Nombre visible del registro. |
| `descripcion` | `TEXT NULL` | Descripción explicativa. |
| `responsable_id` | `BIGINT UNSIGNED NULL` | Referencia a `miembros_empresa` que identifica responsable. |
| `aplica` | `BOOLEAN NOT NULL DEFAULT TRUE` | Indica si el tema es aplicable a esta empresa. |
| `motivo_no_aplica` | `TEXT NULL` | Justificación cuando aplica es falso. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, responsable_id` | `miembros_empresa` | `empresa_id, id` |
| `modulo_id` | `modulos` | `id` |

**Referenciada desde:** `requisitos_documentales` mediante `empresa_id, tema_id`; `indicadores` mediante `empresa_id, tema_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_temas_hse_empresa (empresa_id, id)
UNIQUE KEY uq_tema (empresa_id, codigo)
CHECK (aplica = TRUE OR motivo_no_aplica IS NOT NULL)
```

### 18. requisitos_documentales

Define qué documento debe existir para evaluar un tema.

**Ejemplo:** Política firmada obligatoria con peso 2.

**Regla de uso:** Solo requisitos activos y obligatorios participan en el porcentaje de la vista del tema. La API debe comprobar vencimiento cuando sea requerido.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `tema_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `temas_hse` que identifica tema. |
| `nombre` | `VARCHAR(180) NOT NULL` | Nombre visible del registro. |
| `descripcion` | `TEXT NULL` | Descripción explicativa. |
| `obligatorio` | `BOOLEAN NOT NULL DEFAULT TRUE` | Indica si el requisito documental cuenta para el cumplimiento exigido. |
| `requiere_vencimiento` | `BOOLEAN NOT NULL DEFAULT FALSE` | Exige registrar vencimiento al aprobar el documento. |
| `peso` | `DECIMAL(10,2) NOT NULL DEFAULT 1` | Ponderación usada para evaluar cumplimiento. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, tema_id` | `temas_hse` | `empresa_id, id` |

**Referenciada desde:** `documento_versiones` mediante `empresa_id, requisito_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_requisitos_documentales_empresa (empresa_id, id)
CHECK (peso > 0)
```

### 19. documento_versiones

Conserva cada presentación de un documento y el resultado actual de su revisión.

**Ejemplo:** Versión 1 observada; versión 2 corregida y aprobada.

**Regla de uso:** La última versión sustituye a la anterior para evaluar, incluso si está pendiente. Actualizar su estado junto con el historial de revisiones en una transacción.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `requisito_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `requisitos_documentales` que identifica requisito. |
| `archivo_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `archivos` que identifica archivo. |
| `version` | `INT UNSIGNED NOT NULL` | Número de versión dentro del documento o código de plantilla. |
| `fecha_emision` | `DATE NULL` | Fecha de emisión del documento. |
| `fecha_vencimiento` | `DATE NULL` | Último día de vigencia documental, inclusivo. |
| `estado` | `ENUM('pendiente','aprobado','observado','rechazado') NOT NULL DEFAULT 'pendiente'` | Estado actual; valores y valor inicial indicados en la definición SQL. |
| `revisado_por` | `BIGINT UNSIGNED NULL` | Referencia a `miembros_empresa` que identifica revisado por. |
| `revisado_at` | `DATETIME NULL` | Fecha/hora de la revisión actual. |
| `comentario_revision` | `TEXT NULL` | Motivo o comentario de la revisión actual. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, requisito_id` | `requisitos_documentales` | `empresa_id, id` |
| `empresa_id, archivo_id` | `archivos` | `empresa_id, id` |
| `empresa_id, revisado_por` | `miembros_empresa` | `empresa_id, id` |

**Referenciada desde:** `documento_revisiones` mediante `empresa_id, documento_version_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_documento_versiones_empresa (empresa_id, id)
UNIQUE KEY uq_doc_version (empresa_id, requisito_id, version)
CHECK (version > 0)
CHECK (fecha_vencimiento IS NULL OR fecha_emision IS NULL OR fecha_vencimiento >= fecha_emision)
CHECK ((estado = 'pendiente' AND revisado_por IS NULL AND revisado_at IS NULL) OR (estado <> 'pendiente' AND revisado_por IS NOT NULL AND revisado_at IS NOT NULL))
```

### 20. documento_revisiones

Registra decisiones de revisión y comentarios de una versión documental.

**Ejemplo:** Un revisor observa una firma faltante y posteriormente aprueba la corrección según el flujo adoptado.

**Regla de uso:** Conservar como historial. No hay trigger que sincronice automáticamente la decisión con documento_versiones.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `documento_version_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `documento_versiones` que identifica documento version. |
| `revisor_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `miembros_empresa` que identifica revisor. |
| `resultado` | `ENUM('aprobado','observado','rechazado') NOT NULL DEFAULT 'observado'` | Resultado de revisión o evaluación de opción, según los valores SQL. |
| `comentario` | `TEXT NULL` | Comentario del registro. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, documento_version_id` | `documento_versiones` | `empresa_id, id` |
| `empresa_id, revisor_id` | `miembros_empresa` | `empresa_id, id` |

**Referenciada desde:** ninguna tabla mediante FK en esta revisión.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_documento_revisiones_empresa (empresa_id, id)
```

### 21. checklist_plantillas

Cabecera del formulario configurable y de cada una de sus versiones.

**Ejemplo:** Checklist de maquinarias, código MAQ, versión 1.

**Regla de uso:** Editar solo borradores. Para cambiar una versión publicada, copiar secciones, preguntas y opciones con nuevos IDs; la API impone la inmutabilidad.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `codigo` | `VARCHAR(50) NOT NULL` | Código identificador funcional. |
| `nombre` | `VARCHAR(180) NOT NULL` | Nombre visible del registro. |
| `descripcion` | `TEXT NULL` | Descripción explicativa. |
| `instrucciones` | `TEXT NULL` | Indicaciones generales para completar la plantilla. |
| `version` | `INT UNSIGNED NOT NULL DEFAULT 1` | Número de versión dentro del documento o código de plantilla. |
| `estado` | `ENUM('borrador','publicado','retirado') NOT NULL DEFAULT 'borrador'` | Estado actual; valores y valor inicial indicados en la definición SQL. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |

**Referenciada desde:** `checklist_secciones` mediante `empresa_id, plantilla_id`; `checklist_preguntas` mediante `empresa_id, plantilla_id`; `checklist_registros` mediante `empresa_id, plantilla_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_checklist_plantillas_empresa (empresa_id, id)
UNIQUE KEY uq_plantilla (empresa_id, codigo, version)
CHECK (version > 0)
```

### 22. checklist_secciones

Organiza un formulario en apartados ordenados.

**Ejemplo:** Identificación, Evaluación y Evidencias.

**Regla de uso:** Cada pregunta requiere sección de la misma plantilla. Crear General si no se necesitan apartados visibles.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `plantilla_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `checklist_plantillas` que identifica plantilla. |
| `titulo` | `VARCHAR(180) NOT NULL` | Título visible. |
| `descripcion` | `TEXT NULL` | Descripción explicativa. |
| `orden` | `INT UNSIGNED NOT NULL DEFAULT 1` | Posición de visualización; resolver empates con id. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, plantilla_id` | `checklist_plantillas` | `empresa_id, id` |

**Referenciada desde:** `checklist_preguntas` mediante `empresa_id, plantilla_id, seccion_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_checklist_secciones_empresa (empresa_id, id)
UNIQUE KEY uq_seccion_plantilla (empresa_id, plantilla_id, id)
KEY ix_seccion_orden (empresa_id, plantilla_id, orden)
CHECK (orden > 0)
```

### 23. checklist_preguntas

Configura cada campo del formulario, su tipo, instrucciones y reglas de validación.

**Ejemplo:** Nombre es texto; Estado es selector; Horómetro es número con mínimo cero.

**Regla de uso:** La interfaz interpreta esta configuración y la API valida las respuestas. No se crean columnas de base de datos por cada pregunta.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `plantilla_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `checklist_plantillas` que identifica plantilla. |
| `seccion_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `checklist_secciones` que identifica seccion. |
| `codigo` | `VARCHAR(60) NOT NULL` | Código identificador funcional. |
| `ayuda` | `TEXT NULL` | Texto de ayuda para quien responde. |
| `placeholder` | `VARCHAR(255) NULL` | Texto orientativo mostrado en el control vacío. |
| `pregunta` | `TEXT NOT NULL` | Etiqueta o enunciado del campo. |
| `tipo` | `ENUM('cumplimiento','texto','texto_largo','numero','fecha','fecha_hora','selector','seleccion_multiple','si_no','fotografia','archivo','firma') NOT NULL DEFAULT 'cumplimiento'` | Clasificación o tipo; valores disponibles en la definición SQL. |
| `obligatoria` | `BOOLEAN NOT NULL DEFAULT TRUE` | Exige respuesta antes de enviar el checklist. |
| `orden` | `INT UNSIGNED NOT NULL DEFAULT 1` | Posición de visualización; resolver empates con id. |
| `longitud_min` | `INT UNSIGNED NULL` | Mínimo de caracteres para texto respondido. |
| `longitud_max` | `INT UNSIGNED NULL` | Máximo de caracteres para texto. |
| `numero_min` | `DECIMAL(18,4) NULL` | Mínimo numérico admitido. |
| `numero_max` | `DECIMAL(18,4) NULL` | Máximo numérico admitido. |
| `fecha_min` | `DATE NULL` | Fecha mínima aceptada. |
| `fecha_max` | `DATE NULL` | Fecha máxima aceptada. |
| `seleccion_min` | `INT UNSIGNED NULL` | Cantidad mínima de opciones cuando se responde. |
| `seleccion_max` | `INT UNSIGNED NULL` | Cantidad máxima de opciones elegidas. |
| `archivos_min` | `INT UNSIGNED NULL` | Cantidad mínima de adjuntos cuando se responde. |
| `archivos_max` | `INT UNSIGNED NULL` | Cantidad máxima de adjuntos. |
| `archivo_max_bytes` | `BIGINT UNSIGNED NULL` | Tamaño máximo permitido por archivo. |
| `mime_permitidos` | `JSON NULL` | Array JSON de tipos MIME permitidos. |
| `peso` | `DECIMAL(10,2) NOT NULL DEFAULT 1` | Ponderación usada para evaluar cumplimiento. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, plantilla_id` | `checklist_plantillas` | `empresa_id, id` |
| `empresa_id, plantilla_id, seccion_id` | `checklist_secciones` | `empresa_id, plantilla_id, id` |

**Referenciada desde:** `checklist_pregunta_opciones` mediante `empresa_id, pregunta_id`; `checklist_respuestas` mediante `empresa_id, plantilla_id, pregunta_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_checklist_preguntas_empresa (empresa_id, id)
UNIQUE KEY uq_pregunta_plantilla (empresa_id, plantilla_id, id)
UNIQUE KEY uq_pregunta_codigo (empresa_id, plantilla_id, codigo)
KEY ix_pregunta_orden (empresa_id, plantilla_id, seccion_id, orden)
CHECK (orden > 0)
CHECK (longitud_min IS NULL OR longitud_max IS NULL OR longitud_min <= longitud_max)
CHECK (numero_min IS NULL OR numero_max IS NULL OR numero_min <= numero_max)
CHECK (fecha_min IS NULL OR fecha_max IS NULL OR fecha_min <= fecha_max)
CHECK (seleccion_min IS NULL OR seleccion_max IS NULL OR seleccion_min <= seleccion_max)
CHECK (archivos_min IS NULL OR archivos_max IS NULL OR archivos_min <= archivos_max)
CHECK (archivo_max_bytes IS NULL OR archivo_max_bytes > 0)
CHECK (mime_permitidos IS NULL OR JSON_TYPE(mime_permitidos) = 'ARRAY')
CHECK (peso >= 0)
```

### 24. checklist_pregunta_opciones

Define las alternativas disponibles en selectores simples y múltiples.

**Ejemplo:** Bueno equivale a cumple; Malo equivale a no_cumple y puede exigir foto.

**Regla de uso:** valor es el código interno; etiqueta es el texto mostrado. informativo no puntúa. Las opciones pertenecen a una pregunta y se versionan junto a su plantilla.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `pregunta_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `checklist_preguntas` que identifica pregunta. |
| `valor` | `VARCHAR(100) NOT NULL` | Código interno de la alternativa. |
| `etiqueta` | `VARCHAR(255) NOT NULL` | Texto visible de la alternativa. |
| `orden` | `INT UNSIGNED NOT NULL DEFAULT 1` | Posición de visualización; resolver empates con id. |
| `resultado` | `ENUM('informativo','cumple','no_cumple','no_aplica') NOT NULL DEFAULT 'informativo'` | Resultado de revisión o evaluación de opción, según los valores SQL. |
| `exige_observacion` | `BOOLEAN NOT NULL DEFAULT FALSE` | Exige comentario si se selecciona esta opción. |
| `exige_evidencia` | `BOOLEAN NOT NULL DEFAULT FALSE` | Exige adjunto si se selecciona esta opción. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, pregunta_id` | `checklist_preguntas` | `empresa_id, id` |

**Referenciada desde:** `checklist_respuesta_opciones` mediante `empresa_id, pregunta_id, opcion_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_checklist_pregunta_opciones_empresa (empresa_id, id)
UNIQUE KEY uq_opcion_pregunta (empresa_id, pregunta_id, id)
UNIQUE KEY uq_opcion_valor (empresa_id, pregunta_id, valor)
KEY ix_opcion_orden (empresa_id, pregunta_id, orden)
CHECK (orden > 0)
```

### 25. checklist_registros

Representa una ejecución de un formulario por un usuario en un momento y área.

**Ejemplo:** Inspección de la maquinaria EXC-001 el 22 de septiembre.

**Regla de uso:** La plantilla es la versión concreta utilizada. Al enviar deben validarse todas las respuestas en una transacción. El UUID permite detectar reintentos del móvil.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `plantilla_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `checklist_plantillas` que identifica plantilla. |
| `area_id` | `BIGINT UNSIGNED NULL` | Referencia a `areas` que identifica area. |
| `realizado_por` | `BIGINT UNSIGNED NOT NULL` | Referencia a `miembros_empresa` que identifica realizado por. |
| `uuid_cliente` | `CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL` | UUID generado por cliente; detecta reintentos de envío dentro de una empresa. |
| `fecha` | `DATETIME NOT NULL` | Fecha/hora del evento operativo. |
| `estado` | `ENUM('borrador','enviado','revisado','anulado') NOT NULL DEFAULT 'borrador'` | Estado actual; valores y valor inicial indicados en la definición SQL. |
| `observaciones` | `TEXT NULL` | Notas complementarias. |
| `latitud` | `DECIMAL(10,7) NULL` | Coordenada geográfica entre -90 y 90. |
| `longitud` | `DECIMAL(10,7) NULL` | Coordenada geográfica entre -180 y 180. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, plantilla_id` | `checklist_plantillas` | `empresa_id, id` |
| `empresa_id, area_id` | `areas` | `empresa_id, id` |
| `empresa_id, realizado_por` | `miembros_empresa` | `empresa_id, id` |

**Referenciada desde:** `checklist_respuestas` mediante `empresa_id, plantilla_id, registro_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_checklist_registros_empresa (empresa_id, id)
UNIQUE KEY uq_check_uuid (empresa_id, uuid_cliente)
UNIQUE KEY uq_check_plantilla (empresa_id, plantilla_id, id)
KEY ix_check_fecha (empresa_id, fecha)
CHECK (latitud BETWEEN -90 AND 90)
CHECK (longitud BETWEEN -180 AND 180)
```

### 26. checklist_respuestas

Guarda una respuesta por pregunta de cada inspección.

**Ejemplo:** Nombre = Excavadora; Horómetro = 1250.

**Regla de uso:** Las FK comprueban que pregunta y registro usan la misma plantilla. Selectores y adjuntos usan tablas hijas y mantienen los valores escalares en NULL.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `registro_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `checklist_registros` que identifica registro. |
| `plantilla_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `checklist_registros` que identifica plantilla. |
| `pregunta_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `checklist_preguntas` que identifica pregunta. |
| `cumplimiento` | `ENUM('cumple','no_cumple','no_aplica') NULL` | Respuesta del tipo básico cumple/no_cumple/no_aplica. |
| `valor_texto` | `TEXT NULL` | Respuesta textual corta o larga. |
| `valor_numero` | `DECIMAL(18,4) NULL` | Respuesta numérica. |
| `valor_fecha` | `DATE NULL` | Respuesta de fecha sin hora. |
| `valor_fecha_hora` | `DATETIME NULL` | Respuesta de fecha/hora guardada en UTC. |
| `valor_booleano` | `BOOLEAN NULL` | Respuesta Sí/No; 0 es No válido, NULL es ausencia de respuesta. |
| `observacion` | `TEXT NULL` | Nota complementaria. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, plantilla_id, registro_id` | `checklist_registros` | `empresa_id, plantilla_id, id` |
| `empresa_id, plantilla_id, pregunta_id` | `checklist_preguntas` | `empresa_id, plantilla_id, id` |

**Referenciada desde:** `checklist_respuesta_opciones` mediante `empresa_id, pregunta_id, respuesta_id`; `checklist_evidencias` mediante `empresa_id, respuesta_id`; `hallazgos` mediante `empresa_id, respuesta_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_checklist_respuestas_empresa (empresa_id, id)
UNIQUE KEY uq_respuesta (empresa_id, registro_id, pregunta_id)
UNIQUE KEY uq_respuesta_pregunta (empresa_id, pregunta_id, id)
CHECK (valor_booleano IS NULL OR valor_booleano IN (0,1))
CHECK ((cumplimiento IS NOT NULL) + (valor_texto IS NOT NULL) + (valor_numero IS NOT NULL) + (valor_fecha IS NOT NULL) + (valor_fecha_hora IS NOT NULL) + (valor_booleano IS NOT NULL) <= 1)
```

### 27. checklist_respuesta_opciones

Guarda las alternativas seleccionadas en una respuesta.

**Ejemplo:** Estado = Malo crea una fila; Motor y Frenos crea dos filas en una pregunta múltiple.

**Regla de uso:** Las FK impiden seleccionar opciones de otra pregunta. La API debe imponer una sola opción para selector; la base permite múltiples filas.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `respuesta_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `checklist_respuestas` que identifica respuesta. |
| `pregunta_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `checklist_respuestas` que identifica pregunta. |
| `opcion_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `checklist_pregunta_opciones` que identifica opcion. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, pregunta_id, respuesta_id` | `checklist_respuestas` | `empresa_id, pregunta_id, id` |
| `empresa_id, pregunta_id, opcion_id` | `checklist_pregunta_opciones` | `empresa_id, pregunta_id, id` |

**Referenciada desde:** ninguna tabla mediante FK en esta revisión.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_checklist_respuesta_opciones_empresa (empresa_id, id)
UNIQUE KEY uq_respuesta_opcion (empresa_id, respuesta_id, opcion_id)
```

### 28. checklist_evidencias

Adjunta fotos, archivos o firmas a una respuesta concreta.

**Ejemplo:** Foto de la fuga de aceite como evidencia del estado Malo.

**Regla de uso:** También puede acompañar una respuesta de texto o selector. Cantidad, MIME y obligatoriedad se validan en la API.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `respuesta_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `checklist_respuestas` que identifica respuesta. |
| `archivo_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `archivos` que identifica archivo. |
| `tipo` | `ENUM('fotografia','archivo','firma') NOT NULL DEFAULT 'fotografia'` | Clasificación o tipo; valores disponibles en la definición SQL. |
| `orden` | `INT UNSIGNED NOT NULL DEFAULT 1` | Posición de visualización; resolver empates con id. |
| `descripcion` | `VARCHAR(255) NULL` | Descripción explicativa. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, respuesta_id` | `checklist_respuestas` | `empresa_id, id` |
| `empresa_id, archivo_id` | `archivos` | `empresa_id, id` |

**Referenciada desde:** ninguna tabla mediante FK en esta revisión.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_checklist_evidencias_empresa (empresa_id, id)
CHECK (orden > 0)
UNIQUE KEY uq_check_evidencia (empresa_id, respuesta_id, archivo_id)
```

### 29. hallazgos

Registra desviaciones o condiciones que requieren seguimiento, con responsables y fechas.

**Ejemplo:** Una inspección detecta una protección de máquina deteriorada.

**Regla de uso:** Puede vincularse a una respuesta o registrarse directamente. Un no_cumple no genera automáticamente un hallazgo. La API gestiona sus transiciones y cierre.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `uuid_cliente` | `CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL` | UUID generado por cliente; detecta reintentos de envío dentro de una empresa. |
| `area_id` | `BIGINT UNSIGNED NULL` | Referencia a `areas` que identifica area. |
| `respuesta_id` | `BIGINT UNSIGNED NULL` | Referencia a `checklist_respuestas` que identifica respuesta. |
| `reportado_por` | `BIGINT UNSIGNED NOT NULL` | Referencia a `miembros_empresa` que identifica reportado por. |
| `responsable_id` | `BIGINT UNSIGNED NULL` | Referencia a `miembros_empresa` que identifica responsable. |
| `titulo` | `VARCHAR(200) NOT NULL` | Título visible. |
| `descripcion` | `TEXT NOT NULL` | Descripción explicativa. |
| `fecha` | `DATETIME NOT NULL` | Fecha/hora del evento operativo. |
| `severidad` | `ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media'` | Nivel de criticidad del hallazgo. |
| `estado` | `ENUM('abierto','en_proceso','por_validar','cerrado','anulado') NOT NULL DEFAULT 'abierto'` | Estado actual; valores y valor inicial indicados en la definición SQL. |
| `fecha_limite` | `DATE NULL` | Plazo para atender el hallazgo o acción. |
| `cerrado_at` | `DATETIME NULL` | Fecha/hora de cierre del hallazgo. |
| `cerrado_por` | `BIGINT UNSIGNED NULL` | Referencia a `miembros_empresa` que identifica cerrado por. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, area_id` | `areas` | `empresa_id, id` |
| `empresa_id, respuesta_id` | `checklist_respuestas` | `empresa_id, id` |
| `empresa_id, reportado_por` | `miembros_empresa` | `empresa_id, id` |
| `empresa_id, responsable_id` | `miembros_empresa` | `empresa_id, id` |
| `empresa_id, cerrado_por` | `miembros_empresa` | `empresa_id, id` |

**Referenciada desde:** `hallazgo_seguimientos` mediante `empresa_id, hallazgo_id`; `hallazgo_evidencias` mediante `empresa_id, hallazgo_id`; `acciones_correctivas` mediante `empresa_id, hallazgo_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_hallazgos_empresa (empresa_id, id)
UNIQUE KEY uq_hallazgo_uuid (empresa_id, uuid_cliente)
KEY ix_hallazgo_estado (empresa_id, estado, fecha_limite)
CHECK (estado <> 'cerrado' OR (cerrado_at IS NOT NULL AND cerrado_por IS NOT NULL))
```

### 30. hallazgo_seguimientos

Conserva comentarios y avances del tratamiento del hallazgo.

**Ejemplo:** El supervisor informa que se solicitó el repuesto.

**Regla de uso:** estado_resultante es opcional. Si representa una transición, actualizar también hallazgos en la misma transacción.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `hallazgo_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `hallazgos` que identifica hallazgo. |
| `autor_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `miembros_empresa` que identifica autor. |
| `comentario` | `TEXT NOT NULL` | Comentario del registro. |
| `estado_resultante` | `ENUM('abierto','en_proceso','por_validar','cerrado','anulado') NULL` | Estado informado por el seguimiento; puede ser NULL. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, hallazgo_id` | `hallazgos` | `empresa_id, id` |
| `empresa_id, autor_id` | `miembros_empresa` | `empresa_id, id` |

**Referenciada desde:** ninguna tabla mediante FK en esta revisión.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_hallazgo_seguimientos_empresa (empresa_id, id)
```

### 31. hallazgo_evidencias

Relaciona archivos con detección, levantamiento o cierre del hallazgo.

**Ejemplo:** Foto inicial del daño y fotografía posterior de la reparación.

**Regla de uso:** El tipo distingue el momento de la evidencia. No certifica por sí mismo que una corrección sea válida.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `hallazgo_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `hallazgos` que identifica hallazgo. |
| `archivo_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `archivos` que identifica archivo. |
| `tipo` | `ENUM('deteccion','levantamiento','cierre') NOT NULL DEFAULT 'deteccion'` | Clasificación o tipo; valores disponibles en la definición SQL. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, hallazgo_id` | `hallazgos` | `empresa_id, id` |
| `empresa_id, archivo_id` | `archivos` | `empresa_id, id` |

**Referenciada desde:** ninguna tabla mediante FK en esta revisión.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_hallazgo_evidencias_empresa (empresa_id, id)
```

### 32. acciones_correctivas

Divide el tratamiento de un hallazgo en tareas con responsable y plazo.

**Ejemplo:** Reemplazar protección y capacitar al operador son dos acciones del mismo hallazgo.

**Regla de uso:** Completar acciones no cierra automáticamente el hallazgo. El backend debe definir la revisión y las condiciones de cierre.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `hallazgo_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `hallazgos` que identifica hallazgo. |
| `responsable_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `miembros_empresa` que identifica responsable. |
| `descripcion` | `TEXT NOT NULL` | Descripción explicativa. |
| `fecha_limite` | `DATE NOT NULL` | Plazo para atender el hallazgo o acción. |
| `estado` | `ENUM('pendiente','en_proceso','por_validar','completada','anulada') NOT NULL DEFAULT 'pendiente'` | Estado actual; valores y valor inicial indicados en la definición SQL. |
| `completada_at` | `DATETIME NULL` | Fecha/hora de finalización de la acción. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, hallazgo_id` | `hallazgos` | `empresa_id, id` |
| `empresa_id, responsable_id` | `miembros_empresa` | `empresa_id, id` |

**Referenciada desde:** ninguna tabla mediante FK en esta revisión.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_acciones_correctivas_empresa (empresa_id, id)
KEY ix_accion_plazo (empresa_id, estado, fecha_limite)
```

### 33. epp_catalogo

Define los artículos de protección que se entregan, incluyendo talla y ficha técnica.

**Ejemplo:** Guante anticorte talla M con código GUA-M.

**Regla de uso:** Cada variante cuyo stock deba separarse necesita su propio código. vida_util_dias es una referencia configurable, no una fecha de reposición calculada.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `codigo` | `VARCHAR(50) NOT NULL` | Código identificador funcional. |
| `nombre` | `VARCHAR(180) NOT NULL` | Nombre visible del registro. |
| `talla` | `VARCHAR(30) NOT NULL DEFAULT 'UNICA'` | Talla o variante del EPP. |
| `unidad` | `VARCHAR(30) NOT NULL DEFAULT 'unidad'` | Unidad del artículo o de la métrica. |
| `vida_util_dias` | `INT UNSIGNED NULL` | Vida útil referencial configurable en días. |
| `ficha_archivo_id` | `BIGINT UNSIGNED NULL` | Referencia a `archivos` que identifica ficha archivo. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, ficha_archivo_id` | `archivos` | `empresa_id, id` |

**Referenciada desde:** `epp_entrega_detalles` mediante `empresa_id, epp_id`; `epp_movimientos` mediante `empresa_id, epp_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_epp_catalogo_empresa (empresa_id, id)
UNIQUE KEY uq_epp (empresa_id, codigo)
```

### 34. almacenes

Identifica depósitos de EPP dentro de una sede.

**Ejemplo:** Almacén principal y almacén de campo.

**Regla de uso:** Su nombre es único por sede. El protocolo descrito en el SQL bloquea el almacén al calcular y modificar existencias.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `sede_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `sedes` que identifica sede. |
| `nombre` | `VARCHAR(150) NOT NULL` | Nombre visible del registro. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, sede_id` | `sedes` | `empresa_id, id` |

**Referenciada desde:** `epp_entrega_detalles` mediante `empresa_id, almacen_id`; `epp_movimientos` mediante `empresa_id, almacen_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_almacenes_empresa (empresa_id, id)
UNIQUE KEY uq_almacen (empresa_id, sede_id, nombre)
```

### 35. epp_entregas

Cabecera del acto de entrega a un trabajador.

**Ejemplo:** Juan Pérez recibe casco y guantes en una misma entrega.

**Regla de uso:** Borrador no descuenta stock. Confirmación y movimientos negativos deben guardarse juntos. La firma adjunta es evidencia, no firma digital certificada.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `uuid_cliente` | `CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL` | UUID generado por cliente; detecta reintentos de envío dentro de una empresa. |
| `trabajador_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `trabajadores` que identifica trabajador. |
| `entregado_por` | `BIGINT UNSIGNED NOT NULL` | Referencia a `miembros_empresa` que identifica entregado por. |
| `fecha` | `DATETIME NOT NULL` | Fecha/hora del evento operativo. |
| `estado` | `ENUM('borrador','confirmada','anulada') NOT NULL DEFAULT 'borrador'` | Estado actual; valores y valor inicial indicados en la definición SQL. |
| `firma_archivo_id` | `BIGINT UNSIGNED NULL` | Referencia a `archivos` que identifica firma archivo. |
| `recibido_at` | `DATETIME NULL` | Momento de recepción por el trabajador. |
| `observacion` | `TEXT NULL` | Nota complementaria. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, trabajador_id` | `trabajadores` | `empresa_id, id` |
| `empresa_id, entregado_por` | `miembros_empresa` | `empresa_id, id` |
| `empresa_id, firma_archivo_id` | `archivos` | `empresa_id, id` |

**Referenciada desde:** `epp_entrega_detalles` mediante `empresa_id, entrega_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_epp_entregas_empresa (empresa_id, id)
UNIQUE KEY uq_entrega_uuid (empresa_id, uuid_cliente)
KEY ix_entrega_trabajador (empresa_id, trabajador_id, fecha)
```

### 36. epp_entrega_detalles

Lista los artículos, cantidades y almacenes de una entrega.

**Ejemplo:** Un casco desde almacén principal y dos pares de guantes desde almacén de campo.

**Regla de uso:** La cantidad debe ser positiva. fecha_reposicion es explícita; el backend debe determinarla.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `entrega_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `epp_entregas` que identifica entrega. |
| `epp_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `epp_catalogo` que identifica epp. |
| `almacen_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `almacenes` que identifica almacen. |
| `cantidad` | `DECIMAL(12,3) NOT NULL` | Cantidad de artículos; en movimientos el signo aumenta o reduce el stock. |
| `fecha_reposicion` | `DATE NULL` | Fecha prevista para una nueva entrega. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, entrega_id` | `epp_entregas` | `empresa_id, id` |
| `empresa_id, epp_id` | `epp_catalogo` | `empresa_id, id` |
| `empresa_id, almacen_id` | `almacenes` | `empresa_id, id` |

**Referenciada desde:** `epp_movimientos` mediante `empresa_id, epp_id, almacen_id, entrega_detalle_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_epp_entrega_detalles_empresa (empresa_id, id)
CHECK (cantidad > 0)
UNIQUE KEY uq_detalle_stock (empresa_id, epp_id, almacen_id, id)
```

### 37. epp_movimientos

Lleva el historial de variaciones de inventario mediante cantidades con signo.

**Ejemplo:** Entrada +100 cascos, entrega -1, saldo 99.

**Regla de uso:** No borrar movimientos confirmados: corregir con reversos. Comprobar stock, cantidades de devolución y claves de operación en la API. La FK de detalle valida EPP y almacén.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `epp_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `epp_catalogo` que identifica epp. |
| `almacen_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `almacenes` que identifica almacen. |
| `entrega_detalle_id` | `BIGINT UNSIGNED NULL` | Referencia a `epp_entrega_detalles` que identifica entrega detalle. |
| `registrado_por` | `BIGINT UNSIGNED NOT NULL` | Referencia a `miembros_empresa` que identifica registrado por. |
| `fecha` | `DATETIME NOT NULL` | Fecha/hora del evento operativo. |
| `cantidad` | `DECIMAL(12,3) NOT NULL` | Cantidad de artículos; en movimientos el signo aumenta o reduce el stock. |
| `tipo` | `ENUM('entrada','entrega','devolucion','ajuste','reverso') NOT NULL DEFAULT 'entrada'` | Clasificación o tipo; valores disponibles en la definición SQL. |
| `clave_operacion` | `VARCHAR(100) NOT NULL` | Identificador único por empresa para evitar duplicar movimientos. |
| `observacion` | `TEXT NULL` | Nota complementaria. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, epp_id` | `epp_catalogo` | `empresa_id, id` |
| `empresa_id, almacen_id` | `almacenes` | `empresa_id, id` |
| `empresa_id, registrado_por` | `miembros_empresa` | `empresa_id, id` |
| `empresa_id, epp_id, almacen_id, entrega_detalle_id` | `epp_entrega_detalles` | `empresa_id, epp_id, almacen_id, id` |

**Referenciada desde:** ninguna tabla mediante FK en esta revisión.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_epp_movimientos_empresa (empresa_id, id)
UNIQUE KEY uq_mov_operacion (empresa_id, clave_operacion)
KEY ix_stock (empresa_id, almacen_id, epp_id)
CHECK (cantidad <> 0)
CHECK ((tipo IN ('entrada','devolucion') AND cantidad > 0) OR (tipo = 'entrega' AND cantidad < 0 AND entrega_detalle_id IS NOT NULL) OR tipo IN ('ajuste','reverso'))
```

### 38. indicadores

Define una métrica, su forma de cálculo y sentido de cumplimiento.

**Ejemplo:** Porcentaje de inspecciones ejecutadas: razón con factor 100; mayor es mejor.

**Regla de uso:** La definición no contiene consultas automáticas a los módulos ni fórmulas arbitrarias. Las mediciones se capturan o calculan desde el backend.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `tema_id` | `BIGINT UNSIGNED NULL` | Referencia a `temas_hse` que identifica tema. |
| `codigo` | `VARCHAR(50) NOT NULL` | Código identificador funcional. |
| `nombre` | `VARCHAR(180) NOT NULL` | Nombre visible del registro. |
| `descripcion` | `TEXT NULL` | Descripción explicativa. |
| `unidad` | `VARCHAR(30) NOT NULL` | Unidad del artículo o de la métrica. |
| `tipo_calculo` | `ENUM('manual','razon') NOT NULL DEFAULT 'manual'` | manual o razon (numerador/denominador × factor). |
| `factor` | `DECIMAL(16,4) NOT NULL DEFAULT 100` | Multiplicador de la razón; 100 para porcentajes. |
| `sentido` | `ENUM('mayor_mejor','menor_mejor') NOT NULL DEFAULT 'mayor_mejor'` | Define si cumplir significa llegar/superar la meta o quedar por debajo/igual. |
| `activo` | `BOOLEAN NOT NULL DEFAULT TRUE` | Marca de habilitación (la API debe aplicar su efecto). |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, tema_id` | `temas_hse` | `empresa_id, id` |

**Referenciada desde:** `indicador_mediciones` mediante `empresa_id, indicador_id`.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_indicadores_empresa (empresa_id, id)
UNIQUE KEY uq_indicador (empresa_id, codigo)
CHECK (factor > 0)
```

### 39. indicador_mediciones

Guarda el dato y la meta de un indicador para un mes.

**Ejemplo:** Septiembre: 18 inspecciones de 20 previstas, resultado 90%, meta 95%.

**Regla de uso:** Solo una fila por indicador/mes/empresa. periodo usa el primer día del mes. La API valida que los valores concuerden con el tipo de cálculo del indicador.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `indicador_id` | `BIGINT UNSIGNED NOT NULL` | Referencia a `indicadores` que identifica indicador. |
| `periodo` | `DATE NOT NULL` | Mes representado por su primer día. |
| `numerador` | `DECIMAL(18,4) NULL` | Parte realizada o cantidad base del cálculo. |
| `denominador` | `DECIMAL(18,4) NULL` | Base total de comparación. Cero produce sin_datos en la vista. |
| `valor_manual` | `DECIMAL(18,4) NULL` | Valor directo de la medición manual. |
| `meta` | `DECIMAL(18,4) NOT NULL` | Objetivo numérico de ese mes. |
| `registrado_por` | `BIGINT UNSIGNED NOT NULL` | Referencia a `miembros_empresa` que identifica registrado por. |
| `evidencia_archivo_id` | `BIGINT UNSIGNED NULL` | Referencia a `archivos` que identifica evidencia archivo. |
| `comentario` | `TEXT NULL` | Comentario del registro. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, indicador_id` | `indicadores` | `empresa_id, id` |
| `empresa_id, registrado_por` | `miembros_empresa` | `empresa_id, id` |
| `empresa_id, evidencia_archivo_id` | `archivos` | `empresa_id, id` |

**Referenciada desde:** ninguna tabla mediante FK en esta revisión.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_indicador_mediciones_empresa (empresa_id, id)
UNIQUE KEY uq_indicador_periodo (empresa_id, indicador_id, periodo)
CHECK (DAYOFMONTH(periodo) = 1)
CHECK (denominador IS NULL OR denominador >= 0)
CHECK ((valor_manual IS NOT NULL AND numerador IS NULL AND denominador IS NULL) OR (valor_manual IS NULL AND numerador IS NOT NULL AND denominador IS NOT NULL))
```

### 40. auditoria_eventos

Registra quién realizó una acción y qué datos cambiaron.

**Ejemplo:** Un responsable aprueba un documento y se registra el antes/después.

**Regla de uso:** entidad y registro_id son referencias informativas sin FK a cada entidad. Debe escribirse desde el backend y protegerse contra modificaciones; no se alimenta automáticamente.

**Campos**

| Campo | Definición SQL | Significado |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | Identificador interno autoincremental y clave primaria. |
| `empresa_id` | `BIGINT UNSIGNED NOT NULL` | Empresa propietaria; separa los datos de cada cliente. |
| `actor_id` | `BIGINT UNSIGNED NULL` | Referencia a `miembros_empresa` que identifica actor. |
| `accion` | `VARCHAR(100) NOT NULL` | Operación auditada. |
| `entidad` | `VARCHAR(100) NOT NULL` | Nombre de la entidad afectada. |
| `registro_id` | `BIGINT UNSIGNED NULL` | ID informativo de la fila afectada en entidad; no tiene FK. |
| `datos_antes` | `JSON NULL` | Instantánea JSON previa al cambio. |
| `datos_despues` | `JSON NULL` | Instantánea JSON posterior al cambio. |
| `ip` | `VARCHAR(45) NULL` | Dirección IP asociada al evento o sesión. |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Momento de creación; por defecto fecha/hora del servidor. |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Última modificación de la fila; se actualiza automáticamente. |

**Relaciones de salida**

| Campos locales | Tabla destino | Campos destino |
|---|---|---|
| `empresa_id` | `empresas` | `id` |
| `empresa_id, actor_id` | `miembros_empresa` | `empresa_id, id` |

**Referenciada desde:** ninguna tabla mediante FK en esta revisión.

**Restricciones e índices declarados**

```sql
UNIQUE KEY uq_auditoria_eventos_empresa (empresa_id, id)
KEY ix_auditoria_registro (empresa_id, entidad, registro_id)
KEY ix_auditoria_fecha (empresa_id, created_at)
```

## Vistas de consulta

Las vistas calculan resultados sobre las tablas; no son nuevas copias de los datos. Siempre se deben filtrar por empresa.

| Vista | Tablas o vistas de origen | Finalidad y comportamiento |
|---|---|---|
| `v_estado_requisitos` | requisitos_documentales, documento_versiones, archivos | Considera requisitos activos y la versión de mayor número. Sin versión: sin_documento; no aprobada: conserva pendiente/observado/rechazado; aprobada sin vencimiento requerido: incompleto; aprobada vencida: vencido; archivo eliminado: sin_documento; de otro modo: vigente. Ese es el orden de evaluación del SQL. |
| `v_estado_temas` | temas_hse, v_estado_requisitos | Solo requisitos activos obligatorios. Porcentaje ponderado de vigentes. Prioridad de estado: no_aplica, sin_configurar, cumplido, vencido, observado, pendiente. Sin requisitos o tema no aplicable: porcentaje NULL. |
| `v_epp_stock` | epp_movimientos | Suma cantidad por empresa, almacén y EPP. Un artículo sin movimientos no aparece: el backend puede combinar catálogo y almacenes para mostrar cero. |
| `v_indicador_resultados` | indicador_mediciones, indicadores | Valor manual o numerador/denominador × factor. Evalúa contra meta con sentido mayor_mejor o menor_mejor. Datos insuficientes o divisor cero: sin_datos. |

CURRENT_DATE de las vistas documentales utiliza la zona de la sesión. La API debe considerar la fecha local de la empresa. Subir una versión pendiente reemplaza a la aprobada anterior en la evaluación; el SQL no mantiene la anterior como vigente durante la revisión.

## Flujos completos de ejemplo

### Alta de empresa y acceso

1. Crear empresas y contratos.
2. Habilitar los módulos contratados en empresa_modulos.
3. Crear usuarios y sus miembros_empresa.
4. Crear roles y asignar acciones en rol_permisos.
5. Crear accesos_aplicacion por web o móvil y emitir la sesión.
6. En cada operación, validar empresa, contrato, cuenta, membresía, acceso, módulo y permiso. Estas comprobaciones pertenecen al backend.

### Checklist de maquinaria

1. Crear checklist_plantillas MAQ versión 1 como borrador.
2. Crear secciones Identificación y Evaluación.
3. Crear preguntas Nombre (texto), Código (texto), Estado (selector) y Foto (fotografia).
4. Para Estado, crear Bueno/cumple y Malo/no_cumple. En Malo exigir evidencia si corresponde.
5. Publicar la versión. La aplicación debe impedir cambiar su configuración desde entonces.
6. Crear checklist_registros; guardar respuestas de texto, la alternativa de Estado en checklist_respuesta_opciones y la foto en checklist_evidencias.
7. Al enviar, validar obligatoriedad, tipos, rangos, selección única/múltiple y adjuntos. La FK protege la pertenencia a plantilla/pregunta/empresa; no aplica por sí sola todas estas reglas.
8. Si procede, crear un hallazgo vinculado a la respuesta. Un incumplimiento no lo crea automáticamente.

| Tipo de pregunta | Lugar donde se guarda la respuesta |
|---|---|
| texto / texto_largo | checklist_respuestas.valor_texto |
| numero | checklist_respuestas.valor_numero |
| fecha | checklist_respuestas.valor_fecha |
| fecha_hora | checklist_respuestas.valor_fecha_hora |
| si_no | checklist_respuestas.valor_booleano |
| cumplimiento | checklist_respuestas.cumplimiento |
| selector / seleccion_multiple | checklist_respuesta_opciones, asociada a checklist_respuestas |
| fotografia / archivo / firma | checklist_evidencias y archivos, asociados a checklist_respuestas |

Siempre se crea checklist_respuestas, incluso para selecciones o adjuntos. NULL significa ausencia; 0 en valor_booleano representa una respuesta No válida. Las preguntas opcionales vacías no incumplen mínimos de contenido; las respondidas sí deben respetarlos.

El puntaje del checklist se calcula en la API: suma de pesos que cumplen / suma de pesos evaluables × 100. No aplica e informativo quedan excluidos; denominador cero devuelve NULL. No hay vista de puntaje de checklist ni constructor visual implementado dentro del SQL.

### Entrega de EPP

1. Crear catálogo, sede, almacén y trabajador.
2. Registrar entrada +100 cascos en epp_movimientos.
3. Crear una entrega borrador y un detalle por 1 casco.
4. Al confirmar, bloquear el almacén, consultar existencias y registrar salida -1 junto con la confirmación en una transacción.
5. v_epp_stock devuelve 99. Registrar firma en archivos y vincularla a la entrega si se requiere.
6. Anular una entrega confirmada implica un movimiento inverso y cambio de estado, no borrar el historial. Devolución física y anulación son operaciones diferentes.

La API debe limitar devoluciones a cantidades justificadas y evitar duplicar confirmaciones/reversos. El esquema no gestiona lotes, compras ni estados de cuarentena de EPP devueltos. La matriz de EPP exigidos por puesto todavía no existe.

### Evaluación de documentación

1. Crear tema Política SST y requisito Política firmada.
2. Subir archivo y registrar documento_versiones pendiente.
3. Revisor registra documento_revisiones y actualiza la versión a aprobado en la misma transacción.
4. Las vistas calculan vigencia y cumplimiento sin necesitar almacenar un porcentaje en temas_hse.
5. Las alertas de vencimiento requieren tareas programadas; las vistas no envían avisos.

### Seguimiento de hallazgo

1. Registrar hallazgo, reportante, severidad y responsable.
2. Asociar evidencias de detección y acciones correctivas con plazos.
3. Registrar avances en hallazgo_seguimientos y fotos de levantamiento.
4. Validar la corrección y registrar cierre con responsable y fecha. El cierre de acciones no cierra por sí solo el hallazgo.

### Indicador mensual

Registrar indicador tipo razon, factor 100, sentido mayor_mejor. Para el mes 2026-09-01, capturar numerador 18, denominador 20 y meta 95. La vista calcula 90 y no_cumple. No implica que el SQL haya contado automáticamente las inspecciones: la API debe preparar o capturar los datos.

## Límites y responsabilidades de implementación

- El SQL es de instalación en una base vacía; no es una migración incremental.
- Las FK y CHECK protegen relaciones y restricciones declaradas. Las transiciones de estado, inmutabilidad, autorización y reglas entre filas requieren código del backend.
- El catálogo modulos contempla ampliaciones, pero aún no existen tablas operativas específicas de IPERC, capacitaciones/exámenes, EMO, incidentes, comités, brigadas, permisos de trabajo, vehículos, sustancias químicas o garita.
- No existe matriz de EPP por puesto/actividad ni historial de asignaciones de puestos. Los campos actuales permiten seguimiento de entregas, no determinar automáticamente toda la dotación exigida.
- El constructor de checklist no incluye preguntas condicionales, grupos repetibles ni diseño de impresión. La configuración del formulario sí está modelada.
- Sesiones, auditoría y archivos requieren integración explícita en Laravel. El SQL no genera logs, guarda binarios, envía alertas ni controla por sí mismo las descargas.
- Se documentó el archivo fuente, no una base de producción. No se ejecutó una importación de MySQL durante la elaboración de este documento.


## Control de cobertura

Se documentaron 40 tablas, 405 campos, 97 claves foráneas y 4 vistas. Fuente: revisión 2 de gassip_hse_mysql.sql. SHA-256 del archivo fuente: `226521e595b8fc19ed790293116cbd08a327f98762f68a100d7847df8dcfbe30`.
