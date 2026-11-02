-- GASSIP / HSE - Esquema inicial multiempresa
-- Revision 2: constructor dinamico de checklists. 40 tablas y 4 vistas.
-- INSTALACION NUEVA: este archivo NO es una migracion de una BD ya creada.
-- Si ya importo la revision 1, requiere un ALTER/migracion con conversion de secciones.
-- Destino: MySQL 8.0.16+ (InnoDB, utf8mb4). Ejecutar UNA VEZ en una BD vacia.
-- No elimina tablas ni desactiva las claves foraneas. DDL no es transaccional.
-- VPS: descomentar CREATE DATABASE y USE si dispone de permisos.
-- Hosting: crear/seleccionar la BD asignada en el panel e importar este archivo.
-- CREATE DATABASE gassip_hse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE gassip_hse;
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- CONTRATO DE IMPLEMENTACION
-- Web y movil consumen una API (Laravel); nunca se conectan directamente a MySQL.
-- La API obtiene empresa_id de la membresia autenticada y filtra TODAS las consultas.
-- Las FK compuestas impiden referencias entre empresas; no sustituyen autorizacion.
-- Comprobar empresa, contrato, usuario, membresia, acceso por plataforma y permiso.
-- Conservar historicos: desactivar registros en vez de eliminarlos. FK usa RESTRICT.
-- Password: Hash::make() (bcrypt/Argon2). Tokens: guardar solo digest SHA-256.
-- Archivos privados fuera del directorio publico: ruta/objeto, no BLOB ni URL publica.
-- Descargas con autorizacion. Validar MIME, tamano, firma, malware y privacidad.
-- Fechas de eventos en UTC; fechas calendario se interpretan en zona de la empresa.
-- UUID generado por cliente identifica reintentos; la API resuelve conflictos offline.
-- Controlar transiciones y reglas entre filas mediante transacciones en la API.
-- No se incluyen usuarios ni passwords iniciales.

CREATE TABLE empresas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(40) NOT NULL,
  razon_social VARCHAR(200) NOT NULL,
  nombre_comercial VARCHAR(200) NULL,
  pais CHAR(2) NOT NULL,
  identificacion_fiscal VARCHAR(30) NOT NULL,
  zona_horaria VARCHAR(64) NOT NULL DEFAULT 'America/Lima',
  direccion VARCHAR(255) NULL,
  contacto_email VARCHAR(190) NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_empresa_codigo (codigo),
  UNIQUE KEY uq_empresa_fiscal (pais, identificacion_fiscal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contratos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  numero VARCHAR(60) NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NULL,
  estado ENUM('prueba','activo','suspendido','terminado') NOT NULL DEFAULT 'prueba',
  limite_usuarios INT UNSIGNED NULL,
  limite_almacenamiento_mb BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_contratos_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  UNIQUE KEY uq_contrato (empresa_id, numero),
  CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email_verified_at DATETIME NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuario_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE miembros_empresa (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_miembros_empresa_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  UNIQUE KEY uq_membresia (empresa_id, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plataformas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plataforma (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE modulos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(50) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_modulo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE empresa_modulos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  modulo_id BIGINT UNSIGNED NOT NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_empresa_modulos_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (modulo_id) REFERENCES modulos(id),
  UNIQUE KEY uq_empresa_modulo (empresa_id, modulo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  UNIQUE KEY uq_rol (empresa_id, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  modulo_id BIGINT UNSIGNED NOT NULL,
  codigo VARCHAR(100) NOT NULL,
  descripcion VARCHAR(200) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (modulo_id) REFERENCES modulos(id),
  UNIQUE KEY uq_permiso (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rol_permisos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  rol_id BIGINT UNSIGNED NOT NULL,
  permiso_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rol_permisos_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, rol_id) REFERENCES roles(empresa_id, id),
  FOREIGN KEY (permiso_id) REFERENCES permisos(id),
  UNIQUE KEY uq_rol_permiso (empresa_id, rol_id, permiso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE accesos_aplicacion (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  miembro_id BIGINT UNSIGNED NOT NULL,
  plataforma_id BIGINT UNSIGNED NOT NULL,
  rol_id BIGINT UNSIGNED NOT NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_accesos_aplicacion_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, miembro_id) REFERENCES miembros_empresa(empresa_id, id),
  FOREIGN KEY (empresa_id, rol_id) REFERENCES roles(empresa_id, id),
  FOREIGN KEY (plataforma_id) REFERENCES plataformas(id),
  UNIQUE KEY uq_acceso (empresa_id, miembro_id, plataforma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sesiones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  acceso_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  dispositivo VARCHAR(200) NULL,
  ip VARCHAR(45) NULL,
  expira_at DATETIME NOT NULL,
  revocado_at DATETIME NULL,
  ultimo_uso_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sesiones_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, acceso_id) REFERENCES accesos_aplicacion(empresa_id, id),
  UNIQUE KEY uq_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sedes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  codigo VARCHAR(40) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  direccion VARCHAR(255) NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sedes_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  UNIQUE KEY uq_sede (empresa_id, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE areas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  sede_id BIGINT UNSIGNED NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_areas_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, sede_id) REFERENCES sedes(empresa_id, id),
  UNIQUE KEY uq_area (empresa_id, sede_id, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trabajadores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  area_id BIGINT UNSIGNED NULL,
  miembro_id BIGINT UNSIGNED NULL,
  tipo_documento VARCHAR(20) NOT NULL,
  numero_documento VARCHAR(30) NOT NULL,
  nombres VARCHAR(150) NOT NULL,
  apellidos VARCHAR(150) NOT NULL,
  puesto VARCHAR(150) NULL,
  fecha_ingreso DATE NULL,
  fecha_cese DATE NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_trabajadores_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, area_id) REFERENCES areas(empresa_id, id),
  FOREIGN KEY (empresa_id, miembro_id) REFERENCES miembros_empresa(empresa_id, id),
  UNIQUE KEY uq_trabajador_doc (empresa_id, tipo_documento, numero_documento),
  UNIQUE KEY uq_trabajador_miembro (empresa_id, miembro_id),
  CHECK (fecha_cese IS NULL OR fecha_ingreso IS NULL OR fecha_cese >= fecha_ingreso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE archivos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  subido_por BIGINT UNSIGNED NOT NULL,
  nombre_original VARCHAR(255) NOT NULL,
  disco VARCHAR(40) NOT NULL,
  ruta VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  bytes BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) CHARACTER SET ascii NULL,
  clasificacion ENUM('interno','confidencial','restringido') NOT NULL DEFAULT 'interno',
  eliminado_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_archivos_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, subido_por) REFERENCES miembros_empresa(empresa_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE temas_hse (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  modulo_id BIGINT UNSIGNED NOT NULL,
  codigo VARCHAR(50) NOT NULL,
  nombre VARCHAR(180) NOT NULL,
  descripcion TEXT NULL,
  responsable_id BIGINT UNSIGNED NULL,
  aplica BOOLEAN NOT NULL DEFAULT TRUE,
  motivo_no_aplica TEXT NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_temas_hse_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, responsable_id) REFERENCES miembros_empresa(empresa_id, id),
  FOREIGN KEY (modulo_id) REFERENCES modulos(id),
  UNIQUE KEY uq_tema (empresa_id, codigo),
  CHECK (aplica = TRUE OR motivo_no_aplica IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE requisitos_documentales (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  tema_id BIGINT UNSIGNED NOT NULL,
  nombre VARCHAR(180) NOT NULL,
  descripcion TEXT NULL,
  obligatorio BOOLEAN NOT NULL DEFAULT TRUE,
  requiere_vencimiento BOOLEAN NOT NULL DEFAULT FALSE,
  peso DECIMAL(10,2) NOT NULL DEFAULT 1,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_requisitos_documentales_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, tema_id) REFERENCES temas_hse(empresa_id, id),
  CHECK (peso > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documento_versiones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  requisito_id BIGINT UNSIGNED NOT NULL,
  archivo_id BIGINT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL,
  fecha_emision DATE NULL,
  fecha_vencimiento DATE NULL,
  estado ENUM('pendiente','aprobado','observado','rechazado') NOT NULL DEFAULT 'pendiente',
  revisado_por BIGINT UNSIGNED NULL,
  revisado_at DATETIME NULL,
  comentario_revision TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_documento_versiones_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, requisito_id) REFERENCES requisitos_documentales(empresa_id, id),
  FOREIGN KEY (empresa_id, archivo_id) REFERENCES archivos(empresa_id, id),
  FOREIGN KEY (empresa_id, revisado_por) REFERENCES miembros_empresa(empresa_id, id),
  UNIQUE KEY uq_doc_version (empresa_id, requisito_id, version),
  CHECK (version > 0),
  CHECK (fecha_vencimiento IS NULL OR fecha_emision IS NULL OR fecha_vencimiento >= fecha_emision),
  CHECK ((estado = 'pendiente' AND revisado_por IS NULL AND revisado_at IS NULL) OR (estado <> 'pendiente' AND revisado_por IS NOT NULL AND revisado_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documento_revisiones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  documento_version_id BIGINT UNSIGNED NOT NULL,
  revisor_id BIGINT UNSIGNED NOT NULL,
  resultado ENUM('aprobado','observado','rechazado') NOT NULL DEFAULT 'observado',
  comentario TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_documento_revisiones_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, documento_version_id) REFERENCES documento_versiones(empresa_id, id),
  FOREIGN KEY (empresa_id, revisor_id) REFERENCES miembros_empresa(empresa_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_plantillas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  codigo VARCHAR(50) NOT NULL,
  nombre VARCHAR(180) NOT NULL,
  descripcion TEXT NULL,
  instrucciones TEXT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  estado ENUM('borrador','publicado','retirado') NOT NULL DEFAULT 'borrador',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checklist_plantillas_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  UNIQUE KEY uq_plantilla (empresa_id, codigo, version),
  CHECK (version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_secciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  plantilla_id BIGINT UNSIGNED NOT NULL,
  titulo VARCHAR(180) NOT NULL,
  descripcion TEXT NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checklist_secciones_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, plantilla_id) REFERENCES checklist_plantillas(empresa_id, id),
  UNIQUE KEY uq_seccion_plantilla (empresa_id, plantilla_id, id),
  KEY ix_seccion_orden (empresa_id, plantilla_id, orden),
  CHECK (orden > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_preguntas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  plantilla_id BIGINT UNSIGNED NOT NULL,
  seccion_id BIGINT UNSIGNED NOT NULL,
  codigo VARCHAR(60) NOT NULL,
  ayuda TEXT NULL,
  placeholder VARCHAR(255) NULL,
  pregunta TEXT NOT NULL,
  tipo ENUM('cumplimiento','texto','texto_largo','numero','fecha','fecha_hora','selector','seleccion_multiple','si_no','fotografia','archivo','firma') NOT NULL DEFAULT 'cumplimiento',
  obligatoria BOOLEAN NOT NULL DEFAULT TRUE,
  orden INT UNSIGNED NOT NULL DEFAULT 1,
  longitud_min INT UNSIGNED NULL,
  longitud_max INT UNSIGNED NULL,
  numero_min DECIMAL(18,4) NULL,
  numero_max DECIMAL(18,4) NULL,
  fecha_min DATE NULL,
  fecha_max DATE NULL,
  seleccion_min INT UNSIGNED NULL,
  seleccion_max INT UNSIGNED NULL,
  archivos_min INT UNSIGNED NULL,
  archivos_max INT UNSIGNED NULL,
  archivo_max_bytes BIGINT UNSIGNED NULL,
  mime_permitidos JSON NULL,
  peso DECIMAL(10,2) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checklist_preguntas_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, plantilla_id) REFERENCES checklist_plantillas(empresa_id, id),
  UNIQUE KEY uq_pregunta_plantilla (empresa_id, plantilla_id, id),
  FOREIGN KEY (empresa_id, plantilla_id, seccion_id) REFERENCES checklist_secciones(empresa_id, plantilla_id, id),
  UNIQUE KEY uq_pregunta_codigo (empresa_id, plantilla_id, codigo),
  KEY ix_pregunta_orden (empresa_id, plantilla_id, seccion_id, orden),
  CHECK (orden > 0),
  CHECK (longitud_min IS NULL OR longitud_max IS NULL OR longitud_min <= longitud_max),
  CHECK (numero_min IS NULL OR numero_max IS NULL OR numero_min <= numero_max),
  CHECK (fecha_min IS NULL OR fecha_max IS NULL OR fecha_min <= fecha_max),
  CHECK (seleccion_min IS NULL OR seleccion_max IS NULL OR seleccion_min <= seleccion_max),
  CHECK (archivos_min IS NULL OR archivos_max IS NULL OR archivos_min <= archivos_max),
  CHECK (archivo_max_bytes IS NULL OR archivo_max_bytes > 0),
  CHECK (mime_permitidos IS NULL OR JSON_TYPE(mime_permitidos) = 'ARRAY'),
  CHECK (peso >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_pregunta_opciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  pregunta_id BIGINT UNSIGNED NOT NULL,
  valor VARCHAR(100) NOT NULL,
  etiqueta VARCHAR(255) NOT NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 1,
  resultado ENUM('informativo','cumple','no_cumple','no_aplica') NOT NULL DEFAULT 'informativo',
  exige_observacion BOOLEAN NOT NULL DEFAULT FALSE,
  exige_evidencia BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checklist_pregunta_opciones_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, pregunta_id) REFERENCES checklist_preguntas(empresa_id, id),
  UNIQUE KEY uq_opcion_pregunta (empresa_id, pregunta_id, id),
  UNIQUE KEY uq_opcion_valor (empresa_id, pregunta_id, valor),
  KEY ix_opcion_orden (empresa_id, pregunta_id, orden),
  CHECK (orden > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_registros (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  plantilla_id BIGINT UNSIGNED NOT NULL,
  area_id BIGINT UNSIGNED NULL,
  realizado_por BIGINT UNSIGNED NOT NULL,
  uuid_cliente CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  fecha DATETIME NOT NULL,
  estado ENUM('borrador','enviado','revisado','anulado') NOT NULL DEFAULT 'borrador',
  observaciones TEXT NULL,
  latitud DECIMAL(10,7) NULL,
  longitud DECIMAL(10,7) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checklist_registros_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, plantilla_id) REFERENCES checklist_plantillas(empresa_id, id),
  FOREIGN KEY (empresa_id, area_id) REFERENCES areas(empresa_id, id),
  FOREIGN KEY (empresa_id, realizado_por) REFERENCES miembros_empresa(empresa_id, id),
  UNIQUE KEY uq_check_uuid (empresa_id, uuid_cliente),
  UNIQUE KEY uq_check_plantilla (empresa_id, plantilla_id, id),
  KEY ix_check_fecha (empresa_id, fecha),
  CHECK (latitud BETWEEN -90 AND 90),
  CHECK (longitud BETWEEN -180 AND 180)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_respuestas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  registro_id BIGINT UNSIGNED NOT NULL,
  plantilla_id BIGINT UNSIGNED NOT NULL,
  pregunta_id BIGINT UNSIGNED NOT NULL,
  cumplimiento ENUM('cumple','no_cumple','no_aplica') NULL,
  valor_texto TEXT NULL,
  valor_numero DECIMAL(18,4) NULL,
  valor_fecha DATE NULL,
  valor_fecha_hora DATETIME NULL,
  valor_booleano BOOLEAN NULL,
  observacion TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checklist_respuestas_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, plantilla_id, registro_id) REFERENCES checklist_registros(empresa_id, plantilla_id, id),
  FOREIGN KEY (empresa_id, plantilla_id, pregunta_id) REFERENCES checklist_preguntas(empresa_id, plantilla_id, id),
  UNIQUE KEY uq_respuesta (empresa_id, registro_id, pregunta_id),
  UNIQUE KEY uq_respuesta_pregunta (empresa_id, pregunta_id, id),
  CHECK (valor_booleano IS NULL OR valor_booleano IN (0,1)),
  CHECK ((cumplimiento IS NOT NULL) + (valor_texto IS NOT NULL) + (valor_numero IS NOT NULL) + (valor_fecha IS NOT NULL) + (valor_fecha_hora IS NOT NULL) + (valor_booleano IS NOT NULL) <= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_respuesta_opciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  respuesta_id BIGINT UNSIGNED NOT NULL,
  pregunta_id BIGINT UNSIGNED NOT NULL,
  opcion_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checklist_respuesta_opciones_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, pregunta_id, respuesta_id) REFERENCES checklist_respuestas(empresa_id, pregunta_id, id),
  FOREIGN KEY (empresa_id, pregunta_id, opcion_id) REFERENCES checklist_pregunta_opciones(empresa_id, pregunta_id, id),
  UNIQUE KEY uq_respuesta_opcion (empresa_id, respuesta_id, opcion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_evidencias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  respuesta_id BIGINT UNSIGNED NOT NULL,
  archivo_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('fotografia','archivo','firma') NOT NULL DEFAULT 'fotografia',
  orden INT UNSIGNED NOT NULL DEFAULT 1,
  descripcion VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checklist_evidencias_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, respuesta_id) REFERENCES checklist_respuestas(empresa_id, id),
  FOREIGN KEY (empresa_id, archivo_id) REFERENCES archivos(empresa_id, id),
  CHECK (orden > 0),
  UNIQUE KEY uq_check_evidencia (empresa_id, respuesta_id, archivo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hallazgos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  uuid_cliente CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  area_id BIGINT UNSIGNED NULL,
  respuesta_id BIGINT UNSIGNED NULL,
  reportado_por BIGINT UNSIGNED NOT NULL,
  responsable_id BIGINT UNSIGNED NULL,
  titulo VARCHAR(200) NOT NULL,
  descripcion TEXT NOT NULL,
  fecha DATETIME NOT NULL,
  severidad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
  estado ENUM('abierto','en_proceso','por_validar','cerrado','anulado') NOT NULL DEFAULT 'abierto',
  fecha_limite DATE NULL,
  cerrado_at DATETIME NULL,
  cerrado_por BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hallazgos_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, area_id) REFERENCES areas(empresa_id, id),
  FOREIGN KEY (empresa_id, respuesta_id) REFERENCES checklist_respuestas(empresa_id, id),
  FOREIGN KEY (empresa_id, reportado_por) REFERENCES miembros_empresa(empresa_id, id),
  FOREIGN KEY (empresa_id, responsable_id) REFERENCES miembros_empresa(empresa_id, id),
  FOREIGN KEY (empresa_id, cerrado_por) REFERENCES miembros_empresa(empresa_id, id),
  UNIQUE KEY uq_hallazgo_uuid (empresa_id, uuid_cliente),
  KEY ix_hallazgo_estado (empresa_id, estado, fecha_limite),
  CHECK (estado <> 'cerrado' OR (cerrado_at IS NOT NULL AND cerrado_por IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hallazgo_seguimientos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  hallazgo_id BIGINT UNSIGNED NOT NULL,
  autor_id BIGINT UNSIGNED NOT NULL,
  comentario TEXT NOT NULL,
  estado_resultante ENUM('abierto','en_proceso','por_validar','cerrado','anulado') NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hallazgo_seguimientos_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, hallazgo_id) REFERENCES hallazgos(empresa_id, id),
  FOREIGN KEY (empresa_id, autor_id) REFERENCES miembros_empresa(empresa_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hallazgo_evidencias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  hallazgo_id BIGINT UNSIGNED NOT NULL,
  archivo_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('deteccion','levantamiento','cierre') NOT NULL DEFAULT 'deteccion',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hallazgo_evidencias_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, hallazgo_id) REFERENCES hallazgos(empresa_id, id),
  FOREIGN KEY (empresa_id, archivo_id) REFERENCES archivos(empresa_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE acciones_correctivas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  hallazgo_id BIGINT UNSIGNED NOT NULL,
  responsable_id BIGINT UNSIGNED NOT NULL,
  descripcion TEXT NOT NULL,
  fecha_limite DATE NOT NULL,
  estado ENUM('pendiente','en_proceso','por_validar','completada','anulada') NOT NULL DEFAULT 'pendiente',
  completada_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_acciones_correctivas_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, hallazgo_id) REFERENCES hallazgos(empresa_id, id),
  FOREIGN KEY (empresa_id, responsable_id) REFERENCES miembros_empresa(empresa_id, id),
  KEY ix_accion_plazo (empresa_id, estado, fecha_limite)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE epp_catalogo (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  codigo VARCHAR(50) NOT NULL,
  nombre VARCHAR(180) NOT NULL,
  talla VARCHAR(30) NOT NULL DEFAULT 'UNICA',
  unidad VARCHAR(30) NOT NULL DEFAULT 'unidad',
  vida_util_dias INT UNSIGNED NULL,
  ficha_archivo_id BIGINT UNSIGNED NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_epp_catalogo_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, ficha_archivo_id) REFERENCES archivos(empresa_id, id),
  UNIQUE KEY uq_epp (empresa_id, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE almacenes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  sede_id BIGINT UNSIGNED NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_almacenes_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, sede_id) REFERENCES sedes(empresa_id, id),
  UNIQUE KEY uq_almacen (empresa_id, sede_id, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE epp_entregas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  uuid_cliente CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  trabajador_id BIGINT UNSIGNED NOT NULL,
  entregado_por BIGINT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  estado ENUM('borrador','confirmada','anulada') NOT NULL DEFAULT 'borrador',
  firma_archivo_id BIGINT UNSIGNED NULL,
  recibido_at DATETIME NULL,
  observacion TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_epp_entregas_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, trabajador_id) REFERENCES trabajadores(empresa_id, id),
  FOREIGN KEY (empresa_id, entregado_por) REFERENCES miembros_empresa(empresa_id, id),
  FOREIGN KEY (empresa_id, firma_archivo_id) REFERENCES archivos(empresa_id, id),
  UNIQUE KEY uq_entrega_uuid (empresa_id, uuid_cliente),
  KEY ix_entrega_trabajador (empresa_id, trabajador_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE epp_entrega_detalles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  entrega_id BIGINT UNSIGNED NOT NULL,
  epp_id BIGINT UNSIGNED NOT NULL,
  almacen_id BIGINT UNSIGNED NOT NULL,
  cantidad DECIMAL(12,3) NOT NULL,
  fecha_reposicion DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_epp_entrega_detalles_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, entrega_id) REFERENCES epp_entregas(empresa_id, id),
  FOREIGN KEY (empresa_id, epp_id) REFERENCES epp_catalogo(empresa_id, id),
  FOREIGN KEY (empresa_id, almacen_id) REFERENCES almacenes(empresa_id, id),
  CHECK (cantidad > 0),
  UNIQUE KEY uq_detalle_stock (empresa_id, epp_id, almacen_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE epp_movimientos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  epp_id BIGINT UNSIGNED NOT NULL,
  almacen_id BIGINT UNSIGNED NOT NULL,
  entrega_detalle_id BIGINT UNSIGNED NULL,
  registrado_por BIGINT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  cantidad DECIMAL(12,3) NOT NULL,
  tipo ENUM('entrada','entrega','devolucion','ajuste','reverso') NOT NULL DEFAULT 'entrada',
  clave_operacion VARCHAR(100) NOT NULL,
  observacion TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_epp_movimientos_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, epp_id) REFERENCES epp_catalogo(empresa_id, id),
  FOREIGN KEY (empresa_id, almacen_id) REFERENCES almacenes(empresa_id, id),
  FOREIGN KEY (empresa_id, registrado_por) REFERENCES miembros_empresa(empresa_id, id),
  FOREIGN KEY (empresa_id, epp_id, almacen_id, entrega_detalle_id) REFERENCES epp_entrega_detalles(empresa_id, epp_id, almacen_id, id),
  UNIQUE KEY uq_mov_operacion (empresa_id, clave_operacion),
  KEY ix_stock (empresa_id, almacen_id, epp_id),
  CHECK (cantidad <> 0),
  CHECK ((tipo IN ('entrada','devolucion') AND cantidad > 0) OR (tipo = 'entrega' AND cantidad < 0 AND entrega_detalle_id IS NOT NULL) OR tipo IN ('ajuste','reverso'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE indicadores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  tema_id BIGINT UNSIGNED NULL,
  codigo VARCHAR(50) NOT NULL,
  nombre VARCHAR(180) NOT NULL,
  descripcion TEXT NULL,
  unidad VARCHAR(30) NOT NULL,
  tipo_calculo ENUM('manual','razon') NOT NULL DEFAULT 'manual',
  factor DECIMAL(16,4) NOT NULL DEFAULT 100,
  sentido ENUM('mayor_mejor','menor_mejor') NOT NULL DEFAULT 'mayor_mejor',
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_indicadores_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, tema_id) REFERENCES temas_hse(empresa_id, id),
  UNIQUE KEY uq_indicador (empresa_id, codigo),
  CHECK (factor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE indicador_mediciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  indicador_id BIGINT UNSIGNED NOT NULL,
  periodo DATE NOT NULL,
  numerador DECIMAL(18,4) NULL,
  denominador DECIMAL(18,4) NULL,
  valor_manual DECIMAL(18,4) NULL,
  meta DECIMAL(18,4) NOT NULL,
  registrado_por BIGINT UNSIGNED NOT NULL,
  evidencia_archivo_id BIGINT UNSIGNED NULL,
  comentario TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_indicador_mediciones_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, indicador_id) REFERENCES indicadores(empresa_id, id),
  FOREIGN KEY (empresa_id, registrado_por) REFERENCES miembros_empresa(empresa_id, id),
  FOREIGN KEY (empresa_id, evidencia_archivo_id) REFERENCES archivos(empresa_id, id),
  UNIQUE KEY uq_indicador_periodo (empresa_id, indicador_id, periodo),
  CHECK (DAYOFMONTH(periodo) = 1),
  CHECK (denominador IS NULL OR denominador >= 0),
  CHECK ((valor_manual IS NOT NULL AND numerador IS NULL AND denominador IS NULL) OR (valor_manual IS NULL AND numerador IS NOT NULL AND denominador IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auditoria_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  accion VARCHAR(100) NOT NULL,
  entidad VARCHAR(100) NOT NULL,
  registro_id BIGINT UNSIGNED NULL,
  datos_antes JSON NULL,
  datos_despues JSON NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_auditoria_eventos_empresa (empresa_id, id),
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (empresa_id, actor_id) REFERENCES miembros_empresa(empresa_id, id),
  KEY ix_auditoria_registro (empresa_id, entidad, registro_id),
  KEY ix_auditoria_fecha (empresa_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- VISTAS: SQL SECURITY INVOKER; filtrar siempre por empresa_id desde API.
-- Politica documental: la ultima version subida reemplaza a la anterior incluso
-- si aun esta pendiente/observada. Vigencia inclusiva hasta fecha_vencimiento.
-- CURRENT_DATE usa la zona de la sesion: dashboard debe establecer fecha local
-- de la empresa o reproducir esta consulta con una fecha de corte parametrizada.
CREATE SQL SECURITY INVOKER VIEW v_estado_requisitos AS
SELECT r.empresa_id, r.id requisito_id, r.tema_id, r.nombre,
       r.obligatorio, r.peso, d.id documento_version_id, d.fecha_vencimiento,
       CASE WHEN d.id IS NULL THEN 'sin_documento'
            WHEN d.estado <> 'aprobado' THEN d.estado
            WHEN r.requiere_vencimiento = 1 AND d.fecha_vencimiento IS NULL THEN 'incompleto'
            WHEN d.fecha_vencimiento < CURRENT_DATE THEN 'vencido'
            WHEN a.eliminado_at IS NOT NULL THEN 'sin_documento'
            ELSE 'vigente' END estado
FROM requisitos_documentales r
LEFT JOIN documento_versiones d ON d.empresa_id = r.empresa_id AND d.requisito_id = r.id
 AND NOT EXISTS (SELECT 1 FROM documento_versiones n
                 WHERE n.empresa_id = d.empresa_id AND n.requisito_id = d.requisito_id
                   AND n.version > d.version)
LEFT JOIN archivos a ON a.empresa_id = d.empresa_id AND a.id = d.archivo_id
WHERE r.activo = 1;

-- Solo requisitos obligatorios activos cuentan para cumplimiento.
-- Sin requisitos obligatorios => sin_configurar y porcentaje NULL.
CREATE SQL SECURITY INVOKER VIEW v_estado_temas AS
SELECT t.empresa_id, t.id tema_id, t.nombre,
 COUNT(r.requisito_id) requisitos_obligatorios,
 SUM(CASE WHEN r.estado = 'vigente' THEN 1 ELSE 0 END) requisitos_vigentes,
 CASE WHEN t.aplica = 0 THEN NULL ELSE
 ROUND(100 * SUM(CASE WHEN r.estado = 'vigente' THEN r.peso ELSE 0 END)
       / NULLIF(SUM(r.peso),0),2) END porcentaje_cumplimiento,
 CASE WHEN t.aplica = 0 THEN 'no_aplica'
      WHEN COUNT(r.requisito_id) = 0 THEN 'sin_configurar'
      WHEN SUM(r.estado = 'vigente') = COUNT(r.requisito_id) THEN 'cumplido'
      WHEN SUM(r.estado = 'vencido') > 0 THEN 'vencido'
      WHEN SUM(r.estado IN ('observado','rechazado','incompleto')) > 0 THEN 'observado'
      ELSE 'pendiente' END estado
FROM temas_hse t
LEFT JOIN v_estado_requisitos r ON r.empresa_id = t.empresa_id AND r.tema_id = t.id AND r.obligatorio = 1
WHERE t.activo = 1
GROUP BY t.empresa_id,t.id,t.nombre,t.aplica;

CREATE SQL SECURITY INVOKER VIEW v_epp_stock AS
SELECT empresa_id, almacen_id, epp_id, SUM(cantidad) stock
FROM epp_movimientos GROUP BY empresa_id, almacen_id, epp_id;

CREATE SQL SECURITY INVOKER VIEW v_indicador_resultados AS
SELECT m.*, i.codigo, i.nombre, i.unidad, i.sentido,
 CASE WHEN i.tipo_calculo = 'manual' THEN m.valor_manual
      ELSE m.numerador / NULLIF(m.denominador,0) * i.factor END valor,
 CASE WHEN i.tipo_calculo = 'manual' AND m.valor_manual IS NULL THEN 'sin_datos'
      WHEN i.tipo_calculo = 'razon' AND (m.numerador IS NULL OR m.denominador IS NULL OR m.denominador = 0) THEN 'sin_datos'
      WHEN (i.sentido = 'mayor_mejor' AND
            COALESCE(m.valor_manual,m.numerador / NULLIF(m.denominador,0) * i.factor) >= m.meta)
        OR (i.sentido = 'menor_mejor' AND
            COALESCE(m.valor_manual,m.numerador / NULLIF(m.denominador,0) * i.factor) <= m.meta)
      THEN 'cumple' ELSE 'no_cumple' END estado
FROM indicador_mediciones m
JOIN indicadores i ON i.empresa_id = m.empresa_id AND i.id = m.indicador_id;

INSERT INTO plataformas(codigo,nombre) VALUES ('web','Aplicacion web'),('movil','Aplicacion movil');

INSERT INTO modulos(codigo,nombre) VALUES
('administracion','Administración'),
('documentos','Documentos'),
('indicadores','Indicadores'),
('epp','EPP'),
('almacenes','Almacenes'),
('hallazgos','Hallazgos'),
('gestion_sst','Gestión SST'),
('organizacion','Organización'),
('inspecciones','Inspecciones'),
('capacitaciones','Capacitaciones');

-- Permisos iniciales; cada rol recibe explicitamente los necesarios.
INSERT INTO permisos(modulo_id,codigo,descripcion)
SELECT m.id, CONCAT(m.codigo,'.',a.accion), CONCAT(a.etiqueta,' · ',m.nombre)
FROM modulos m CROSS JOIN (
 SELECT 'ver' accion, 'Consultar' etiqueta UNION ALL SELECT 'crear', 'Crear' UNION ALL SELECT 'editar', 'Editar'
 UNION ALL SELECT 'eliminar', 'Eliminar' UNION ALL SELECT 'descargar', 'Descargar informes'
) a;
INSERT INTO permisos(modulo_id,codigo,descripcion)
SELECT id, 'documentos.confidenciales', 'Acceder a documentos confidenciales · Documentos' FROM modulos WHERE codigo = 'documentos';

-- GUIA DE ARRANQUE
-- 1. Crear empresa y contrato; habilitar empresa_modulos.
-- 2. Crear usuario con hash generado por backend; crear miembros_empresa.
-- 3. Crear roles y rol_permisos de esa empresa.
-- 4. Crear accesos_aplicacion separados para web y movil (rol puede ser distinto).
-- 5. Crear sedes, areas y trabajadores. Un trabajador no necesita usuario.
-- 6. Crear temas_hse y requisitos_documentales por empresa.
--    Ejemplo: tema Politica SST -> requisito Politica firmada (obligatorio).
--    Subir archivo -> documento_versiones pendiente -> revisor aprueba/rechaza.
--    En una transaccion actualizar documento_versiones y agregar documento_revisiones.
--    Asignar version bajo bloqueo del requisito; UNIQUE evita duplicados concurrentes.
-- 7. SELECT * FROM v_estado_temas WHERE empresa_id = ?;
-- 8. SELECT * FROM v_indicador_resultados WHERE empresa_id = ? AND periodo = ?;
-- 9. SELECT * FROM v_epp_stock WHERE empresa_id = ?;
-- Los ? son parametros de consultas preparadas, no ejecutar estos comentarios.
--
-- REGLAS DE LA API (no todas son expresables mediante FK/CHECK)
-- * Inmutabilidad: plantillas publicadas/preguntas usadas, versiones de archivos,
--   registros enviados, revisiones, auditoria y movimientos de stock.
--   Para cambiar una plantilla crear otra version y copiar sus preguntas.
-- * Al enviar checklist: validar obligatorias y que valor corresponda al tipo.
-- * Al evaluar documentos: validar vencimiento requerido y archivo disponible.
-- * Mantener contratos no solapados si se exige uno vigente por empresa.
-- * EPP: confirmar entrega y registrar salidas negativas en UNA transaccion.
--   Bloquear fila del almacen con SELECT ... FOR UPDATE antes de leer stock;
--   todas las entradas/salidas del almacen deben respetar ese mismo bloqueo.
--   Comprobar saldo suficiente, cantidad de salida = cantidad entregada,
--   una sola salida por detalle (clave_operacion determinista entrega:ID).
--   Anular mediante movimientos inversos, nunca borrando movimientos originales.
--   Esta estrategia serializa el almacen completo; se puede optimizar posteriormente.
-- * Indicadores: validar manual/razon contra tipo_calculo de su definicion.
--   No cambiar definiciones con mediciones; crear otro indicador si cambia formula.
--   Periodo = primer dia del mes. Denominador cero devuelve sin_datos, nunca 0%.
-- * Auditoria: registro_id es referencia informativa polimorfica, sin FK.
--   Escribir con usuario de BD controlado; no guardar passwords/tokens/datos medicos.
-- * Caducidad de documentos es calculada; alertas necesitan tarea programada/API.
-- * Reintentos offline: misma uuid_cliente devuelve registro ya creado, no duplicar.
-- * Firmas son evidencia de recepcion; no se implementa firma digital certificada.
--
-- ALCANCE
-- Operativos: identidad multiempresa, accesos, documentos/temas, checklists,
-- hallazgos/acciones, EPP/stock, indicadores mensuales y auditoria.
-- El resto del Word se registra en modulos para futura ampliacion; no se incluyen
-- todavia tablas especificas de IPERC, capacitaciones/examenes, EMO, incidentes,
-- comites, brigadas, permisos de trabajo, vehiculos, quimicos o garita.
-- Sus documentos iniciales pueden organizarse con temas y requisitos.
-- Esto no constituye una certificacion de cumplimiento legal SGSST.


-- CONSTRUCTOR DE CHECKLISTS: CONTRATO PARA ANGULAR / FLUTTER / LARAVEL
-- La configuracion esta en filas, no se crean columnas MySQL por cada campo.
-- Publicar: plantilla -> secciones ordenadas -> preguntas ordenadas -> opciones.
-- Crear una seccion General si el formulario no necesita apartados visibles.
-- GET de plantilla devuelve secciones, preguntas, validaciones y opciones anidadas.
-- Texto/codigo: tipo texto; comentario: texto_largo; Estado Bueno/Malo: selector.
-- Estado: opciones valor bueno/etiqueta Bueno/resultado cumple;
--                  valor malo/etiqueta Malo/resultado no_cumple.
-- Marcar exige_observacion/exige_evidencia en Malo si corresponde.
-- Una seleccion informativa (p.ej. tipo de maquinaria) no evalua cumplimiento.
--
-- DONDE SE GUARDAN LAS RESPUESTAS
-- texto, texto_largo -> checklist_respuestas.valor_texto
-- numero -> valor_numero; fecha -> valor_fecha; fecha_hora -> valor_fecha_hora (UTC)
-- si_no -> valor_booleano (0 = No es una respuesta valida; NULL = sin responder)
-- cumplimiento -> cumplimiento: cumple/no_cumple/no_aplica (tipo basico conservado)
-- selector -> UNA fila en checklist_respuesta_opciones
-- seleccion_multiple -> VARIAS filas en checklist_respuesta_opciones
-- fotografia/archivo/firma -> checklist_evidencias + archivos privados
-- En opciones/archivos, las columnas de valor de checklist_respuestas quedan NULL.
-- Crear siempre la fila checklist_respuestas, incluso cuando solo contenga adjuntos.
-- FK de respuesta_opciones evita elegir una opcion perteneciente a otra pregunta.
-- FK de seccion evita asociar la pregunta con una seccion de otra plantilla.
--
-- VALIDACIONES OBLIGATORIAS EN API (ademas de CHECK/FK)
-- 1. Solo editar plantillas/secciones/preguntas/opciones en borrador. Para cambiar una
--    publicada, copiar todo a otra version del mismo codigo, con IDs nuevos.
--    Impedir edicion/borrado de versiones publicadas aunque aun no tengan respuestas.
--    Publicar/crear version bajo bloqueo de la empresa o plantilla en transaccion.
--    No mezclar preguntas de una version con registros de otra.
-- 2. Al publicar: exigir nombre, secciones y preguntas; validar tipos y rangos;
--    exigir opciones solo para selector/seleccion_multiple (al menos dos opciones).
--    Ordenar por orden,id para resolver empates. Validar que seleccion_min/max no
--    supere opciones disponibles; selector admite como maximo una opcion.
--    mime_permitidos es un array de strings MIME, p.ej. ["image/jpeg","image/png"].
--    Validar que reglas correspondan al tipo (numero_min solo en numero, etc.).
-- 3. En borrador permitir respuestas incompletas; al enviar comprobar obligatorias,
--    contenido no vacio, tipo exacto, rangos y longitudes en caracteres.
--    Por implementacion actual valor_texto usa TEXT: comprobar tambien limite de
--    65535 bytes UTF-8 aunque longitud_max permita mas caracteres.
--    No aceptar valores escalares junto con selecciones ni tipos de valor incorrectos.
--    Preguntas opcionales vacias no fallan sus minimos; si estan respondidas, si.
--    fecha_min/max para fecha_hora se comparan en zona local de la empresa.
-- 4. Obligatoria de selector: exactamente una opcion; multiple: al menos una y
--    seleccion_min/max si se configuraron. Reemplazar selecciones atomicamente.
--    Adjuntos obligatorios: al menos uno; firma: exactamente un archivo de firma.
--    Validar cantidad, MIME real, bytes, propiedad de empresa y disponibilidad.
--    Evidencias adicionales pueden acompanar respuestas escalares o selecciones.
--    Si cualquier opcion elegida exige observacion/evidencia, comprobarlas al enviar.
-- 5. Bloquear checklist_registros durante modificaciones de respuestas/opciones/
--    evidencias y envio; todas las escrituras respetan el bloqueo y estado borrador.
--    Retirado: no iniciar registros nuevos; rechazar envio hasta decision explicita
--    de negocio para los borradores existentes. Enviado/revisado: solo lectura.
-- 6. Resultado de pregunta evaluable: no_cumple si alguna opcion evaluable falla;
--    cumple si hay alguna cumple y ninguna falla; no_aplica si todas las opciones
--    evaluables son no_aplica; solo informativas => no puntua. Mezclar no_aplica
--    con cumple/no_cumple en seleccion multiple debe rechazarse al enviar.
--    Porcentaje = 100 * suma pesos de preguntas cumple / suma pesos de preguntas
--    cumple o no_cumple. Sin preguntas evaluables/peso total 0 => NULL, no 100%.
--    Los resultados y validaciones dinamicas se calculan en la API; no hay triggers.
-- 7. La interfaz de armado y el renderizador movil/web deben implementarse en sus
--    aplicaciones. El SQL define soporte de datos, no una interfaz Google Forms.
--    No incluye saltos condicionales ni grupos repetibles en esta revision.
