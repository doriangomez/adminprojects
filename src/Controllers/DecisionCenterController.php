<?php

declare(strict_types=1);

class DecisionCenterController extends Controller
{
    public function index(): void
    {
        if (!$this->auth->can('pmo_decision_center_view')) {
            $this->denyAccess('Necesitas el permiso "Centro de Decisiones PMO" para acceder a este módulo.');
        }

        $filters = $this->parseFilters();
        $service = new DecisionCenterService($this->db);

        $summary         = [];
        $alerts          = [];
        $recommendations = [];
        $projectRanking  = [];
        $teamCapacity    = [];
        $errors          = [];

        try {
            $summary = $service->getPortfolioSummary($filters);
        } catch (\Throwable $e) {
            error_log('DecisionCenter summary error: ' . $e->getMessage());
            $errors[] = 'No se pudo calcular el resumen del portafolio.';
        }

        try {
            $alerts = $service->getAlerts($filters);
        } catch (\Throwable $e) {
            error_log('DecisionCenter alerts error: ' . $e->getMessage());
            $errors[] = 'No se pudieron cargar las alertas.';
        }

        try {
            $recommendations = $service->getRecommendations($filters);
        } catch (\Throwable $e) {
            error_log('DecisionCenter recommendations error: ' . $e->getMessage());
            $errors[] = 'No se pudieron cargar las recomendaciones.';
        }

        try {
            $projectRanking = $service->getProjectRanking($filters);
        } catch (\Throwable $e) {
            error_log('DecisionCenter ranking error: ' . $e->getMessage());
            $errors[] = 'No se pudo cargar el ranking de proyectos.';
        }

        try {
            $teamCapacity = $service->getTeamCapacity($filters);
        } catch (\Throwable $e) {
            error_log('DecisionCenter capacity error: ' . $e->getMessage());
            $errors[] = 'No se pudo cargar la capacidad del equipo.';
        }

        // Build filter options for UI
        $clients = [];
        $pms     = [];
        try {
            $clients = $this->db->fetchAll('SELECT id, name FROM clients WHERE active = 1 ORDER BY name ASC');
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            if ($this->db->columnExists('projects', 'pm_id')) {
                $pms = $this->db->fetchAll(
                    'SELECT DISTINCT u.id, u.name FROM users u
                     JOIN projects p ON p.pm_id = u.id
                     WHERE u.active = 1 ORDER BY u.name ASC'
                );
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $canAi = $this->auth->can('pmo_decision_center_ai');

        $this->render('pmo/decision_center', [
            'title'           => 'Centro de Decisiones PMO',
            'filters'         => $filters,
            'summary'         => $summary,
            'alerts'          => $alerts,
            'recommendations' => $recommendations,
            'projectRanking'  => $projectRanking,
            'teamCapacity'    => $teamCapacity,
            'clients'         => $clients,
            'pms'             => $pms,
            'errors'          => $errors,
            'canAi'           => $canAi,
            'canExport'       => $this->auth->can('pmo_decision_center_export'),
        ]);
    }

    public function simulate(): void
    {
        if (!$this->auth->can('pmo_decision_center_view')) {
            $this->json(['error' => 'Acceso denegado'], 403);
            return;
        }

        $payload = [
            'area'             => trim((string) ($_POST['area']             ?? '')),
            'hours'            => (float) ($_POST['hours']            ?? 0),
            'from'             => (string) ($_POST['from']             ?? date('Y-m-01')),
            'to'               => (string) ($_POST['to']               ?? date('Y-m-t')),
            'resources_needed' => (int)   ($_POST['resources_needed'] ?? 1),
        ];

        if ($payload['hours'] <= 0) {
            $this->json(['error' => 'Las horas estimadas deben ser mayores a 0.'], 422);
            return;
        }

        $service = new DecisionCenterService($this->db);
        $result  = $service->simulateCapacity($payload);

        $this->json($result);
    }

    private function parseFilters(): array
    {
        return [
            'from'      => (string) ($_GET['from']      ?? date('Y-m-01')),
            'to'        => (string) ($_GET['to']        ?? date('Y-m-t')),
            'client_id' => !empty($_GET['client_id']) ? (int) $_GET['client_id'] : null,
            'pm_id'     => !empty($_GET['pm_id'])     ? (int) $_GET['pm_id']     : null,
        ];
    }
}
