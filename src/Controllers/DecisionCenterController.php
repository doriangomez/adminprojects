<?php

declare(strict_types=1);

class DecisionCenterController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('pmo_decision_center_view');

        $user = $this->auth->user() ?? [];
        $service = new DecisionCenterService($this->db);

        $filters = [
            'from' => trim((string) ($_GET['from'] ?? date('Y-m-01'))),
            'to' => trim((string) ($_GET['to'] ?? date('Y-m-t'))),
            'client_id' => ((int) ($_GET['client_id'] ?? 0)) ?: null,
            'pm_id' => ((int) ($_GET['pm_id'] ?? 0)) ?: null,
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];

        try {
            $summary = $service->getPortfolioSummary($filters, $user);
        } catch (\Throwable $e) {
            error_log('DecisionCenter summary error: ' . $e->getMessage());
            $summary = ['portfolio_score' => 0, 'active_projects' => 0, 'at_risk_projects' => 0, 'open_blockers' => 0, 'billing_pending_amount' => 0, 'billing_pending_projects' => 0, 'utilization_pct' => 0, 'avg_progress' => 0];
        }

        try {
            $alerts = $service->getAlerts($filters, $user);
        } catch (\Throwable $e) {
            error_log('DecisionCenter alerts error: ' . $e->getMessage());
            $alerts = [];
        }

        try {
            $recommendations = $service->getRecommendations($filters, $user);
        } catch (\Throwable $e) {
            error_log('DecisionCenter recommendations error: ' . $e->getMessage());
            $recommendations = [];
        }

        try {
            $projectRanking = $service->getProjectRanking($filters, $user);
        } catch (\Throwable $e) {
            error_log('DecisionCenter ranking error: ' . $e->getMessage());
            $projectRanking = [];
        }

        try {
            $teamCapacity = $service->getTeamCapacity($filters);
        } catch (\Throwable $e) {
            error_log('DecisionCenter capacity error: ' . $e->getMessage());
            $teamCapacity = ['talents' => [], 'summary' => ['total' => 0, 'available' => 0, 'overloaded' => 0, 'critical' => 0, 'utilization_pct' => 0]];
        }

        $aiAnalysis = null;
        if ($this->auth->can('pmo_decision_center_ai')) {
            try {
                $aiAnalysis = $service->getAiAnalysis($filters, $user);
            } catch (\Throwable $e) {
                error_log('DecisionCenter AI error: ' . $e->getMessage());
            }
        }

        $clients = $this->db->fetchAll('SELECT id, name FROM clients ORDER BY name ASC');
        $pms = $this->db->fetchAll(
            "SELECT DISTINCT u.id, u.name FROM users u JOIN projects p ON p.pm_id = u.id ORDER BY u.name ASC"
        );

        $this->render('decision_center/index', [
            'title' => 'Centro de Decisiones PMO',
            'filters' => $filters,
            'summary' => $summary,
            'alerts' => $alerts,
            'recommendations' => $recommendations,
            'projectRanking' => $projectRanking,
            'teamCapacity' => $teamCapacity,
            'aiAnalysis' => $aiAnalysis,
            'canExport' => $this->auth->can('pmo_decision_center_export'),
            'canAi' => $this->auth->can('pmo_decision_center_ai'),
            'clients' => $clients,
            'pms' => $pms,
        ]);
    }

    public function apiSummary(): void
    {
        $this->requirePermission('pmo_decision_center_view');

        $user = $this->auth->user() ?? [];
        $service = new DecisionCenterService($this->db);

        $filters = [
            'from' => trim((string) ($_GET['from'] ?? date('Y-m-01'))),
            'to' => trim((string) ($_GET['to'] ?? date('Y-m-t'))),
            'client_id' => ((int) ($_GET['client_id'] ?? 0)) ?: null,
            'pm_id' => ((int) ($_GET['pm_id'] ?? 0)) ?: null,
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];

        try {
            $this->json([
                'summary' => $service->getPortfolioSummary($filters, $user),
                'alerts' => $service->getAlerts($filters, $user),
                'recommendations' => $service->getRecommendations($filters, $user),
                'project_ranking' => $service->getProjectRanking($filters, $user),
                'team_capacity' => $service->getTeamCapacity($filters),
            ]);
        } catch (\Throwable $e) {
            error_log('DecisionCenter API error: ' . $e->getMessage());
            $this->json(['error' => 'No se pudo obtener los datos del centro de decisiones'], 500);
        }
    }

    public function apiSimulate(): void
    {
        $this->requirePermission('pmo_decision_center_view');

        $service = new DecisionCenterService($this->db);
        $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;

        try {
            $result = $service->simulateCapacity($input);
            $this->json($result);
        } catch (\Throwable $e) {
            error_log('DecisionCenter simulate error: ' . $e->getMessage());
            $this->json(['error' => 'No se pudo ejecutar la simulación'], 500);
        }
    }

    public function apiAiAnalysis(): void
    {
        $this->requirePermission('pmo_decision_center_ai');

        $user = $this->auth->user() ?? [];
        $service = new DecisionCenterService($this->db);

        $filters = [
            'from' => trim((string) ($_GET['from'] ?? date('Y-m-01'))),
            'to' => trim((string) ($_GET['to'] ?? date('Y-m-t'))),
            'client_id' => ((int) ($_GET['client_id'] ?? 0)) ?: null,
            'pm_id' => ((int) ($_GET['pm_id'] ?? 0)) ?: null,
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];

        try {
            $analysis = $service->getAiAnalysis($filters, $user);
            $this->json(['analysis' => $analysis]);
        } catch (\Throwable $e) {
            error_log('DecisionCenter AI API error: ' . $e->getMessage());
            $this->json(['error' => 'No se pudo generar el análisis'], 500);
        }
    }
}
