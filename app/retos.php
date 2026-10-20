<?php
/** RioLab 4.0 — Acceso a datos de retos y metodologías. */

const RETO_JSON = ['ods', 'dirigido', 'requisitos', 'resultados', 'competencias', 'aliados', 'cronograma', 'evaluacion', 'recursos', 'compromisos'];

const FAQ = [
    ['¿Tiene algún costo participar?', 'No. La participación en los retos RioLab 4.0 es gratuita para estudiantes, docentes, comunidad y actores productivos.'],
    ['¿Puedo participar si no soy estudiante de UNIMINUTO?', 'Sí. Los equipos son mixtos: integran estudiantes, docentes, pescadores, comunidad y actores productivos. Solo debes cumplir los requisitos del reto.'],
    ['¿Necesito tener un equipo conformado?', 'No es necesario. Puedes inscribirte de forma individual y en la sesión de lanzamiento se conforman los equipos de manera interdisciplinaria.'],
    ['¿El reto da créditos académicos?', 'Para estudiantes de UNIMINUTO, el reto puede homologarse como opción de práctica, proyección social u opción de grado, según la aprobación de cada programa académico.'],
    ['¿Cómo entrego los documentos del reto?', 'Al inscribirte recibes un código de acceso. Con tu correo y ese código ingresas a "Mis entregas" y cargas los documentos de cada entregable.'],
    ['¿Qué pasa con las soluciones que desarrollemos?', 'Las soluciones se construyen con y para el territorio. Los equipos conservan el reconocimiento de autoría y se comprometen a compartir los resultados con las comunidades participantes.'],
];

/** Convierte una fila de `retos` en un arreglo listo para usar en las vistas. */
function reto_hidratar(array $r): array
{
    foreach (RETO_JSON as $f) $r[$f] = jdecode($r[$f] ?? '[]');
    $r['contexto_parrafos'] = array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/', $r['contexto'] ?? ''))));
    $r['entregables'] = q('SELECT * FROM reto_entregables WHERE reto_id = ? ORDER BY orden, id', [$r['id']])->fetchAll();
    $r['metodologia'] = metodologia($r['metodologia_id']);
    $r['ocupados'] = (int) q("SELECT COUNT(*) FROM inscripciones WHERE reto_id = ? AND estado <> 'rechazado'", [$r['id']])->fetchColumn();
    $r['disponibles'] = max(0, (int) $r['cupos'] - $r['ocupados']);
    $r['cierre_pasado'] = $r['fecha_cierre'] && $r['fecha_cierre'] < date('Y-m-d');
    $r['abierto'] = $r['estado'] === 'publicado' && !$r['cierre_pasado'] && $r['disponibles'] > 0;
    $r['motivo_cerrado'] = $r['abierto'] ? '' : match (true) {
        $r['estado'] === 'borrador' => 'Este reto aún no está publicado.',
        $r['disponibles'] <= 0 => 'Los cupos de este reto ya están completos.',
        default => 'Las inscripciones para este reto están cerradas.',
    };
    return $r;
}

function metodologia(int $id): array
{
    static $cache = [];
    if (!isset($cache[$id])) {
        $m = q('SELECT * FROM metodologias WHERE id = ?', [$id])->fetch() ?: ['nombre' => '', 'sigla' => '', 'descripcion' => '', 'fases' => '[]', 'clave' => ''];
        $m['fases'] = jdecode($m['fases']);
        $cache[$id] = $m;
    }
    return $cache[$id];
}

function metodologias_todas(): array
{
    return q('SELECT * FROM metodologias ORDER BY nombre')->fetchAll();
}

/** Retos visibles en el sitio (publicados y cerrados), con conteo de cupos. */
function retos_publicos(): array
{
    $rows = q("SELECT r.*, m.nombre AS metodologia_nombre,
                 (SELECT COUNT(*) FROM inscripciones i WHERE i.reto_id = r.id AND i.estado <> 'rechazado') AS ocupados
               FROM retos r JOIN metodologias m ON m.id = r.metodologia_id
               WHERE r.estado IN ('publicado','cerrado') ORDER BY r.orden, r.id")->fetchAll();
    foreach ($rows as &$r) {
        $r['disponibles'] = max(0, (int) $r['cupos'] - (int) $r['ocupados']);
        $r['abierto'] = $r['estado'] === 'publicado' && $r['disponibles'] > 0 && !($r['fecha_cierre'] && $r['fecha_cierre'] < date('Y-m-d'));
    }
    return $rows;
}

function reto_por_slug(string $slug, bool $soloVisibles = true): ?array
{
    $sql = 'SELECT * FROM retos WHERE slug = ?' . ($soloVisibles ? " AND estado IN ('publicado','cerrado')" : '');
    $r = q($sql, [$slug])->fetch();
    return $r ? reto_hidratar($r) : null;
}

function reto_por_id(int $id): ?array
{
    $r = q('SELECT * FROM retos WHERE id = ?', [$id])->fetch();
    return $r ? reto_hidratar($r) : null;
}
