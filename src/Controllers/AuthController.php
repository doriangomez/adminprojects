<?php

declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\ThemeRepository;

class AuthController extends Controller
{
    private function resolveBasePath(): string
    {
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return str_starts_with($requestPath, '/project/public') ? '/project/public' : '';
    }

    public function showLogin(): void
    {
        $theme = (new ThemeRepository($this->db))->getActiveTheme();
        $configService = new ConfigService($this->db);
        $configData = $configService->getConfig();
        $appName = $this->getAppName();
        $basePath = $this->resolveBasePath();

        if ($this->auth->check()) {
            header('Location: /dashboard');
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
        $basePath = $this->resolveBasePath();

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if (!$this->auth->attempt($email, $password)) {
            (new AuditLogRepository($this->db))->log(null, 'google_login', 0, 'rejected_manual_login', ['email' => $email]);
            $error = 'Credenciales inválidas';
            include __DIR__ . '/../Views/auth/login.php';
            return;
        }

        header('Location: /dashboard');
    }

    public function loginWithGoogle(): void
    {
        $theme = (new ThemeRepository($this->db))->getActiveTheme();
        $configService = new ConfigService($this->db);
        $configData = $configService->getConfig();
        $appName = $this->getAppName();
        $basePath = $this->resolveBasePath();

        $email = strtolower(trim((string) ($_POST['google_email'] ?? '')));
        $googleConfig = $configData['access']['google_workspace'] ?? [];
        $domain = strtolower(trim((string) ($googleConfig['corporate_domain'] ?? '')));

        if (!(bool) ($googleConfig['enabled'] ?? false)) {
            (new AuditLogRepository($this->db))->log(null, 'google_login', 0, 'rejected_google_disabled', ['email' => $email]);
            $error = 'El acceso con Google Workspace está deshabilitado.';
            include __DIR__ . '/../Views/auth/login.php';
            return;
        }

        if ($email === '' || $domain === '' || !str_ends_with($email, '@' . $domain)) {
            (new AuditLogRepository($this->db))->log(null, 'google_login', 0, 'rejected_invalid_domain', ['email' => $email, 'domain' => $domain]);
            $error = 'Correo no permitido para acceso corporativo.';
            include __DIR__ . '/../Views/auth/login.php';
            return;
        }

        if (!$this->auth->attemptGoogle($email)) {
            (new AuditLogRepository($this->db))->log(null, 'google_login', 0, 'rejected_not_enrolled_or_inactive', ['email' => $email]);
            $error = 'Usuario no enrolado, inactivo o no configurado para Google.';
            include __DIR__ . '/../Views/auth/login.php';
            return;
        }

        $current = $this->auth->user();
        (new AuditLogRepository($this->db))->log(
            (int) ($current['id'] ?? 0),
            'google_login',
            (int) ($current['id'] ?? 0),
            'success',
            [
                'email' => $email,
                'auth_type' => 'google',
            ]
        );

        header('Location: /dashboard');
    }

    public function logout(): void
    {
        $this->auth->logout();
        header('Location: /login');
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

        header('Location: /dashboard');
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
        header('Location: /dashboard');
    }
}
