<?php
/**
 * Ficha completa del reto en PDF (FPDF).
 * Incluye información adicional que no aparece en la página: recursos, compromisos,
 * lugar, contacto, proceso de inscripción y entregas, preguntas frecuentes y datos personales.
 */
require __DIR__ . '/app/bootstrap.php';
require ROOT . '/lib/fpdf/fpdf.php';

$reto = reto_por_slug((string) ($_GET['r'] ?? ''));
if (!$reto) not_found('El reto solicitado no existe.');
track('pdf', 'reto', (int) $reto['id']);

/** Las fuentes estándar de FPDF usan Windows-1252: convertimos desde UTF-8. */
function t(?string $s): string
{
    $s = strtr((string) $s, ['→' => '-', '“' => '"', '”' => '"', '‘' => "'", '’' => "'", '…' => '...']);
    return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
}

class RetoPDF extends FPDF
{
    public array $reto;
    const RIVER = [4, 22, 31];
    const TEAL = [15, 118, 110];
    const AQUA = [45, 212, 191];
    const SUN = [255, 199, 44];
    const INK = [11, 31, 38];
    const MUTED = [61, 85, 96];
    const MIST = [244, 248, 247];
    const LINE = [211, 224, 221];

    function Header(): void
    {
        if ($this->PageNo() === 1) return;
        $this->SetFillColor(...self::RIVER);
        $this->Rect(0, 0, 210, 14, 'F');
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(15, 4.5);
        $this->Cell(90, 5, t('RioLab 4.0 · ' . $this->reto['codigo']));
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(...self::AQUA);
        $this->Cell(90, 5, t($this->reto['titulo']), 0, 0, 'R');
        $this->SetY(22);
    }

    function Footer(): void
    {
        $this->SetY(-14);
        $this->SetDrawColor(...self::LINE);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...self::MUTED);
        $this->Cell(130, 8, t('Centro Universitario Girardot de UNIMINUTO · RioLab 4.0 · Generado el ' . fecha_larga(date('Y-m-d'))));
        $this->Cell(50, 8, t('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    function ensure(float $h): void
    {
        if ($this->GetY() + $h > $this->GetPageHeight() - 22) $this->AddPage();
    }

    function section(string $kicker, string $title): void
    {
        $this->ensure(30);
        $this->Ln(6);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(...self::TEAL);
        $this->Cell(0, 5, t(mb_strtoupper($kicker)), 0, 1);
        $this->SetFont('Helvetica', 'B', 15);
        $this->SetTextColor(...self::INK);
        $this->MultiCell(0, 7, t($title), 0, 'L');
        $this->SetDrawColor(...self::AQUA);
        $this->SetLineWidth(0.6);
        $this->Line(15, $this->GetY() + 1, 30, $this->GetY() + 1);
        $this->SetLineWidth(0.2);
        $this->Ln(4);
    }

    function para(string $text, float $size = 10): void
    {
        $this->SetFont('Helvetica', '', $size);
        $this->SetTextColor(...self::MUTED);
        $this->MultiCell(0, 5.2, t($text), 0, 'L');
        $this->Ln(2);
    }

    function bullets(array $items, array $color = self::TEAL): void
    {
        $this->SetFont('Helvetica', '', 10);
        foreach ($items as $it) {
            $this->ensure(8);
            $y = $this->GetY();
            $this->SetFillColor(...$color);
            $this->Rect(17, $y + 1.8, 1.8, 1.8, 'F');
            $this->SetX(22);
            $this->SetTextColor(...self::INK);
            $this->MultiCell(0, 5.2, t($it), 0, 'L');
            $this->Ln(1);
        }
    }

    function numbered(array $items, string $prefix): void
    {
        foreach ($items as $i => $it) {
            $this->ensure(10);
            $y = $this->GetY();
            $this->SetFillColor(...self::RIVER);
            $this->SetTextColor(...self::AQUA);
            $this->SetFont('Helvetica', 'B', 8);
            $this->SetXY(15, $y);
            $this->Cell(11, 6, t($prefix . ($i + 1)), 0, 0, 'C', true);
            $this->SetXY(29, $y + 0.5);
            $this->SetFont('Helvetica', '', 10);
            $this->SetTextColor(...self::INK);
            $this->MultiCell(0, 5.2, t($it), 0, 'L');
            $this->Ln(2);
        }
    }

    /** Tabla simple con encabezado oscuro y filas alternas. */
    function table(array $headers, array $widths, array $rows, array $aligns = []): void
    {
        $this->ensure(34);   // encabezado + al menos una fila en la misma página
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetFillColor(...self::RIVER);
        $this->SetTextColor(255, 255, 255);
        $this->SetX(15);
        foreach ($headers as $i => $h) {
            $x = $this->GetX();
            $this->Cell($widths[$i], 8, '', 0, 0, '', true);
            $this->SetX($x + 1);
            $this->Cell($widths[$i] - 2, 8, t($h), 0, 0, $aligns[$i] ?? 'L');
            $this->SetX($x + $widths[$i]);
        }
        $this->Ln();
        $this->SetFont('Helvetica', '', 9);
        foreach ($rows as $n => $row) {
            // Altura de la fila según el texto más largo
            $lines = 1;
            foreach ($row as $i => $cell) $lines = max($lines, $this->nbLines($widths[$i] - 2, t($cell)));
            $h = 5 * $lines + 3;
            $this->ensure($h);
            $x = 15;
            $y = $this->GetY();
            $fill = $n % 2 ? self::MIST : [255, 255, 255];
            foreach ($row as $i => $cell) {
                $this->SetFillColor(...$fill);
                $this->Rect($x, $y, $widths[$i], $h, 'F');
                $this->SetXY($x + 1, $y + 1.5);
                $this->SetTextColor(...($i === 0 ? self::INK : self::MUTED));
                $this->SetFont('Helvetica', $i === 0 ? 'B' : '', 9);
                $this->MultiCell($widths[$i] - 2, 5, t($cell), 0, $aligns[$i] ?? 'L');
                $x += $widths[$i];
            }
            $this->SetXY(15, $y + $h);
        }
        $this->Ln(3);
    }

    function nbLines(float $w, string $txt): int
    {
        $cw = $this->CurrentFont['cw'];
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c === ' ') $sep = $i;
            $l += $cw[$c] ?? 500;
            if ($l > $wmax) {
                if ($sep === -1) { if ($i === $j) $i++; } else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }

    function box(string $label, string $text): void
    {
        $this->SetFont('Helvetica', 'B', 12);
        $lines = $this->nbLines(170, t($text));
        $h = 6 * $lines + 16;
        $this->ensure($h);
        $y = $this->GetY();
        $this->SetFillColor(...self::RIVER);
        $this->Rect(15, $y, 180, $h, 'F');
        $this->SetFillColor(...self::SUN);
        $this->Rect(15, $y, 2, $h, 'F');
        $this->SetXY(22, $y + 4);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(...self::SUN);
        $this->Cell(0, 4, t(mb_strtoupper($label)), 0, 1);
        $this->SetX(22);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(255, 255, 255);
        $this->MultiCell(166, 6, t($text), 0, 'L');
        $this->SetY($y + $h + 3);
    }
}

$r = $reto;
$met = $r['metodologia'];
$pdf = new RetoPDF('P', 'mm', 'Letter');
$pdf->reto = $r;
$pdf->AliasNbPages();
$pdf->SetMargins(15, 18, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetTitle(t('Ficha del reto ' . $r['codigo'] . ' - ' . $r['titulo']), false);
$pdf->SetAuthor(t('RioLab 4.0 - Centro Universitario Girardot de UNIMINUTO'), false);
$pdf->SetSubject(t('Ficha completa del reto'), false);
$pdf->AddPage();

/* ---------- Portada ---------- */
$pdf->SetFillColor(...RetoPDF::RIVER);
$pdf->Rect(0, 0, 216, 112, 'F');
$pdf->SetFillColor(...RetoPDF::TEAL);
$pdf->Rect(0, 109, 216, 3, 'F');
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect(15, 14, 30, 30, 'F');
$pdf->Image(ROOT . '/image/logo_riolab-nav.png', 16, 15, 28, 28);
$pdf->SetXY(50, 18);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetTextColor(...RetoPDF::AQUA);
$pdf->Cell(0, 5, t('RIOLAB 4.0 · FICHA DEL RETO'), 0, 2);
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(200, 215, 214);
$pdf->MultiCell(140, 4.5, t('Ecosistema de Aprendizaje para la Innovación y el Desarrollo Sostenible del Sector Pesquero del Alto Magdalena'), 0, 'L');

$pdf->SetXY(15, 54);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetTextColor(...RetoPDF::SUN);
$pdf->Cell(0, 5, t(mb_strtoupper($r['tag']) . '   ·   ' . $r['codigo']), 0, 1);
$pdf->SetX(15);
$pdf->SetFont('Helvetica', 'B', 24);
$pdf->SetTextColor(255, 255, 255);
$pdf->MultiCell(180, 10, t($r['titulo']), 0, 'L');
$pdf->Ln(2);
$pdf->SetX(15);
$pdf->SetFont('Helvetica', '', 11);
$pdf->SetTextColor(220, 230, 229);
$pdf->MultiCell(180, 5.5, t($r['resumen']), 0, 'L');

/* ---------- Ficha técnica ---------- */
$pdf->SetY(120);
$facts = [
    ['Metodología', $met['nombre']],
    ['Duración', $r['duracion'] . ' · ' . $r['dedicacion']],
    ['Modalidad', $r['modalidad'] . ($r['modalidad_detalle'] ? ' — ' . $r['modalidad_detalle'] : '')],
    ['Cupos', $r['cupos'] . ' participantes (' . $r['equipos'] . ') · ' . $r['disponibles'] . ' disponibles al ' . fecha_larga(date('Y-m-d'))],
    ['Nivel', $r['nivel']],
    ['Inicio', fecha_larga($r['fecha_inicio'])],
    ['Cierre de inscripciones', fecha_larga($r['fecha_cierre'])],
    ['Estado', $r['abierto'] ? 'Inscripciones abiertas' : $r['motivo_cerrado']],
];
if ($r['lugar']) $facts[] = ['Lugar', $r['lugar']];
$pdf->section('Ficha técnica', 'Datos clave del reto');
$pdf->table(['Aspecto', 'Detalle'], [52, 128], $facts);

/* ---------- El reto ---------- */
$pdf->section('El reto', '¿Qué vamos a resolver?');
foreach ($r['contexto_parrafos'] as $p) $pdf->para($p);
$pdf->Ln(1);
$pdf->box('Pregunta reto', $r['pregunta']);
if ($r['ods']) {
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(...RetoPDF::TEAL);
    $pdf->Cell(0, 6, t('OBJETIVOS DE DESARROLLO SOSTENIBLE'), 0, 1);
    foreach ($r['ods'] as $n) {
        if (!isset(ODS[$n])) continue;
        $pdf->ensure(8);
        [$rr, $gg, $bb] = sscanf(ODS[$n][1], '#%02x%02x%02x');
        $pdf->SetFillColor($rr, $gg, $bb);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->Cell(16, 6, 'ODS ' . $n, 0, 0, 'C', true);
        $pdf->SetTextColor(...RetoPDF::INK);
        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->Cell(0, 6, t('  ' . ODS[$n][0]), 0, 1);
        $pdf->Ln(1);
    }
}

/* ---------- Metodología ---------- */
$pdf->section('Metodología', $met['nombre'] . ($met['sigla'] ? ' (' . $met['sigla'] . ')' : ''));
$pdf->para($met['descripcion']);
$pdf->table(['Fase', 'Qué se hace'], [45, 135], array_map(fn($i, $f) => [sprintf('%02d. ', $i + 1) . $f['nombre'], $f['desc']], array_keys($met['fases']), $met['fases']));

/* ---------- Cronograma ---------- */
$pdf->section('Cronograma', 'Ruta de ' . $r['duracion']);
$pdf->table(['Semanas', 'Fase', 'Actividades'], [25, 40, 115], array_map(fn($c) => [$c['semanas'], $c['titulo'], $c['desc']], $r['cronograma']));

/* ---------- Entregables ---------- */
$pdf->section('Entregables', 'Lo que construirá tu equipo');
$pdf->table(['#', 'Entregable', 'Descripción', 'Entrega'], [10, 45, 100, 25],
    array_map(fn($i, $e) => ['E' . ($i + 1), $e['titulo'], $e['descripcion'], $e['semana']], array_keys($r['entregables']), $r['entregables']));

/* ---------- Resultados ---------- */
$pdf->section('Resultados de aprendizaje', 'Al finalizar el reto, el participante…');
$pdf->numbered($r['resultados'], 'RA');
if ($r['competencias']) {
    $pdf->Ln(1);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(...RetoPDF::TEAL);
    $pdf->Cell(0, 6, t('COMPETENCIAS QUE SE FORTALECEN'), 0, 1);
    $pdf->para(implode('  ·  ', $r['competencias']));
}

/* ---------- Evaluación ---------- */
$pdf->section('Evaluación', '¿Cómo se valora el trabajo?');
$total = array_sum(array_column($r['evaluacion'], 'peso'));
$rows = array_map(fn($e) => [$e['criterio'], $e['peso'] . ' %'], $r['evaluacion']);
$rows[] = ['Total', $total . ' %'];
$pdf->table(['Criterio', 'Peso'], [150, 30], $rows, [0 => 'L', 1 => 'R']);
$pdf->para('Certificación: quienes cumplan con el 80 % de asistencia y presenten los entregables recibirán un certificado de participación del Centro Universitario Girardot de UNIMINUTO. Los mejores equipos serán reconocidos en el Demo Day RioLab.');

/* ---------- Participantes ---------- */
$pdf->section('Participantes', '¿Quiénes pueden sumarse?');
$pdf->SetFont('Helvetica', 'B', 10); $pdf->SetTextColor(...RetoPDF::INK); $pdf->Cell(0, 6, t('Dirigido a'), 0, 1);
$pdf->bullets($r['dirigido']);
$pdf->Ln(2);
$pdf->SetFont('Helvetica', 'B', 10); $pdf->SetTextColor(...RetoPDF::INK); $pdf->Cell(0, 6, t('Requisitos'), 0, 1);
$pdf->bullets($r['requisitos'], RetoPDF::SUN);
if ($r['aliados']) {
    $pdf->Ln(2);
    $pdf->SetFont('Helvetica', 'B', 10); $pdf->SetTextColor(...RetoPDF::INK); $pdf->Cell(0, 6, t('Aliados y acompañamiento'), 0, 1);
    $pdf->bullets(array_merge($r['aliados'], ['Cada equipo cuenta con un docente mentor de UNIMINUTO y un líder de la comunidad pesquera.']));
}

/* ---------- Información exclusiva del PDF ---------- */
if ($r['recursos']) {
    $pdf->section('Recursos', 'Lo que RioLab pone a tu disposición');
    $pdf->bullets($r['recursos']);
}
if ($r['compromisos']) {
    $pdf->section('Compromisos', 'Lo que esperamos de cada participante');
    $pdf->bullets($r['compromisos'], RetoPDF::SUN);
}
if (trim((string) $r['info_adicional']) !== '') {
    $pdf->section('Información adicional', 'Para tener en cuenta');
    foreach (preg_split('/\R\s*\R/', trim($r['info_adicional'])) as $p) $pdf->para(trim($p));
}

$pdf->section('Cómo participar', 'Inscripción y entrega de documentos');
$base = rtrim(config('base_url'), '/');
$pdf->numbered([
    'Inscríbete en la página del reto: ' . $base . '/reto.php?r=' . $r['slug'] . '#inscripcion',
    'Al enviar el formulario recibirás un código de acceso único (por ejemplo RL-AB12-CD34). Guárdalo en un lugar seguro.',
    'El equipo RioLab revisará tu inscripción y te contactará por correo o WhatsApp para confirmar tu cupo.',
    'Durante el reto, ingresa a «Mis entregas» (' . $base . '/participantes/) con tu correo y tu código para cargar los documentos de cada entregable.',
    'Tu mentor revisará cada entrega y dejará retroalimentación visible en la misma plataforma.',
], 'P');

if ($r['contacto_nombre'] || $r['contacto_correo']) {
    $pdf->Ln(2);
    $pdf->box('Contacto del reto', trim($r['contacto_nombre'] . ($r['contacto_correo'] ? ' · ' . $r['contacto_correo'] : '')));
}

$pdf->section('Preguntas frecuentes', 'Resolvemos tus dudas');
foreach (FAQ as [$q, $a]) {
    $pdf->ensure(16);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(...RetoPDF::INK);
    $pdf->MultiCell(0, 5.5, t($q), 0, 'L');
    $pdf->para($a, 9.5);
}

$pdf->Ln(3);
$pdf->ensure(20);
$pdf->SetFont('Helvetica', 'I', 8);
$pdf->SetTextColor(...RetoPDF::MUTED);
$pdf->MultiCell(0, 4.2, t('Tratamiento de datos personales: la información suministrada en la inscripción se usa exclusivamente para fines del proyecto RioLab 4.0, conforme a la Ley 1581 de 2012 y la política de tratamiento de datos de UNIMINUTO. Este documento se genera automáticamente con la información vigente del reto; las fechas y cupos pueden cambiar.'), 0, 'L');

$pdf->Output('D', 'RioLab-' . $r['codigo'] . '-' . $r['slug'] . '.pdf', true);
