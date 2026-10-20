<?php
/** Íconos disponibles para los retos (trazos SVG en viewBox 24×24). */
const RETO_ICONOS = [
    'qr'      => ['Código QR / tecnología', '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/>'],
    'gota'    => ['Agua', '<path d="M12 2.5s6.5 7 6.5 11.5a6.5 6.5 0 0 1-13 0C5.5 9.5 12 2.5 12 2.5Z"/><path d="M9 14.5a3 3 0 0 0 3 3"/>'],
    'ciclo'   => ['Economía circular', '<path d="M21 12a9 9 0 0 1-15.5 6.2M3 12a9 9 0 0 1 15.5-6.2"/><path d="M18.5 2v3.8h-3.8M5.5 22v-3.8h3.8"/>'],
    'movil'   => ['Digital / móvil', '<rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M10 18h4M9.5 9.5l2 2 3.5-3.5"/>'],
    'libro'   => ['Cultura / saberes', '<path d="M2 5.5C4.5 4 8 4 12 6c4-2 7.5-2 10-.5V19c-2.5-1.5-6-1.5-10 .5-4-2-7.5-2-10-.5Z"/><path d="M12 6v13.5"/>'],
    'hoja'    => ['Producción sostenible', '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 1 9.5a7 7 0 0 1-9 8.5Z"/><path d="M2 21c0-3 1.9-5.4 5.8-6.4"/>'],
    'pez'     => ['Pesca', '<path d="M3 12c3-4.5 7-6 11-6 3 0 5.5 2 7 6-1.5 4-4 6-7 6-4 0-8-1.5-11-6Z"/><path d="M3 12 1 8m2 4-2 4"/><circle cx="16.5" cy="10.5" r=".9" fill="currentColor"/>'],
    'ola'     => ['Río', '<path d="M2 6c2.5-2 5-2 7.5 0S14.5 8 17 6s5-2 5 0"/><path d="M2 12c2.5-2 5-2 7.5 0s5 2 7.5 0 5-2 5 0"/><path d="M2 18c2.5-2 5-2 7.5 0s5 2 7.5 0 5-2 5 0"/>'],
    'grafica' => ['Datos / análisis', '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 6-6"/>'],
    'bombillo'=> ['Innovación', '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.4 1 1.1 1 1.8V16h5v-.3c0-.7.4-1.4 1-1.8A6 6 0 0 0 12 3Z"/>'],
    'personas'=> ['Comunidad', '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>'],
    'tienda'  => ['Comercio', '<path d="M3 9l1.5-5h15L21 9"/><path d="M3 9h18v2a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0Z"/><path d="M5 13v8h14v-8"/>'],
];

/** Objetivos de Desarrollo Sostenible (número => [nombre, color oficial]) */
const ODS = [
    1 => ['Fin de la pobreza', '#e5243b'], 2 => ['Hambre cero', '#dda63a'], 3 => ['Salud y bienestar', '#4c9f38'],
    4 => ['Educación de calidad', '#c5192d'], 5 => ['Igualdad de género', '#ff3a21'], 6 => ['Agua limpia y saneamiento', '#26bde2'],
    7 => ['Energía asequible y no contaminante', '#fcc30b'], 8 => ['Trabajo decente y crecimiento económico', '#a21942'],
    9 => ['Industria, innovación e infraestructura', '#fd6925'], 10 => ['Reducción de las desigualdades', '#dd1367'],
    11 => ['Ciudades y comunidades sostenibles', '#fd9d24'], 12 => ['Producción y consumo responsables', '#bf8b2e'],
    13 => ['Acción por el clima', '#3f7e44'], 14 => ['Vida submarina', '#0a97d9'], 15 => ['Vida de ecosistemas terrestres', '#56c02b'],
    16 => ['Paz, justicia e instituciones sólidas', '#00689d'], 17 => ['Alianzas para lograr los objetivos', '#19486a'],
];

const PERFILES = ['Estudiante', 'Docente / Investigador', 'Pescador / Comunidad ribereña', 'Actor productivo / Empresa', 'Entidad pública u ONG', 'Otro'];
const MODALIDADES = ['Presencial', 'Híbrida', 'Virtual', 'Presencial en territorio'];
const MUNICIPIOS = ['Girardot', 'Flandes', 'Ricaurte', 'Nariño', 'Guataquí', 'Tocaima', 'Agua de Dios', 'Jerusalén', 'Coello'];

function icon_svg(string $key, string $class = ''): string
{
    $path = RETO_ICONOS[$key][1] ?? RETO_ICONOS['ola'][1];
    return '<svg viewBox="0 0 24 24"' . ($class ? ' class="' . $class . '"' : '') . ' aria-hidden="true">' . $path . '</svg>';
}
