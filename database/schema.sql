-- ==========================================================
-- RioLab 4.0 — Esquema de base de datos (MySQL 8+ / MariaDB 10.5+)
-- ==========================================================
SET NAMES utf8mb4;

-- Metodologías de aprendizaje (ABR, ABP, IAP…)
CREATE TABLE IF NOT EXISTS metodologias (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave         VARCHAR(20)  NOT NULL UNIQUE,
  nombre        VARCHAR(120) NOT NULL,
  sigla         VARCHAR(120) NOT NULL DEFAULT '',
  descripcion   TEXT         NOT NULL,
  fases         JSON         NOT NULL,           -- [{nombre, desc}]
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Retos
CREATE TABLE IF NOT EXISTS retos (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug               VARCHAR(80)  NOT NULL UNIQUE,
  codigo             VARCHAR(20)  NOT NULL,
  titulo             VARCHAR(160) NOT NULL,
  tag                VARCHAR(60)  NOT NULL,
  icono              VARCHAR(30)  NOT NULL DEFAULT 'ola',
  resumen            VARCHAR(400) NOT NULL,
  contexto           TEXT         NOT NULL,       -- párrafos separados por línea en blanco
  pregunta           VARCHAR(500) NOT NULL,
  metodologia_id     INT UNSIGNED NOT NULL,
  duracion           VARCHAR(60)  NOT NULL,
  dedicacion         VARCHAR(60)  NOT NULL,
  modalidad          VARCHAR(60)  NOT NULL,
  modalidad_detalle  VARCHAR(400) NOT NULL DEFAULT '',
  cupos              SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  equipos            VARCHAR(120) NOT NULL DEFAULT '',
  nivel              VARCHAR(60)  NOT NULL DEFAULT '',
  fecha_inicio       DATE         NULL,
  fecha_cierre       DATE         NULL,           -- cierre de inscripciones
  ods                JSON         NOT NULL,       -- [9, 12, 14]
  dirigido           JSON         NOT NULL,       -- ["…"]
  requisitos         JSON         NOT NULL,
  resultados         JSON         NOT NULL,
  competencias       JSON         NOT NULL,
  aliados            JSON         NOT NULL,
  cronograma         JSON         NOT NULL,       -- [{semanas, titulo, desc}]
  evaluacion         JSON         NOT NULL,       -- [{criterio, peso}]
  -- Información adicional que solo aparece en el PDF descargable
  recursos           JSON         NOT NULL,
  compromisos        JSON         NOT NULL,
  lugar              VARCHAR(255) NOT NULL DEFAULT '',
  contacto_nombre    VARCHAR(120) NOT NULL DEFAULT '',
  contacto_correo    VARCHAR(160) NOT NULL DEFAULT '',
  info_adicional     TEXT         NULL,
  estado             ENUM('borrador','publicado','cerrado') NOT NULL DEFAULT 'borrador',
  orden              SMALLINT     NOT NULL DEFAULT 0,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_retos_met FOREIGN KEY (metodologia_id) REFERENCES metodologias(id),
  INDEX idx_retos_estado (estado, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Entregables de cada reto (tabla propia para que las entregas los referencien)
CREATE TABLE IF NOT EXISTS reto_entregables (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reto_id      INT UNSIGNED NOT NULL,
  orden        SMALLINT     NOT NULL DEFAULT 0,
  titulo       VARCHAR(160) NOT NULL,
  descripcion  VARCHAR(500) NOT NULL DEFAULT '',
  semana       VARCHAR(40)  NOT NULL DEFAULT '',
  CONSTRAINT fk_ent_reto FOREIGN KEY (reto_id) REFERENCES retos(id) ON DELETE CASCADE,
  INDEX idx_ent_reto (reto_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personas inscritas (visitantes del sitio)
CREATE TABLE IF NOT EXISTS participantes (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre           VARCHAR(160) NOT NULL,
  correo           VARCHAR(160) NOT NULL UNIQUE,
  telefono         VARCHAR(40)  NOT NULL,
  municipio        VARCHAR(80)  NOT NULL,
  perfil           VARCHAR(60)  NOT NULL,
  codigo_hash      VARCHAR(255) NOT NULL,         -- código de acceso (solo se guarda el hash)
  acepto_datos_at  DATETIME     NOT NULL,
  ultimo_acceso    DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_part_perfil (perfil),
  INDEX idx_part_municipio (municipio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inscripciones a retos (reto_id NULL = interés general, "aún no lo sé")
CREATE TABLE IF NOT EXISTS inscripciones (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participante_id  INT UNSIGNED NOT NULL,
  reto_id          INT UNSIGNED NULL,
  mensaje          VARCHAR(500) NOT NULL DEFAULT '',
  origen           VARCHAR(20)  NOT NULL DEFAULT 'inicio',   -- inicio | reto
  estado           ENUM('pendiente','aceptado','en_curso','finalizado','rechazado') NOT NULL DEFAULT 'pendiente',
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ins_part FOREIGN KEY (participante_id) REFERENCES participantes(id) ON DELETE CASCADE,
  CONSTRAINT fk_ins_reto FOREIGN KEY (reto_id) REFERENCES retos(id) ON DELETE SET NULL,
  UNIQUE KEY uq_ins (participante_id, reto_id),
  INDEX idx_ins_reto (reto_id, estado),
  INDEX idx_ins_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios del panel administrativo
CREATE TABLE IF NOT EXISTS usuarios (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(120) NOT NULL,
  correo         VARCHAR(160) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  rol            ENUM('admin','mentor') NOT NULL DEFAULT 'mentor',
  activo         TINYINT(1)   NOT NULL DEFAULT 1,
  ultimo_acceso  DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Retos asignados a cada mentor
CREATE TABLE IF NOT EXISTS mentor_retos (
  usuario_id  INT UNSIGNED NOT NULL,
  reto_id     INT UNSIGNED NOT NULL,
  PRIMARY KEY (usuario_id, reto_id),
  CONSTRAINT fk_mr_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_mr_reto FOREIGN KEY (reto_id) REFERENCES retos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documentos cargados por los participantes
CREATE TABLE IF NOT EXISTS entregas (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inscripcion_id    INT UNSIGNED NOT NULL,
  entregable_id     INT UNSIGNED NULL,            -- NULL = "otro documento"
  nombre_original   VARCHAR(255) NOT NULL,
  archivo           VARCHAR(80)  NOT NULL,         -- nombre aleatorio en storage/uploads
  mime              VARCHAR(120) NOT NULL,
  tamano            INT UNSIGNED NOT NULL,
  comentario        VARCHAR(500) NOT NULL DEFAULT '',
  estado            ENUM('recibido','en_revision','aprobado','requiere_ajustes') NOT NULL DEFAULT 'recibido',
  retroalimentacion TEXT         NULL,
  revisado_por      INT UNSIGNED NULL,
  revisado_at       DATETIME     NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_entr_ins FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id) ON DELETE CASCADE,
  CONSTRAINT fk_entr_ent FOREIGN KEY (entregable_id) REFERENCES reto_entregables(id) ON DELETE SET NULL,
  CONSTRAINT fk_entr_rev FOREIGN KEY (revisado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_entr_estado (estado, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eventos para las estadísticas (visitas, descargas de PDF…)
CREATE TABLE IF NOT EXISTS eventos (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo           VARCHAR(20)  NOT NULL,             -- visita | pdf
  pagina         VARCHAR(40)  NOT NULL,             -- inicio | reto | portal
  reto_id        INT UNSIGNED NULL,
  visitante      CHAR(16)     NOT NULL,             -- hash diario anónimo (no guarda IP)
  dispositivo    VARCHAR(10)  NOT NULL DEFAULT 'desktop',
  referer_host   VARCHAR(120) NOT NULL DEFAULT '',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ev_reto FOREIGN KEY (reto_id) REFERENCES retos(id) ON DELETE SET NULL,
  INDEX idx_ev_tipo_fecha (tipo, created_at),
  INDEX idx_ev_reto (reto_id, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Control de intentos de inicio de sesión (fuerza bruta)
CREATE TABLE IF NOT EXISTS login_intentos (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ambito      VARCHAR(12) NOT NULL,                -- admin | portal
  clave       CHAR(64)    NOT NULL,                -- hash de IP + correo
  created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_li (ambito, clave, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
