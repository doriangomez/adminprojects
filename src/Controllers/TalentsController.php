<?php

declare(strict_types=1);

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
        $servicePayload = $this->servicePayload($_POST);

        if (($servicePayload['client_id'] ?? 0) > 0 && $payload['email'] === '') {
            http_response_code(400);
            exit('El correo del talento es obligatorio para asignarlo a outsourcing.');
        }

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

            $this->createOutsourcingService($created['user_id'] ?? null, $servicePayload, $user);

            header('Location: /project/public/talents?saved=created');
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
        $servicePayload = $this->servicePayload($_POST);
        $talentId = (int) ($_POST['talent_id'] ?? 0);
        if ($talentId <= 0) {
            http_response_code(400);
            exit('Selecciona un talento válido.');
        }

        if (($servicePayload['client_id'] ?? 0) > 0 && $payload['email'] === '') {
            http_response_code(400);
            exit('El correo del talento es obligatorio para asignarlo a outsourcing.');
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

            $this->createOutsourcingService($updated['user_id'] ?? null, $servicePayload, $user);

            header('Location: /project/public/talents?edit=' . $talentId . '&saved=updated');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error al actualizar talento: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo actualizar el talento.');
        }
    }

    private function talentPayload(array $payload): array
    {
        return [
            'name' => trim((string) ($payload['name'] ?? '')),
            'email' => trim((string) ($payload['email'] ?? '')),
            'role' => trim((string) ($payload['role'] ?? '')),
            'seniority' => trim((string) ($payload['seniority'] ?? '')),
            'weekly_capacity' => (int) ($payload['weekly_capacity'] ?? 0),
            'availability' => (int) ($payload['availability'] ?? 0),
            'hourly_cost' => (float) ($payload['hourly_cost'] ?? 0),
            'hourly_rate' => (float) ($payload['hourly_rate'] ?? 0),
            'is_outsourcing' => !empty($payload['is_outsourcing']) ? 1 : 0,
        ];
    }

    private function servicePayload(array $payload): array
    {
        return [
            'client_id' => (int) ($payload['client_id'] ?? 0),
            'project_id' => (int) ($payload['project_id'] ?? 0),
            'start_date' => $payload['start_date'] ?? '',
            'end_date' => $payload['end_date'] ?? '',
            'followup_frequency' => $payload['followup_frequency'] ?? 'monthly',
            'service_status' => $payload['service_status'] ?? 'active',
            'observations' => $payload['observations'] ?? '',
        ];
    }

    private function createOutsourcingService(?int $userId, array $servicePayload, array $currentUser): void
    {
        if (($servicePayload['client_id'] ?? 0) <= 0) {
            return;
        }

        if (!$userId) {
            throw new InvalidArgumentException('El talento requiere un correo válido para asignarse a outsourcing.');
        }

        $servicePayload['talent_id'] = $userId;
        $servicePayload['created_by'] = (int) ($currentUser['id'] ?? 0);

        $servicesRepo = new OutsourcingServicesRepository($this->db);
        $serviceId = $servicesRepo->createService($servicePayload);

        (new AuditLogRepository($this->db))->log(
            (int) ($currentUser['id'] ?? 0),
            'outsourcing_service',
            $serviceId,
            'created',
            [
                'talent_id' => $userId,
                'client_id' => $servicePayload['client_id'],
                'project_id' => $servicePayload['project_id'] ?: null,
                'service_status' => $servicePayload['service_status'],
            ]
        );
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
