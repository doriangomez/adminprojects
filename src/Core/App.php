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
        $migrator->ensureProjectDeliverySchema();
        $migrator->ensureProjectActiveColumn();
        $migrator->ensureUserProgressPermissionColumn();
        $migrator->ensureUserOutsourcingPermissionColumn();
        $migrator->ensureUserAuthTypeColumn();
        $migrator->ensureUserTimesheetPermissionColumns();
        $migrator->ensureClientDeletionCascades();
        $migrator->ensureAssignmentsTable();
        $migrator->ensureTalentSchema();
        $migrator->ensureSystemSettings();
        $migrator->resetProjectModuleDataOnce();
        $migrator->ensureProjectManagementPermission();
        $migrator->ensureTimesheetPermissions();
        $migrator->ensureOutsourcingModule();
        $migrator->ensureTimesheetSchema();
        $migrator->ensureNotificationsLog();
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
        if ($path === '/login/google' && $method === 'POST') {
            (new AuthController($this->db, $this->auth))->loginWithGoogle();
            return;
        }
        if ($path === '/logout') {
            (new AuthController($this->db, $this->auth))->logout();
            return;
        }

        if (!$this->auth->check()) {
            header('Location: /login');
            return;
        }

        if ($this->auth->isImpersonating() && $path !== '/impersonate/stop') {
            $this->logImpersonationRequest($path, $method);
        }

        if ($path === '/impersonate/start' && $method === 'POST') {
            (new AuthController($this->db, $this->auth))->startImpersonation();
            return;
        }

        if ($path === '/impersonate/stop' && $method === 'POST') {
            (new AuthController($this->db, $this->auth))->stopImpersonation();
            return;
        }

        if ($path === '/' || $path === '/dashboard') {
            (new DashboardController($this->db, $this->auth))->index();
            return;
        }

        if ($path === '/approvals' && $method === 'GET') {
            (new ApprovalsController($this->db, $this->auth))->index();
            return;
        }

        if ($path === '/users' && $method === 'GET') {
            (new UsersController($this->db, $this->auth))->index();
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

            if ($path === '/projects/create') {
                if ($method === 'POST') {
                    $controller->store();
                    return;
                }
                $controller->create();
                return;
            }

            if ($path === '/projects/delete' && $method === 'POST') {
                $controller->destroy();
                return;
            }

            if (preg_match('#^/projects/(\\d+)/inactivate$#', $path, $matches) && $method === 'POST') {
                $controller->inactivate((int) $matches[1]);
                return;
            }

            if ($path === '/projects/assign-talent' && $method === 'POST') {
                $controller->assignTalent();
                return;
            }
            if (preg_match('#^/projects/(\\d+)/design-inputs/(\\d+)/delete$#', $path, $matches) && $method === 'POST') {
                $controller->deleteDesignInput((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/design-inputs$#', $path, $matches) && $method === 'POST') {
                $controller->storeDesignInput((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/design-controls$#', $path, $matches) && $method === 'POST') {
                $controller->storeDesignControl((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/design-outputs$#', $path, $matches) && $method === 'POST') {
                $controller->updateDesignOutputs((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/design-changes$#', $path, $matches) && $method === 'POST') {
                $controller->storeDesignChange((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/design-changes/(\\d+)/approve$#', $path, $matches) && $method === 'POST') {
                $controller->approveDesignChange((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes/(\\d+)/files$#', $path, $matches) && $method === 'POST') {
                $controller->uploadNodeFile((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes/(\\d+)/download$#', $path, $matches) && $method === 'GET') {
                $controller->downloadNodeFile((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes/(\\d+)/delete$#', $path, $matches) && $method === 'POST') {
                $controller->deleteNode((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes/(\\d+)/document-flow$#', $path, $matches) && $method === 'POST') {
                $controller->saveDocumentFlow((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes/(\\d+)/document-review-approve$#', $path, $matches) && $method === 'POST') {
                $controller->approveDocumentReview((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes/(\\d+)/document-validate$#', $path, $matches) && $method === 'POST') {
                $controller->validateDocument((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes/(\\d+)/document-approve$#', $path, $matches) && $method === 'POST') {
                $controller->approveDocumentFinal((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes/(\\d+)/document-status$#', $path, $matches) && $method === 'POST') {
                $controller->updateDocumentStatus((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes/(\\d+)/document-metadata$#', $path, $matches) && $method === 'POST') {
                $controller->updateDocumentMetadata((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes/(\\d+)/document-history$#', $path, $matches) && $method === 'GET') {
                $controller->documentHistory((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes/(\\d+)$#', $path, $matches) && $method === 'DELETE') {
                $controller->deleteNode((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/sprints$#', $path, $matches) && $method === 'POST') {
                $controller->createSprint((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes$#', $path, $matches) && $method === 'POST') {
                $controller->createFolder((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes/(\\d+)/children$#', $path, $matches) && $method === 'GET') {
                $controller->listNodeChildren((int) $matches[1], (int) $matches[2]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/nodes/children$#', $path, $matches) && $method === 'GET') {
                $controller->listNodeChildren((int) $matches[1], null);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/progress$#', $path, $matches) && $method === 'POST') {
                $controller->updateProgress((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/notes$#', $path, $matches) && $method === 'POST') {
                $controller->createNote((int) $matches[1]);
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
            if (preg_match('#^/projects/(\\d+)/tasks$#', $path, $matches)) {
                if ($method === 'POST') {
                    $controller->storeTask((int) $matches[1]);
                    return;
                }
                if ($method === 'GET') {
                    $controller->tasks((int) $matches[1]);
                    return;
                }
            }
            if (preg_match('#^/projects/(\\d+)/outsourcing$#', $path, $matches) && $method === 'GET') {
                $controller->outsourcing((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/outsourcing/settings$#', $path, $matches) && $method === 'POST') {
                $controller->updateOutsourcingSettings((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/outsourcing/followups$#', $path, $matches) && $method === 'POST') {
                $controller->createOutsourcingFollowup((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/outsourcing/assignments$#', $path, $matches) && $method === 'POST') {
                $controller->assignTalent((int) $matches[1]);
                return;
            }
            if (preg_match('#^/projects/(\\d+)/outsourcing/assignments/(\\d+)/status$#', $path, $matches) && $method === 'POST') {
                $controller->updateOutsourcingAssignmentStatus((int) $matches[1], (int) $matches[2]);
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

        if (str_starts_with($path, '/tasks')) {
            $controller = new TasksController($this->db, $this->auth);
            if (preg_match('#^/tasks/(\\d+)/edit$#', $path, $matches) && $method === 'GET') {
                $controller->edit((int) $matches[1]);
                return;
            }
            if (preg_match('#^/tasks/(\\d+)/update$#', $path, $matches) && $method === 'POST') {
                $controller->update((int) $matches[1]);
                return;
            }
            if (preg_match('#^/tasks/(\\d+)/status$#', $path, $matches) && $method === 'POST') {
                $controller->updateStatus((int) $matches[1]);
                return;
            }
            $controller->index();
            return;
        }

        if (str_starts_with($path, '/outsourcing')) {
            $controller = new OutsourcingServicesController($this->db, $this->auth);
            if ($path === '/outsourcing' && $method === 'POST') {
                $controller->store();
                return;
            }
            if (preg_match('#^/outsourcing/(\\d+)$#', $path, $matches) && $method === 'GET') {
                $controller->show((int) $matches[1]);
                return;
            }
            if (preg_match('#^/outsourcing/(\\d+)/status$#', $path, $matches) && $method === 'POST') {
                $controller->updateStatus((int) $matches[1]);
                return;
            }
            if (preg_match('#^/outsourcing/(\\d+)/frequency$#', $path, $matches) && $method === 'POST') {
                $controller->updateFrequency((int) $matches[1]);
                return;
            }
            if (preg_match('#^/outsourcing/(\\d+)/followups$#', $path, $matches) && $method === 'POST') {
                $controller->createFollowup((int) $matches[1]);
                return;
            }
            if (preg_match('#^/outsourcing/(\\d+)/followups/(\\d+)/close$#', $path, $matches) && $method === 'POST') {
                $controller->closeFollowup((int) $matches[1], (int) $matches[2]);
                return;
            }
            if ($path === '/outsourcing/talents' && $method === 'POST') {
                $controller->storeTalent();
                return;
            }

            $controller->index();
            return;
        }

        if (str_starts_with($path, '/talents')) {
            $controller = new TalentsController($this->db, $this->auth);
            if ($path === '/talents/create' && $method === 'POST') {
                $controller->store();
                return;
            }
            if ($path === '/talents/update' && $method === 'POST') {
                $controller->update();
                return;
            }
            if ($path === '/talents/delete' && $method === 'POST') {
                $controller->destroy();
                return;
            }

            $controller->index();
            return;
        }

        if (str_starts_with($path, '/timesheets')) {
            $controller = new TimesheetsController($this->db, $this->auth);
            if ($path === '/timesheets/create' && $method === 'POST') {
                $controller->create();
                return;
            }
            if (preg_match('#^/timesheets/(\\d+)/(approve|reject)$#', $path, $matches) && $method === 'POST') {
                $controller->updateStatus((int) $matches[1], $matches[2]);
                return;
            }

            $controller->index();
            return;
        }

        if (str_starts_with($path, '/config')) {
            $controller = new ConfigController($this->db, $this->auth);
            if (($path === '/config' || $path === '/config/theme') && $method === 'POST') {
                $controller->updateTheme();
                return;
            }

            if ($path === '/config/notifications' && $method === 'POST') {
                $controller->updateNotifications();
                return;
            }

            if ($path === '/config/google-workspace' && $method === 'POST') {
                $controller->updateGoogleWorkspace();
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

            if (str_starts_with($path, '/config/risk-catalog') && $method === 'POST') {
                if ($path === '/config/risk-catalog/create') {
                    $controller->storeRisk();
                    return;
                }
                if ($path === '/config/risk-catalog/update') {
                    $controller->updateRisk();
                    return;
                }
                if ($path === '/config/risk-catalog/delete') {
                    $controller->deleteRisk();
                    return;
                }
            }

            $controller->index();
            return;
        }

        http_response_code(404);
        echo 'Ruta no encontrada';
    }

    private function logImpersonationRequest(string $path, string $method): void
    {
        $impersonator = $this->auth->impersonator();
        $current = $this->auth->user();

        if (empty($impersonator) || empty($current)) {
            return;
        }

        try {
            $auditRepo = new AuditLogRepository($this->db);
            $auditRepo->log(
                $impersonator['id'] ?? null,
                'impersonation',
                (int) ($current['id'] ?? 0),
                'request',
                [
                    'impersonator' => $impersonator,
                    'impersonated' => $current,
                    'path' => $path,
                    'method' => $method,
                ]
            );
        } catch (\Throwable $e) {
            error_log('No se pudo registrar auditoría de impersonación: ' . $e->getMessage());
        }
    }
}
