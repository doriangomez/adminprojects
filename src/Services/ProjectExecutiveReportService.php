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
    private float $margin = 28.0;
    private float $contentWidth = 539.0;
    private string $projectName = 'Proyecto';
    private string $generatedAt = '';

    public function __construct()
    {
        $this->pdf = new CorporatePdf();
    }

    public function render(array $d): string
    {
        return $this->renderLegacy($d);
    }

    private function renderLegacy(array $d): string
    {
        $this->projectName = (string) (($d['project'] ?? [])['name'] ?? 'Proyecto');
        $this->generatedAt = (string) ($d['generated_at'] ?? date('d/m/Y H:i'));

        $this->pdf->addPage();
        $this->cursorY = 804;

        $this->drawCover($d);
        $this->drawExecutiveSummary($d);
        $this->drawBilling($d);
        $this->drawRequirements($d);
        $this->drawBlockers($d);
        $this->drawAlerts($d);

        $this->decoratePages();
        return $this->pdf->output();
    }

    private function drawCover(array $d): void
    {
        $project = (array) ($d['project'] ?? []);
        $statusLabel = $this->projectStatusLabel((string) ($project['status'] ?? ''));
        $statusTone = $this->statusTone($statusLabel);
        $logoPath = $d['logo_path'] ?? null;

        $heroTop = 842.0;
        $heroHeight = 250.0;
        $heroBottom = $heroTop - $heroHeight;

        $this->pdf->fillRect(0, $heroBottom, 595, $heroHeight, [22, 40, 74]);
        $this->pdf->fillRect(0, $heroBottom + 8, 595, 8, [86, 166, 255]);

        if (is_string($logoPath) && $logoPath !== '') {
            $this->pdf->drawImage($logoPath, $this->margin, 762, 98, 54);
        } else {
            $this->drawPanel($this->margin, 816, 98, 54, [255, 255, 255], [255, 255, 255]);
            $this->pdf->text($this->margin + 33, 790, 'AOS', 16, [22, 40, 74]);
        }

        $this->pdf->text($this->margin + 118, 802, 'INFORME EJECUTIVO', 12, [181, 215, 255]);
        $this->pdf->text($this->margin + 118, 776, mb_strtoupper($this->safeText((string) ($project['name'] ?? 'Proyecto'))), 19, [255, 255, 255]);
        $this->pdf->text($this->margin + 118, 750, 'Dashboard gerencial de proyecto', 10, [204, 219, 239]);

        $meta = [
            ['Cliente', (string) ($project['client_name'] ?? 'Sin cliente')],
            ['PM', (string) ($project['pm_name'] ?? 'No asignado')],
            ['Estado', $statusLabel],
            ['Fecha', $this->generatedAt],
        ];

        $gap = 10.0;
        $metaTop = 700.0;
        $metaH = 72.0;
        $metaW = ($this->contentWidth - ($gap * 3)) / 4;
        foreach ($meta as $i => [$label, $value]) {
            $x = $this->margin + ($i * ($metaW + $gap));
            $this->drawPanel($x, $metaTop, $metaW, $metaH, [247, 250, 255], [203, 217, 237]);
            $this->pdf->text($x + 12, $metaTop - 20, mb_strtoupper($label), 8, [92, 109, 137]);
            if ($label === 'Estado') {
                $this->drawStatusBadge($x + 12, $metaTop - 52, $this->safeTrim($value, 20), $statusTone);
            } else {
                $this->pdf->text($x + 12, $metaTop - 46, $this->safeTrim($value, 34), 10, [29, 45, 72]);
            }
        }

        $this->cursorY = 610;
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

        $this->drawSectionTitle('RESUMEN EJECUTIVO', 'Vista consolidada para decision directiva');

        $cards = [
            ['Score', $score . ' / 100', 'SC', $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger')],
            ['Avance', number_format($progress, 1) . '%', 'AV', $progress >= 75 ? 'success' : ($progress >= 45 ? 'warning' : 'danger')],
            ['Presupuesto', $this->fmtMoney((float) ($project['budget'] ?? 0), (string) ($project['currency_code'] ?? 'USD')), 'BG', 'primary'],
            ['Costo actual', $this->fmtMoney((float) ($project['actual_cost'] ?? 0), (string) ($project['currency_code'] ?? 'USD')), 'CT', 'warning'],
            ['Saldo', $this->fmtMoney($balance, $currency), 'SL', $balance >= 0 ? 'success' : 'danger'],
        ];

        $this->drawKpiCards($cards);
    }

    private function drawBilling(array $d): void
    {
        $summary = (array) ($d['billing_summary'] ?? []);
        $currency = (string) ($summary['currency_code'] ?? 'USD');

        $this->drawSectionTitle('FACTURACION', 'Bloques financieros y plan de cobro');

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
                $this->safeTrim((string) ($item['concept'] ?? '-'), 48),
                $this->fmtDate($item['expected_date'] ?? null),
                ['type' => 'badge', 'text' => $statusLabel, 'tone' => $this->statusTone($statusLabel)],
            ];
        }, array_slice((array) ($d['billing_plan'] ?? []), 0, 10));

        $this->drawTableCard(
            'Items del plan de facturacion',
            ['Concepto', 'Fecha esperada', 'Estado'],
            $billingPlanRows,
            [300, 120, 119],
            8,
            'Sin items de facturacion registrados.'
        );

        $invoiceRows = array_map(function (array $inv) use ($currency): array {
            return [
                $this->safeTrim((string) ($inv['invoice_number'] ?? '#'), 24),
                $this->fmtDate($inv['issued_at'] ?? null),
                $this->fmtMoney((float) ($inv['amount'] ?? 0), (string) ($inv['currency_code'] ?? $currency)),
            ];
        }, array_slice((array) ($d['invoices'] ?? []), 0, 12));

        $this->drawTableCard(
            'Facturas emitidas',
            ['Factura', 'Fecha', 'Monto'],
            $invoiceRows,
            [180, 130, 229],
            8,
            'No hay facturas emitidas en el periodo.'
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

        $this->drawSectionTitle('CUMPLIMIENTO', 'Visualizacion grafica de requisitos');

        $panelH = 150.0;
        $this->ensureSpace($panelH + 14);
        $this->drawPanel($this->margin, $this->cursorY, $this->contentWidth, $panelH, [255, 255, 255], [214, 223, 236]);

        $leftX = $this->margin + 16;
        $top = $this->cursorY - 20;
        $this->pdf->text($leftX, $top, 'Cumplimiento actual', 10, [72, 87, 116]);
        $this->pdf->text($leftX, $top - 24, number_format($value, 1) . '%', 24, [30, 50, 84]);
        $this->drawStatusBadge($leftX + 140, $top - 30, 'Meta ' . number_format($target, 1) . '%', $tone);

        $barX = $leftX;
        $barY = $top - 62;
        $barW = 340.0;
        $barH = 16.0;
        $this->pdf->fillRect($barX, $barY, $barW, $barH, [231, 236, 244]);
        $fill = max(0.0, min(100.0, $value));
        $this->pdf->fillRect($barX, $barY, $barW * ($fill / 100), $barH, $palette[0]);
        $this->pdf->text($barX, $barY - 16, '0%', 8, [90, 103, 128]);
        $this->pdf->text($barX + $barW - 18, $barY - 16, '100%', 8, [90, 103, 128]);

        $statsX = $this->margin + 382;
        $statsW = 140.0;
        $this->drawMiniStat($statsX, $this->cursorY - 22, $statsW, 'Total', (string) count($requirements));
        $this->drawMiniStat($statsX, $this->cursorY - 52, $statsW, 'Aprobados', (string) $counts['aprobado']);
        $this->drawMiniStat($statsX, $this->cursorY - 82, $statsW, 'En revision', (string) $counts['en_revision']);
        $this->drawMiniStat($statsX, $this->cursorY - 112, $statsW, 'Rechazados', (string) $counts['rechazado']);

        $this->cursorY -= ($panelH + 16);
    }

    private function drawBlockers(array $d): void
    {
        $rows = array_map(function (array $s): array {
            return [
                $this->safeTrim((string) ($s['title'] ?? 'Bloqueo'), 36),
                $this->fmtDate($s['detected_at'] ?? null),
                $this->safeTrim(trim((string) ($s['description'] ?? 'Sin descripcion')), 68),
            ];
        }, array_slice((array) ($d['stoppers_open'] ?? []), 0, 8));

        $this->drawSectionTitle('BLOQUEOS ACTIVOS', 'Impedimentos operativos con seguimiento');
        $this->drawTableCard(
            'Bloqueos',
            ['Bloqueo', 'Detectado', 'Detalle'],
            $rows,
            [220, 100, 219],
            7,
            'No se registran bloqueos activos.'
        );
    }

    private function drawAlerts(array $d): void
    {
        $alerts = array_slice((array) ($d['alerts'] ?? []), 0, 6);

        $this->drawSectionTitle('RIESGOS Y ALERTAS', 'Tarjetas ejecutivas para priorizacion');

        if (count($alerts) === 0) {
            $this->ensureSpace(72);
            $this->drawPanel($this->margin, $this->cursorY, $this->contentWidth, 64, [248, 251, 255], [214, 223, 236]);
            $this->pdf->text($this->margin + 18, $this->cursorY - 36, 'No se registran alertas activas.', 11, [72, 87, 116]);
            $this->cursorY -= 78;
            return;
        }

        foreach ($alerts as $alert) {
            $this->ensureSpace(76);
            $severity = strtolower((string) ($alert['severity'] ?? 'info'));
            $tone = match ($severity) {
                'critical', 'high' => 'danger',
                'medium' => 'warning',
                default => 'success',
            };

            $this->drawPanel($this->margin, $this->cursorY, $this->contentWidth, 66, [255, 255, 255], [214, 223, 236]);
            $this->drawStatusBadge($this->margin + 14, $this->cursorY - 30, strtoupper($severity), $tone);
            $this->pdf->text($this->margin + 128, $this->cursorY - 22, $this->safeTrim((string) ($alert['title'] ?? 'Alerta'), 58), 10, [30, 50, 84]);
            $this->pdf->text($this->margin + 128, $this->cursorY - 44, $this->safeTrim(trim((string) ($alert['message'] ?? 'Sin detalle')), 110), 9, [84, 95, 116]);
            $this->cursorY -= 74;
        }
    }

    private function drawSectionTitle(string $title, string $subtitle = ''): void
    {
        $this->ensureSpace(54);
        $this->pdf->fillRect($this->margin, $this->cursorY - 10, 6, 26, [86, 166, 255]);
        $this->pdf->text($this->margin + 12, $this->cursorY, mb_strtoupper($title), 13, [20, 37, 68]);
        if ($subtitle !== '') {
            $this->pdf->text($this->margin + 12, $this->cursorY - 16, $subtitle, 9, [99, 114, 141]);
        }
        $this->cursorY -= 34;
    }

    private function drawKpiCards(array $cards): void
    {
        $this->ensureSpace(170);
        $gap = 10.0;

        $w3 = ($this->contentWidth - (2 * $gap)) / 3;
        $h = 72.0;
        for ($i = 0; $i < 3 && isset($cards[$i]); $i++) {
            $x = $this->margin + ($i * ($w3 + $gap));
            $this->drawMetricCard($x, $this->cursorY, $w3, $h, $cards[$i]);
        }

        $row2Top = $this->cursorY - ($h + 12);
        $w2 = ($this->contentWidth - $gap) / 2;
        for ($i = 3; $i < 5 && isset($cards[$i]); $i++) {
            $x = $this->margin + (($i - 3) * ($w2 + $gap));
            $this->drawMetricCard($x, $row2Top, $w2, $h, $cards[$i]);
        }

        $this->cursorY -= 164;
    }

    private function drawMetricCard(float $x, float $top, float $w, float $h, array $card): void
    {
        [$label, $value, $icon, $tone] = $card;
        $palette = $this->tonePalette((string) $tone);

        $this->drawPanel($x, $top, $w, $h, [255, 255, 255], [214, 223, 236]);
        $this->pdf->fillRect($x + 1, $top - 4, $w - 2, 4, $palette[0]);
        $this->drawPanel($x + $w - 36, $top - 14, 22, 18, $palette[0], $palette[0]);
        $this->pdf->text($x + $w - 31, $top - 27, $this->safeTrim((string) $icon, 4), 8, [255, 255, 255]);
        $this->pdf->text($x + 12, $top - 20, mb_strtoupper($this->safeTrim((string) $label, 26)), 8, [95, 109, 133]);
        $this->pdf->text($x + 12, $top - 46, $this->safeTrim((string) $value, 30), 13, [27, 44, 74]);
    }

    private function drawSummaryStrip(array $items): void
    {
        $this->ensureSpace(86);
        $gap = 10.0;
        $w = ($this->contentWidth - 2 * $gap) / 3;
        $h = 62.0;
        foreach ($items as $idx => $item) {
            $x = $this->margin + ($idx * ($w + $gap));
            $this->drawPanel($x, $this->cursorY, $w, $h, [248, 251, 255], [214, 223, 236]);
            $this->pdf->text($x + 12, $this->cursorY - 20, mb_strtoupper($this->safeTrim((string) ($item[0] ?? ''), 26)), 8, [95, 109, 133]);
            $this->pdf->text($x + 12, $this->cursorY - 42, $this->safeTrim((string) ($item[1] ?? '-'), 32), 11, [25, 42, 70]);
        }
        $this->cursorY -= 78;
    }

    private function drawTableCard(
        string $title,
        array $headers,
        array $rows,
        array $widths,
        int $maxRows = 8,
        string $emptyMessage = 'Sin datos disponibles.'
    ): void {
        $slice = array_slice($rows, 0, $maxRows);
        if ($slice === []) {
            $slice[] = [$emptyMessage, '', ''];
        }

        $rowH = 22.0;
        $headerH = 24.0;
        $titleH = 28.0;
        $panelH = $titleH + $headerH + (count($slice) * $rowH) + 12;

        $this->ensureSpace($panelH + 10);
        $this->drawPanel($this->margin, $this->cursorY, $this->contentWidth, $panelH, [255, 255, 255], [214, 223, 236]);

        $this->pdf->text($this->margin + 14, $this->cursorY - 18, $title, 10, [42, 60, 91]);

        $tableTop = $this->cursorY - $titleH;
        $this->pdf->fillRect($this->margin + 1, $tableTop - $headerH, $this->contentWidth - 2, $headerH, [33, 59, 95]);

        $x = $this->margin + 10;
        foreach ($headers as $idx => $header) {
            $this->pdf->text($x, $tableTop - 16, mb_strtoupper((string) $header), 8, [255, 255, 255]);
            $x += (float) ($widths[$idx] ?? 100.0);
        }

        $rowTop = $tableTop - $headerH;
        foreach ($slice as $i => $row) {
            $bg = $i % 2 === 0 ? [255, 255, 255] : [247, 250, 255];
            $this->pdf->fillRect($this->margin + 1, $rowTop - $rowH, $this->contentWidth - 2, $rowH, $bg);

            $x = $this->margin + 10;
            foreach ($widths as $col => $width) {
                $cell = $row[$col] ?? '';
                if (is_array($cell) && (($cell['type'] ?? '') === 'badge')) {
                    $this->drawStatusBadge(
                        $x,
                        $rowTop - 17,
                        $this->safeTrim((string) ($cell['text'] ?? ''), 16),
                        (string) ($cell['tone'] ?? 'neutral')
                    );
                } else {
                    $this->pdf->text($x, $rowTop - 15, $this->safeTrim((string) $cell, $col === 2 ? 44 : 30), 9, [48, 62, 88]);
                }
                $x += (float) $width;
            }
            $rowTop -= $rowH;
        }

        $this->cursorY -= ($panelH + 12);
    }

    private function drawMiniStat(float $x, float $top, float $w, string $label, string $value): void
    {
        $this->drawPanel($x, $top, $w, 24, [248, 251, 255], [214, 223, 236]);
        $this->pdf->text($x + 8, $top - 16, $this->safeTrim($label, 14), 8, [95, 109, 133]);
        $this->pdf->text($x + $w - 30, $top - 16, $this->safeTrim($value, 8), 9, [26, 43, 72]);
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
        $safe = mb_strtoupper($this->safeTrim($text, 16));
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
            'primary' => [[216, 236, 255], [31, 95, 171]],
            default => [[234, 239, 247], [60, 76, 108]],
        };
    }

    private function ensureSpace(float $needed): void
    {
        if ($this->cursorY - $needed > 52) {
            return;
        }
        $this->newPage();
    }

    private function newPage(): void
    {
        $this->pdf->addPage();
        $this->cursorY = 792;
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
            $header .= sprintf('%.3F %.3F %.3F rg 0 818 595 24 re f', 22 / 255, 40 / 255, 74 / 255) . "\n";
            $header .= sprintf('%.3F %.3F %.3F rg 0 816 595 2 re f', 86 / 255, 166 / 255, 255 / 255) . "\n";
            $header .= $this->pdf->buildText($this->margin, 826, $this->safeTrim($this->projectName, 54), 9, [245, 248, 255]);
            $header .= $this->pdf->buildText(516, 826, 'Pag ' . $page, 9, [245, 248, 255]);
            $this->pdf->prependToPage($i, $header);

            $footer = '';
            $footer .= sprintf('%.3F %.3F %.3F rg 28 34 539 1 re f', 212 / 255, 220 / 255, 234 / 255) . "\n";
            $footer .= $this->pdf->buildText($this->margin, 20, 'Informe ejecutivo corporativo', 8, [99, 111, 134]);
            $footer .= $this->pdf->buildText(448, 20, $this->safeTrim($this->generatedAt, 26), 8, [99, 111, 134]);
            $this->pdf->appendToPage($i, $footer);
        }
    }

    private function safeTrim(string $value, int $limit): string
    {
        $clean = $this->safeText($value);
        return mb_strimwidth($clean, 0, $limit, '...');
    }

    private function safeText(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return $value;
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
