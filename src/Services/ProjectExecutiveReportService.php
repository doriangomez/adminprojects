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
    private float $margin = 36.0;
    private float $contentWidth = 523.0;

    public function __construct()
    {
        $this->pdf = new CorporatePdf();
    }

    public function render(array $d): string
    {
        $this->pdf->addPage();
        $this->cursorY = 806;

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

        return $this->pdf->output();
    }

    private function drawCover(array $d): void
    {
        $p = $d['project'] ?? [];
        $logoPath = $d['logo_path'] ?? null;

        $this->pdf->fillRect($this->margin, 745, $this->contentWidth, 115, [243, 241, 255]);
        $this->pdf->fillRect($this->margin, 845, 140, 10, [127, 119, 221]);
        if (is_string($logoPath) && $logoPath !== '') {
            $this->pdf->drawImage($logoPath, $this->margin + 14, 760, 90, 60);
        } else {
            $this->pdf->fillRect($this->margin + 14, 768, 86, 40, [127, 119, 221]);
            $this->pdf->text($this->margin + 38, 790, 'AOS', 22, [255, 255, 255]);
        }

        $this->pdf->text($this->margin + 170, 815, 'INFORME GERENCIAL', 22, [53, 47, 104]);
        $this->pdf->text($this->margin + 170, 790, (string) ($p['name'] ?? 'Proyecto'), 16, [34, 34, 34]);

        $meta = [
            'Cliente' => (string) ($p['client_name'] ?? 'Sin cliente'),
            'Fecha de generación' => (string) ($d['generated_at'] ?? date('d/m/Y H:i')),
            'Estado actual' => ucfirst((string) ($p['status'] ?? 'sin estado')),
            'PM responsable' => (string) ($p['pm_name'] ?? 'No asignado'),
        ];

        $y = 706;
        foreach ($meta as $label => $value) {
            $this->pdf->text($this->margin, $y, $label . ':', 11, [84, 84, 84]);
            $this->pdf->text($this->margin + 170, $y, $value, 11, [24, 24, 24]);
            $y -= 20;
        }
        $this->cursorY = $y - 10;
    }

    private function drawExecutiveSummary(array $d): void
    {
        $project = $d['project'] ?? [];
        $health = $d['health'] ?? [];
        $billingSummary = $d['billing_summary'] ?? [];

        $rows = [
            ['Score salud', (string) ((int) ($health['total_score'] ?? 0)) . ' / 100'],
            ['Estado general', $this->projectStatusLabel((string) ($project['status'] ?? ''))],
            ['Fecha inicio', $this->fmtDate($project['start_date'] ?? null)],
            ['Fecha fin', $this->fmtDate($project['end_date'] ?? null)],
            ['PM responsable', (string) ($project['pm_name'] ?? 'No asignado')],
            ['Presupuesto vs costo actual', $this->fmtMoney((float) ($project['budget'] ?? 0), (string) ($project['currency_code'] ?? 'USD')) . ' vs ' . $this->fmtMoney((float) ($project['actual_cost'] ?? 0), (string) ($project['currency_code'] ?? 'USD'))],
            ['Avance porcentual', number_format((float) ($project['progress'] ?? 0), 1) . '%'],
            ['Saldo por facturar', $this->fmtMoney((float) ($billingSummary['balance_to_invoice'] ?? 0), (string) ($billingSummary['currency_code'] ?? ($project['currency_code'] ?? 'USD')))],
        ];

        $this->drawDataRows($rows);
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
                $statusLabel,
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
                trim((string) ($s['description'] ?? 'Sin descripción')),
            ];
        }, (array) ($d['stoppers_open'] ?? []));
        $this->drawMiniTable('Bloqueos abiertos', ['Bloqueo', 'Fecha', 'Descripción'], $rows, 8);
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
        $this->ensureSpace(58);
        $this->pdf->fillRect($this->margin, $this->cursorY - 10, $this->contentWidth, 30, [127, 119, 221]);
        $this->pdf->text($this->margin + 12, $this->cursorY + 1, $title, 12, [255, 255, 255]);
        $this->cursorY -= 40;
    }

    private function drawDataRows(array $rows): void
    {
        foreach ($rows as $idx => $row) {
            $this->ensureSpace(28);
            $shade = $idx % 2 === 0 ? [249, 248, 255] : [255, 255, 255];
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

        $widths = [150, 100, 273];
        $this->drawTableHeader($headers, $widths);

        $slice = array_slice($rows, 0, $maxRows);
        if (empty($slice)) {
            $slice[] = ['Sin datos disponibles', '', ''];
        }

        foreach ($slice as $i => $row) {
            $this->ensureSpace(28);
            $this->pdf->fillRect($this->margin, $this->cursorY - 8, $this->contentWidth, 22, $i % 2 === 0 ? [252, 252, 252] : [245, 244, 255]);
            $x = $this->margin + 8;
            foreach ($widths as $col => $w) {
                $this->pdf->text($x, $this->cursorY, mb_strimwidth((string) ($row[$col] ?? ''), 0, $col === 2 ? 75 : 35, '…'), 9, [30, 30, 30]);
                $x += $w;
            }
            $this->cursorY -= 22;
        }

        $this->cursorY -= 8;
    }

    private function drawTableHeader(array $headers, array $widths): void
    {
        $this->ensureSpace(26);
        $this->pdf->fillRect($this->margin, $this->cursorY - 8, $this->contentWidth, 20, [226, 223, 250]);
        $x = $this->margin + 8;
        foreach ($headers as $idx => $header) {
            $this->pdf->text($x, $this->cursorY, (string) $header, 9, [40, 40, 40]);
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
        $this->cursorY = 806;
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
        $this->content(sprintf('BT /F1 %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET', $fontSize, $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255, $x, $y, $this->escape($text)));
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
        $text = str_replace(["\\", '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $text = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        return $text;
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
