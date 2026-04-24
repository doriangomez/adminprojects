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
            'brand_palette' => [
                'primary' => (string) ($config['theme']['primary'] ?? '#2563eb'),
                'secondary' => (string) ($config['theme']['secondary'] ?? '#0f172a'),
                'accent' => (string) ($config['theme']['accent'] ?? '#f97316'),
            ],
        ];

        $renderer = new ExecutivePdfRenderer();
        try {
            $pdf = $renderer->render($reportData);
        } catch (\Throwable $e) {
            error_log(sprintf('[projects.executive_report] Renderer fallback (%d): %s', $projectId, $e->getMessage()));
            $pdf = $this->renderFallbackPdf($reportData);
        }

        return [
            'filename' => 'informe-gerencial-' . $this->slug((string) ($project['name'] ?? ('proyecto-' . $projectId))) . '-' . date('Ymd') . '.pdf',
            'content' => $pdf,
        ];
    }

    private function resolveLogoPath(string $logoUrl): ?string
    {
        if ($logoUrl === '') {
            return $this->latestUploadedLogo();
        }

        if (str_starts_with($logoUrl, '/')) {
            $candidate = __DIR__ . '/../../public' . $logoUrl;
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return $this->latestUploadedLogo();
    }

    private function latestUploadedLogo(): ?string
    {
        $dir = __DIR__ . '/../../public/uploads/logos';
        if (!is_dir($dir) || !is_readable($dir)) {
            return null;
        }

        $files = glob($dir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE) ?: [];
        if ($files === []) {
            return null;
        }

        usort($files, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
        $latest = (string) ($files[0] ?? '');
        return ($latest !== '' && is_file($latest) && is_readable($latest)) ? $latest : null;
    }

    private function slug(string $value): string
    {
        $text = strtolower(trim($value));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? 'reporte';
        return trim($text, '-') ?: 'reporte';
    }

    private function renderFallbackPdf(array $data): string
    {
        $project = (array) ($data['project'] ?? []);
        $health = (array) ($data['health'] ?? []);
        $billingSummary = (array) ($data['billing_summary'] ?? []);
        $requirements = (array) ($data['requirements'] ?? []);
        $alerts = (array) ($data['alerts'] ?? []);

        $pdf = new CorporatePdf();
        $pdf->addPage();
        $pdf->fillRect(0, 792, 595, 50, [15, 23, 42]);
        $pdf->text(32, 812, 'Informe gerencial de proyecto (modo contingencia)', 12, [255, 255, 255]);
        $pdf->text(32, 786, 'Si visualizas este formato, hubo un error no bloqueante en el render corporativo.', 8, [71, 85, 105]);

        $y = 744.0;
        $lineGap = 16.0;
        $sectionGap = 24.0;

        $writeLine = static function (CorporatePdf $p, float $x, float $lineY, string $label, string $value): void {
            $safeLabel = trim($label);
            $safeValue = trim($value);
            if ($safeValue === '') {
                $safeValue = '-';
            }
            if (strlen($safeValue) > 90) {
                $safeValue = substr($safeValue, 0, 90);
            }
            $p->text($x, $lineY, $safeLabel . ': ' . $safeValue, 10, [31, 41, 55]);
        };

        $pdf->text(32, $y, 'Datos generales', 11, [15, 23, 42]);
        $y -= $lineGap;
        $writeLine($pdf, 32, $y, 'Proyecto', (string) ($project['name'] ?? 'Proyecto'));
        $y -= $lineGap;
        $writeLine($pdf, 32, $y, 'Cliente', (string) ($project['client_name'] ?? 'Sin cliente'));
        $y -= $lineGap;
        $writeLine($pdf, 32, $y, 'PM', (string) ($project['pm_name'] ?? 'No asignado'));
        $y -= $lineGap;
        $writeLine($pdf, 32, $y, 'Estado', (string) ($project['status'] ?? 'Sin definir'));
        $y -= $lineGap;
        $writeLine($pdf, 32, $y, 'Fecha de generacion', (string) ($data['generated_at'] ?? date('d/m/Y H:i')));

        $y -= $sectionGap;
        $pdf->text(32, $y, 'Indicadores clave', 11, [15, 23, 42]);
        $y -= $lineGap;
        $writeLine($pdf, 32, $y, 'Score de salud', (string) (($health['total_score'] ?? 0) . ' / 100'));
        $y -= $lineGap;
        $writeLine($pdf, 32, $y, 'Avance', number_format((float) ($project['progress'] ?? 0), 1) . '%');
        $y -= $lineGap;
        $writeLine(
            $pdf,
            32,
            $y,
            'Saldo por facturar',
            strtoupper((string) ($billingSummary['currency_code'] ?? 'USD')) . ' ' . number_format((float) ($billingSummary['balance_to_invoice'] ?? 0), 2, ',', '.')
        );
        $y -= $lineGap;
        $writeLine($pdf, 32, $y, 'Requisitos registrados', (string) count($requirements));
        $y -= $lineGap;
        $writeLine($pdf, 32, $y, 'Alertas registradas', (string) count($alerts));

        return $pdf->output();
    }
}

class ExecutivePdfRenderer
{
    private CorporatePdf $pdf;
    private float $cursorY = 0.0;
    private float $margin = 40.0;
    private float $contentWidth = 515.0;
    private string $projectName = 'Proyecto';
    private string $generatedAt = '';
    private ?string $logoPath = null;
    private int $sectionIndex = 0;
    private array $brand = [
        'primary' => [37, 99, 235],
        'secondary' => [15, 23, 42],
        'accent' => [249, 115, 22],
    ];

    public function __construct()
    {
        $this->pdf = new CorporatePdf();
    }

    public function render(array $d): string
    {
        $this->projectName = (string) (($d['project'] ?? [])['name'] ?? 'Proyecto');
        $this->generatedAt = (string) ($d['generated_at'] ?? date('d/m/Y H:i'));
        $this->logoPath = is_string($d['logo_path'] ?? null) ? (string) $d['logo_path'] : null;
        $this->sectionIndex = 0;
        $this->initBrand((array) ($d['brand_palette'] ?? []));

        $this->pdf->addPage();
        $this->drawCover($d);
        $this->drawExecutiveSummary($d);
        $this->drawBilling($d);
        $this->drawRequirements($d);
        $this->drawBlockers($d);
        $this->drawAlerts($d);

        $this->decoratePages();
        return $this->pdf->output();
    }

    private function initBrand(array $palette): void
    {
        $this->brand['primary'] = $this->hexToRgb((string) ($palette['primary'] ?? ''), $this->brand['primary']);
        $this->brand['secondary'] = $this->hexToRgb((string) ($palette['secondary'] ?? ''), $this->brand['secondary']);
        $this->brand['accent'] = $this->hexToRgb((string) ($palette['accent'] ?? ''), $this->brand['accent']);
    }

    private function hexToRgb(string $hex, array $fallback): array
    {
        $value = ltrim(trim($hex), '#');
        if ($value === '') {
            return $fallback;
        }

        if (strlen($value) === 3) {
            $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
        }

        if (!preg_match('/^[a-fA-F0-9]{6}$/', $value)) {
            return $fallback;
        }

        return [
            (int) hexdec(substr($value, 0, 2)),
            (int) hexdec(substr($value, 2, 2)),
            (int) hexdec(substr($value, 4, 2)),
        ];
    }

    private function drawCover(array $d): void
    {
        $project = (array) ($d['project'] ?? []);
        $statusLabel = $this->projectStatusLabel((string) ($project['status'] ?? ''));
        $statusTone = $this->statusTone($statusLabel);

        $heroTop = 842.0;
        $heroHeight = 232.0;
        $heroBottom = $heroTop - $heroHeight;

        $this->pdf->fillRect(0, 0, 595, 842, [246, 249, 254]);
        $this->pdf->fillRect(0, $heroBottom, 595, $heroHeight, $this->brand['secondary']);
        $this->pdf->fillRect(0, $heroBottom + 10, 595, 5, $this->brand['primary']);

        $logoCardTop = 806.0;
        $this->drawPanel($this->margin, $logoCardTop, 144, 58, [255, 255, 255], [255, 255, 255]);
        $logoDrawn = false;
        if (is_string($this->logoPath) && $this->logoPath !== '') {
            $logoDrawn = $this->pdf->drawImage($this->logoPath, $this->margin + 10, $logoCardTop - 43, 122, 34);
        }
        if (!$logoDrawn) {
            $this->pdf->text($this->margin + 18, $logoCardTop - 34, 'Marca corporativa', 8, [71, 85, 108]);
        }

        $titleX = $this->margin + 172;
        $this->pdf->text($titleX, 804, 'INFORME EJECUTIVO', 10.5, [198, 222, 255]);
        $nameLines = $this->wrapText($this->safeText((string) ($project['name'] ?? 'Proyecto')), 40, 2);
        $nameY = 778.0;
        foreach ($nameLines as $line) {
            $this->pdf->text($titleX, $nameY, $line, 16, [255, 255, 255]);
            $nameY -= 19;
        }
        $this->pdf->text($titleX, $nameY - 2, 'Reporte gerencial para cliente y direccion', 9, [210, 225, 246]);

        $meta = [
            ['Cliente', (string) ($project['client_name'] ?? 'Sin cliente')],
            ['PM', (string) ($project['pm_name'] ?? 'No asignado')],
            ['Estado', $statusLabel],
            ['Fecha', $this->generatedAt],
        ];

        $gap = 10.0;
        $metaTop = 578.0;
        $metaH = 56.0;
        $metaW = ($this->contentWidth - $gap) / 2;
        foreach ($meta as $i => $item) {
            $label = (string) ($item[0] ?? '');
            $value = (string) ($item[1] ?? '-');
            $row = intdiv($i, 2);
            $col = $i % 2;
            $x = $this->margin + ($col * ($metaW + $gap));
            $top = $metaTop - ($row * ($metaH + 7));
            $this->drawPanel($x, $top, $metaW, $metaH, [255, 255, 255], [219, 228, 243]);
            $this->pdf->text($x + 12, $top - 18, $this->strUpper($label), 7.2, [93, 107, 132]);

            if ($label === 'Estado') {
                $this->drawStatusBadge($x + 12, $top - 39, $this->safeTrim($value, 30), $statusTone);
                continue;
            }

            $valueLines = $this->wrapText($this->safeText($value), 40, 2);
            $valueY = $top - 34;
            foreach ($valueLines as $line) {
                $this->pdf->text($x + 12, $valueY, $line, 8.6, [31, 46, 73]);
                $valueY -= 10;
            }
        }

        $this->cursorY = 406;
    }

    private function drawExecutiveSummary(array $d): void
    {
        $project = (array) ($d['project'] ?? []);
        $health = (array) ($d['health'] ?? []);
        $billingSummary = (array) ($d['billing_summary'] ?? []);

        $currency = (string) ($billingSummary['currency_code'] ?? ($project['currency_code'] ?? 'USD'));
        $score = (int) ($health['total_score'] ?? 0);
        $progress = (float) ($project['progress'] ?? 0);
        $balance = (float) ($billingSummary['balance_to_invoice'] ?? 0);

        $this->drawSectionTitle('Resumen ejecutivo', 'Vista consolidada de rendimiento, alcance y presupuesto');

        $cards = [
            ['Score de salud', $score . ' / 100', $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger')],
            ['Avance', number_format($progress, 1) . '%', $progress >= 75 ? 'success' : ($progress >= 45 ? 'warning' : 'danger')],
            ['Presupuesto', $this->fmtMoney((float) ($project['budget'] ?? 0), (string) ($project['currency_code'] ?? 'USD')), 'primary'],
            ['Costo actual', $this->fmtMoney((float) ($project['actual_cost'] ?? 0), (string) ($project['currency_code'] ?? 'USD')), 'warning'],
            ['Saldo por facturar', $this->fmtMoney($balance, $currency), $balance >= 0 ? 'success' : 'danger'],
        ];

        $this->drawKpiCards($cards);
    }

    private function drawBilling(array $d): void
    {
        $summary = (array) ($d['billing_summary'] ?? []);
        $currency = (string) ($summary['currency_code'] ?? 'USD');

        $this->drawSectionTitle('Gestion financiera', 'Contrato, facturacion y plan de cobro');

        $summaryCards = [
            ['Contrato total', $this->fmtMoney((float) ($summary['total_contract'] ?? 0), $currency)],
            ['Facturado', $this->fmtMoney((float) ($summary['total_invoiced'] ?? 0), $currency)],
            ['Saldo por facturar', $this->fmtMoney((float) ($summary['balance_to_invoice'] ?? 0), $currency)],
        ];
        $this->drawSummaryStrip($summaryCards);

        $billingPlanRows = array_map(function (array $item): array {
            $status = (string) ($item['status'] ?? 'pendiente');
            $statusLabel = match ($status) {
                'atrasado' => 'Vencido',
                'proximo' => 'Proximo',
                default => 'Al dia',
            };

            return [
                $this->safeText((string) ($item['concept'] ?? '-')),
                $this->fmtDate($item['expected_date'] ?? null),
                ['type' => 'badge', 'text' => $statusLabel, 'tone' => $this->statusTone($statusLabel)],
            ];
        }, array_slice((array) ($d['billing_plan'] ?? []), 0, 10));

        $this->drawTableCard(
            'Plan de facturacion',
            ['Concepto', 'Fecha esperada', 'Estado'],
            $billingPlanRows,
            [287, 120, 96],
            8,
            'Sin items de facturacion registrados.',
            ['left', 'center', 'center'],
            [0 => 2, 1 => 1, 2 => 1]
        );

        $invoiceRows = array_map(function (array $inv) use ($currency): array {
            return [
                $this->safeText((string) ($inv['invoice_number'] ?? '#')),
                $this->fmtDate($inv['issued_at'] ?? null),
                $this->fmtMoney((float) ($inv['amount'] ?? 0), (string) ($inv['currency_code'] ?? $currency)),
            ];
        }, array_slice((array) ($d['invoices'] ?? []), 0, 12));

        $this->drawTableCard(
            'Facturas emitidas',
            ['Factura', 'Fecha', 'Monto'],
            $invoiceRows,
            [166, 112, 225],
            8,
            'No hay facturas emitidas en el periodo.',
            ['left', 'center', 'right']
        );
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
        $tone = $value >= $target ? 'success' : ($value >= ($target - 10) ? 'warning' : 'danger');
        $palette = $this->tonePalette($tone);

        $this->drawSectionTitle('Cumplimiento de requisitos', 'Indicadores de calidad documental y aprobaciones');

        $panelH = 146.0;
        $this->ensureSpace($panelH + 20);
        $this->drawElevatedPanel($this->margin, $this->cursorY, $this->contentWidth, $panelH, [255, 255, 255], [214, 223, 236]);

        $leftX = $this->margin + 14;
        $top = $this->cursorY - 20;
        $this->pdf->text($leftX, $top, 'Cumplimiento actual', 10, [67, 84, 115]);
        $this->pdf->text($leftX, $top - 26, number_format($value, 1) . '%', 23, [22, 39, 67]);
        $this->drawStatusBadge($leftX + 144, $top - 30, 'Meta ' . number_format($target, 1) . '%', $tone);

        $barX = $leftX;
        $barY = $top - 64;
        $barW = 300.0;
        $barH = 14.0;
        $this->pdf->fillRect($barX, $barY, $barW, $barH, [231, 237, 248]);
        $fill = max(0.0, min(100.0, $value));
        $this->pdf->fillRect($barX, $barY, $barW * ($fill / 100), $barH, $palette[0]);
        $this->pdf->text($barX, $barY - 14, '0%', 8, [95, 109, 133]);
        $this->pdf->text($barX + $barW - 20, $barY - 14, '100%', 8, [95, 109, 133]);

        $statsX = $this->margin + 344;
        $statsW = 146.0;
        $this->drawMiniStat($statsX, $this->cursorY - 22, $statsW, 'Total', (string) count($requirements));
        $this->drawMiniStat($statsX, $this->cursorY - 50, $statsW, 'Aprobados', (string) $counts['aprobado']);
        $this->drawMiniStat($statsX, $this->cursorY - 78, $statsW, 'En revision', (string) $counts['en_revision']);
        $this->drawMiniStat($statsX, $this->cursorY - 106, $statsW, 'Rechazados', (string) $counts['rechazado']);

        $this->cursorY -= ($panelH + 14);
    }

    private function drawBlockers(array $d): void
    {
        $rows = array_map(function (array $s): array {
            return [
                $this->safeText((string) ($s['title'] ?? 'Bloqueo')),
                $this->fmtDate($s['detected_at'] ?? null),
                $this->safeText(trim((string) ($s['description'] ?? 'Sin descripcion'))),
            ];
        }, array_slice((array) ($d['stoppers_open'] ?? []), 0, 8));

        $this->drawSectionTitle('Bloqueos activos', 'Impedimentos operativos priorizados para decision');
        $this->drawTableCard(
            'Bloqueos',
            ['Bloqueo', 'Detectado', 'Detalle'],
            $rows,
            [190, 95, 218],
            7,
            'No se registran bloqueos activos.',
            ['left', 'center', 'left'],
            [0 => 2, 2 => 2]
        );
    }

    private function drawAlerts(array $d): void
    {
        $alerts = array_slice((array) ($d['alerts'] ?? []), 0, 6);

        $this->drawSectionTitle('Riesgos y alertas', 'Monitoreo ejecutivo de eventos criticos');

        if ($alerts === []) {
            $this->ensureSpace(62);
            $this->drawPanel($this->margin, $this->cursorY, $this->contentWidth, 54, [249, 252, 255], [214, 223, 236]);
            $this->pdf->text($this->margin + 16, $this->cursorY - 30, 'No se registran alertas activas.', 10, [72, 87, 116]);
            $this->cursorY -= 64;
            return;
        }

        foreach ($alerts as $alert) {
            $severity = strtolower((string) ($alert['severity'] ?? 'info'));
            $tone = match ($severity) {
                'critical', 'high' => 'danger',
                'medium' => 'warning',
                default => 'success',
            };

            $titleLines = $this->wrapText($this->safeText((string) ($alert['title'] ?? 'Alerta')), 70, 2);
            $msgLines = $this->wrapText($this->safeText((string) ($alert['message'] ?? 'Sin detalle')), 106, 2);
            $lineCount = count($titleLines) + count($msgLines);
            $cardH = max(60.0, 20.0 + ($lineCount * 9.0));
            $this->ensureSpace($cardH + 8);

            $this->drawPanel($this->margin, $this->cursorY, $this->contentWidth, $cardH, [255, 255, 255], [214, 223, 236]);
            $this->drawStatusBadge($this->margin + 12, $this->cursorY - 26, $this->strUpper($severity), $tone);

            $textX = $this->margin + 118;
            $textY = $this->cursorY - 20;
            foreach ($titleLines as $line) {
                $this->pdf->text($textX, $textY, $line, 9.6, [31, 47, 76]);
                $textY -= 10.5;
            }
            $textY -= 2;
            foreach ($msgLines as $line) {
                $this->pdf->text($textX, $textY, $line, 8.4, [84, 95, 116]);
                $textY -= 9;
            }

            $this->cursorY -= ($cardH + 6);
        }
    }

    private function drawSectionTitle(string $title, string $subtitle = ''): void
    {
        $this->ensureSpace(44);
        $this->sectionIndex++;
        $idx = str_pad((string) $this->sectionIndex, 2, '0', STR_PAD_LEFT);

        $lineY = $this->cursorY + 6;
        $this->pdf->fillRect($this->margin, $lineY, $this->contentWidth, 1, [219, 228, 243]);
        $this->pdf->fillRect($this->margin, $lineY - 1, 38, 3, $this->brand['primary']);
        $this->pdf->text($this->margin, $this->cursorY - 8, $idx, 8.6, [83, 103, 133]);
        $this->pdf->text($this->margin + 24, $this->cursorY - 8, $this->strUpper($title), 11.2, [22, 39, 67]);
        if ($subtitle !== '') {
            $this->pdf->text($this->margin + 24, $this->cursorY - 21, $subtitle, 8.1, [99, 114, 141]);
        }
        $this->cursorY -= 28;
    }

    private function drawKpiCards(array $cards): void
    {
        $this->ensureSpace(126);
        $gap = 8.0;
        $w3 = ($this->contentWidth - (2 * $gap)) / 3;
        $h = 58.0;

        for ($i = 0; $i < 3 && isset($cards[$i]); $i++) {
            $x = $this->margin + ($i * ($w3 + $gap));
            $this->drawMetricCard($x, $this->cursorY, $w3, $h, $cards[$i]);
        }

        $row2Top = $this->cursorY - ($h + 8);
        $w2 = ($this->contentWidth - $gap) / 2;
        for ($i = 3; $i < 5 && isset($cards[$i]); $i++) {
            $x = $this->margin + (($i - 3) * ($w2 + $gap));
            $this->drawMetricCard($x, $row2Top, $w2, $h, $cards[$i]);
        }

        $this->cursorY -= 122;
    }

    private function drawMetricCard(float $x, float $top, float $w, float $h, array $card): void
    {
        [$label, $value, $tone] = $card;
        $palette = $this->tonePalette((string) $tone);

        $this->drawPanel($x, $top, $w, $h, [255, 255, 255], [214, 223, 236]);
        $this->pdf->fillRect($x + 1, $top - 5, $w - 2, 5, $palette[0]);
        $this->pdf->text($x + 10, $top - 18, $this->strUpper($this->safeTrim((string) $label, 34)), 7.2, [95, 109, 133]);
        $valueLines = $this->wrapText($this->safeText((string) $value), 26, 2);
        $valueY = $top - 37;
        foreach ($valueLines as $line) {
            $this->pdf->text($x + 10, $valueY, $line, 11, [27, 44, 74]);
            $valueY -= 11;
        }
    }

    private function drawSummaryStrip(array $items): void
    {
        $this->ensureSpace(58);
        $gap = 8.0;
        $w = ($this->contentWidth - (2 * $gap)) / 3;
        $h = 42.0;
        foreach ($items as $idx => $item) {
            $x = $this->margin + ($idx * ($w + $gap));
            $this->drawPanel($x, $this->cursorY, $w, $h, [250, 252, 255], [214, 223, 236]);
            $this->pdf->text($x + 10, $this->cursorY - 14, $this->strUpper($this->safeTrim((string) ($item[0] ?? ''), 28)), 6.9, [95, 109, 133]);
            $valueLines = $this->wrapText($this->safeText((string) ($item[1] ?? '-')), 30, 1);
            $textY = $this->cursorY - 27;
            foreach ($valueLines as $line) {
                $this->pdf->text($x + 10, $textY, $line, 9.2, [25, 42, 70]);
                $textY -= 10;
            }
        }
        $this->cursorY -= 50;
    }

    private function drawTableCard(
        string $title,
        array $headers,
        array $rows,
        array $widths,
        int $maxRows = 8,
        string $emptyMessage = 'Sin datos disponibles.',
        array $aligns = [],
        array $lineLimits = []
    ): void {
        $slice = array_slice($rows, 0, $maxRows);
        if ($slice === []) {
            $slice[] = [$emptyMessage, '', ''];
        }

        $innerWidth = $this->contentWidth - 20;
        $totalWidth = array_sum($widths);
        if ($totalWidth > 0 && abs($totalWidth - $innerWidth) > 0.5) {
            $ratio = $innerWidth / $totalWidth;
            foreach ($widths as $idx => $width) {
                $widths[$idx] = (float) $width * $ratio;
            }
        }

        $rowPaddingTop = 5.0;
        $rowPaddingBottom = 5.0;
        $lineHeight = 8.2;
        $headerH = 20.0;
        $titleH = 24.0;
        $preparedRows = [];
        $rowsHeight = 0.0;

        foreach ($slice as $row) {
            $preparedCells = [];
            $maxContentH = 14.0;
            foreach ($widths as $col => $width) {
                $cell = $row[$col] ?? '';
                if (is_array($cell) && (($cell['type'] ?? '') === 'badge')) {
                    $preparedCells[$col] = ['type' => 'badge', 'data' => $cell];
                    $maxContentH = max($maxContentH, 18.0);
                    continue;
                }

                $maxChars = max(8, (int) floor(($width - 12) / 4.15));
                $maxLines = max(1, (int) ($lineLimits[$col] ?? 1));
                $lines = $this->wrapText($this->safeText((string) $cell), $maxChars, $maxLines);
                $preparedCells[$col] = ['type' => 'text', 'lines' => $lines];
                $maxContentH = max($maxContentH, count($lines) * $lineHeight);
            }

            $rowH = $rowPaddingTop + $maxContentH + $rowPaddingBottom;
            $preparedRows[] = ['cells' => $preparedCells, 'height' => $rowH];
            $rowsHeight += $rowH;
        }

        $panelH = $titleH + $headerH + $rowsHeight + 8;
        $this->ensureSpace($panelH + 6);
        $this->drawPanel($this->margin, $this->cursorY, $this->contentWidth, $panelH, [255, 255, 255], [214, 223, 236]);

        $this->pdf->text($this->margin + 10, $this->cursorY - 15, $title, 8.8, [42, 60, 91]);

        $tableTop = $this->cursorY - $titleH;
        $this->pdf->fillRect($this->margin + 1, $tableTop - $headerH, $this->contentWidth - 2, $headerH, $this->brand['secondary']);

        $x = $this->margin + 10;
        foreach ($headers as $idx => $header) {
            $width = (float) ($widths[$idx] ?? 100.0);
            $align = (string) ($aligns[$idx] ?? 'left');
            $headerText = $this->strUpper((string) $header);
            $this->drawAlignedText($x, $tableTop - 13, $width, $headerText, 7.0, [255, 255, 255], $align);
            $x += $width;
        }

        $rowTop = $tableTop - $headerH;
        foreach ($preparedRows as $i => $rowData) {
            $rowH = (float) ($rowData['height'] ?? 24.0);
            $bg = $i % 2 === 0 ? [255, 255, 255] : [248, 251, 255];
            $this->pdf->fillRect($this->margin + 1, $rowTop - $rowH, $this->contentWidth - 2, $rowH, $bg);
            $this->pdf->fillRect($this->margin + 1, $rowTop - $rowH, $this->contentWidth - 2, 1, [231, 236, 246]);

            $x = $this->margin + 10;
            foreach ($widths as $col => $width) {
                $cellData = $rowData['cells'][$col] ?? ['type' => 'text', 'lines' => ['-']];
                if (($cellData['type'] ?? '') === 'badge') {
                    $badgeY = $rowTop - (($rowH - 14) / 2);
                    $badge = (array) ($cellData['data'] ?? []);
                    $this->drawStatusBadge(
                        $x + 4,
                        $badgeY,
                        $this->safeTrim((string) ($badge['text'] ?? ''), 16),
                        (string) ($badge['tone'] ?? 'neutral')
                    );
                } else {
                    $align = (string) ($aligns[$col] ?? 'left');
                    $lines = (array) ($cellData['lines'] ?? ['']);
                    $textY = $rowTop - $rowPaddingTop - 5;
                    foreach ($lines as $line) {
                        $this->drawAlignedText($x, $textY, (float) $width, $line, 7.8, [48, 62, 88], $align);
                        $textY -= $lineHeight;
                    }
                }
                $x += (float) $width;
            }
            $rowTop -= $rowH;
        }

        $this->cursorY -= ($panelH + 6);
    }

    private function drawMiniStat(float $x, float $top, float $w, string $label, string $value): void
    {
        $this->drawPanel($x, $top, $w, 22, [248, 251, 255], [214, 223, 236]);
        $this->pdf->text($x + 8, $top - 14, $this->safeTrim($label, 16), 7.4, [95, 109, 133]);
        $this->drawAlignedText($x + 66, $top - 14, $w - 74, $this->safeTrim($value, 10), 8.2, [26, 43, 72], 'right');
    }

    private function drawElevatedPanel(float $x, float $top, float $w, float $h, array $bg, array $border): void
    {
        $shadow = $this->mix([230, 237, 248], [255, 255, 255], 0.55);
        $this->pdf->fillRect($x + 1, $top - $h - 1, $w, $h, $shadow);
        $this->drawPanel($x, $top, $w, $h, $bg, $border);
    }

    private function drawPanel(float $x, float $top, float $w, float $h, array $bg, array $border): void
    {
        $this->pdf->fillRect($x, $top - $h, $w, $h, $border);
        if ($w > 2 && $h > 2) {
            $this->pdf->fillRect($x + 1, $top - $h + 1, $w - 2, $h - 2, $bg);
        }
    }

    private function drawStatusBadge(float $x, float $y, string $text, string $tone): void
    {
        $palette = $this->tonePalette($tone);
        $safe = $this->strUpper($this->safeTrim($text, 16));
        $w = max(54.0, (float) (strlen($safe) * 4.8) + 16.0);
        $this->drawPanel($x, $y + 14, $w, 16, $palette[0], $palette[0]);
        $this->pdf->text($x + 6, $y + 3, $safe, 8, $palette[1]);
    }

    private function tonePalette(string $tone): array
    {
        return match ($tone) {
            'success' => [[220, 247, 229], [17, 112, 61]],
            'warning' => [[255, 242, 207], [145, 95, 0]],
            'danger' => [[255, 226, 226], [159, 36, 36]],
            'primary' => [
                $this->mix($this->brand['primary'], [255, 255, 255], 0.78),
                $this->mix($this->brand['primary'], [15, 23, 42], 0.15),
            ],
            default => [[234, 239, 247], [60, 76, 108]],
        };
    }

    private function mix(array $rgbA, array $rgbB, float $ratio): array
    {
        $r = max(0.0, min(1.0, $ratio));
        return [
            (int) round(($rgbA[0] * (1 - $r)) + ($rgbB[0] * $r)),
            (int) round(($rgbA[1] * (1 - $r)) + ($rgbB[1] * $r)),
            (int) round(($rgbA[2] * (1 - $r)) + ($rgbB[2] * $r)),
        ];
    }

    private function ensureSpace(float $needed): void
    {
        if ($this->cursorY - $needed > 50) {
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
            'on_hold', 'at_risk', 'en_riesgo' => 'En riesgo',
            'closed', 'completado', 'finalizado' => 'Completado',
            'execution', 'en_curso', 'active' => 'En curso',
            'planning', 'planeacion' => 'Planeacion',
            default => ucfirst($status ?: 'Sin definir'),
        };
    }

    private function statusTone(string $value): string
    {
        $norm = strtolower(trim($value));
        return match ($norm) {
            'al dia', 'aprobado', 'completado', 'en curso', 'low', 'info', 'success' => 'success',
            'proximo', 'en revision', 'en riesgo', 'medium', 'warning' => 'warning',
            'vencido', 'rechazado', 'critical', 'high', 'danger' => 'danger',
            default => 'primary',
        };
    }

    private function decoratePages(): void
    {
        $total = $this->pdf->pageCount();
        for ($i = 1; $i < $total; $i++) {
            $page = $i + 1;
            $header = '';
            $header .= sprintf(
                '%.3F %.3F %.3F rg 0 814 595 28 re f',
                $this->brand['secondary'][0] / 255,
                $this->brand['secondary'][1] / 255,
                $this->brand['secondary'][2] / 255
            ) . "\n";
            $header .= sprintf(
                '%.3F %.3F %.3F rg 0 812 595 2 re f',
                $this->brand['primary'][0] / 255,
                $this->brand['primary'][1] / 255,
                $this->brand['primary'][2] / 255
            ) . "\n";
            $logoDrawn = false;
            if (is_string($this->logoPath) && $this->logoPath !== '') {
                $img = $this->pdf->buildImageCommand($this->logoPath, $this->margin, 818, 68, 20);
                if ($img !== null) {
                    $header .= $img . "\n";
                    $logoDrawn = true;
                }
            }

            if (!$logoDrawn) {
                $header .= $this->pdf->buildText($this->margin, 825, 'AOS', 8.6, [245, 248, 255]);
            }

            $projectLines = $this->wrapText($this->safeText($this->projectName), 86, 2);
            $header .= $this->pdf->buildText($this->margin + 74, 829, (string) ($projectLines[0] ?? ''), 7.6, [245, 248, 255]);
            if (isset($projectLines[1])) {
                $header .= $this->pdf->buildText($this->margin + 74, 820, (string) $projectLines[1], 7.6, [223, 235, 255]);
            }
            $header .= $this->pdf->buildText(508, 825, 'Pag ' . $page, 9, [245, 248, 255]);
            $this->pdf->prependToPage($i, $header);

            $footer = '';
            $footer .= sprintf('%.3F %.3F %.3F rg 36 34 523 1 re f', 212 / 255, 220 / 255, 234 / 255) . "\n";
            $footer .= $this->pdf->buildText($this->margin, 20, 'Informe ejecutivo corporativo', 8, [99, 111, 134]);
            $footer .= $this->pdf->buildText(440, 20, $this->safeTrim($this->generatedAt, 26), 8, [99, 111, 134]);
            $this->pdf->appendToPage($i, $footer);
        }
    }

    private function safeTrim(string $value, int $limit): string
    {
        $clean = $this->safeText($value);
        return $this->strTrimWidth($clean, $limit);
    }

    private function safeText(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return $value;
    }

    private function wrapText(string $text, int $maxChars, int $maxLines = 2): array
    {
        $clean = $this->safeText($text);
        if ($clean === '') {
            return ['-'];
        }

        $words = preg_split('/\s+/', $clean) ?: [$clean];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $word = (string) $word;
            if ($word === '') {
                continue;
            }

            $candidate = $current === '' ? $word : ($current . ' ' . $word);
            if ($this->strLen($candidate) <= $maxChars) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                if (count($lines) >= $maxLines) {
                    return $lines;
                }
                $current = '';
            }

            if ($this->strLen($word) <= $maxChars) {
                $current = $word;
                continue;
            }

            $offset = 0;
            while ($offset < $this->strLen($word)) {
                $lines[] = $this->strSubstr($word, $offset, $maxChars);
                if (count($lines) >= $maxLines) {
                    return $lines;
                }
                $offset += $maxChars;
            }
        }

        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }

        return $lines !== [] ? $lines : ['-'];
    }

    private function drawAlignedText(float $x, float $y, float $width, string $text, float $fontSize, array $rgb, string $align): void
    {
        $safe = $this->safeText($text);
        $textW = (float) $this->strLen($safe) * $fontSize * 0.42;
        $posX = $x + 4;
        if ($align === 'center') {
            $posX = $x + max(4.0, ($width - $textW) / 2);
        } elseif ($align === 'right') {
            $posX = $x + max(4.0, $width - $textW - 4);
        }
        $this->pdf->text($posX, $y, $safe, $fontSize, $rgb);
    }

    private function strUpper(string $text): string
    {
        if (function_exists('mb_strtoupper')) {
            return (string) mb_strtoupper($text, 'UTF-8');
        }
        return strtoupper($text);
    }

    private function strLen(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($text, 'UTF-8');
        }
        return strlen($text);
    }

    private function strSubstr(string $text, int $start, int $length): string
    {
        if (function_exists('mb_substr')) {
            return (string) mb_substr($text, $start, $length, 'UTF-8');
        }
        return substr($text, $start, $length);
    }

    private function strTrimWidth(string $text, int $limit): string
    {
        if ($limit <= 0) {
            return '';
        }
        if (function_exists('mb_strimwidth')) {
            return (string) mb_strimwidth($text, 0, $limit, '', 'UTF-8');
        }
        return substr($text, 0, $limit);
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
        $command = $this->buildImageCommand($path, $x, $y, $w, $h);
        if ($command === null) {
            return;
        }
        $this->content($command);
    }

    public function buildImageCommand(string $path, float $x, float $y, float $w, float $h): ?string
    {
        $name = $this->registerImage($path);
        if ($name === null) {
            return null;
        }
        return sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q', $w, $h, $x, $y, $name);
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

    private function registerImage(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $jpegData = $this->toJpegData($path);
        if ($jpegData === null) {
            return null;
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
        return $name;
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
        if (!function_exists('imagejpeg')) {
            return null;
        }

        $img = false;
        if (in_array($mime, ['image/jpeg', 'image/jpg'], true) && function_exists('imagecreatefromjpeg')) {
            $img = @imagecreatefromjpeg($path);
        } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
            $img = @imagecreatefrompng($path);
        } elseif ($mime === 'image/gif' && function_exists('imagecreatefromgif')) {
            $img = @imagecreatefromgif($path);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $img = @imagecreatefromwebp($path);
        }

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
