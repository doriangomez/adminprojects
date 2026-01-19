<?php

declare(strict_types=1);

class AuthController extends Controller
{
    public function showLogin(): void
    {
        $theme = (new ThemeRepository($this->db))->getActiveTheme();
        $configService = new ConfigService($this->db);
        $configData = $configService->getConfig();
        $appName = $this->getAppName();

        if ($this->auth->check()) {
            header('Location: /project/public/dashboard');
            return;
        }

        include __DIR__ . '/../Views/auth/login.php';
    }

    public function login(): void
    {
        $theme = (new ThemeRepository($this->db))->getActiveTheme();
        $configService = new ConfigService($this->db);
        $configData = $configService->getConfig();
        $appName = $this->getAppName();

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        if (!$this->auth->attempt($email, $password)) {
            $error = 'Credenciales inválidas';
            include __DIR__ . '/../Views/auth/login.php';
            return;
        }
        header('Location: /project/public/dashboard');
    }

    public function logout(): void
    {
        $this->auth->logout();
        header('Location: /project/public/login');
    }

    public function startImpersonation(): void
    {
        if (!$this->auth->startImpersonation((int) ($_POST['user_id'] ?? 0))) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $impersonator = $this->auth->impersonator();
        $current = $this->auth->user();
        $auditRepo = new AuditLogRepository($this->db);
        $auditRepo->log(
            $impersonator['id'] ?? null,
            'impersonation',
            (int) ($current['id'] ?? 0),
            'start',
            [
                'impersonator' => $impersonator,
                'impersonated' => $current,
            ]
        );

        header('Location: /project/public/dashboard');
    }

    public function stopImpersonation(): void
    {
        $impersonator = $this->auth->impersonator();
        $current = $this->auth->user();
        $auditRepo = new AuditLogRepository($this->db);
        $auditRepo->log(
            $impersonator['id'] ?? null,
            'impersonation',
            (int) ($current['id'] ?? 0),
            'stop',
            [
                'impersonator' => $impersonator,
                'impersonated' => $current,
            ]
        );

        $this->auth->stopImpersonation();
        header('Location: /project/public/dashboard');
    }
}
