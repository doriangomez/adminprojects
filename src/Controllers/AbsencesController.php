<?php

declare(strict_types=1);

use App\Repositories\AbsencesRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\TalentsRepository;

class AbsencesController extends Controller
{
    public function index(): void
    {
        $this->ensureAbsencesAccess();

        $repo = new AbsencesRepository($this->db);
        $talentsRepo = new TalentsRepository($this->db);

        $filters = [
            'talent_id' => $_GET['talent_id'] ?? '',
            'status' => $_GET['status'] ?? '',
            'absence_type' => $_GET['absence_type'] ?? '',
            'from_date' => $_GET['from_date'] ?? '',
            'to_date' => $_GET['to_date'] ?? '',
        ];

        $absences = $repo->listAll(array_filter($filters));
        $stats = $repo->countByStatus();
        $absenceConfig = $this->absenceConfig();

        $this->render('absences/index', [
            'title' => 'Gestión de ausencias',
            'absences' => $absences,
            'talents' => $talentsRepo->summary(),
            'absenceTypes' => $repo->absenceTypes(),
            'statusLabels' => $repo->statusLabels(),
            'filters' => $filters,
            'stats' => $stats,
            'canManage' => $this->auth->canManageAbsences(),
            'canApprove' => $this->auth->canApproveAbsences(),
            'canDelete' => $this->auth->canDeleteAbsences(),
            'absenceConfig' => $absenceConfig,
            'flashMessage' => $_GET['saved'] ?? '',
        ]);
    }

    public function store(): void
    {
        $this->ensureAbsencesManageAccess();

        $repo = new AbsencesRepository($this->db);
        $user = $this->auth->user() ?? [];
        $absenceConfig = $this->absenceConfig();

        $absenceType = trim((string) ($_POST['absence_type'] ?? ''));
        if ($absenceType === 'vacaciones' && empty($absenceConfig['enable_vacations'])) {
            http_response_code(422);
            exit('Las vacaciones no están habilitadas en la configuración.');
        }

        $status = 'pendiente';
        if ($this->auth->hasRole('Administrador') || $this->auth->canApproveAbsences()) {
            $status = trim((string) ($_POST['status'] ?? 'pendiente'));
            if (!in_array($status, ['pendiente', 'aprobado', 'rechazado'], true)) {
                $status = 'pendiente';
            }
        }

        $isFullDay = isset($_POST['is_full_day']) || !isset($_POST['hours_mode']);
        $hours = $isFullDay ? null : (float) ($_POST['hours'] ?? 0);

        $id = $repo->create([
            'talent_id' => (int) $_POST['talent_id'],
            'user_id' => $user['id'] ?? null,
            'absence_type' => $absenceType,
            'start_date' => $_POST['start_date'],
            'end_date' => $_POST['end_date'],
            'hours' => $hours,
            'is_full_day' => $isFullDay ? 1 : 0,
            'status' => $status,
            'reason' => trim((string) ($_POST['reason'] ?? '')),
        ]);

        if ($status === 'aprobado') {
            $repo->updateStatus($id, 'aprobado', (int) ($user['id'] ?? 0));
        }

        (new AuditLogRepository($this->db))->log(
            (int) ($user['id'] ?? 0),
            'talent_absence',
            $id,
            'created',
            ['status' => $status, 'type' => $absenceType]
        );

        header('Location: /absences?saved=created');
    }

    public function update(int $id): void
    {
        $this->ensureAbsencesManageAccess();

        $repo = new AbsencesRepository($this->db);
        $existing = $repo->find($id);
        if (!$existing) {
            http_response_code(404);
            exit('Ausencia no encontrada.');
        }

        $isFullDay = isset($_POST['is_full_day']) || !isset($_POST['hours_mode']);
        $hours = $isFullDay ? null : (float) ($_POST['hours'] ?? 0);

        $repo->update($id, [
            'absence_type' => trim((string) ($_POST['absence_type'] ?? $existing['absence_type'])),
            'start_date' => $_POST['start_date'] ?? $existing['start_date'],
            'end_date' => $_POST['end_date'] ?? $existing['end_date'],
            'hours' => $hours,
            'is_full_day' => $isFullDay ? 1 : 0,
            'reason' => trim((string) ($_POST['reason'] ?? '')),
        ]);

        $user = $this->auth->user() ?? [];
        (new AuditLogRepository($this->db))->log(
            (int) ($user['id'] ?? 0),
            'talent_absence',
            $id,
            'updated',
            []
        );

        header('Location: /absences?saved=updated');
    }

    public function approve(int $id): void
    {
        $this->ensureAbsencesApproveAccess();

        $repo = new AbsencesRepository($this->db);
        $existing = $repo->find($id);
        if (!$existing) {
            http_response_code(404);
            exit('Ausencia no encontrada.');
        }

        $user = $this->auth->user() ?? [];
        $repo->updateStatus($id, 'aprobado', (int) ($user['id'] ?? 0));

        (new AuditLogRepository($this->db))->log(
            (int) ($user['id'] ?? 0),
            'talent_absence',
            $id,
            'approved',
            []
        );

        header('Location: /absences?saved=approved');
    }

    public function reject(int $id): void
    {
        $this->ensureAbsencesApproveAccess();

        $repo = new AbsencesRepository($this->db);
        $existing = $repo->find($id);
        if (!$existing) {
            http_response_code(404);
            exit('Ausencia no encontrada.');
        }

        $user = $this->auth->user() ?? [];
        $repo->updateStatus($id, 'rechazado', (int) ($user['id'] ?? 0));

        (new AuditLogRepository($this->db))->log(
            (int) ($user['id'] ?? 0),
            'talent_absence',
            $id,
            'rejected',
            []
        );

        header('Location: /absences?saved=rejected');
    }

    public function destroy(int $id): void
    {
        $this->ensureAbsencesDeleteAccess();

        $repo = new AbsencesRepository($this->db);
        $existing = $repo->find($id);
        if (!$existing) {
            http_response_code(404);
            exit('Ausencia no encontrada.');
        }

        $user = $this->auth->user() ?? [];
        $repo->delete($id);

        (new AuditLogRepository($this->db))->log(
            (int) ($user['id'] ?? 0),
            'talent_absence',
            $id,
            'deleted',
            ['talent_id' => $existing['talent_id'] ?? 0]
        );

        header('Location: /absences?saved=deleted');
    }

    public function import(): void
    {
        $this->ensureAbsencesManageAccess();

        $repo = new AbsencesRepository($this->db);
        $user = $this->auth->user() ?? [];

        $csvContent = trim((string) ($_POST['csv_data'] ?? ''));
        if ($csvContent === '') {
            http_response_code(422);
            exit('No se proporcionaron datos para importar.');
        }

        $rows = $this->parseCsvImport($csvContent);
        $imported = $repo->importBulk($rows, (int) ($user['id'] ?? 0));

        (new AuditLogRepository($this->db))->log(
            (int) ($user['id'] ?? 0),
            'talent_absence',
            0,
            'bulk_import',
            ['imported' => $imported, 'total_rows' => count($rows)]
        );

        header('Location: /absences?saved=imported&count=' . $imported);
    }

    private function parseCsvImport(string $csvContent): array
    {
        $lines = preg_split('/\R/', $csvContent) ?: [];
        $rows = [];
        $headerParsed = false;
        $headerMap = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $cols = str_getcsv($line, ',');

            if (!$headerParsed) {
                $normalizedHeaders = array_map(fn ($h) => strtolower(trim((string) $h)), $cols);
                $expectedHeaders = ['talent_id', 'absence_type', 'start_date', 'end_date'];
                $matchCount = count(array_intersect($normalizedHeaders, $expectedHeaders));
                if ($matchCount >= 3) {
                    $headerMap = array_flip($normalizedHeaders);
                    $headerParsed = true;
                    continue;
                }
                $headerMap = [
                    'talent_id' => 0,
                    'absence_type' => 1,
                    'start_date' => 2,
                    'end_date' => 3,
                    'hours' => 4,
                    'is_full_day' => 5,
                    'status' => 6,
                    'reason' => 7,
                ];
                $headerParsed = true;
            }

            $row = [];
            foreach (['talent_id', 'absence_type', 'start_date', 'end_date', 'hours', 'is_full_day', 'status', 'reason'] as $field) {
                $idx = $headerMap[$field] ?? null;
                $row[$field] = ($idx !== null && isset($cols[$idx])) ? trim((string) $cols[$idx]) : null;
            }

            if (!empty($row['talent_id']) && !empty($row['start_date'])) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function absenceConfig(): array
    {
        $config = (new ConfigService($this->db))->getConfig();
        return $config['operational_rules']['absences'] ?? [];
    }

    private function ensureAbsencesAccess(): void
    {
        if (!$this->auth->isAbsencesEnabled()) {
            $this->denyAccess('El módulo de gestión de ausencias no está habilitado.');
        }

        if (!$this->auth->canAccessAbsences()) {
            $this->denyAccess('No tienes permisos para ver las ausencias.');
        }
    }

    private function ensureAbsencesManageAccess(): void
    {
        $this->ensureAbsencesAccess();

        if (!$this->auth->canManageAbsences()) {
            $this->denyAccess('No tienes permisos para gestionar ausencias.');
        }
    }

    private function ensureAbsencesApproveAccess(): void
    {
        $this->ensureAbsencesAccess();

        if (!$this->auth->canApproveAbsences()) {
            $this->denyAccess('No tienes permisos para aprobar ausencias.');
        }
    }

    private function ensureAbsencesDeleteAccess(): void
    {
        $this->ensureAbsencesAccess();

        if (!$this->auth->canDeleteAbsences()) {
            $this->denyAccess('No tienes permisos para eliminar ausencias.');
        }
    }
}
