<?php

declare(strict_types=1);

use App\Repositories\AbsencesRepository;
use App\Repositories\TalentsRepository;

class AbsencesController extends Controller
{
    private const ABSENCE_TYPES = [
        'vacaciones'       => 'Vacaciones',
        'permiso_personal' => 'Permiso personal',
        'permiso_medico'   => 'Permiso médico',
        'incapacidad'      => 'Incapacidad',
        'capacitacion'     => 'Capacitación',
        'licencia'         => 'Licencia',
        'ausencia'         => 'Ausencia',
    ];

    public function index(): void
    {
        $this->requirePermission('talent.absences.view');

        $config = (new ConfigService($this->db))->getConfig();
        $absenceConfig = $config['operational_rules']['absences'] ?? [];

        if (empty($absenceConfig['enabled'])) {
            $this->denyAccess('El módulo de gestión de ausencias no está habilitado. Actívalo desde Configuración → Operación.');
        }

        $repo = new AbsencesRepository($this->db);
        $talentsRepo = new TalentsRepository($this->db);

        $filters = [
            'talent_id'    => !empty($_GET['talent_id']) ? (int) $_GET['talent_id'] : null,
            'status'       => $_GET['status'] ?? '',
            'absence_type' => $_GET['absence_type'] ?? '',
            'from'         => $_GET['from'] ?? '',
            'to'           => $_GET['to'] ?? '',
        ];

        $filtersClean = array_filter($filters, static fn ($v) => $v !== null && $v !== '');

        $absences = $repo->listAll($filtersClean);
        $counts = $repo->countByStatus();
        $talents = $talentsRepo->summary();

        $this->render('absences/index', [
            'title'         => 'Gestión de Ausencias',
            'absences'      => $absences,
            'counts'        => $counts,
            'talents'       => $talents,
            'absenceTypes'  => self::ABSENCE_TYPES,
            'absenceConfig' => $absenceConfig,
            'filters'       => $filters,
            'flashMessage'  => $_GET['saved'] ?? '',
            'canCreate'     => $this->auth->can('talent.absences.create'),
            'canEdit'       => $this->auth->can('talent.absences.edit'),
            'canDelete'     => $this->auth->can('talent.absences.delete'),
            'canApprove'    => $this->auth->can('talent.absences.approve'),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('talent.absences.create');

        $config = (new ConfigService($this->db))->getConfig();
        if (empty($config['operational_rules']['absences']['enabled'])) {
            http_response_code(403);
            exit('El módulo de ausencias no está habilitado.');
        }

        $repo = new AbsencesRepository($this->db);
        $user = $this->auth->user() ?? [];

        $talentId  = (int) ($_POST['talent_id'] ?? 0);
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate   = trim($_POST['end_date'] ?? '');
        $type      = trim($_POST['absence_type'] ?? '');

        if ($talentId <= 0 || $startDate === '' || $endDate === '' || $type === '') {
            http_response_code(400);
            exit('Datos incompletos. Talento, tipo de ausencia, fecha inicio y fecha fin son obligatorios.');
        }

        if ($startDate > $endDate) {
            http_response_code(400);
            exit('La fecha de inicio no puede ser posterior a la fecha de fin.');
        }

        if ($repo->hasOverlap($talentId, $startDate, $endDate)) {
            http_response_code(409);
            exit('Ya existe una ausencia registrada para ese talento en el periodo indicado.');
        }

        $initialStatus = $this->auth->can('talent.absences.approve') ? 'aprobado' : 'pendiente';

        $data = [
            'talent_id'    => $talentId,
            'user_id'      => $user['id'] ?? null,
            'absence_type' => $type,
            'start_date'   => $startDate,
            'end_date'     => $endDate,
            'hours'        => $_POST['hours'] ?? null,
            'is_full_day'  => isset($_POST['is_full_day']) ? 1 : 0,
            'status'       => $initialStatus,
            'reason'       => trim($_POST['reason'] ?? ''),
        ];

        $id = $repo->create($data);

        if ($initialStatus === 'aprobado') {
            $repo->updateStatus($id, 'aprobado', (int) ($user['id'] ?? 0));
        }

        header('Location: /absences?saved=created');
    }

    public function edit(int $id): void
    {
        $this->requirePermission('talent.absences.edit');

        $repo = new AbsencesRepository($this->db);
        $absence = $repo->findById($id);

        if (!$absence) {
            http_response_code(404);
            exit('Ausencia no encontrada.');
        }

        $talentsRepo = new TalentsRepository($this->db);
        $config = (new ConfigService($this->db))->getConfig();
        $absenceConfig = $config['operational_rules']['absences'] ?? [];

        $this->render('absences/edit', [
            'title'        => 'Editar Ausencia',
            'absence'      => $absence,
            'talents'      => $talentsRepo->summary(),
            'absenceTypes' => self::ABSENCE_TYPES,
            'absenceConfig' => $absenceConfig,
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('talent.absences.edit');

        $repo = new AbsencesRepository($this->db);
        $absence = $repo->findById($id);

        if (!$absence) {
            http_response_code(404);
            exit('Ausencia no encontrada.');
        }

        $talentId  = (int) ($_POST['talent_id'] ?? 0);
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate   = trim($_POST['end_date'] ?? '');
        $type      = trim($_POST['absence_type'] ?? '');

        if ($talentId <= 0 || $startDate === '' || $endDate === '' || $type === '') {
            http_response_code(400);
            exit('Datos incompletos.');
        }

        if ($startDate > $endDate) {
            http_response_code(400);
            exit('La fecha de inicio no puede ser posterior a la fecha de fin.');
        }

        if ($repo->hasOverlap($talentId, $startDate, $endDate, $id)) {
            http_response_code(409);
            exit('Ya existe una ausencia registrada para ese talento en el periodo indicado.');
        }

        $data = [
            'talent_id'    => $talentId,
            'absence_type' => $type,
            'start_date'   => $startDate,
            'end_date'     => $endDate,
            'hours'        => $_POST['hours'] ?? null,
            'is_full_day'  => isset($_POST['is_full_day']) ? 1 : 0,
            'reason'       => trim($_POST['reason'] ?? ''),
        ];

        $repo->update($id, $data);

        header('Location: /absences?saved=updated');
    }

    public function approve(int $id): void
    {
        $this->requirePermission('talent.absences.approve');

        $repo = new AbsencesRepository($this->db);
        $absence = $repo->findById($id);

        if (!$absence) {
            http_response_code(404);
            exit('Ausencia no encontrada.');
        }

        $user = $this->auth->user() ?? [];
        $repo->updateStatus($id, 'aprobado', (int) ($user['id'] ?? 0));

        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($acceptHeader, 'application/json')) {
            $this->json(['success' => true, 'status' => 'aprobado']);
            return;
        }

        header('Location: /absences?saved=approved');
    }

    public function reject(int $id): void
    {
        $this->requirePermission('talent.absences.approve');

        $repo = new AbsencesRepository($this->db);
        $absence = $repo->findById($id);

        if (!$absence) {
            http_response_code(404);
            exit('Ausencia no encontrada.');
        }

        $repo->updateStatus($id, 'rechazado', null);

        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($acceptHeader, 'application/json')) {
            $this->json(['success' => true, 'status' => 'rechazado']);
            return;
        }

        header('Location: /absences?saved=rejected');
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('talent.absences.delete');

        $repo = new AbsencesRepository($this->db);
        $absence = $repo->findById($id);

        if (!$absence) {
            http_response_code(404);
            exit('Ausencia no encontrada.');
        }

        $repo->delete($id);

        header('Location: /absences?saved=deleted');
    }

    public static function absenceTypeLabel(string $type): string
    {
        return self::ABSENCE_TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}
