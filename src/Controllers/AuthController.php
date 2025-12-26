<?php

declare(strict_types=1);

class AuthController extends Controller
{
    public function showLogin(): void
    {
        $configService = new ConfigService($this->db);
        $branding = $configService->getBranding();
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
        $configService = new ConfigService($this->db);
        $branding = $configService->getBranding();
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
}
