<?php

declare(strict_types=1);

class App
{
    private Database $db;
    private Auth $auth;

    public function __construct()
    {
        $config = require __DIR__ . '/../config.php';
        $this->db = new Database($config['db']);
        $this->auth = new Auth($this->db);
    }

    public function handle(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'];

        $basePath = '/project/public';
        if (str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }

        if ($path === '/login' && $method === 'GET') {
            (new AuthController($this->db, $this->auth))->showLogin();
            return;
        }
        if ($path === '/login' && $method === 'POST') {
            (new AuthController($this->db, $this->auth))->login();
            return;
        }
        if ($path === '/logout') {
            (new AuthController($this->db, $this->auth))->logout();
            return;
        }

        if (!$this->auth->check()) {
            header('Location: /project/public/login');
            return;
        }

        if ($path === '/' || $path === '/dashboard') {
            (new DashboardController($this->db, $this->auth))->index();
            return;
        }

        if (str_starts_with($path, '/clients')) {
            $controller = new ClientsController($this->db, $this->auth);
            if ($path === '/clients/create' && $method === 'POST') {
                $controller->store();
                return;
            }
            if ($path === '/clients/edit' && $method === 'POST') {
                $controller->update();
                return;
            }
            if ($path === '/clients/delete' && $method === 'POST') {
                $controller->destroy();
                return;
            }
            $controller->index();
            return;
        }

        if (str_starts_with($path, '/projects')) {
            (new ProjectsController($this->db, $this->auth))->index();
            return;
        }

        if (str_starts_with($path, '/tasks')) {
            (new TasksController($this->db, $this->auth))->index();
            return;
        }

        if (str_starts_with($path, '/talents')) {
            (new TalentsController($this->db, $this->auth))->index();
            return;
        }

        if (str_starts_with($path, '/timesheets')) {
            (new TimesheetsController($this->db, $this->auth))->index();
            return;
        }

        if (str_starts_with($path, '/config')) {
            $controller = new ConfigController($this->db, $this->auth);
            if ($path === '/config' && $method === 'POST') {
                $controller->update();
                return;
            }

            $controller->index();
            return;
        }

        http_response_code(404);
        echo 'Ruta no encontrada';
    }
}
