<?php

declare(strict_types=1);

use App\Repositories\AbsencesRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\TalentsRepository;

class AbsencesController extends Controller
{
    public function index(): void
    {
        $config = (new ConfigService($this->db))->getConfig();
        $absencesEnabled = (bool) ($config['operational_rules']['absences']['enabled'] ?? true);

        if (!$absencesEnabled) {
            $this->denyAccess('El módulo de ausencias está deshabilitado. Actívalo en Configuración → Talento.');
        }

        $this->requirePermission('talent.absences.view');

        $repo = new AbsencesRepository($this->db);
        $talentsRepo = new TalentsRepository($this->db);

        $talentId = isset($_GET['talent_id']) ? (int) $_GET['talent_id'] : null;
        $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
        $startFrom = isset($_GET['start_from']) ? trim((string) $_GET['start_from']) : null;
        $endTo = isset($_GET['end_to']) ? trim((string) $_GET['end_to']) : null;

        $absences = $repo->listAll($talentId, $status ?: null, $startFrom ?: null, $endTo ?: null);
        $talents = $talentsRepo->summary();

        $this->render('absences/index', [
            'title' => 'Ausencias',
            'absences' => $absences,
            'talents' => $talents,
            'canCreate' => $this->auth->can('talent.absences.create'),
            'canEdit' => $this->auth->can('talent.absences.edit'),
            'canDelete' => $this->auth->can('talent.absences.delete'),
            'canApprove' => $this->auth->can('talent.absences.approve'),
            'flashMessage' => $_GET['saved'] ?? '',
        ]);
    }

    public function create(): void
    {
        $config = (new ConfigService($this->db))->getConfig();
        if (!($config['operational_rules']['absences']['enabled'] ?? true)) {
            $this->denyAccess('El módulo de ausencias está deshabilitado.');
        }

        $this->requirePermission('talent.absences.create');

        $talentsRepo = new TalentsRepository($this->db);

        $this->render('absences/form', [
            'title' => 'Nueva ausencia',
            'absence' => null,
            'talents' => $talentsRepo->summary(),
        ]);
    }

    public function store(): void
    {
        $config = (new ConfigService($this->db))->getConfig();
        if (!($config['operational_rules']['absences']['enabled'] ?? true)) {
            $this->denyAccess('El módulo de ausencias está deshabilitado.');
        }

        $this->requirePermission('talent.absences.create');

        $user = $this->auth->user() ?? [];

        try {
            $service = new AbsenceService($this->db);
            $created = $service->createAbsence($_POST);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'absence',
                (int) ($created['id'] ?? 0),
                'created',
                ['talent_id' => $created['talent_id'] ?? null]
            );

            header('Location: /absences?saved=created');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->renderFormWithError($e->getMessage(), $_POST);
        } catch (\Throwable $e) {
            error_log('Error al crear ausencia: ' . $e->getMessage());
            http_response_code(500);
            $this->renderFormWithError('No se pudo registrar la ausencia.');
        }
    }

    public function edit(int $id): void
    {
        $config = (new ConfigService($this->db))->getConfig();
        if (!($config['operational_rules']['absences']['enabled'] ?? true)) {
            $this->denyAccess('El módulo de ausencias está deshabilitado.');
        }

        $this->requirePermission('talent.absences.edit');

        $repo = new AbsencesRepository($this->db);
        $absence = $repo->find($id);
        if (!$absence) {
            http_response_code(404);
            exit('Ausencia no encontrada.');
        }

        $talentsRepo = new TalentsRepository($this->db);

        $this->render('absences/form', [
            'title' => 'Editar ausencia',
            'absence' => $absence,
            'talents' => $talentsRepo->summary(),
        ]);
    }

    public function update(int $id): void
    {
        $config = (new ConfigService($this->db))->getConfig();
        if (!($config['operational_rules']['absences']['enabled'] ?? true)) {
            $this->denyAccess('El módulo de ausencias está deshabilitado.');
        }

        $this->requirePermission('talent.absences.edit');

        $user = $this->auth->user() ?? [];

        try {
            $service = new AbsenceService($this->db);
            $service->updateAbsence($id, $_POST);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'absence',
                $id,
                'updated',
                []
            );

            header('Location: /absences?saved=updated');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->renderFormWithError($e->getMessage(), $_POST, $id);
        } catch (\Throwable $e) {
            error_log('Error al actualizar ausencia: ' . $e->getMessage());
            http_response_code(500);
            $this->renderFormWithError('No se pudo actualizar la ausencia.', $_POST, $id);
        }
    }

    public function destroy(int $id): void
    {
        $config = (new ConfigService($this->db))->getConfig();
        if (!($config['operational_rules']['absences']['enabled'] ?? true)) {
            $this->denyAccess('El módulo de ausencias está deshabilitado.');
        }

        $this->requirePermission('talent.absences.delete');

        $user = $this->auth->user() ?? [];

        try {
            $service = new AbsenceService($this->db);
            $service->deleteAbsence($id);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'absence',
                $id,
                'deleted',
                []
            );

            header('Location: /absences?saved=deleted');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error al eliminar ausencia: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo eliminar la ausencia.');
        }
    }

    public function approve(int $id): void
    {
        $config = (new ConfigService($this->db))->getConfig();
        if (!($config['operational_rules']['absences']['enabled'] ?? true)) {
            $this->denyAccess('El módulo de ausencias está deshabilitado.');
        }

        $this->requirePermission('talent.absences.approve');

        $user = $this->auth->user() ?? [];

        try {
            $service = new AbsenceService($this->db);
            $service->approveAbsence($id, (int) ($user['id'] ?? 0));

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'absence',
                $id,
                'approved',
                []
            );

            header('Location: /absences?saved=approved');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error al aprobar ausencia: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo aprobar la ausencia.');
        }
    }

    public function reject(int $id): void
    {
        $config = (new ConfigService($this->db))->getConfig();
        if (!($config['operational_rules']['absences']['enabled'] ?? true)) {
            $this->denyAccess('El módulo de ausencias está deshabilitado.');
        }

        $this->requirePermission('talent.absences.approve');

        $user = $this->auth->user() ?? [];

        try {
            $service = new AbsenceService($this->db);
            $service->rejectAbsence($id);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'absence',
                $id,
                'rejected',
                []
            );

            header('Location: /absences?saved=rejected');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error al rechazar ausencia: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo rechazar la ausencia.');
        }
    }

    private function renderFormWithError(string $message, array $post = [], ?int $id = null): void
    {
        $talentsRepo = new TalentsRepository($this->db);
        $absence = $id ? (new AbsencesRepository($this->db))->find($id) : null;
        $merged = $absence ? array_merge($absence, $post) : $post;

        $this->render('absences/form', [
            'title' => $id ? 'Editar ausencia' : 'Nueva ausencia',
            'absence' => $merged,
            'talents' => $talentsRepo->summary(),
            'errorMessage' => $message,
        ]);
    }
}
