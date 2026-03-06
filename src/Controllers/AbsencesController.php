<?php

declare(strict_types=1);

use App\Repositories\AbsencesRepository;

class AbsencesController extends Controller
{
    private function service(): AbsenceService
    {
        return new AbsenceService($this->db);
    }

    public function index(): void
    {
        $user = $this->auth->user() ?? [];
        $isAdmin = in_array($user['role'] ?? '', ['Administrador', 'PMO'], true);

        $service = $this->service();
        $repo = $service->getRepository();

        $statusFilter = trim((string) ($_GET['status'] ?? ''));
        $fromFilter   = trim((string) ($_GET['from'] ?? ''));
        $toFilter     = trim((string) ($_GET['to'] ?? ''));

        if ($isAdmin) {
            $absences = $repo->listAll(
                $statusFilter !== '' ? $statusFilter : null,
                $fromFilter !== '' ? $fromFilter : null,
                $toFilter !== '' ? $toFilter : null
            );
        } else {
            $talentRow = $this->db->fetchOne(
                'SELECT id FROM talents WHERE user_id = :uid LIMIT 1',
                [':uid' => $user['id'] ?? 0]
            );
            $absences = $talentRow
                ? $repo->listForTalent(
                    (int) $talentRow['id'],
                    $fromFilter !== '' ? $fromFilter : null,
                    $toFilter !== '' ? $toFilter : null
                )
                : [];
        }

        $this->render('absences/index', [
            'title'         => 'Ausencias y vacaciones',
            'absences'      => $absences,
            'talents'       => $service->talentOptions(),
            'absenceTypes'  => AbsencesRepository::ABSENCE_TYPES,
            'typeColors'    => AbsencesRepository::TYPE_COLORS,
            'isAdmin'       => $isAdmin,
            'statusFilter'  => $statusFilter,
            'fromFilter'    => $fromFilter,
            'toFilter'      => $toFilter,
        ]);
    }

    public function store(): void
    {
        $user = $this->auth->user() ?? [];
        $isAdmin = in_array($user['role'] ?? '', ['Administrador', 'PMO'], true);
        $service = $this->service();

        try {
            $input = $_POST;

            if (!$isAdmin) {
                $talentRow = $this->db->fetchOne(
                    'SELECT id FROM talents WHERE user_id = :uid LIMIT 1',
                    [':uid' => $user['id'] ?? 0]
                );
                if (!$talentRow) {
                    throw new InvalidArgumentException('No tienes un talento asociado.');
                }
                $input['talent_id'] = (int) $talentRow['id'];
            }

            $autoApprove = $isAdmin && !empty($input['auto_approve']);
            $service->createAbsence($input, (int) ($user['id'] ?? 0), $autoApprove);

            $this->redirectBack('/absences', 'Ausencia registrada correctamente.');
        } catch (InvalidArgumentException $e) {
            $this->redirectBack('/absences', null, $e->getMessage());
        }
    }

    public function approve(int $id): void
    {
        $user = $this->auth->user() ?? [];
        if (!in_array($user['role'] ?? '', ['Administrador', 'PMO'], true)) {
            http_response_code(403);
            exit('Sin permisos.');
        }

        try {
            $this->service()->approve($id, (int) ($user['id'] ?? 0));
            $this->redirectBack('/absences', 'Ausencia aprobada.');
        } catch (InvalidArgumentException $e) {
            $this->redirectBack('/absences', null, $e->getMessage());
        }
    }

    public function reject(int $id): void
    {
        $user = $this->auth->user() ?? [];
        if (!in_array($user['role'] ?? '', ['Administrador', 'PMO'], true)) {
            http_response_code(403);
            exit('Sin permisos.');
        }

        try {
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $this->service()->reject($id, (int) ($user['id'] ?? 0), $reason);
            $this->redirectBack('/absences', 'Ausencia rechazada.');
        } catch (InvalidArgumentException $e) {
            $this->redirectBack('/absences', null, $e->getMessage());
        }
    }

    public function destroy(int $id): void
    {
        $user = $this->auth->user() ?? [];
        $isAdmin = in_array($user['role'] ?? '', ['Administrador', 'PMO'], true);

        try {
            $repo = $this->service()->getRepository();
            $absence = $repo->findById($id);
            if (!$absence) {
                throw new InvalidArgumentException('Ausencia no encontrada.');
            }

            if (!$isAdmin) {
                $talentRow = $this->db->fetchOne(
                    'SELECT id FROM talents WHERE user_id = :uid LIMIT 1',
                    [':uid' => $user['id'] ?? 0]
                );
                if (!$talentRow || (int) $absence['talent_id'] !== (int) $talentRow['id']) {
                    http_response_code(403);
                    exit('Sin permisos.');
                }
                if ($absence['status'] === 'aprobado') {
                    throw new InvalidArgumentException('No puedes eliminar una ausencia ya aprobada.');
                }
            }

            $this->service()->delete($id);
            $this->redirectBack('/absences', 'Ausencia eliminada.');
        } catch (InvalidArgumentException $e) {
            $this->redirectBack('/absences', null, $e->getMessage());
        }
    }

    private function redirectBack(string $default, ?string $success = null, ?string $error = null): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? $default;
        if (!str_contains($ref, '/absences')) {
            $ref = $default;
        }

        $sep = str_contains($ref, '?') ? '&' : '?';
        if ($success !== null) {
            $ref .= $sep . 'success=' . urlencode($success);
        } elseif ($error !== null) {
            $ref .= $sep . 'error=' . urlencode($error);
        }

        header('Location: ' . $ref);
        exit;
    }
}
