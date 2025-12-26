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
        $migrator = new DatabaseMigrator($this->db);
        $migrator->ensureClientSchema();
        $migrator->ensureClientPmIntegrity();
        $migrator->ensureProjectPmIntegrity();
        $migrator->ensureAssignmentsTable();
        $migrator->ensurePortfoliosTable();
        $migrator->ensureProjectPortfolioLink();
        $migrator->ensureProjectManagementPermission();
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
            if ($path === '/clients/create') {
                if ($method === 'POST') {
                    $controller->store();
                    return;
                }

                $controller->create();
                return;
            }

            if (preg_match('#^/clients/(\\d+)/edit$#', $path, $matches)) {
                $clientId = (int) $matches[1];
                if ($method === 'POST') {
                    $controller->update($clientId);
                    return;
                }

                $controller->edit($clientId);
                return;
            }

            if ($path === '/clients/delete' && $method === 'POST') {
                $controller->destroy();
                return;
            }

            if (preg_match('#^/clients/(\\d+)/inactivate$#', $path, $matches) && $method === 'POST') {
                $controller->inactivate((int) $matches[1]);
                return;
            }

            if (preg_match('#^/clients/(\\d+)$#', $path, $matches)) {
                $controller->show((int) $matches[1]);
                return;
            }

            $controller->index();
            return;
        }

        if (str_starts_with($path, '/projects')) {
            $controller = new ProjectsController($this->db, $this->auth);

            if ($path === '/projects/portfolio') {
                $controller->portfolio();
                return;
            }

            if ($path === '/projects/assign-talent' && $method === 'POST') {
                $controller->assignTalent();
                return;
            }
            if (preg_match('#^/projects/(\\d+)$#', $path, $matches) && $method === 'GET') {
                $controller->show((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/edit$#', $path, $matches)) {
                if ($method === 'POST') {
                    $controller->update((int) $matches[1]);
                    return;
                }
                $controller->edit((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/talent$#', $path, $matches)) {
                if ($method === 'POST') {
                    $controller->assignTalent((int) $matches[1]);
                    return;
                }
                $controller->talent((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/costs$#', $path, $matches) && $method === 'GET') {
                $controller->costs((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/close$#', $path, $matches)) {
                if ($method === 'POST') {
                    $controller->close((int) $matches[1]);
                    return;
                }
                $controller->confirmClose((int) $matches[1]);
                return;
            }

            $controller->index();
            return;
        }

        if (str_starts_with($path, '/portfolio')) {
            $controller = new PortfoliosController($this->db, $this->auth);
            if ($path === '/portfolio/create') {
                $controller->create();
                return;
            }
            if ($path === '/portfolio/wizard') {
                if ($method === 'POST') {
                    $controller->storeWizard();
                    return;
                }

                $controller->wizard();
                return;
            }
            if ($path === '/portfolio' && $method === 'POST') {
                $controller->store();
                return;
            }

            if ($path === '/portfolio/update' && $method === 'POST') {
                $controller->update();
                return;
            }

            if ($path === '/portfolio/delete' && $method === 'POST') {
                $controller->destroy();
                return;
            }

            if (preg_match('#^/portfolio/(\\d+)/inactivate$#', $path, $matches) && $method === 'POST') {
                $controller->inactivate((int) $matches[1]);
                return;
            }

            $controller->index();
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
            if (($path === '/config' || $path === '/config/theme') && $method === 'POST') {
                $controller->updateTheme();
                return;
            }

            if ($path === '/config/users/create' && $method === 'POST') {
                $controller->storeUser();
                return;
            }

            if ($path === '/config/users/update' && $method === 'POST') {
                $controller->updateUser();
                return;
            }

            if ($path === '/config/users/deactivate' && $method === 'POST') {
                $controller->deactivateUser();
                return;
            }

            if ($path === '/config/roles/create' && $method === 'POST') {
                $controller->storeRole();
                return;
            }

            if ($path === '/config/roles/update' && $method === 'POST') {
                $controller->updateRole();
                return;
            }

            if (str_starts_with($path, '/config/master-files') && $method === 'POST') {
                if ($path === '/config/master-files/create') {
                    $controller->manageMasterFile('create');
                    return;
                }
                if ($path === '/config/master-files/update') {
                    $controller->manageMasterFile('update');
                    return;
                }
                if ($path === '/config/master-files/delete') {
                    $controller->manageMasterFile('delete');
                    return;
                }
            }

            $controller->index();
            return;
        }

        http_response_code(404);
        echo 'Ruta no encontrada';
    }
}
