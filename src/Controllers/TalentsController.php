<?php

declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\ClientsRepository;
use App\Repositories\OutsourcingServicesRepository;
use App\Repositories\ProjectNodesRepository;
use App\Repositories\ProjectsRepository;
use App\Repositories\TalentsRepository;

class TalentsController extends Controller
{
    public function index(): void
    {
        $repo = new TalentsRepository($this->db);
        $outsourcingRepo = new OutsourcingServicesRepository($this->db);
        $clientsRepo = new ClientsRepository($this->db);
        $projectsRepo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $editingId = (int) ($_GET['edit'] ?? 0);
        $services = $outsourcingRepo->listServices($user);
        $this->requirePermission('talents.view');
        $this->render('talents/index', [
            'title' => 'Talento',
            'talents' => $repo->summary(),
            'editingTalent' => $editingId > 0 ? $repo->find($editingId) : null,
            'clients' => $clientsRepo->listForUser($user),
            'projects' => $projectsRepo->summary($user),
            'services' => $services,
            'documentsByService' => $this->serviceDocuments($services),
            'flashMessage' => $_GET['saved'] ?? '',
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('talents.view');

        $user = $this->auth->user() ?? [];
        $payload = $this->talentPayload($_POST);

        try {
            $talentService = new TalentService($this->db);
            $created = $talentService->createTalent($payload);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'talent',
                (int) ($created['talent_id'] ?? 0),
                'created',
                [
                    'user_id' => $created['user_id'] ?? null,
                ]
            );

            $flashKey = !empty($payload['is_outsourcing']) ? 'created_outsourcing' : 'created';
            header('Location: /talents?saved=' . $flashKey);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error al registrar talento: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo registrar el talento.');
        }
    }

    public function update(): void
    {
        $this->requirePermission('talents.view');

        $user = $this->auth->user() ?? [];
        $payload = $this->talentPayload($_POST);
        $talentId = (int) ($_POST['talent_id'] ?? 0);
        if ($talentId <= 0) {
            http_response_code(400);
            exit('Selecciona un talento válido.');
        }

        try {
            $talentService = new TalentService($this->db);
            $updated = $talentService->updateTalent($talentId, $payload);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'talent',
                $talentId,
                'updated',
                [
                    'user_id' => $updated['user_id'] ?? null,
                ]
            );

            header('Location: /talents?edit=' . $talentId . '&saved=updated');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error al actualizar talento: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo actualizar el talento.');
        }
    }


    public function inactivate(): void
    {
        $this->requirePermission('talents.view');

        $user = $this->auth->user() ?? [];
        $talentId = (int) ($_POST['talent_id'] ?? 0);
        $mathOperator = trim((string) ($_POST['math_operator'] ?? ''));
        $operand1 = isset($_POST['math_operand1']) ? (int) $_POST['math_operand1'] : 0;
        $operand2 = isset($_POST['math_operand2']) ? (int) $_POST['math_operand2'] : 0;
        $mathResult = trim((string) ($_POST['math_result'] ?? ''));

        $repo = new TalentsRepository($this->db);
        $talent = $repo->find($talentId);

        if ($talentId <= 0 || !$talent) {
            http_response_code(404);
            exit('Talento no encontrado.');
        }

        if (!in_array($mathOperator, ['+', '-'], true) || $operand1 < 1 || $operand1 > 10 || $operand2 < 1 || $operand2 > 10) {
            http_response_code(400);
            exit('La verificación matemática no es válida.');
        }

        $expected = $mathOperator === '+' ? $operand1 + $operand2 : $operand1 - $operand2;
        if ($mathResult === '' || (int) $mathResult !== $expected) {
            http_response_code(400);
            exit('La confirmación matemática es incorrecta.');
        }

        try {
            if (!$repo->inactivateTalent($talentId)) {
                http_response_code(500);
                exit('No se pudo inactivar el talento.');
            }

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'talent',
                $talentId,
                'inactivated',
                [
                    'name' => $talent['name'] ?? null,
                ]
            );

            header('Location: /talents?edit=' . $talentId . '&saved=inactivated');
        } catch (\Throwable $e) {
            error_log('Error al inactivar talento: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo inactivar el talento. Intenta nuevamente o contacta al administrador.');
        }
    }

    public function destroy(): void
    {
        $this->requirePermission('talents.view');

        $user = $this->auth->user() ?? [];
        $talentId = (int) ($_POST['talent_id'] ?? 0);
        $mathOperator = trim((string) ($_POST['math_operator'] ?? ''));
        $operand1 = isset($_POST['math_operand1']) ? (int) $_POST['math_operand1'] : 0;
        $operand2 = isset($_POST['math_operand2']) ? (int) $_POST['math_operand2'] : 0;
        $mathResult = trim((string) ($_POST['math_result'] ?? ''));

        $repo = new TalentsRepository($this->db);
        $talent = $repo->find($talentId);

        if ($talentId <= 0 || !$talent) {
            http_response_code(404);
            exit('Talento no encontrado.');
        }

        if (!in_array($mathOperator, ['+', '-'], true) || $operand1 < 1 || $operand1 > 10 || $operand2 < 1 || $operand2 > 10) {
            http_response_code(400);
            exit('La verificación matemática no es válida.');
        }

        $expected = $mathOperator === '+' ? $operand1 + $operand2 : $operand1 - $operand2;
        if ($mathResult === '' || (int) $mathResult !== $expected) {
            http_response_code(400);
            exit('La confirmación matemática es incorrecta.');
        }

        try {
            $deleted = $repo->deleteTalentCascade($talentId);
            if (!$deleted['success']) {
                http_response_code(500);
                exit('No se pudo eliminar el talento.');
            }

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'talent',
                $talentId,
                'deleted',
                [
                    'name' => $talent['name'] ?? null,
                    'deleted' => $deleted['deleted'] ?? [],
                ]
            );

            header('Location: /talents?saved=deleted');
        } catch (\Throwable $e) {
            error_log('Error al eliminar talento: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo eliminar el talento. Intenta nuevamente o contacta al administrador.');
        }
    }

    private function talentPayload(array $payload): array
    {
        return [
            'name' => trim((string) ($payload['name'] ?? '')),
            'email' => trim((string) ($payload['email'] ?? '')),
            'role' => trim((string) ($payload['role'] ?? '')),
            'seniority' => trim((string) ($payload['seniority'] ?? '')),
            'capacidad_horaria' => (float) ($payload['capacidad_horaria'] ?? 0),
            'availability' => (int) ($payload['availability'] ?? 0),
            'hourly_cost' => (float) ($payload['hourly_cost'] ?? 0),
            'hourly_rate' => (float) ($payload['hourly_rate'] ?? 0),
            'requiere_reporte_horas' => !empty($payload['requiere_reporte_horas']) ? 1 : 0,
            'requiere_aprobacion_horas' => !empty($payload['requiere_aprobacion_horas']) ? 1 : 0,
            'tipo_talento' => strtolower(trim((string) ($payload['tipo_talento'] ?? 'interno'))),
        ];
    }

    private function serviceDocuments(array $services): array
    {
        if (empty($services)) {
            return [];
        }

        $servicesByProject = [];
        foreach ($services as $service) {
            $projectId = (int) ($service['project_id'] ?? 0);
            if ($projectId > 0) {
                $servicesByProject[$projectId][] = $service;
            }
        }

        if (!$servicesByProject) {
            return [];
        }

        $nodesRepo = new ProjectNodesRepository($this->db);
        $documentsByService = [];

        foreach ($servicesByProject as $projectId => $projectServices) {
            $tree = $nodesRepo->treeWithFiles($projectId);
            $nodesById = [];
            $filesByNodeId = [];
            $this->indexProjectNodes($tree, $nodesById, $filesByNodeId);

            foreach ($projectServices as $service) {
                $nodeId = (int) ($service['last_followup_document_node_id'] ?? 0);
                if ($nodeId <= 0 || !isset($nodesById[$nodeId])) {
                    continue;
                }

                $documentsByService[(int) $service['id']] = [
                    'node_title' => $nodesById[$nodeId]['name'] ?? 'Evidencias',
                    'files' => $filesByNodeId[$nodeId] ?? [],
                ];
            }
        }

        return $documentsByService;
    }

    private function indexProjectNodes(array $nodes, array &$nodesById, array &$filesByNodeId): array
    {
        $collectedFiles = [];

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $nodeId = (int) ($node['id'] ?? 0);
            $nodesById[$nodeId] = $node;

            $nodeFiles = $node['files'] ?? [];
            $childFiles = [];
            $children = $node['children'] ?? [];
            if (is_array($children)) {
                $childFiles = $this->indexProjectNodes($children, $nodesById, $filesByNodeId);
            }

            $mergedFiles = array_merge($nodeFiles, $childFiles);
            $filesByNodeId[$nodeId] = $mergedFiles;
            $collectedFiles = array_merge($collectedFiles, $mergedFiles);
        }

        return $collectedFiles;
    }
}
