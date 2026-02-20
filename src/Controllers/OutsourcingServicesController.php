<?php

declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\ClientsRepository;
use App\Repositories\OutsourcingServicesRepository;
use App\Repositories\ProjectNodesRepository;
use App\Repositories\ProjectsRepository;
use App\Repositories\UsersRepository;

class OutsourcingServicesController extends Controller
{
    public function index(): void
    {
        $this->requireOutsourcingAccess();
        $user = $this->auth->user() ?? [];

        $servicesRepo = new OutsourcingServicesRepository($this->db);
        $clientsRepo = new ClientsRepository($this->db);
        $projectsRepo = new ProjectsRepository($this->db);
        $usersRepo = new UsersRepository($this->db);
        $filters = [
            'client_id' => (int) ($_GET['client_id'] ?? 0),
            'talent_id' => (int) ($_GET['talent_id'] ?? 0),
            'project_id' => (int) ($_GET['project_id'] ?? 0),
            'service_health' => (string) ($_GET['service_health'] ?? ''),
        ];
        $preselectedTalentId = (int) ($_GET['selected_talent_id'] ?? 0);
        $talentCreatedMessage = !empty($_GET['talent_created']) ? 'Talento registrado y listo para asignar.' : null;

        $users = array_values(array_filter(
            $usersRepo->all(),
            static fn (array $candidate): bool => (int) ($candidate['active'] ?? 0) === 1
        ));

        $talents = array_values(array_filter(
            $users,
            static fn (array $candidate): bool => ($candidate['role_name'] ?? '') === 'Talento'
        ));

        $this->render('outsourcing/index', [
            'title' => 'Outsourcing',
            'services' => $servicesRepo->listServices($user, $filters),
            'clients' => $clientsRepo->listForUser($user),
            'projects' => $projectsRepo->summary($user),
            'talents' => $talents,
            'users' => $users,
            'canManage' => $this->auth->canAccessOutsourcing(),
            'filters' => $filters,
            'preselectedTalentId' => $preselectedTalentId,
            'talentCreatedMessage' => $talentCreatedMessage,
        ]);
    }

    public function show(int $serviceId): void
    {
        $this->requireOutsourcingAccess();
        $user = $this->auth->user() ?? [];

        $servicesRepo = new OutsourcingServicesRepository($this->db);
        $service = $servicesRepo->findService($serviceId);

        if (!$service) {
            http_response_code(404);
            exit('Servicio de outsourcing no encontrado.');
        }

        $timesheetSummary = $servicesRepo->timesheetSummary($service);

        $users = array_values(array_filter(
            (new UsersRepository($this->db))->all(),
            static fn (array $candidate): bool => (int) ($candidate['active'] ?? 0) === 1
        ));

        $followups = $servicesRepo->followupsForService($serviceId);

        if (!empty($service['project_id'])) {
            $nodesRepo = new ProjectNodesRepository($this->db);
            $projectNodes = $nodesRepo->treeWithFiles((int) $service['project_id']);
            $nodesById = $this->flattenProjectNodes($projectNodes);

            foreach ($followups as &$followup) {
                $nodeId = (int) ($followup['document_node_id'] ?? 0);
                $followup['document_node'] = $nodeId > 0 ? ($nodesById[$nodeId] ?? null) : null;
            }
            unset($followup);
        }

        $this->render('outsourcing/show', [
            'title' => 'Servicio de outsourcing',
            'service' => $service,
            'followups' => $followups,
            'users' => $users,
            'documentFlowConfig' => (new ConfigService($this->db))->getConfig()['document_flow'] ?? [],
            'currentUser' => $user,
            'canManage' => $this->auth->canAccessOutsourcing(),
            'timesheetSummary' => $timesheetSummary,
        ]);
    }

    public function store(): void
    {
        $this->requireOutsourcingAccess();
        $user = $this->auth->user() ?? [];

        $payload = [
            'talent_id' => (int) ($_POST['talent_id'] ?? 0),
            'client_id' => (int) ($_POST['client_id'] ?? 0),
            'project_id' => (int) ($_POST['project_id'] ?? 0),
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'followup_frequency' => $_POST['followup_frequency'] ?? 'monthly',
            'service_status' => $_POST['service_status'] ?? 'active',
            'observations' => $_POST['observations'] ?? '',
            'created_by' => (int) ($user['id'] ?? 0),
        ];

        if ($payload['talent_id'] <= 0 || $payload['client_id'] <= 0) {
            http_response_code(400);
            exit('Selecciona un talento y un cliente válidos.');
        }

        try {
            $servicesRepo = new OutsourcingServicesRepository($this->db);
            $serviceId = $servicesRepo->createService($payload);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'outsourcing_service',
                $serviceId,
                'created',
                [
                    'talent_id' => $payload['talent_id'],
                    'client_id' => $payload['client_id'],
                    'project_id' => $payload['project_id'] ?: null,
                    'followup_frequency' => $payload['followup_frequency'],
                    'service_status' => $payload['service_status'],
                ]
            );

            header('Location: /outsourcing/' . $serviceId);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }

    public function updateStatus(int $serviceId): void
    {
        $this->requireOutsourcingAccess();
        $user = $this->auth->user() ?? [];

        try {
            $servicesRepo = new OutsourcingServicesRepository($this->db);
            $servicesRepo->updateServiceStatus($serviceId, (string) ($_POST['service_status'] ?? 'active'));

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'outsourcing_service',
                $serviceId,
                'status_updated',
                ['service_status' => $_POST['service_status'] ?? 'active']
            );

            header('Location: /outsourcing/' . $serviceId);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }

    public function storeTalent(): void
    {
        $this->requireOutsourcingAccess();

        try {
            $email = trim((string) ($_POST['email'] ?? ''));
            if ($email === '') {
                http_response_code(400);
                exit('El correo del talento es obligatorio para crear el usuario.');
            }

            $payload = $this->talentPayloadFromRequest($_POST);
            $talentService = new TalentService($this->db);
            $created = $talentService->createTalent($payload);
            $userId = $created['user_id'] ?? null;

            if (!$userId) {
                http_response_code(400);
                exit('El talento debe tener un correo válido para crear el usuario.');
            }

            (new AuditLogRepository($this->db))->log(
                (int) ($this->auth->user()['id'] ?? 0),
                'talent',
                (int) ($created['talent_id'] ?? 0),
                'created',
                [
                    'user_id' => $userId,
                    'source' => 'outsourcing',
                ]
            );

            header('Location: /outsourcing?selected_talent_id=' . $userId . '&talent_created=1');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error al crear talento desde outsourcing: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo crear el talento.');
        }
    }

    public function updateFrequency(int $serviceId): void
    {
        $this->requireOutsourcingAccess();
        $user = $this->auth->user() ?? [];

        try {
            $servicesRepo = new OutsourcingServicesRepository($this->db);
            $servicesRepo->updateServiceFrequency($serviceId, (string) ($_POST['followup_frequency'] ?? 'monthly'));

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'outsourcing_service',
                $serviceId,
                'frequency_updated',
                ['followup_frequency' => $_POST['followup_frequency'] ?? 'monthly']
            );

            header('Location: /outsourcing/' . $serviceId);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }

    public function createFollowup(int $serviceId): void
    {
        $this->requireOutsourcingAccess();
        $user = $this->auth->user() ?? [];

        $servicesRepo = new OutsourcingServicesRepository($this->db);
        $service = $servicesRepo->findService($serviceId);
        if (!$service) {
            http_response_code(404);
            exit('Servicio de outsourcing no encontrado.');
        }

        $periodStart = trim((string) ($_POST['period_start'] ?? ''));
        $periodEnd = trim((string) ($_POST['period_end'] ?? ''));
        if ($periodStart === '' || $periodEnd === '') {
            http_response_code(400);
            exit('El periodo del seguimiento es obligatorio.');
        }
        if (strtotime($periodEnd) < strtotime($periodStart)) {
            http_response_code(400);
            exit('El periodo del seguimiento es inválido.');
        }

        $responsibleId = (int) ($_POST['responsible_user_id'] ?? 0);
        if ($responsibleId <= 0) {
            http_response_code(400);
            exit('Selecciona un responsable válido.');
        }

        $documentNodeId = null;
        $projectId = (int) ($service['project_id'] ?? 0);

        if ($projectId > 0) {
            $nodesRepo = new ProjectNodesRepository($this->db);
            $rootNode = $nodesRepo->findNodeByCode($projectId, 'ROOT');
            $rootNodeId = $rootNode ? (int) ($rootNode['id'] ?? 0) : null;

            $serviceCode = 'OUTSOURCING-SVC-' . $serviceId;
            $servicePath = [
                [
                    'code' => 'OUTSOURCING',
                    'title' => 'Outsourcing',
                    'node_type' => ProjectTreeService::NODE_TYPE_FOLDER,
                ],
                [
                    'code' => 'OUTSOURCING-SERVICES',
                    'title' => 'Servicios',
                    'node_type' => ProjectTreeService::NODE_TYPE_FOLDER,
                ],
                [
                    'code' => $serviceCode,
                    'title' => 'Servicio ' . ($service['talent_name'] ?? ''),
                    'node_type' => ProjectTreeService::NODE_TYPE_FOLDER,
                ],
            ];

            $serviceFolderId = $nodesRepo->ensureFolderPath($projectId, $servicePath, $rootNodeId);

            $folderCode = 'OUTSOURCING-FU-' . date('YmdHis') . '-' . random_int(100, 999);
            $folderTitle = sprintf('Seguimiento %s a %s', $periodStart, $periodEnd);
            $documentNodeId = $nodesRepo->createNode([
                'project_id' => $projectId,
                'parent_id' => $serviceFolderId,
                'code' => $folderCode,
                'title' => $folderTitle,
                'node_type' => ProjectTreeService::NODE_TYPE_FOLDER,
                'iso_clause' => null,
                'description' => 'Documentación de seguimiento outsourcing',
                'sort_order' => 0,
                'created_by' => (int) ($user['id'] ?? 0),
            ]);
        }

        try {
            $followupId = $servicesRepo->createFollowup([
                'service_id' => $serviceId,
                'document_node_id' => $documentNodeId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'followup_frequency' => $service['followup_frequency'] ?? 'monthly',
                'service_health' => $_POST['service_health'] ?? '',
                'observations' => $_POST['observations'] ?? '',
                'responsible_user_id' => $responsibleId,
                'followup_status' => $_POST['followup_status'] ?? 'open',
                'created_by' => (int) ($user['id'] ?? 0),
            ]);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'outsourcing_followup',
                $followupId,
                'created',
                [
                    'service_id' => $serviceId,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'service_health' => $_POST['service_health'] ?? '',
                    'responsible_user_id' => $responsibleId,
                    'followup_status' => $_POST['followup_status'] ?? 'open',
                ]
            );

            header('Location: /outsourcing/' . $serviceId);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error al crear seguimiento outsourcing: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo crear el seguimiento.');
        }
    }

    public function closeFollowup(int $serviceId, int $followupId): void
    {
        $this->requireOutsourcingAccess();
        $user = $this->auth->user() ?? [];

        $servicesRepo = new OutsourcingServicesRepository($this->db);
        $followup = $servicesRepo->findFollowup($followupId, $serviceId);

        if (!$followup) {
            http_response_code(404);
            exit('Seguimiento de outsourcing no encontrado.');
        }

        if (($followup['followup_status'] ?? '') !== 'closed') {
            $servicesRepo->closeFollowup($followupId);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'outsourcing_followup',
                $followupId,
                'closed',
                [
                    'service_id' => $serviceId,
                    'previous_status' => $followup['followup_status'] ?? null,
                ]
            );
        }

        header('Location: /outsourcing/' . $serviceId);
    }

    private function requireOutsourcingAccess(): void
    {
        if (!$this->auth->canAccessOutsourcing()) {
            http_response_code(403);
            exit('Acceso denegado');
        }
    }

    private function talentPayloadFromRequest(array $payload): array
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
            'tipo_talento' => strtolower(trim((string) ($payload['tipo_talento'] ?? 'externo'))),
        ];
    }

    private function flattenProjectNodes(array $nodes): array
    {
        $flattened = [];
        $stack = $nodes;

        while (!empty($stack)) {
            $node = array_pop($stack);
            if (!is_array($node)) {
                continue;
            }
            $nodeId = (int) ($node['id'] ?? 0);
            if ($nodeId > 0) {
                $flattened[$nodeId] = $node;
            }
            $children = $node['children'] ?? [];
            if (is_array($children)) {
                foreach ($children as $child) {
                    $stack[] = $child;
                }
            }
        }

        return $flattened;
    }
}
