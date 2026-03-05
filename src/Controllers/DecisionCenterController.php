<?php

declare(strict_types=1);

class DecisionCenterController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('pmo_decision_center_view');

        $service = new DecisionCenterService($this->db);
        $user = $this->auth->user() ?? [];

        $filters = [
            'from' => $_GET['from'] ?? date('Y-m-01'),
            'to' => $_GET['to'] ?? date('Y-m-t'),
            'client_id' => $_GET['client_id'] ?? null,
            'pm_id' => $_GET['pm_id'] ?? null,
            'status' => $_GET['status'] ?? null,
        ];

        $summary = $service->getPortfolioSummary($filters, $user);
        $alerts = $service->getAlerts($filters, $user);
        $recommendations = $service->getRecommendations($filters, $user);
        $projectRanking = $service->getProjectRanking($filters, $user);
        $teamCapacity = $service->getTeamCapacity($filters, $user);

        $this->render('decision_center/index', [
            'title' => 'Centro de Decisiones PMO',
            'summary' => $summary,
            'alerts' => $alerts,
            'recommendations' => $recommendations,
            'projectRanking' => $projectRanking,
            'teamCapacity' => $teamCapacity,
            'filters' => $filters,
            'canExport' => $this->auth->can('pmo_decision_center_export'),
            'canAi' => $this->auth->can('pmo_decision_center_ai'),
        ]);
    }

    public function api(): void
    {
        $this->requirePermission('pmo_decision_center_view');

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (!str_contains($accept, 'application/json') && !str_contains($accept, 'text/json')) {
            $this->denyAccess('Se requiere Accept: application/json');
        }

        $service = new DecisionCenterService($this->db);
        $user = $this->auth->user() ?? [];

        $filters = [
            'from' => $_GET['from'] ?? date('Y-m-01'),
            'to' => $_GET['to'] ?? date('Y-m-t'),
            'client_id' => $_GET['client_id'] ?? null,
            'pm_id' => $_GET['pm_id'] ?? null,
            'status' => $_GET['status'] ?? null,
            'date_from' => $_GET['from'] ?? date('Y-m-01'),
            'date_to' => $_GET['to'] ?? date('Y-m-t'),
        ];

        $payload = [
            'summary' => $service->getPortfolioSummary($filters, $user),
            'alerts' => $service->getAlerts($filters, $user),
            'recommendations' => $service->getRecommendations($filters, $user),
            'project_ranking' => $service->getProjectRanking($filters, $user),
            'team_capacity' => $service->getTeamCapacity($filters, $user),
        ];

        $this->json($payload);
    }

    public function simulate(): void
    {
        $this->requirePermission('pmo_decision_center_view');

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (!str_contains($accept, 'application/json') && !str_contains($accept, 'text/json')) {
            $this->denyAccess('Se requiere Accept: application/json');
        }

        $input = json_decode((string) file_get_contents('php://input'), true) ?: [];
        if (!is_array($input)) {
            $this->json(['error' => 'Payload JSON inválido'], 400);
            return;
        }

        $service = new DecisionCenterService($this->db);
        $user = $this->auth->user() ?? [];

        $result = $service->simulateCapacity($input, $user);
        $this->json($result);
    }
}
