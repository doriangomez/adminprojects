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
        if (class_exists(\Mpdf\Mpdf::class)) {
            return $this->renderWithMpdf($d);
        }

        if (class_exists(\Dompdf\Dompdf::class)) {
            return $this->renderWithDompdf($d);
        }

        return $this->renderLegacy($d);
    }

    private function renderLegacy(array $d): string
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

    private function renderWithDompdf(array $d): string
    {
        $project = (array) ($d['project'] ?? []);
        $this->projectName = (string) ($project['name'] ?? 'Proyecto');
        $this->generatedAt = (string) ($d['generated_at'] ?? date('d/m/Y H:i'));

        $html = $this->buildHtmlTemplate($d);

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function renderWithMpdf(array $d): string
    {
        $project = (array) ($d['project'] ?? []);
        $this->projectName = (string) ($project['name'] ?? 'Proyecto');
        $this->generatedAt = (string) ($d['generated_at'] ?? date('d/m/Y H:i'));

        $html = $this->buildHtmlTemplate($d);
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 18,
            'margin_bottom' => 16,
            'margin_left' => 12,
            'margin_right' => 12,
        ]);
        $mpdf->WriteHTML($html);
        return (string) $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    private function buildHtmlTemplate(array $d): string
    {
        $project = (array) ($d['project'] ?? []);
        $billingSummary = (array) ($d['billing_summary'] ?? []);
        $currency = (string) ($billingSummary['currency_code'] ?? ($project['currency_code'] ?? 'USD'));
        $planRows = array_slice((array) ($d['billing_plan'] ?? []), 0, 10);
        $invoiceRows = array_slice((array) ($d['invoices'] ?? []), 0, 12);
        $alertRows = array_slice((array) ($d['alerts'] ?? []), 0, 12);
        $stopperRows = array_slice((array) ($d['stoppers_open'] ?? []), 0, 10);
        $requirements = (array) ($d['requirements'] ?? []);

        $requirementsCounts = ['aprobado' => 0, 'en_revision' => 0, 'rechazado' => 0];
        foreach ($requirements as $req) {
            $status = (string) ($req['status'] ?? '');
            if (isset($requirementsCounts[$status])) {
                $requirementsCounts[$status]++;
            }
        }

        $requirementsIndicator = (array) ($d['requirements_indicator'] ?? []);
        $requirementsValue = (float) ($requirementsIndicator['value'] ?? 0.0);
        $requirementsTarget = (float) ($d['requirements_target'] ?? 95.0);

        $kpis = [
            ['Score de salud', (string) ((int) (($d['health']['total_score'] ?? 0))) . ' / 100'],
            ['Estado general', $this->projectStatusLabel((string) ($project['status'] ?? ''))],
            ['Avance', number_format((float) ($project['progress'] ?? 0), 1) . '%'],
            ['Saldo por facturar', $this->fmtMoney((float) ($billingSummary['balance_to_invoice'] ?? 0), $currency)],
            ['Presupuesto', $this->fmtMoney((float) ($project['budget'] ?? 0), (string) ($project['currency_code'] ?? 'USD'))],
            ['Costo actual', $this->fmtMoney((float) ($project['actual_cost'] ?? 0), (string) ($project['currency_code'] ?? 'USD'))],
        ];

        $billingPlanHtml = '';
        foreach ($planRows as $idx => $item) {
            $status = (string) ($item['status'] ?? 'pendiente');
            $statusLabel = match ($status) {
                'atrasado' => 'Vencido',
                'proximo' => 'Próximo',
                default => 'Al día',
            };
            $billingPlanHtml .= '<tr class="' . (($idx % 2 === 1) ? 'alt' : '') . '">'
                . '<td><span class="truncate">' . htmlspecialchars((string) ($item['concept'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</span></td>'
                . '<td>' . htmlspecialchars($this->fmtDate($item['expected_date'] ?? null), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td><span class="badge ' . $this->badgeClass($statusLabel) . '">' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</span></td>'
                . '</tr>';
        }
        if ($billingPlanHtml === '') {
            $billingPlanHtml = '<tr><td colspan="3">Sin datos disponibles.</td></tr>';
        }

        $invoiceHtml = '';
        foreach ($invoiceRows as $idx => $inv) {
            $invoiceHtml .= '<tr class="' . (($idx % 2 === 1) ? 'alt' : '') . '">'
                . '<td>' . htmlspecialchars((string) ($inv['invoice_number'] ?? '#'), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars($this->fmtDate($inv['issued_at'] ?? null), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="align-right">' . htmlspecialchars($this->fmtMoney((float) ($inv['amount'] ?? 0), (string) ($inv['currency_code'] ?? $currency)), ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }
        if ($invoiceHtml === '') {
            $invoiceHtml = '<tr><td colspan="3">Sin datos disponibles.</td></tr>';
        }

        $alertHtml = '';
        foreach ($alertRows as $idx => $a) {
            $severity = strtoupper((string) ($a['severity'] ?? 'info'));
            $severityTone = match (strtolower((string) ($a['severity'] ?? ''))) {
                'high', 'critical' => 'badge-danger',
                'medium' => 'badge-warning',
                default => 'badge-success',
            };
            $alertHtml .= '<tr class="' . (($idx % 2 === 1) ? 'alt' : '') . '">'
                . '<td><span class="badge ' . $severityTone . '">' . htmlspecialchars($severity, ENT_QUOTES, 'UTF-8') . '</span></td>'
                . '<td><span class="truncate">' . htmlspecialchars((string) ($a['title'] ?? 'Alerta'), ENT_QUOTES, 'UTF-8') . '</span></td>'
                . '<td><span class="wrap">' . htmlspecialchars(trim((string) ($a['message'] ?? 'Sin detalle')), ENT_QUOTES, 'UTF-8') . '</span></td>'
                . '</tr>';
        }
        if ($alertHtml === '') {
            $alertHtml = '<tr><td colspan="3">Sin datos disponibles.</td></tr>';
        }

        $stopperHtml = '';
        foreach ($stopperRows as $idx => $stopper) {
            $stopperHtml .= '<tr class="' . (($idx % 2 === 1) ? 'alt' : '') . '">'
                . '<td><span class="truncate">' . htmlspecialchars((string) ($stopper['title'] ?? 'Bloqueo'), ENT_QUOTES, 'UTF-8') . '</span></td>'
                . '<td>' . htmlspecialchars($this->fmtDate((string) ($stopper['detected_at'] ?? null)), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td><span class="wrap">' . htmlspecialchars(trim((string) ($stopper['description'] ?? 'Sin descripción')), ENT_QUOTES, 'UTF-8') . '</span></td>'
                . '</tr>';
        }
        if ($stopperHtml === '') {
            $stopperHtml = '<tr><td colspan="3">Sin bloqueos abiertos.</td></tr>';
        }

        $reqComplianceTone = $requirementsValue >= $requirementsTarget ? 'badge-success' : 'badge-warning';

        return '<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; margin: 0; color: #1f1f1f; font-size: 10px; }
    * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; line-height: 1.38; }
    @page { margin: 18mm 12mm 16mm 12mm; }
    .page-break { page-break-before: always; }
    .avoid-break { page-break-inside: avoid; }
    .header { position: fixed; top: -12mm; left: 0; right: 0; font-size: 9px; color: #7A7A7A; }
    .header-right { float: right; }
    .header-line { margin-top: 2mm; border-top: 1px solid #D6D6D6; }
    .footer { position: fixed; bottom: -10mm; left: 0; right: 0; border-top: 1px solid #D6D6D6; color: #8A8A8A; font-size: 9px; padding-top: 2mm; }
    .footer-right { float: right; }
    .section-title { color: #2A3150; font-size: 12px; text-transform: uppercase; font-weight: 700; margin: 14px 0 8px; letter-spacing: 0.5px; }
    .section-subtitle { color: #667085; font-size: 9px; margin: -3px 0 10px; }
    .cards { width: 100%; margin: 0 -5px 8px; }
    .cards:after { content: ""; display: table; clear: both; }
    .card { float: left; width: calc(33.333% - 10px); margin: 0 5px 10px; border: 1px solid #DFE3EC; border-radius: 8px; background: #FAFBFF; padding: 9px; min-height: 64px; }
    .card-label { color: #667085; font-size: 8px; text-transform: uppercase; letter-spacing: 0.4px; }
    .card-value { color: #111827; font-size: 14px; font-weight: 700; margin-top: 4px; word-wrap: break-word; }
    .card-value.small { font-size: 12px; }
    .dual { width: 100%; margin: 0 -5px; }
    .dual:after { content: ""; display: table; clear: both; }
    .panel { float: left; width: calc(50% - 10px); margin: 0 5px 10px; border: 1px solid #DFE3EC; border-radius: 8px; background: #FFFFFF; padding: 9px; }
    .panel h4 { margin: 0 0 7px; color: #344054; font-size: 10px; text-transform: uppercase; }
    table.report { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 9px; table-layout: fixed; border: 1px solid #E4E7EC; }
    table.report th { background: #344054; color: #fff; text-align: left; padding: 6px; border: 1px solid #344054; text-transform: uppercase; font-size: 8px; }
    table.report td { padding: 6px; border: 1px solid #E4E7EC; vertical-align: top; }
    table.report tr.alt td { background: #F9FAFB; }
    .badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 700; text-transform: uppercase; }
    .badge-success { background: #DDF8E6; color: #137C3A; }
    .badge-warning { background: #FFF2C7; color: #9E6E00; }
    .badge-danger { background: #FFDCDC; color: #A52222; }
    .align-right { text-align: right; white-space: nowrap; }
    .truncate { display: inline-block; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .wrap { white-space: normal; word-wrap: break-word; }
  </style>
</head>
<body>
  <table style="width:100%; height:297mm; background-color:#FFFFFF; margin:0; padding:0; border:none;" class="avoid-break">
    <tr>
      <td style="vertical-align:top; color:#111; padding:34px 28px;">
        <div style="font-size:26px; font-weight:700; color:#111;">AOS</div>
        <div style="text-align:center; margin-top:110px; font-size:30px; font-weight:700; color:#111;"><span class="wrap">' . htmlspecialchars((string) ($project['name'] ?? 'Proyecto'), ENT_QUOTES, 'UTF-8') . '</span></div>
        <div style="margin:14px auto 0; width:78%; border-top:1px solid #D6D6D6;"></div>
        <table style="width:100%; margin-top:36px; color:#1E1E1E; border-collapse:collapse;">
          <tr class="avoid-break">
            <td style="padding:10px; width:25%; vertical-align:top;"><div style="color:#9A9A9A; font-size:10px; text-transform:uppercase;">Cliente</div><div style="color:#111; font-size:12px; margin-top:4px;">' . htmlspecialchars((string) ($project['client_name'] ?? 'Sin cliente'), ENT_QUOTES, 'UTF-8') . '</div></td>
            <td style="padding:10px; width:25%; vertical-align:top;"><div style="color:#9A9A9A; font-size:10px; text-transform:uppercase;">Fecha</div><div style="color:#111; font-size:12px; margin-top:4px;">' . htmlspecialchars($this->generatedAt, ENT_QUOTES, 'UTF-8') . '</div></td>
            <td style="padding:10px; width:25%; vertical-align:top;"><div style="color:#9A9A9A; font-size:10px; text-transform:uppercase;">Estado</div><div style="color:#111; font-size:12px; margin-top:4px;">' . htmlspecialchars($this->projectStatusLabel((string) ($project['status'] ?? '')), ENT_QUOTES, 'UTF-8') . '</div></td>
            <td style="padding:10px; width:25%; vertical-align:top;"><div style="color:#9A9A9A; font-size:10px; text-transform:uppercase;">PM</div><div style="color:#111; font-size:12px; margin-top:4px;">' . htmlspecialchars((string) ($project['pm_name'] ?? 'No asignado'), ENT_QUOTES, 'UTF-8') . '</div></td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <div class="page-break"></div>
  <div class="header">' . htmlspecialchars($this->projectName, ENT_QUOTES, 'UTF-8') . '<span class="header-right">Página {PAGENO}</span><div class="header-line"></div></div>
  <div class="footer">Generado por AOS Prompt Maestro PMO<span class="footer-right">' . htmlspecialchars($this->generatedAt, ENT_QUOTES, 'UTF-8') . '</span></div>

  <div class="section-title">Resumen Ejecutivo</div>
  <div class="section-subtitle">Indicadores clave del estado general del proyecto.</div>
  <div class="cards avoid-break">
    <div class="card"><div class="card-label">' . htmlspecialchars((string) ($kpis[0][0] ?? ''), ENT_QUOTES, 'UTF-8') . '</div><div class="card-value">' . htmlspecialchars((string) ($kpis[0][1] ?? ''), ENT_QUOTES, 'UTF-8') . '</div></div>
    <div class="card"><div class="card-label">' . htmlspecialchars((string) ($kpis[1][0] ?? ''), ENT_QUOTES, 'UTF-8') . '</div><div class="card-value small">' . htmlspecialchars((string) ($kpis[1][1] ?? ''), ENT_QUOTES, 'UTF-8') . '</div></div>
    <div class="card"><div class="card-label">' . htmlspecialchars((string) ($kpis[2][0] ?? ''), ENT_QUOTES, 'UTF-8') . '</div><div class="card-value">' . htmlspecialchars((string) ($kpis[2][1] ?? ''), ENT_QUOTES, 'UTF-8') . '</div></div>
    <div class="card"><div class="card-label">' . htmlspecialchars((string) ($kpis[3][0] ?? ''), ENT_QUOTES, 'UTF-8') . '</div><div class="card-value small align-right">' . htmlspecialchars((string) ($kpis[3][1] ?? ''), ENT_QUOTES, 'UTF-8') . '</div></div>
    <div class="card"><div class="card-label">' . htmlspecialchars((string) ($kpis[4][0] ?? ''), ENT_QUOTES, 'UTF-8') . '</div><div class="card-value small align-right">' . htmlspecialchars((string) ($kpis[4][1] ?? ''), ENT_QUOTES, 'UTF-8') . '</div></div>
    <div class="card"><div class="card-label">' . htmlspecialchars((string) ($kpis[5][0] ?? ''), ENT_QUOTES, 'UTF-8') . '</div><div class="card-value small align-right">' . htmlspecialchars((string) ($kpis[5][1] ?? ''), ENT_QUOTES, 'UTF-8') . '</div></div>
  </div>

  <div class="section-title">Facturación</div>
  <div class="section-subtitle">Resumen financiero y estado del plan de cobros.</div>
  <div class="dual avoid-break">
    <div class="panel">
      <h4>Resumen financiero</h4>
      <table class="report">
        <colgroup><col style="width:60%"><col style="width:40%"></colgroup>
        <tbody>
          <tr><td>Total contrato</td><td class="align-right">' . htmlspecialchars($this->fmtMoney((float) ($billingSummary['total_contract'] ?? 0), $currency), ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr class="alt"><td>Total facturado</td><td class="align-right">' . htmlspecialchars($this->fmtMoney((float) ($billingSummary['total_invoiced'] ?? 0), $currency), ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr><td>Saldo por facturar</td><td class="align-right">' . htmlspecialchars($this->fmtMoney((float) ($billingSummary['balance_to_invoice'] ?? 0), $currency), ENT_QUOTES, 'UTF-8') . '</td></tr>
        </tbody>
      </table>
    </div>
    <div class="panel">
      <h4>Cumplimiento de requisitos</h4>
      <table class="report">
        <tbody>
          <tr><td>Total requisitos</td><td class="align-right">' . htmlspecialchars((string) count($requirements), ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr class="alt"><td>Aprobados</td><td class="align-right">' . htmlspecialchars((string) $requirementsCounts['aprobado'], ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr><td>En revisión</td><td class="align-right">' . htmlspecialchars((string) $requirementsCounts['en_revision'], ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr class="alt"><td>Rechazados</td><td class="align-right">' . htmlspecialchars((string) $requirementsCounts['rechazado'], ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr><td>Cumplimiento actual</td><td class="align-right"><span class="badge ' . $reqComplianceTone . '">' . htmlspecialchars(number_format($requirementsValue, 1) . '%', ENT_QUOTES, 'UTF-8') . '</span></td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <table class="report avoid-break">
    <colgroup><col style="width:45%"><col style="width:25%"><col style="width:30%"></colgroup>
    <thead><tr><th>Concepto</th><th>Fecha esperada</th><th>Estado</th></tr></thead>
    <tbody>' . $billingPlanHtml . '</tbody>
  </table>
  <table class="report avoid-break">
    <colgroup><col style="width:35%"><col style="width:25%"><col style="width:40%"></colgroup>
    <thead><tr><th>Factura</th><th>Fecha</th><th>Monto</th></tr></thead>
    <tbody>' . $invoiceHtml . '</tbody>
  </table>

  <div class="section-title">Bloqueos</div>
  <div class="section-subtitle">Elementos operativos que impactan avance y entrega.</div>
  <table class="report avoid-break">
    <colgroup><col style="width:36%"><col style="width:20%"><col style="width:44%"></colgroup>
    <thead><tr><th>Bloqueo</th><th>Detectado</th><th>Detalle</th></tr></thead>
    <tbody>' . $stopperHtml . '</tbody>
  </table>

  <div class="section-title">Riesgos y alertas</div>
  <div class="section-subtitle">Alertas recientes para seguimiento gerencial.</div>
  <table class="report avoid-break">
    <colgroup><col style="width:20%"><col style="width:30%"><col style="width:50%"></colgroup>
    <thead><tr><th>Severidad</th><th>Título</th><th>Detalle</th></tr></thead>
    <tbody>' . $alertHtml . '</tbody>
  </table>
</body>
</html>';
    }

    private function badgeClass(string $status): string
    {
        return match ($this->statusTone($status)) {
            'success' => 'badge-success',
            'warning' => 'badge-warning',
            'danger' => 'badge-danger',
            default => 'badge-warning',
        };
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
