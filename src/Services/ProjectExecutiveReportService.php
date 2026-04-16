<?php

declare(strict_types=1);

use App\Repositories\ProjectBillingRepository;
use App\Repositories\ProjectStoppersRepository;
use App\Repositories\ProjectsRepository;

class ProjectExecutiveReportService
{
    public function __construct(private Database $db)
    {
    }

    public function buildForProject(int $projectId, array $user): array
    {
        $projectsRepo = new ProjectsRepository($this->db);
        $project = $projectsRepo->findForUser($projectId, $user);
        if (!$project) {
            throw new \RuntimeException('Proyecto no encontrado.');
        }

        $config = (new ConfigService($this->db))->getConfig();
        $projectService = new ProjectService($this->db);
        $billingRepo = new ProjectBillingRepository($this->db);
        $requirementsRepo = new RequirementsRepository($this->db);
        $stoppersRepo = new ProjectStoppersRepository($this->db);
        $pmo = new PmoAutomationService($this->db);

        $health = $projectService->calculateProjectHealthReport($projectId);
        $billingConfig = $billingRepo->config($projectId);
        $billingSummary = $billingRepo->financialSummary($projectId, $billingConfig);
        $billingPlan = $billingRepo->billingPlan($projectId);
        $invoices = $billingRepo->invoices($projectId);
        $requirements = $requirementsRepo->listByProject($projectId);
        $indicator = $requirementsRepo->indicatorForProject($projectId, date('Y-m-01'), date('Y-m-t'));
        $stoppers = $stoppersRepo->forProject($projectId);
        $alerts = $pmo->latestAlertsForProject($projectId, 12);

        $reportData = [
            'generated_at' => date('d/m/Y H:i'),
            'project' => $project,
            'health' => $health,
            'billing_config' => $billingConfig,
            'billing_summary' => $billingSummary,
            'billing_plan' => $billingPlan,
            'invoices' => $invoices,
            'requirements' => $requirements,
            'requirements_indicator' => $indicator,
            'requirements_target' => (float) (
                $config['operational_rules']['health_scoring']['meta_cumplimiento_requisitos']
                ?? $config['operational_rules']['health_scoring']['requirements_indicator']['target']
                ?? 95
            ),
            'stoppers_open' => array_values(array_filter($stoppers, static fn (array $s): bool => in_array((string) ($s['status'] ?? ''), ['abierto', 'en_gestion', 'escalado'], true))),
            'alerts' => $alerts,
            'logo_path' => $this->resolveLogoPath((string) ($config['theme']['logo'] ?? '')),
        ];

        $renderer = new ExecutivePdfRenderer();
        $pdf = $renderer->render($reportData);

        return [
            'filename' => 'informe-gerencial-' . $this->slug((string) ($project['name'] ?? ('proyecto-' . $projectId))) . '-' . date('Ymd') . '.pdf',
            'content' => $pdf,
        ];
    }

    private function resolveLogoPath(string $logoUrl): ?string
    {
        if ($logoUrl === '') {
            return null;
        }

        if (str_starts_with($logoUrl, '/')) {
            $candidate = __DIR__ . '/../../public' . $logoUrl;
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function slug(string $value): string
    {
        $text = strtolower(trim($value));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? 'reporte';
        return trim($text, '-') ?: 'reporte';
    }
}

class ExecutivePdfRenderer
{
    private CorporatePdf $pdf;
    private float $cursorY = 0.0;
    private float $margin = 30.0;
    private float $contentWidth = 535.0;
    private string $projectName = 'Proyecto';
    private string $generatedAt = '';

    public function __construct()
    {
        $this->pdf = new CorporatePdf();
    }

    public function render(array $d): string
    {
        $this->projectName = (string) (($d['project'] ?? [])['name'] ?? 'Proyecto');
        $this->generatedAt = (string) ($d['generated_at'] ?? date('d/m/Y H:i'));

        $this->pdf->addPage();
        $this->cursorY = 812;

        $this->drawCover($d);
        $this->newPage();

        $this->drawSectionTitle('SECCIÓN 1 · RESUMEN EJECUTIVO');
        $this->drawExecutiveSummary($d);

        $this->drawSectionTitle('SECCIÓN 2 · FACTURACIÓN');
        $this->drawBilling($d);

        $this->drawSectionTitle('SECCIÓN 3 · CUMPLIMIENTO DE REQUISITOS');
        $this->drawRequirements($d);

        $this->drawSectionTitle('SECCIÓN 4 · BLOQUEOS ACTIVOS');
        $this->drawBlockers($d);

        $this->drawSectionTitle('SECCIÓN 5 · ALERTAS Y RIESGOS');
        $this->drawAlerts($d);

        $this->decoratePages();
        return $this->pdf->output();
    }

    private function drawCover(array $d): void
    {
        $p = $d['project'] ?? [];
        $logoPath = $d['logo_path'] ?? null;
        $this->pdf->fillRect(0, 0, 595, 842, [45, 42, 110]);

        if (is_string($logoPath) && $logoPath !== '') {
            $this->pdf->drawImage($logoPath, 220, 680, 155, 95);
        } else {
            $this->pdf->fillRect(238, 700, 120, 55, [255, 255, 255]);
            $this->pdf->text(273, 724, 'AOS', 30, [45, 42, 110]);
        }

        $this->pdf->textCentered(420, 'INFORME GERENCIAL', 20, [255, 255, 255]);
        $this->pdf->textCentered(380, mb_strtoupper((string) ($p['name'] ?? 'Proyecto')), 16, [255, 255, 255]);
        $this->pdf->textCentered(340, (string) ($p['name'] ?? 'Proyecto'), 28, [255, 255, 255]);

        $this->pdf->fillRect(0, 0, 595, 120, [255, 255, 255]);
        $meta = [
            ['Cliente', (string) ($p['client_name'] ?? 'Sin cliente')],
            ['Fecha', (string) ($d['generated_at'] ?? date('d/m/Y H:i'))],
            ['Estado', $this->projectStatusLabel((string) ($p['status'] ?? ''))],
            ['PM', (string) ($p['pm_name'] ?? 'No asignado')],
        ];

        $colWidth = 595 / 4;
        foreach ($meta as $i => [$label, $value]) {
            $x = ($colWidth * $i) + 20;
            $this->pdf->text($x, 84, $label, 10, [90, 90, 90]);
            $this->pdf->text($x, 60, mb_strimwidth($value, 0, 28, '...'), 11, [35, 35, 35]);
        }
        $this->cursorY = 36;
    }

    private function drawExecutiveSummary(array $d): void
    {
        $project = $d['project'] ?? [];
        $health = $d['health'] ?? [];
        $billingSummary = $d['billing_summary'] ?? [];
        $kpis = [
            ['Score de salud', (string) ((int) ($health['total_score'] ?? 0)) . ' / 100'],
            ['Estado general', $this->projectStatusLabel((string) ($project['status'] ?? ''))],
            ['Avance', number_format((float) ($project['progress'] ?? 0), 1) . '%'],
            ['Inicio', $this->fmtDate($project['start_date'] ?? null)],
            ['Fin', $this->fmtDate($project['end_date'] ?? null)],
            ['PM responsable', (string) ($project['pm_name'] ?? 'No asignado')],
            ['Presupuesto', $this->fmtMoney((float) ($project['budget'] ?? 0), (string) ($project['currency_code'] ?? 'USD'))],
            ['Costo actual', $this->fmtMoney((float) ($project['actual_cost'] ?? 0), (string) ($project['currency_code'] ?? 'USD'))],
            ['Saldo por facturar', $this->fmtMoney((float) ($billingSummary['balance_to_invoice'] ?? 0), (string) ($billingSummary['currency_code'] ?? ($project['currency_code'] ?? 'USD')))],
        ];
        $this->drawKpiGrid($kpis, 3);
    }

    private function drawBilling(array $d): void
    {
        $summary = $d['billing_summary'] ?? [];
        $currency = (string) ($summary['currency_code'] ?? 'USD');
        $rows = [
            ['Total del contrato', $this->fmtMoney((float) ($summary['total_contract'] ?? 0), $currency)],
            ['Total facturado', $this->fmtMoney((float) ($summary['total_invoiced'] ?? 0), $currency)],
            ['Saldo por facturar', $this->fmtMoney((float) ($summary['balance_to_invoice'] ?? 0), $currency)],
        ];
        $this->drawDataRows($rows);

        $this->drawMiniTable('Ítems del plan de facturación', ['Concepto', 'Fecha esperada', 'Estado'], array_map(function (array $item): array {
            $status = (string) ($item['status'] ?? 'pendiente');
            $statusLabel = match ($status) {
                'atrasado' => 'Vencido',
                'proximo' => 'Próximo',
                default => 'Al día',
            };
            return [
                (string) ($item['concept'] ?? '-'),
                $this->fmtDate($item['expected_date'] ?? null),
                ['type' => 'badge', 'text' => $statusLabel, 'tone' => $this->statusTone($statusLabel)],
            ];
        }, array_slice((array) ($d['billing_plan'] ?? []), 0, 10)));

        $this->drawMiniTable('Facturas emitidas', ['Factura', 'Fecha', 'Monto'], array_map(function (array $inv) use ($currency): array {
            return [
                (string) ($inv['invoice_number'] ?? '#'),
                $this->fmtDate($inv['issued_at'] ?? null),
                $this->fmtMoney((float) ($inv['amount'] ?? 0), (string) ($inv['currency_code'] ?? $currency)),
            ];
        }, array_slice((array) ($d['invoices'] ?? []), 0, 12)));
    }

    private function drawRequirements(array $d): void
    {
        $requirements = (array) ($d['requirements'] ?? []);
        $counts = ['aprobado' => 0, 'en_revision' => 0, 'rechazado' => 0];
        foreach ($requirements as $req) {
            $status = (string) ($req['status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        $indicator = (array) ($d['requirements_indicator'] ?? []);
        $target = (float) ($d['requirements_target'] ?? 95);
        $value = (float) ($indicator['value'] ?? 0);

        $rows = [
            ['Total de requisitos', (string) count($requirements)],
            ['Aprobados', (string) $counts['aprobado']],
            ['En revisión', (string) $counts['en_revision']],
            ['Rechazados', (string) $counts['rechazado']],
            ['Cumplimiento actual', number_format($value, 1) . '%'],
            ['Meta objetivo', number_format($target, 1) . '%'],
        ];
        $this->drawDataRows($rows);
    }

    private function drawBlockers(array $d): void
    {
        $rows = array_map(function (array $s): array {
            return [
                (string) ($s['title'] ?? 'Bloqueo'),
                $this->fmtDate($s['detected_at'] ?? null),
                trim((string) ($s['description'] ?? 'Sin descripcion')),
            ];
        }, (array) ($d['stoppers_open'] ?? []));
        $this->drawMiniTable('Bloqueos abiertos', ['Bloqueo', 'Fecha', 'Descripcion'], $rows, 8);
    }

    private function drawAlerts(array $d): void
    {
        $rows = array_map(function (array $a): array {
            return [
                strtoupper((string) ($a['severity'] ?? 'info')),
                (string) ($a['title'] ?? 'Alerta'),
                trim((string) ($a['message'] ?? 'Sin detalle')),
            ];
        }, (array) ($d['alerts'] ?? []));
        $this->drawMiniTable('Alertas activas', ['Severidad', 'Título', 'Detalle'], $rows, 10);
    }

    private function drawSectionTitle(string $title): void
    {
        $this->ensureSpace(46);
        $this->pdf->text($this->margin, $this->cursorY, mb_strtoupper($title), 16, [127, 119, 221]);
        $this->pdf->fillRect($this->margin, $this->cursorY - 12, $this->contentWidth, 2, [127, 119, 221]);
        $this->cursorY -= 28;
    }

    private function drawDataRows(array $rows): void
    {
        foreach ($rows as $idx => $row) {
            $this->ensureSpace(28);
            $shade = $idx % 2 === 0 ? [247, 247, 245] : [255, 255, 255];
            $this->pdf->fillRect($this->margin, $this->cursorY - 8, $this->contentWidth, 22, $shade);
            $this->pdf->text($this->margin + 10, $this->cursorY, (string) ($row[0] ?? ''), 10, [74, 74, 74]);
            $this->pdf->text($this->margin + 260, $this->cursorY, (string) ($row[1] ?? ''), 10, [23, 23, 23]);
            $this->cursorY -= 22;
        }
        $this->cursorY -= 8;
    }

    private function drawMiniTable(string $title, array $headers, array $rows, int $maxRows = 10): void
    {
        $this->ensureSpace(36);
        $this->pdf->text($this->margin, $this->cursorY, $title, 11, [53, 47, 104]);
        $this->cursorY -= 18;

        $widths = [170, 110, 255];
        $this->drawTableHeader($headers, $widths);

        $slice = array_slice($rows, 0, $maxRows);
        if (empty($slice)) {
            $slice[] = ['Sin datos disponibles', '', ''];
        }

        foreach ($slice as $i => $row) {
            $this->ensureSpace(28);
            $this->pdf->fillRect($this->margin, $this->cursorY - 8, $this->contentWidth, 22, $i % 2 === 0 ? [255, 255, 255] : [247, 247, 245]);
            $x = $this->margin + 8;
            foreach ($widths as $col => $w) {
                $cell = $row[$col] ?? '';
                if (is_array($cell) && (($cell['type'] ?? '') === 'badge')) {
                    $this->drawStatusBadge($x, $this->cursorY - 6, (string) ($cell['text'] ?? ''), (string) ($cell['tone'] ?? 'neutral'));
                } else {
                    $this->pdf->text($x, $this->cursorY, mb_strimwidth((string) $cell, 0, $col === 2 ? 70 : 34, '...'), 9, [30, 30, 30]);
                }
                $x += $w;
            }
            $this->cursorY -= 22;
        }

        $this->cursorY -= 8;
    }

    private function drawTableHeader(array $headers, array $widths): void
    {
        $this->ensureSpace(26);
        $this->pdf->fillRect($this->margin, $this->cursorY - 8, $this->contentWidth, 20, [127, 119, 221]);
        $x = $this->margin + 8;
        foreach ($headers as $idx => $header) {
            $this->pdf->text($x, $this->cursorY, (string) $header, 9, [255, 255, 255]);
            $x += (float) ($widths[$idx] ?? 100);
        }
        $this->cursorY -= 20;
    }

    private function ensureSpace(float $needed): void
    {
        if ($this->cursorY - $needed > 48) {
            return;
        }
        $this->newPage();
    }

    private function newPage(): void
    {
        $this->pdf->addPage();
        $this->cursorY = 780;
    }

    private function fmtDate(?string $date): string
    {
        if (!$date) {
            return '-';
        }
        $ts = strtotime($date);
        return $ts ? date('d/m/Y', $ts) : '-';
    }

    private function fmtMoney(float $value, string $currency): string
    {
        return strtoupper($currency) . ' ' . number_format($value, 2, ',', '.');
    }

    private function projectStatusLabel(string $status): string
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'on_hold' => 'En riesgo',
            'closed', 'completado', 'finalizado' => 'Completado',
            'execution', 'en_curso', 'active' => 'En curso',
            default => ucfirst($status ?: 'Sin definir'),
        };
    }

    private function decoratePages(): void
    {
        $total = $this->pdf->pageCount();
        for ($i = 1; $i < $total; $i++) {
            $page = $i + 1;
            $header = '';
            $header .= sprintf('%.3F %.3F %.3F rg 0 812 595 20 re f', 127 / 255, 119 / 255, 221 / 255) . "\n";
            $header .= $this->pdf->buildText($this->margin, 822, $this->projectName, 9, [255, 255, 255]);
            $header .= $this->pdf->buildText(540, 822, 'Pag. ' . $page, 9, [255, 255, 255]);
            $this->pdf->prependToPage($i, $header);

            $footer = '';
            $footer .= '0.85 0.85 0.85 rg 30 34 535 1 re f' . "\n";
            $footer .= $this->pdf->buildText($this->margin, 20, 'Generado por AOS Prompt Maestro PMO', 8, [110, 110, 110]);
            $footer .= $this->pdf->buildText(470, 20, $this->generatedAt, 8, [110, 110, 110]);
            $this->pdf->appendToPage($i, $footer);
        }
    }

    private function drawKpiGrid(array $kpis, int $cols): void
    {
        $colWidth = ($this->contentWidth - 16) / $cols;
        foreach ($kpis as $idx => $kpi) {
            if ($idx % $cols === 0) {
                $this->ensureSpace(76);
            }
            $row = intdiv($idx, $cols);
            $col = $idx % $cols;
            $x = $this->margin + ($col * ($colWidth + 8));
            $y = $this->cursorY - ($row * 74);
            if ($col === 0 && $idx > 0) {
                $this->cursorY -= 74;
            }
            $this->pdf->fillRect($x, $y - 48, $colWidth, 62, [255, 255, 255]);
            $this->pdf->fillRect($x, $y - 48, 4, 62, [127, 119, 221]);
            $this->pdf->text($x + 10, $y - 10, mb_strtoupper((string) ($kpi[0] ?? '')), 7, [130, 130, 130]);
            $this->pdf->text($x + 10, $y - 30, mb_strimwidth((string) ($kpi[1] ?? '-'), 0, 28, '...'), 13, [25, 25, 25]);
        }
        $this->cursorY -= (74 * (int) ceil(count($kpis) / $cols)) + 8;
    }

    private function drawStatusBadge(float $x, float $y, string $text, string $tone): void
    {
        $palette = match ($tone) {
            'success' => [[220, 248, 229], [23, 124, 58]],
            'warning' => [[255, 246, 210], [153, 104, 0]],
            'danger' => [[255, 226, 226], [165, 33, 33]],
            default => [[235, 235, 235], [70, 70, 70]],
        };
        $w = max(52.0, (float) (strlen($text) * 4.8) + 14);
        $this->pdf->fillRect($x, $y, $w, 14, $palette[0]);
        $this->pdf->text($x + 6, $y + 4, mb_strtoupper($text), 8, $palette[1]);
    }

    private function statusTone(string $value): string
    {
        $norm = strtolower(trim($value));
        return match ($norm) {
            'al dia', 'al día', 'aprobado' => 'success',
            'proximo', 'próximo', 'en revision', 'en revisión' => 'warning',
            'vencido', 'rechazado' => 'danger',
            default => 'neutral',
        };
    }
}

class CorporatePdf
{
    private array $pages = [];
    private array $images = [];
    private int $currentPage = -1;

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->currentPage = count($this->pages) - 1;
    }

    public function text(float $x, float $y, string $text, float $fontSize = 11, array $rgb = [0, 0, 0]): void
    {
        $this->content($this->buildText($x, $y, $text, $fontSize, $rgb));
    }

    public function textCentered(float $y, string $text, float $fontSize = 11, array $rgb = [0, 0, 0]): void
    {
        $x = max(30.0, 297.5 - (strlen($text) * $fontSize * 0.22));
        $this->text($x, $y, $text, $fontSize, $rgb);
    }

    public function fillRect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->content(sprintf('%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255, $x, $y, $w, $h));
    }

    public function drawImage(string $path, float $x, float $y, float $w, float $h): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $jpegData = $this->toJpegData($path);
        if ($jpegData === null) {
            return;
        }

        [$data, $imgW, $imgH] = $jpegData;
        $id = count($this->images) + 1;
        $name = 'Im' . $id;
        $this->images[] = [
            'name' => $name,
            'data' => $data,
            'width' => $imgW,
            'height' => $imgH,
        ];
        $this->content(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q', $w, $h, $x, $y, $name));
    }

    public function output(): string
    {
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

        $kids = [];
        $pageObjectStart = 3;
        $contentStart = $pageObjectStart + count($this->pages);
        $imageStart = $contentStart + count($this->pages);

        for ($i = 0; $i < count($this->pages); $i++) {
            $kids[] = ($pageObjectStart + $i) . ' 0 R';
        }
        $objects[] = '<< /Type /Pages /Count ' . count($this->pages) . ' /Kids [' . implode(' ', $kids) . '] >>';

        $imageRefs = [];
        foreach ($this->images as $idx => $image) {
            $objNum = $imageStart + $idx;
            $imageRefs[$image['name']] = $objNum . ' 0 R';
        }
        $xObject = '';
        if (!empty($imageRefs)) {
            $parts = [];
            foreach ($imageRefs as $name => $ref) {
                $parts[] = '/' . $name . ' ' . $ref;
            }
            $xObject = '/XObject << ' . implode(' ', $parts) . ' >>';
        }

        for ($i = 0; $i < count($this->pages); $i++) {
            $contentRef = ($contentStart + $i) . ' 0 R';
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> ' . $xObject . ' >> /Contents ' . $contentRef . ' >>';
        }

        for ($i = 0; $i < count($this->pages); $i++) {
            $stream = $this->pages[$i];
            $objects[] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }

        foreach ($this->images as $image) {
            $data = $image['data'];
            $objects[] = '<< /Type /XObject /Subtype /Image /Width ' . $image['width'] . ' /Height ' . $image['height'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($data) . " >>\nstream\n" . $data . "\nendstream";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $obj . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        }

        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function content(string $command): void
    {
        if ($this->currentPage < 0) {
            $this->addPage();
        }
        $this->pages[$this->currentPage] .= $command . "\n";
    }

    private function escape(string $text): string
    {
        $text = $this->toWinAnsi($text);
        $text = str_replace(["\\", '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $text = preg_replace('/[^\x20-\xFF]/', '?', $text) ?? $text;
        return $text;
    }

    public function buildText(float $x, float $y, string $text, float $fontSize = 11, array $rgb = [0, 0, 0]): string
    {
        return sprintf('BT /F1 %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET', $fontSize, $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255, $x, $y, $this->escape($text));
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function prependToPage(int $pageIndex, string $command): void
    {
        if (!isset($this->pages[$pageIndex])) {
            return;
        }
        $this->pages[$pageIndex] = $command . $this->pages[$pageIndex];
    }

    public function appendToPage(int $pageIndex, string $command): void
    {
        if (!isset($this->pages[$pageIndex])) {
            return;
        }
        $this->pages[$pageIndex] .= $command;
    }

    private function toWinAnsi(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        return $converted === false ? $text : $converted;
    }

    private function toJpegData(string $path): ?array
    {
        $info = @getimagesize($path);
        if (!$info) {
            return null;
        }

        $mime = strtolower((string) ($info['mime'] ?? ''));
        $img = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if (!$img) {
            return null;
        }

        $tmp = fopen('php://temp', 'w+b');
        if ($tmp === false) {
            imagedestroy($img);
            return null;
        }

        imagejpeg($img, $tmp, 88);
        imagedestroy($img);
        rewind($tmp);
        $data = stream_get_contents($tmp);
        fclose($tmp);

        if (!is_string($data) || $data === '') {
            return null;
        }

        return [$data, (int) ($info[0] ?? 0), (int) ($info[1] ?? 0)];
    }
}
