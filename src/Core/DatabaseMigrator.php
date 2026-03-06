<?php

declare(strict_types=1);

class DatabaseMigrator
{
    public function __construct(private Database $db)
    {
    }

    public function normalizeClientsSchema(): void
    {
        if (!$this->db->tableExists('clients')) {
            return;
        }

        try {
            $this->ensureClientPriorityCode();
            $this->ensureClientStatusCode();
            $this->ensureClientRiskCode();
            $this->ensureClientAreaCode();
        } catch (\PDOException $e) {
            error_log('Error normalizando tabla clients: ' . $e->getMessage());
        }
    }

    public function ensureClientPmIntegrity(): void
    {
        $this->ensurePmIntegrity('clients', 'status_code');
    }

    public function ensureClientSchema(): void
    {
        if (!$this->db->tableExists('clients')) {
            return;
        }

        try {
            $this->ensureClientPriorityCode();
            $this->ensureClientStatusCode();
            $this->ensureClientRiskCode();
            $this->ensureClientAreaCode();
            $this->dropLegacyClientColumns();
            $this->relaxLegacyClientColumns();
        } catch (\PDOException $e) {
            error_log('Error ejecutando normalización de clients: ' . $e->getMessage());
        }
    }

    public function ensureProjectPmIntegrity(): void
    {
        $this->ensurePmIntegrity('projects', 'client_id');
    }

    public function ensureProjectDeliverySchema(): void
    {
        if (!$this->db->tableExists('projects')) {
            return;
        }

        try {
            $this->addProjectMethodology();
            $this->addProjectPhase();
            $this->addProjectTreeMetadata();
            $this->ensureProjectIsoControls();
            $this->ensureProjectDesignInputsTable();
            $this->ensureProjectDesignControlsTable();
            $this->ensureProjectDesignChangesTable();
            $this->ensureProjectNodesTable();
            $this->ensureProjectFilesTable();
            $this->ensureRiskCatalogTable();
            $this->ensureProjectRiskEvaluationsTable();
            $this->seedRiskCatalog();
            $this->migrateProjectRiskSelections();
        } catch (\PDOException $e) {
            error_log('Error asegurando esquema de entrega de proyectos: ' . $e->getMessage());
        }
    }

    public function ensureClientDeletionCascades(): void
    {
        try {
            $this->ensureCascade('projects', 'client_id', 'clients', 'fk_projects_client_id_clients');
            $this->ensureCascade('tasks', 'project_id', 'projects', 'fk_tasks_project_id_projects');
            $this->ensureCascade('project_risks', 'project_id', 'projects', 'fk_project_risks_project_id_projects');
            $this->ensureCascade('project_risk_evaluations', 'project_id', 'projects', 'fk_project_risk_eval_project_id_projects');
            $this->ensureCascade('timesheets', 'task_id', 'tasks', 'fk_timesheets_task_id_tasks');
            $this->ensureCascade('project_talent_assignments', 'project_id', 'projects', 'fk_project_talent_assignments_project_id_projects');
            $this->ensureCascade('costs', 'project_id', 'projects', 'fk_costs_project_id_projects');
            $this->ensureCascade('revenues', 'project_id', 'projects', 'fk_revenues_project_id_projects');
            $this->ensureCascade('contracts', 'client_id', 'clients', 'fk_contracts_client_id_clients');
            $this->ensureCascade('outsourcing_services', 'client_id', 'clients', 'fk_outsourcing_services_client_id_clients');
        } catch (\PDOException $e) {
            error_log('Error asegurando eliminaciones en cascada de clientes: ' . $e->getMessage());
        }
    }

    public function ensureProjectActiveColumn(): void
    {
        try {
            $this->addProjectActiveColumn();
        } catch (\PDOException $e) {
            error_log('Error agregando columna active a projects: ' . $e->getMessage());
        }
    }

    public function ensureUserProgressPermissionColumn(): void
    {
        if (!$this->db->tableExists('users') || $this->db->columnExists('users', 'can_update_project_progress')) {
            return;
        }

        try {
            $this->db->execute(
                'ALTER TABLE users ADD COLUMN can_update_project_progress TINYINT(1) DEFAULT 0 AFTER can_approve_documents'
            );
        } catch (\PDOException $e) {
            error_log('Error agregando columna can_update_project_progress a users: ' . $e->getMessage());
        }
    }

    public function ensureUserOutsourcingPermissionColumn(): void
    {
        if (!$this->db->tableExists('users') || $this->db->columnExists('users', 'can_access_outsourcing')) {
            return;
        }

        try {
            $this->db->execute(
                'ALTER TABLE users ADD COLUMN can_access_outsourcing TINYINT(1) DEFAULT 0 AFTER can_update_project_progress'
            );
        } catch (\PDOException $e) {
            error_log('Error agregando columna can_access_outsourcing a users: ' . $e->getMessage());
        }
    }

    public function ensureUserAuthTypeColumn(): void
    {
        if (!$this->db->tableExists('users') || $this->db->columnExists('users', 'auth_type')) {
            return;
        }

        try {
            $this->db->execute(
                "ALTER TABLE users ADD COLUMN auth_type ENUM('manual', 'google') NOT NULL DEFAULT 'manual' AFTER password_hash"
            );
        } catch (\PDOException $e) {
            error_log('Error agregando columna auth_type a users: ' . $e->getMessage());
        }
    }

    public function ensureUserOutsourcingDeletePermissionColumn(): void
    {
        if (!$this->db->tableExists('users') || $this->db->columnExists('users', 'can_delete_outsourcing_records')) {
            return;
        }

        try {
            $this->db->execute(
                'ALTER TABLE users ADD COLUMN can_delete_outsourcing_records TINYINT(1) DEFAULT 0 AFTER can_access_outsourcing'
            );
        } catch (\PDOException $e) {
            error_log('Error agregando columna can_delete_outsourcing_records a users: ' . $e->getMessage());
        }
    }

    public function ensureUserTimesheetPermissionColumns(): void
    {
        if (!$this->db->tableExists('users')) {
            return;
        }

        try {
            if (!$this->db->columnExists('users', 'can_access_timesheets')) {
                $this->db->execute(
                    'ALTER TABLE users ADD COLUMN can_access_timesheets TINYINT(1) DEFAULT 0 AFTER can_access_outsourcing'
                );
            }

            if (!$this->db->columnExists('users', 'can_approve_timesheets')) {
                $this->db->execute(
                    'ALTER TABLE users ADD COLUMN can_approve_timesheets TINYINT(1) DEFAULT 0 AFTER can_access_timesheets'
                );
            }
        } catch (\PDOException $e) {
            error_log('Error agregando columnas de permisos de timesheets a users: ' . $e->getMessage());
        }
    }

    public function ensureAssignmentsTable(): void
    {
        try {
            $this->createAssignmentsTableIfMissing();
            $this->ensureAssignmentTimesheetApprovalColumn();
            $this->ensureAssignmentStatusColumn();
        } catch (\PDOException $e) {
            error_log('Error asegurando tabla project_talent_assignments: ' . $e->getMessage());
        }
    }

    public function ensureTalentSchema(): void
    {
        if (!$this->db->tableExists('talents')) {
            return;
        }

        try {
            $this->ensureTalentOutsourcingFlag();
            $this->ensureTalentTimesheetFlags();
            $this->ensureTalentTimesheetApproverColumn();
            $this->ensureTalentCapacityColumn();
            $this->ensureTalentTypeColumn();
            $this->ensureTalentDeletionCascades();
            $this->ensureTimesheetApproverColumn();
        } catch (\PDOException $e) {
            error_log('Error asegurando columnas de talentos: ' . $e->getMessage());
        }
    }


    public function ensureOutsourcingDeletePermission(): void
    {
        if (!$this->db->tableExists('permissions') || !$this->db->tableExists('role_permissions')) {
            return;
        }

        try {
            $this->db->execute(
                'INSERT INTO permissions (code, name)
                 SELECT :code_value, :name
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code_check)',
                [
                    ':code_value' => 'outsourcing.delete',
                    ':code_check' => 'outsourcing.delete',
                    ':name' => 'Eliminar servicios de outsourcing',
                ]
            );

            $roles = $this->db->fetchAll(
                "SELECT id FROM roles WHERE nombre IN ('Administrador', 'PMO')"
            );

            foreach ($roles as $role) {
                $this->db->execute(
                    'INSERT INTO role_permissions (role_id, permission_id)
                     SELECT :role_id_value, p.id
                     FROM permissions p
                     WHERE p.code = :code_value
                     AND NOT EXISTS (
                        SELECT 1 FROM role_permissions rp
                        WHERE rp.role_id = :role_id_check AND rp.permission_id = p.id
                    )',
                    [
                        ':role_id_value' => (int) $role['id'],
                        ':role_id_check' => (int) $role['id'],
                        ':code_value' => 'outsourcing.delete',
                    ]
                );
            }
        } catch (\PDOException $e) {
            error_log('Error asegurando permisos de eliminación en outsourcing: ' . $e->getMessage());
        }
    }

    public function ensureOutsourcingModule(): void
    {
        try {
            $this->createOutsourcingSettingsTable();
            $this->createOutsourcingFollowupsTable();
            $this->createOutsourcingServicesTable();
            $this->createOutsourcingServiceFollowupsTable();
            $this->ensureOutsourcingFollowupStatusColumns();
            $this->ensureOutsourcingServiceObservationsColumn();
        } catch (\PDOException $e) {
            error_log('Error asegurando tablas de outsourcing: ' . $e->getMessage());
        }
    }

    public function ensureSystemSettings(): void
    {
        try {
            $this->createConfigSettingsTable();
            $this->seedDefaultConfig();
        } catch (\PDOException $e) {
            error_log('Error asegurando tabla config_settings: ' . $e->getMessage());
        }
    }

    public function ensureProjectManagementPermission(): void
    {
        if (!$this->db->tableExists('permissions') || !$this->db->tableExists('role_permissions')) {
            return;
        }

        try {
            $this->db->execute(
                'INSERT INTO permissions (code, name)
                 SELECT :code_value, :name
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code_check)',
                [
                    ':code_value' => 'projects.manage',
                    ':code_check' => 'projects.manage',
                    ':name' => 'Gestionar proyectos',
                ]
            );

            $roles = $this->db->fetchAll(
                "SELECT id FROM roles WHERE nombre IN ('Administrador', 'PMO')"
            );

            foreach ($roles as $role) {
                $this->db->execute(
                    'INSERT INTO role_permissions (role_id, permission_id)
                     SELECT :role_id_value, p.id
                     FROM permissions p
                     WHERE p.code = :code_value
                     AND NOT EXISTS (
                        SELECT 1 FROM role_permissions rp
                        WHERE rp.role_id = :role_id_check AND rp.permission_id = p.id
                    )',
                    [
                        ':role_id_value' => (int) $role['id'],
                        ':role_id_check' => (int) $role['id'],
                        ':code_value' => 'projects.manage',
                    ]
                );
            }
        } catch (\PDOException $e) {
            error_log('Error asegurando permisos de gestión de proyectos: ' . $e->getMessage());
        }
    }

    public function ensureTimesheetPermissions(): void
    {
        if (!$this->db->tableExists('permissions') || !$this->db->tableExists('role_permissions')) {
            return;
        }

        try {
            $this->db->execute(
                'INSERT INTO permissions (code, name)
                 SELECT :code_value, :name
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code_check)',
                [
                    ':code_value' => 'timesheets.approve',
                    ':code_check' => 'timesheets.approve',
                    ':name' => 'Aprobar timesheets',
                ]
            );
            $this->db->execute(
                'INSERT INTO permissions (code, name)
                 SELECT :code_value, :name
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code_check)',
                [
                    ':code_value' => 'timesheets.view',
                    ':code_check' => 'timesheets.view',
                    ':name' => 'Ver y registrar timesheets',
                ]
            );
            $this->db->execute(
                'INSERT INTO permissions (code, name)
                 SELECT :code_value, :name
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code_check)',
                [
                    ':code_value' => 'approve_timesheet',
                    ':code_check' => 'approve_timesheet',
                    ':name' => 'Aprobar timesheets',
                ]
            );
            $this->db->execute(
                'INSERT INTO permissions (code, name)
                 SELECT :code_value, :name
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code_check)',
                [
                    ':code_value' => 'view_timesheet',
                    ':code_check' => 'view_timesheet',
                    ':name' => 'Ver y registrar timesheets',
                ]
            );

            $roles = $this->db->fetchAll(
                "SELECT id FROM roles WHERE nombre IN ('Administrador', 'PMO')"
            );

            foreach ($roles as $role) {
                $this->db->execute(
                    'INSERT INTO role_permissions (role_id, permission_id)
                     SELECT :role_id_value, p.id
                     FROM permissions p
                     WHERE p.code = :code_value
                     AND NOT EXISTS (
                        SELECT 1 FROM role_permissions rp
                        WHERE rp.role_id = :role_id_check AND rp.permission_id = p.id
                    )',
                    [
                        ':role_id_value' => (int) $role['id'],
                        ':role_id_check' => (int) $role['id'],
                        ':code_value' => 'timesheets.approve',
                    ]
                );
                $this->db->execute(
                    'INSERT INTO role_permissions (role_id, permission_id)
                     SELECT :role_id_value, p.id
                     FROM permissions p
                     WHERE p.code = :code_value
                     AND NOT EXISTS (
                        SELECT 1 FROM role_permissions rp
                        WHERE rp.role_id = :role_id_check AND rp.permission_id = p.id
                    )',
                    [
                        ':role_id_value' => (int) $role['id'],
                        ':role_id_check' => (int) $role['id'],
                        ':code_value' => 'approve_timesheet',
                    ]
                );
            }

            $talentRoles = $this->db->fetchAll("SELECT id FROM roles WHERE nombre = 'Talento'");
            foreach ($talentRoles as $role) {
                $this->db->execute(
                    'INSERT INTO role_permissions (role_id, permission_id)
                     SELECT :role_id_value, p.id
                     FROM permissions p
                     WHERE p.code = :code_value
                     AND NOT EXISTS (
                        SELECT 1 FROM role_permissions rp
                        WHERE rp.role_id = :role_id_check AND rp.permission_id = p.id
                    )',
                    [
                        ':role_id_value' => (int) $role['id'],
                        ':role_id_check' => (int) $role['id'],
                        ':code_value' => 'timesheets.view',
                    ]
                );
                $this->db->execute(
                    'INSERT INTO role_permissions (role_id, permission_id)
                     SELECT :role_id_value, p.id
                     FROM permissions p
                     WHERE p.code = :code_value
                     AND NOT EXISTS (
                        SELECT 1 FROM role_permissions rp
                        WHERE rp.role_id = :role_id_check AND rp.permission_id = p.id
                    )',
                    [
                        ':role_id_value' => (int) $role['id'],
                        ':role_id_check' => (int) $role['id'],
                        ':code_value' => 'view_timesheet',
                    ]
                );
            }
        } catch (\PDOException $e) {
            error_log('Error asegurando permisos de timesheets: ' . $e->getMessage());
        }
    }


    public function ensureTimesheetWorkflowSchema(): void
    {
        if (!$this->db->tableExists('timesheets')) {
            return;
        }

        try {
            if (!$this->db->tableExists('timesheet_week_actions')) {
                $this->db->execute(
                    'CREATE TABLE timesheet_week_actions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        week_key VARCHAR(32) NOT NULL,
                        week_start DATE NOT NULL,
                        week_end DATE NOT NULL,
                        action VARCHAR(20) NOT NULL,
                        action_comment TEXT NULL,
                        actor_user_id INT NULL,
                        previous_status VARCHAR(20) NULL,
                        resulting_status VARCHAR(20) NOT NULL,
                        target_approver_user_id INT NULL,
                        deleted_at DATETIME NULL,
                        deleted_by INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (actor_user_id) REFERENCES users(id),
                        FOREIGN KEY (target_approver_user_id) REFERENCES users(id),
                        FOREIGN KEY (deleted_by) REFERENCES users(id)
                    ) ENGINE=InnoDB'
                );
            }

            if (!$this->db->columnExists('users', 'can_manage_timesheet_workflow')) {
                $this->db->execute('ALTER TABLE users ADD COLUMN can_manage_timesheet_workflow TINYINT(1) DEFAULT 0 AFTER can_approve_timesheets');
            }

            if (!$this->db->columnExists('users', 'can_delete_timesheet_workflow_records')) {
                $this->db->execute('ALTER TABLE users ADD COLUMN can_delete_timesheet_workflow_records TINYINT(1) DEFAULT 0 AFTER can_manage_timesheet_workflow');
            }

            if (!$this->db->columnExists('users', 'can_manage_advanced_timesheets')) {
                $this->db->execute('ALTER TABLE users ADD COLUMN can_manage_advanced_timesheets TINYINT(1) DEFAULT 0 AFTER can_delete_timesheet_workflow_records');
            }

            if (!$this->db->tableExists('permissions') || !$this->db->tableExists('role_permissions')) {
                return;
            }

            $this->db->execute(
                'INSERT INTO permissions (code, name)
                 SELECT :code_value, :name
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code_check)',
                [
                    ':code_value' => 'timesheets.reopen',
                    ':code_check' => 'timesheets.reopen',
                    ':name' => 'Reabrir semanas de timesheets',
                ]
            );
            $this->db->execute(
                'INSERT INTO permissions (code, name)
                 SELECT :code_value, :name
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code_check)',
                [
                    ':code_value' => 'timesheets.delete',
                    ':code_check' => 'timesheets.delete',
                    ':name' => 'Eliminar historial de flujo de timesheets',
                ]
            );
            $this->db->execute(
                'INSERT INTO permissions (code, name)
                 SELECT :code_value, :name
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code_check)',
                [
                    ':code_value' => 'timesheets.advanced_manage',
                    ':code_check' => 'timesheets.advanced_manage',
                    ':name' => 'Gestión avanzada de timesheets (edición/eliminación masiva)',
                ]
            );

            $roles = $this->db->fetchAll("SELECT id FROM roles WHERE nombre IN ('Administrador', 'PMO')");
            foreach ($roles as $role) {
                foreach (['timesheets.reopen', 'timesheets.delete', 'timesheets.advanced_manage'] as $code) {
                    $this->db->execute(
                        'INSERT INTO role_permissions (role_id, permission_id)
                         SELECT :role_id_value, p.id
                         FROM permissions p
                         WHERE p.code = :code_value
                         AND NOT EXISTS (
                            SELECT 1 FROM role_permissions rp
                            WHERE rp.role_id = :role_id_check AND rp.permission_id = p.id
                        )',
                        [
                            ':role_id_value' => (int) $role['id'],
                            ':role_id_check' => (int) $role['id'],
                            ':code_value' => $code,
                        ]
                    );
                }
            }
        } catch (\PDOException $e) {
            error_log('Error asegurando workflow de aprobación semanal de timesheets: ' . $e->getMessage());
        }
    }

    public function ensureTimesheetSchema(): void
    {
        if (!$this->db->tableExists('timesheets')) {
            return;
        }

        try {
            if (!$this->db->columnExists('timesheets', 'project_id')) {
                $this->db->execute('ALTER TABLE timesheets ADD COLUMN project_id INT NULL AFTER task_id');
                $this->db->clearColumnCache();
            }

            if (!$this->db->columnExists('timesheets', 'assignment_id')) {
                $this->db->execute('ALTER TABLE timesheets ADD COLUMN assignment_id INT NULL AFTER talent_id');
                $this->db->clearColumnCache();
            }

            if (!$this->db->columnExists('timesheets', 'user_id')) {
                $this->db->execute('ALTER TABLE timesheets ADD COLUMN user_id INT NULL AFTER talent_id');
                $this->db->clearColumnCache();
            }

            foreach (['approved_by', 'approved_at', 'rejected_by', 'rejected_at'] as $column) {
                if (!$this->db->columnExists('timesheets', $column)) {
                    $this->db->execute(sprintf('ALTER TABLE timesheets ADD COLUMN %s %s NULL', $column, str_ends_with($column, '_at') ? 'DATETIME' : 'INT'));
                    $this->db->clearColumnCache();
                }
            }

            if (!$this->db->columnExists('timesheets', 'comment')) {
                $this->db->execute('ALTER TABLE timesheets ADD COLUMN comment TEXT NULL AFTER status');
                $this->db->clearColumnCache();
            }

            $structuredColumns = [
                'phase_name' => 'VARCHAR(120) NULL AFTER comment',
                'subphase_name' => 'VARCHAR(120) NULL AFTER phase_name',
                'activity_type' => 'VARCHAR(60) NULL AFTER subphase_name',
                'activity_description' => 'VARCHAR(255) NULL AFTER activity_type',
                'had_blocker' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER activity_description',
                'blocker_description' => 'TEXT NULL AFTER had_blocker',
                'had_significant_progress' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER blocker_description',
                'generated_deliverable' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER had_significant_progress',
                'operational_comment' => 'TEXT NULL AFTER generated_deliverable',
                'linked_stopper_id' => 'BIGINT NULL AFTER operational_comment',
            ];
            foreach ($structuredColumns as $column => $definition) {
                if (!$this->db->columnExists('timesheets', $column)) {
                    $this->db->execute(sprintf('ALTER TABLE timesheets ADD COLUMN %s %s', $column, $definition));
                    $this->db->clearColumnCache();
                }
            }

            if (!$this->db->columnExists('timesheets', 'approval_comment')) {
                $this->db->execute('ALTER TABLE timesheets ADD COLUMN approval_comment TEXT NULL AFTER comment');
                $this->db->clearColumnCache();
            }

            if ($this->db->columnExists('timesheets', 'comment')) {
                $this->db->execute('UPDATE timesheets SET comment = \'\' WHERE comment IS NULL');
                $this->db->execute('ALTER TABLE timesheets MODIFY comment TEXT NOT NULL');
                $this->db->clearColumnCache();
            }

            if ($this->db->columnExists('timesheets', 'project_id')
                && !$this->db->foreignKeyExists('timesheets', 'project_id', 'projects')
                && $this->db->tableExists('projects')
            ) {
                $this->db->execute(
                    'ALTER TABLE timesheets ADD CONSTRAINT fk_timesheets_project_id FOREIGN KEY (project_id) REFERENCES projects(id)'
                );
            }

            if ($this->db->columnExists('timesheets', 'user_id')
                && !$this->db->foreignKeyExists('timesheets', 'user_id', 'users')
                && $this->db->tableExists('users')
            ) {
                $this->db->execute(
                    'ALTER TABLE timesheets ADD CONSTRAINT fk_timesheets_user_id FOREIGN KEY (user_id) REFERENCES users(id)'
                );
            }

            if ($this->db->columnExists('timesheets', 'assignment_id')
                && !$this->db->foreignKeyExists('timesheets', 'assignment_id', 'project_talent_assignments')
                && $this->db->tableExists('project_talent_assignments')
            ) {
                $this->db->execute(
                    'ALTER TABLE timesheets ADD CONSTRAINT fk_timesheets_assignment_id
                     FOREIGN KEY (assignment_id) REFERENCES project_talent_assignments(id)'
                );
            }

            foreach (['approved_by', 'rejected_by'] as $column) {
                if ($this->db->columnExists('timesheets', $column)
                    && !$this->db->foreignKeyExists('timesheets', $column, 'users')
                    && $this->db->tableExists('users')
                ) {
                    $this->db->execute(
                        sprintf(
                            'ALTER TABLE timesheets ADD CONSTRAINT fk_timesheets_%s_users FOREIGN KEY (%s) REFERENCES users(id)',
                            $column,
                            $column
                        )
                    );
                }
            }

            if ($this->db->columnExists('timesheets', 'linked_stopper_id')
                && !$this->db->foreignKeyExists('timesheets', 'linked_stopper_id', 'project_stoppers')
                && $this->db->tableExists('project_stoppers')
            ) {
                $this->db->execute(
                    'ALTER TABLE timesheets ADD CONSTRAINT fk_timesheets_linked_stopper_id
                     FOREIGN KEY (linked_stopper_id) REFERENCES project_stoppers(id)'
                );
            }

            if (!$this->db->indexExists('timesheets', 'idx_timesheets_activity_type')) {
                $this->db->execute('ALTER TABLE timesheets ADD INDEX idx_timesheets_activity_type (activity_type)');
            }

            if (!$this->db->indexExists('timesheets', 'idx_timesheets_project_week')) {
                $this->db->execute('ALTER TABLE timesheets ADD INDEX idx_timesheets_project_week (project_id, date)');
            }

            if ($this->db->columnExists('timesheets', 'assignment_id') && $this->db->tableExists('tasks')) {
                $rows = $this->db->fetchAll(
                    'SELECT ts.id, ts.talent_id, t.project_id
                     FROM timesheets ts
                     JOIN tasks t ON t.id = ts.task_id
                     WHERE ts.assignment_id IS NULL'
                );

                foreach ($rows as $row) {
                    $assignment = $this->db->fetchOne(
                        'SELECT id FROM project_talent_assignments
                         WHERE project_id = :project AND talent_id = :talent
                         ORDER BY id ASC LIMIT 1',
                        [
                            ':project' => (int) ($row['project_id'] ?? 0),
                            ':talent' => (int) ($row['talent_id'] ?? 0),
                        ]
                    );

                    if ($assignment) {
                        $this->db->execute(
                            'UPDATE timesheets SET assignment_id = :assignment WHERE id = :id',
                            [
                                ':assignment' => (int) $assignment['id'],
                                ':id' => (int) $row['id'],
                            ]
                        );
                    }
                }
            }

            if ($this->db->columnExists('timesheets', 'project_id') && $this->db->tableExists('tasks')) {
                $this->db->execute(
                    'UPDATE timesheets ts
                     JOIN tasks t ON t.id = ts.task_id
                     SET ts.project_id = t.project_id
                     WHERE ts.project_id IS NULL'
                );
            }

            if ($this->db->columnExists('timesheets', 'user_id') && $this->db->tableExists('talents')) {
                $this->db->execute(
                    'UPDATE timesheets ts
                     JOIN talents ta ON ta.id = ts.talent_id
                     SET ts.user_id = ta.user_id
                     WHERE ts.user_id IS NULL'
                );
            }

            if ($this->db->tableExists('tasks') && !$this->db->columnExists('tasks', 'completed_at')) {
                $this->db->execute('ALTER TABLE tasks ADD COLUMN completed_at DATETIME NULL AFTER due_date');
                $this->db->clearColumnCache();
            }

            if (
                $this->db->tableExists('tasks')
                && $this->db->columnExists('tasks', 'status')
                && $this->db->columnExists('tasks', 'completed_at')
            ) {
                $this->db->execute(
                    'UPDATE tasks
                     SET completed_at = CASE
                        WHEN status IN ("done", "completed") THEN COALESCE(completed_at, updated_at, created_at, NOW())
                        ELSE NULL
                     END'
                );
            }
        } catch (\PDOException $e) {
            error_log('Error asegurando esquema de timesheets: ' . $e->getMessage());
        }
    }

    public function ensureNotificationsLog(): void
    {
        if ($this->db->tableExists('notifications_log')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE notifications_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(80) NOT NULL,
                channel VARCHAR(40) NOT NULL,
                recipient_email VARCHAR(190) NOT NULL,
                recipient_user_id INT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "sent",
                error_message TEXT NULL,
                payload JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_notifications_log_created (created_at)
            ) ENGINE=InnoDB'
        );
    }

    public function ensureProjectHealthHistoryTable(): void
    {
        if ($this->db->tableExists('project_health_history')) {
            return;
        }

        try {
            $this->db->execute(
                'CREATE TABLE project_health_history (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    project_id INT NOT NULL,
                    score INT NOT NULL,
                    breakdown_json JSON NOT NULL,
                    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_project_health_history_project_date (project_id, calculated_at),
                    CONSTRAINT fk_project_health_history_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\PDOException $e) {
            error_log('Error asegurando tabla project_health_history: ' . $e->getMessage());
        }
    }


    public function ensureRequirementsModule(): void
    {
        try {
            $this->createProjectRequirementsTable();
            $this->createRequirementAuditLogTable();
            $this->createRequirementIndicatorSnapshotsTable();
            $this->ensureRequirementMetaPermissions();
        } catch (\PDOException $e) {
            error_log('Error asegurando módulo de requisitos: ' . $e->getMessage());
        }
    }

    public function ensureProjectBillingModule(): void
    {
        if (!$this->db->tableExists('projects')) {
            return;
        }

        try {
            $this->ensureProjectBillingColumns();
            $this->ensureProjectInvoicesTable();
            $this->ensureProjectInvoiceStatusEnum();
            $this->ensureProjectInvoiceTimesheetsTable();
            $this->ensureBillingPermissions();
        } catch (\PDOException $e) {
            error_log('Error asegurando módulo de facturación por proyecto: ' . $e->getMessage());
        }
    }


    public function ensureProjectStoppersModule(): void
    {
        if (!$this->db->tableExists('projects')) {
            return;
        }

        try {
            $this->ensureProjectStoppersTable();
            $this->ensureProjectStoppersPermissions();
        } catch (\PDOException $e) {
            error_log('Error asegurando módulo de bloqueos por proyecto: ' . $e->getMessage());
        }
    }

    public function ensureProjectPmoAutomationModule(): void
    {
        if (!$this->db->tableExists('projects')) {
            return;
        }

        try {
            $this->ensureProjectPmoSnapshotsTable();
            $this->ensureProjectPmoAlertsTable();
        } catch (\PDOException $e) {
            error_log('Error asegurando módulo PMO automático: ' . $e->getMessage());
        }
    }

    public function ensureDecisionCenterPermissions(): void
    {
        if (!$this->db->tableExists('permissions') || !$this->db->tableExists('role_permissions') || !$this->db->tableExists('roles')) {
            return;
        }

        $permissions = [
            'pmo_decision_center_view' => 'Ver Centro de decisiones PMO',
            'pmo_decision_center_export' => 'Exportar Centro de decisiones PMO',
            'pmo_decision_center_ai' => 'Usar análisis IA en Centro de decisiones PMO',
        ];

        foreach ($permissions as $code => $name) {
            $this->db->execute(
                'INSERT INTO permissions (code, name)
                 SELECT :code_value, :name
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code_check)',
                [
                    ':code_value' => $code,
                    ':code_check' => $code,
                    ':name' => $name,
                ]
            );
        }

        $grants = [
            'Administrador' => array_keys($permissions),
            'PMO' => ['pmo_decision_center_view', 'pmo_decision_center_ai'],
            'Líder de Proyecto' => ['pmo_decision_center_view'],
            'Visualizador' => ['pmo_decision_center_view'],
        ];

        foreach ($grants as $roleName => $codes) {
            $role = $this->db->fetchOne('SELECT id FROM roles WHERE nombre = :name LIMIT 1', [':name' => $roleName]);
            if (!$role) {
                continue;
            }

            foreach ($codes as $code) {
                $this->db->execute(
                    'INSERT INTO role_permissions (role_id, permission_id)
                     SELECT :role_id_value, p.id
                     FROM permissions p
                     WHERE p.code = :code_value
                     AND NOT EXISTS (
                        SELECT 1 FROM role_permissions rp
                        WHERE rp.role_id = :role_id_check AND rp.permission_id = p.id
                     )',
                    [
                        ':role_id_value' => (int) $role['id'],
                        ':role_id_check' => (int) $role['id'],
                        ':code_value' => $code,
                    ]
                );
            }
        }
    }

    public function resetProjectModuleDataOnce(): void
    {
        if (!$this->db->tableExists('projects')) {
            return;
        }

        $resetKey = 'project_module_reset_2025_02';
        if ($this->db->tableExists('config_settings')) {
            $existing = $this->db->fetchOne(
                'SELECT 1 FROM config_settings WHERE config_key = :key LIMIT 1',
                [':key' => $resetKey]
            );

            if ($existing) {
                return;
            }
        }

        $this->purgeProjectModuleTables();

        if ($this->db->tableExists('config_settings')) {
            $this->db->execute(
                'INSERT INTO config_settings (config_key, config_value, updated_at) VALUES (:key, :value, NOW())',
                [
                    ':key' => $resetKey,
                    ':value' => json_encode(['executed_at' => date('c')], JSON_UNESCAPED_UNICODE),
                ]
            );
        }
    }

    private function ensureTalentDeletionCascades(): void
    {
        $this->ensureCascade('talent_skills', 'talent_id', 'talents', 'fk_talent_skills_talent_id_talents');
        $this->ensureCascade('project_talent_assignments', 'talent_id', 'talents', 'fk_project_talent_assignments_talent_id_talents');
        $this->ensureCascade('timesheets', 'talent_id', 'talents', 'fk_timesheets_talent_id_talents');
    }

    private function ensurePmIntegrity(string $table, string $afterColumn): void
    {
        try {
            $this->addPmColumnIfMissing($table, $afterColumn);
            $this->assignMissingProjectManagers($table);
            $this->enforcePmNotNull($table);
            $this->addPmForeignKey($table);
        } catch (\PDOException $e) {
            error_log("Error ejecutando migración de {$table}.pm_id: " . $e->getMessage());
        }
    }

    private function ensureCascade(string $table, string $column, string $referencedTable, string $constraintName): void
    {
        if (!$this->db->tableExists($table) || !$this->db->tableExists($referencedTable) || !$this->db->columnExists($table, $column)) {
            return;
        }

        $details = $this->db->foreignKeyDetails($table, $column);

        if ($details && strtoupper((string) ($details['DELETE_RULE'] ?? '')) === 'CASCADE') {
            return;
        }

        if ($details && ($details['CONSTRAINT_NAME'] ?? '') !== '') {
            $this->db->dropForeignKey($table, $details['CONSTRAINT_NAME']);
        }

        $this->db->execute(
            sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`id`) ON DELETE CASCADE',
                $table,
                $constraintName,
                $column,
                $referencedTable
            )
        );
    }

    private function purgeProjectModuleTables(): void
    {
        $tables = [
            'timesheets',
            'tasks',
            'project_talent_assignments',
            'project_risk_evaluations',
            'project_design_changes',
            'project_design_controls',
            'project_design_inputs',
            'project_files',
            'project_nodes',
            'costs',
            'revenues',
            'projects',
        ];

        try {
            $this->db->execute('SET FOREIGN_KEY_CHECKS=0');
        } catch (\Throwable) {
            // Ignorar si el motor no soporta la operación
        }

        foreach ($tables as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->execute('DELETE FROM ' . $table);
            }
        }

        try {
            $this->db->execute('SET FOREIGN_KEY_CHECKS=1');
        } catch (\Throwable) {
            // Ignorar si el motor no soporta la operación
        }
    }

    private function ensureClientPriorityCode(): void
    {
        if ($this->db->columnExists('clients', 'priority_code')) {
            return;
        }

        if ($this->db->columnExists('clients', 'priority')) {
            $this->db->execute('ALTER TABLE clients CHANGE COLUMN priority priority_code VARCHAR(20) NOT NULL');
        } else {
            $this->db->execute("ALTER TABLE clients ADD COLUMN priority_code VARCHAR(20) NOT NULL AFTER category_code");
        }

        $this->db->clearColumnCache();
    }

    private function ensureClientStatusCode(): void
    {
        if ($this->db->columnExists('clients', 'status_code')) {
            return;
        }

        if ($this->db->columnExists('clients', 'status')) {
            $this->db->execute('ALTER TABLE clients CHANGE COLUMN status status_code VARCHAR(50) NOT NULL');
        } else {
            $this->db->execute("ALTER TABLE clients ADD COLUMN status_code VARCHAR(50) NOT NULL AFTER priority_code");
        }

        $this->db->clearColumnCache();
    }

    private function ensureClientRiskCode(): void
    {
        if ($this->db->columnExists('clients', 'risk_code')) {
            return;
        }

        if ($this->db->columnExists('clients', 'risk_level')) {
            $this->db->execute('ALTER TABLE clients CHANGE COLUMN risk_level risk_code VARCHAR(50) NULL');
        } else {
            $this->db->execute("ALTER TABLE clients ADD COLUMN risk_code VARCHAR(50) NULL AFTER nps");
        }

        $this->db->clearColumnCache();
    }

    private function ensureClientAreaCode(): void
    {
        if ($this->db->columnExists('clients', 'area_code')) {
            return;
        }

        if ($this->db->columnExists('clients', 'area')) {
            $this->db->execute('ALTER TABLE clients CHANGE COLUMN area area_code VARCHAR(120) NULL');
        } else {
            $this->db->execute("ALTER TABLE clients ADD COLUMN area_code VARCHAR(120) NULL AFTER tags");
        }

        $this->db->clearColumnCache();
    }

    private function dropLegacyClientColumns(): void
    {
        $legacyColumns = ['health', 'industry'];

        foreach ($legacyColumns as $column) {
            if (!$this->db->columnExists('clients', $column)) {
                continue;
            }

            $this->db->execute('ALTER TABLE clients DROP COLUMN ' . $column);
            $this->db->clearColumnCache();
        }
    }

    private function relaxLegacyClientColumns(): void
    {
        $columns = [
            'risk_code' => 'VARCHAR(50) NULL',
            'tags' => 'VARCHAR(255) NULL',
            'area_code' => 'VARCHAR(120) NULL',
            'feedback_notes' => 'TEXT NULL',
            'feedback_history' => 'TEXT NULL',
            'operational_context' => 'TEXT NULL',
            'logo_path' => 'VARCHAR(255) NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->db->columnExists('clients', $column)) {
                continue;
            }

            $this->db->execute(sprintf('ALTER TABLE clients MODIFY %s %s', $column, $definition));
            $this->db->clearColumnCache();
        }
    }

    private function addProjectActiveColumn(): void
    {
        if (!$this->db->tableExists('projects') || $this->db->columnExists('projects', 'active')) {
            return;
        }

        $this->db->execute("ALTER TABLE projects ADD COLUMN active TINYINT(1) DEFAULT 1 AFTER progress");
        $this->db->clearColumnCache();
    }

    private function addPmColumnIfMissing(string $table, string $afterColumn): void
    {
        if ($this->db->columnExists($table, 'pm_id')) {
            return;
        }

        $this->db->execute("ALTER TABLE {$table} ADD COLUMN pm_id INT NULL AFTER {$afterColumn}");
        $this->db->clearColumnCache();
    }

    private function assignMissingProjectManagers(string $table): void
    {
        $defaultPmId = $this->defaultPmId();
        if ($defaultPmId === null || !$this->db->columnExists($table, 'pm_id')) {
            return;
        }

        $this->db->execute(
            "UPDATE {$table} t
             LEFT JOIN users u ON u.id = t.pm_id
             SET t.pm_id = :defaultPm
             WHERE u.id IS NULL",
            [':defaultPm' => $defaultPmId]
        );
    }

    private function enforcePmNotNull(string $table): void
    {
        if (!$this->db->columnExists($table, 'pm_id')) {
            return;
        }

        $stmt = $this->db->connection()->prepare(
            'SELECT IS_NULLABLE FROM information_schema.columns
             WHERE table_schema = :schema AND table_name = :table AND column_name = :column'
        );

        $stmt->execute([
            ':schema' => $this->db->databaseName(),
            ':table' => $table,
            ':column' => 'pm_id',
        ]);

        $isNullable = $stmt->fetchColumn();

        if ($isNullable === 'YES') {
            $this->db->execute("ALTER TABLE {$table} MODIFY pm_id INT NOT NULL");
            $this->db->clearColumnCache();
        }
    }

    private function addPmForeignKey(string $table): void
    {
        if (!$this->db->columnExists($table, 'pm_id')) {
            return;
        }

        if ($this->db->foreignKeyExists($table, 'pm_id', 'users')) {
            return;
        }

        $constraintName = 'fk_' . $table . '_pm_id_users';

        $this->db->execute(
            "ALTER TABLE {$table} ADD CONSTRAINT {$constraintName} FOREIGN KEY (pm_id) REFERENCES users(id)"
        );
    }

    private function ensureProjectOwnership(): void
    {
        if (!$this->db->tableExists('projects')) {
            return;
        }

        if (!$this->db->columnExists('projects', 'pm_id')) {
            $this->db->execute('ALTER TABLE projects ADD COLUMN pm_id INT NULL AFTER client_id');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('projects', 'project_type')) {
            $this->db->execute("ALTER TABLE projects ADD COLUMN project_type VARCHAR(20) NOT NULL DEFAULT 'convencional' AFTER priority");
            $this->db->clearColumnCache();
        }

        $defaultPmId = $this->defaultPmId();
        if ($defaultPmId !== null) {
            $this->db->execute('UPDATE projects p LEFT JOIN users u ON u.id = p.pm_id SET p.pm_id = :pm WHERE u.id IS NULL', [':pm' => $defaultPmId]);
        }

        $stmt = $this->db->connection()->prepare(
            'SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name = :column'
        );
        $stmt->execute([
            ':schema' => $this->db->databaseName(),
            ':table' => 'projects',
            ':column' => 'pm_id',
        ]);

        if ($stmt->fetchColumn() === 'YES') {
            $this->db->execute('ALTER TABLE projects MODIFY pm_id INT NOT NULL');
            $this->db->clearColumnCache();
        }

        if (!$this->db->foreignKeyExists('projects', 'pm_id', 'users')) {
            $this->db->execute('ALTER TABLE projects ADD CONSTRAINT fk_projects_pm_id_users FOREIGN KEY (pm_id) REFERENCES users(id)');
        }
    }

    private function addProjectMethodology(): void
    {
        if ($this->db->columnExists('projects', 'methodology')) {
            return;
        }

        $this->db->execute("ALTER TABLE projects ADD COLUMN methodology VARCHAR(40) NOT NULL DEFAULT 'scrum' AFTER project_type");
        $this->db->clearColumnCache();
    }

    private function addProjectPhase(): void
    {
        if ($this->db->columnExists('projects', 'phase')) {
            return;
        }

        $this->db->execute("ALTER TABLE projects ADD COLUMN phase VARCHAR(80) NULL AFTER methodology");
        $this->db->clearColumnCache();
    }

    private function addProjectTreeMetadata(): void
    {
        if (!$this->db->columnExists('projects', 'tree_version')) {
            $this->db->execute("ALTER TABLE projects ADD COLUMN tree_version VARCHAR(40) NULL AFTER phase");
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('projects', 'tree_methodology')) {
            $this->db->execute("ALTER TABLE projects ADD COLUMN tree_methodology VARCHAR(40) NULL AFTER tree_version");
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('projects', 'tree_phase')) {
            $this->db->execute("ALTER TABLE projects ADD COLUMN tree_phase VARCHAR(80) NULL AFTER tree_methodology");
            $this->db->clearColumnCache();
        }
    }

    private function ensureProjectIsoControls(): void
    {
        $columns = [
            'design_inputs_defined',
            'design_review_done',
            'design_verification_done',
            'design_validation_done',
            'client_participation',
            'legal_requirements',
            'change_control_required',
        ];

        foreach ($columns as $column) {
            if ($this->db->columnExists('projects', $column)) {
                continue;
            }

            $this->db->execute("ALTER TABLE projects ADD COLUMN {$column} TINYINT(1) DEFAULT 0 AFTER phase");
            $this->db->clearColumnCache();
        }
    }

    private function ensureProjectDesignInputsTable(): void
    {
        if ($this->db->tableExists('project_design_inputs')) {
            $this->ensureProjectDesignInputsColumns();
            return;
        }

        $this->db->execute(
            "CREATE TABLE project_design_inputs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                project_node_id INT NULL,
                input_type ENUM(
                    'requisitos_funcionales',
                    'requisitos_desempeno',
                    'requisitos_legales',
                    'normativa',
                    'referencias_previas',
                    'input_cliente',
                    'otro'
                ) NOT NULL,
                description TEXT NOT NULL,
                source VARCHAR(255),
                resolved_conflict TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (project_node_id) REFERENCES project_nodes(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );
    }

    private function ensureProjectDesignControlsTable(): void
    {
        if ($this->db->tableExists('project_design_controls')) {
            $this->ensureProjectDesignControlsColumns();
            return;
        }

        $this->db->execute(
            "CREATE TABLE project_design_controls (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                project_node_id INT NULL,
                control_type ENUM(
                    'revision',
                    'verificacion',
                    'validacion'
                ) NOT NULL,
                description TEXT NOT NULL,
                result ENUM('aprobado','observaciones','rechazado') NOT NULL,
                corrective_action TEXT NULL,
                performed_by INT NOT NULL,
                performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (project_node_id) REFERENCES project_nodes(id) ON DELETE SET NULL,
                FOREIGN KEY (performed_by) REFERENCES users(id)
            ) ENGINE=InnoDB"
        );
    }

    private function ensureProjectDesignChangesTable(): void
    {
        if ($this->db->tableExists('project_design_changes')) {
            $this->ensureProjectDesignChangesColumns();
            return;
        }

        $this->db->execute(
            "CREATE TABLE project_design_changes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                project_node_id INT NULL,
                description TEXT NOT NULL,
                impact_scope ENUM('bajo','medio','alto') NOT NULL,
                impact_time ENUM('bajo','medio','alto') NOT NULL,
                impact_cost ENUM('bajo','medio','alto') NOT NULL,
                impact_quality ENUM('bajo','medio','alto') NOT NULL,
                requires_review_validation TINYINT(1) DEFAULT 0,
                status ENUM('pendiente','aprobado') NOT NULL DEFAULT 'pendiente',
                created_by INT NULL,
                approved_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                approved_at TIMESTAMP NULL,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (project_node_id) REFERENCES project_nodes(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (approved_by) REFERENCES users(id)
            ) ENGINE=InnoDB"
        );
    }

    private function ensureProjectNodesTable(): void
    {
        if ($this->db->tableExists('project_nodes')) {
            $this->migrateProjectNodesTable();
            return;
        }

        $this->db->execute(
            "CREATE TABLE project_nodes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                parent_id INT NULL,
                code VARCHAR(180) NOT NULL,
                node_type ENUM('folder','file','iso_control','metadata') NOT NULL,
                iso_clause VARCHAR(20) NULL,
                title VARCHAR(180) NOT NULL,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                file_path VARCHAR(255) NULL,
                created_by INT NULL,
                reviewer_id INT NULL,
                validator_id INT NULL,
                approver_id INT NULL,
                reviewed_by INT NULL,
                reviewed_at DATETIME NULL,
                validated_by INT NULL,
                validated_at DATETIME NULL,
                approved_by INT NULL,
                approved_at DATETIME NULL,
                document_status VARCHAR(40) NOT NULL DEFAULT 'borrador',
                document_tags TEXT NULL,
                document_version VARCHAR(40) NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'pendiente',
                critical TINYINT(1) NOT NULL DEFAULT 0,
                completed_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_project_nodes_code (project_id, code),
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES project_nodes(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (reviewer_id) REFERENCES users(id),
                FOREIGN KEY (validator_id) REFERENCES users(id),
                FOREIGN KEY (approver_id) REFERENCES users(id),
                FOREIGN KEY (reviewed_by) REFERENCES users(id),
                FOREIGN KEY (validated_by) REFERENCES users(id),
                FOREIGN KEY (approved_by) REFERENCES users(id)
            ) ENGINE=InnoDB"
        );
    }

    private function ensureProjectFilesTable(): void
    {
        if (!$this->db->tableExists('project_nodes') || $this->db->tableExists('project_files')) {
            return;
        }

        $this->db->execute(
            "CREATE TABLE project_files (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_node_id INT NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                storage_path VARCHAR(255) NOT NULL,
                mime_type VARCHAR(150),
                size_bytes BIGINT,
                uploaded_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_node_id) REFERENCES project_nodes(id) ON DELETE CASCADE,
                FOREIGN KEY (uploaded_by) REFERENCES users(id)
            ) ENGINE=InnoDB"
        );
    }

    private function ensureProjectDesignInputsColumns(): void
    {
        if (!$this->db->columnExists('project_design_inputs', 'project_node_id')) {
            $this->db->execute('ALTER TABLE project_design_inputs ADD COLUMN project_node_id INT NULL AFTER project_id');
            $this->db->clearColumnCache();

            try {
                $this->db->execute('ALTER TABLE project_design_inputs ADD CONSTRAINT fk_design_inputs_node FOREIGN KEY (project_node_id) REFERENCES project_nodes(id) ON DELETE SET NULL');
            } catch (\Throwable) {
                // Evitar fallo en entornos anteriores
            }
        }
    }

    private function ensureProjectDesignControlsColumns(): void
    {
        if (!$this->db->columnExists('project_design_controls', 'project_node_id')) {
            $this->db->execute('ALTER TABLE project_design_controls ADD COLUMN project_node_id INT NULL AFTER project_id');
            $this->db->clearColumnCache();

            try {
                $this->db->execute('ALTER TABLE project_design_controls ADD CONSTRAINT fk_design_controls_node FOREIGN KEY (project_node_id) REFERENCES project_nodes(id) ON DELETE SET NULL');
            } catch (\Throwable) {
                // Evitar fallo en entornos anteriores
            }
        }
    }

    private function ensureProjectDesignChangesColumns(): void
    {
        if (!$this->db->columnExists('project_design_changes', 'project_node_id')) {
            $this->db->execute('ALTER TABLE project_design_changes ADD COLUMN project_node_id INT NULL AFTER project_id');
            $this->db->clearColumnCache();

            try {
                $this->db->execute('ALTER TABLE project_design_changes ADD CONSTRAINT fk_design_changes_node FOREIGN KEY (project_node_id) REFERENCES project_nodes(id) ON DELETE SET NULL');
            } catch (\Throwable) {
                // Evitar fallo en entornos anteriores
            }
        }
    }

    private function migrateProjectNodesTable(): void
    {
        if ($this->db->indexExists('project_nodes', 'project_code')) {
            try {
                $this->db->execute('ALTER TABLE project_nodes DROP INDEX project_code');
            } catch (\PDOException $e) {
                if ((int) $e->errorInfo[1] !== 1553) {
                    throw $e;
                }
            }
        }

        $this->ensureProjectNodesColumns();
        $this->backfillProjectNodeCodes();
        $this->ensureProjectNodeCodeIndex();
    }

    private function ensureProjectNodesColumns(): void
    {
        if (!$this->db->columnExists('project_nodes', 'code')) {
            $this->db->execute("ALTER TABLE project_nodes ADD COLUMN code VARCHAR(180) NOT NULL DEFAULT '' AFTER parent_id");
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'iso_clause')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN iso_clause VARCHAR(20) NULL AFTER node_type');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'title')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN title VARCHAR(180) NOT NULL AFTER iso_clause');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'description')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN description TEXT NULL AFTER title');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'sort_order')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER description');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'file_path')) {
            $afterColumn = $this->db->columnExists('project_nodes', 'sort_order') ? 'sort_order' : 'description';
            $this->db->execute("ALTER TABLE project_nodes ADD COLUMN file_path VARCHAR(255) NULL AFTER {$afterColumn}");
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'created_by')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN created_by INT NULL AFTER file_path');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'reviewer_id')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN reviewer_id INT NULL AFTER created_by');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'validator_id')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN validator_id INT NULL AFTER reviewer_id');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'approver_id')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN approver_id INT NULL AFTER validator_id');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'reviewed_by')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN reviewed_by INT NULL AFTER approver_id');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'reviewed_at')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'validated_by')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN validated_by INT NULL AFTER reviewed_at');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'validated_at')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN validated_at DATETIME NULL AFTER validated_by');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'approved_by')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN approved_by INT NULL AFTER validated_at');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'approved_at')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN approved_at DATETIME NULL AFTER approved_by');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'document_status')) {
            $this->db->execute("ALTER TABLE project_nodes ADD COLUMN document_status VARCHAR(40) NOT NULL DEFAULT 'borrador' AFTER approved_at");
            $this->db->clearColumnCache();
        }
        if (!$this->db->columnExists('project_nodes', 'document_tags')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN document_tags TEXT NULL AFTER document_status');
            $this->db->clearColumnCache();
        }
        if (!$this->db->columnExists('project_nodes', 'document_version')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN document_version VARCHAR(40) NULL AFTER document_tags');
            $this->db->clearColumnCache();
        }
        if (!$this->db->columnExists('project_nodes', 'document_type')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN document_type VARCHAR(120) NULL AFTER document_version');
            $this->db->clearColumnCache();
        }

        try {
            if ($this->db->columnExists('project_nodes', 'reviewer_id')) {
                $this->db->execute('ALTER TABLE project_nodes ADD CONSTRAINT fk_project_nodes_reviewer_id FOREIGN KEY (reviewer_id) REFERENCES users(id)');
            }
        } catch (\Throwable) {
            // Ignorar si ya existe la clave foránea
        }

        try {
            if ($this->db->columnExists('project_nodes', 'validator_id')) {
                $this->db->execute('ALTER TABLE project_nodes ADD CONSTRAINT fk_project_nodes_validator_id FOREIGN KEY (validator_id) REFERENCES users(id)');
            }
        } catch (\Throwable) {
            // Ignorar si ya existe la clave foránea
        }

        try {
            if ($this->db->columnExists('project_nodes', 'approver_id')) {
                $this->db->execute('ALTER TABLE project_nodes ADD CONSTRAINT fk_project_nodes_approver_id FOREIGN KEY (approver_id) REFERENCES users(id)');
            }
        } catch (\Throwable) {
            // Ignorar si ya existe la clave foránea
        }

        try {
            if ($this->db->columnExists('project_nodes', 'reviewed_by')) {
                $this->db->execute('ALTER TABLE project_nodes ADD CONSTRAINT fk_project_nodes_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id)');
            }
        } catch (\Throwable) {
            // Ignorar si ya existe la clave foránea
        }

        try {
            if ($this->db->columnExists('project_nodes', 'validated_by')) {
                $this->db->execute('ALTER TABLE project_nodes ADD CONSTRAINT fk_project_nodes_validated_by FOREIGN KEY (validated_by) REFERENCES users(id)');
            }
        } catch (\Throwable) {
            // Ignorar si ya existe la clave foránea
        }

        try {
            if ($this->db->columnExists('project_nodes', 'approved_by')) {
                $this->db->execute('ALTER TABLE project_nodes ADD CONSTRAINT fk_project_nodes_approved_by FOREIGN KEY (approved_by) REFERENCES users(id)');
            }
        } catch (\Throwable) {
            // Ignorar si ya existe la clave foránea
        }

        if (!$this->db->columnExists('project_nodes', 'status')) {
            $this->db->execute("ALTER TABLE project_nodes ADD COLUMN status VARCHAR(40) NOT NULL DEFAULT 'pendiente' AFTER created_by");
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'critical')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN critical TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('project_nodes', 'completed_at')) {
            $this->db->execute('ALTER TABLE project_nodes ADD COLUMN completed_at DATETIME NULL AFTER critical');
            $this->db->clearColumnCache();
        }

        $this->db->execute("ALTER TABLE project_nodes MODIFY COLUMN node_type ENUM('folder','file','iso_control','metadata') NOT NULL");

        foreach (['iso_code', 'linked_entity_type', 'linked_entity_id', 'updated_at', 'name'] as $deprecated) {
            if ($this->db->columnExists('project_nodes', $deprecated)) {
                $this->db->execute("ALTER TABLE project_nodes DROP COLUMN {$deprecated}");
                $this->db->clearColumnCache();
            }
        }

        if ($this->db->columnExists('project_nodes', 'sort_order')) {
            $this->db->execute('UPDATE project_nodes SET sort_order = id WHERE sort_order IS NULL OR sort_order = 0');
        }

        $this->db->execute("ALTER TABLE project_nodes MODIFY COLUMN code VARCHAR(180) NOT NULL");
        $this->db->clearColumnCache();

        if ($this->db->columnExists('project_nodes', 'status')) {
            $this->db->execute(
                "UPDATE project_nodes
                 SET status = CASE
                     WHEN node_type = 'file' THEN 'completado'
                     WHEN status IS NULL OR status = '' THEN 'pendiente'
                     ELSE status
                 END,
                 completed_at = CASE
                     WHEN node_type = 'file' AND completed_at IS NULL THEN created_at
                     ELSE completed_at
                 END"
            );
        }
    }

    private function backfillProjectNodeCodes(): void
    {
        if (!$this->db->columnExists('project_nodes', 'code')) {
            return;
        }

        $nodes = $this->db->fetchAll('SELECT id, code FROM project_nodes');
        foreach ($nodes as $node) {
            $code = trim((string) ($node['code'] ?? ''));
            if ($code !== '') {
                continue;
            }

            $this->db->execute(
                'UPDATE project_nodes SET code = :code WHERE id = :id',
                [
                    ':code' => 'node-' . (int) ($node['id'] ?? 0),
                    ':id' => (int) ($node['id'] ?? 0),
                ]
            );
        }
    }

    private function ensureProjectNodeCodeIndex(): void
    {
        if (!$this->db->columnExists('project_nodes', 'code')) {
            return;
        }

        if (!$this->db->indexExists('project_nodes', 'uq_project_nodes_code')) {
            $this->db->execute('CREATE UNIQUE INDEX uq_project_nodes_code ON project_nodes (project_id, code)');
        }
    }

    private function createAssignmentsTableIfMissing(): void
    {
        if ($this->db->tableExists('project_talent_assignments')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE project_talent_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                user_id INT NOT NULL,
                talent_id INT,
                role VARCHAR(120) NOT NULL,
                allocation_percent DECIMAL(5,2) DEFAULT 0,
                allocation_percentage DECIMAL(5,2) GENERATED ALWAYS AS (allocation_percent) STORED,
                planned_hours DECIMAL(10,2) DEFAULT 0,
                reported_hours DECIMAL(10,2) DEFAULT 0,
                weekly_hours DECIMAL(8,2),
                cost_type VARCHAR(20),
                cost_value DECIMAL(12,2),
                hourly_rate DECIMAL(12,2),
                monthly_cost DECIMAL(12,2),
                is_external TINYINT(1) DEFAULT 0,
                requires_timesheet TINYINT(1) DEFAULT 0,
                requires_timesheet_approval TINYINT(1) DEFAULT 0,
                assignment_status VARCHAR(20) DEFAULT "active",
                active TINYINT(1) DEFAULT 1,
                start_date DATE,
                end_date DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (talent_id) REFERENCES talents(id)
            ) ENGINE=InnoDB'
        );
    }

    private function ensureAssignmentStatusColumn(): void
    {
        if (!$this->db->tableExists('project_talent_assignments')) {
            return;
        }

        if (!$this->db->columnExists('project_talent_assignments', 'assignment_status')) {
            $this->db->execute(
                'ALTER TABLE project_talent_assignments ADD COLUMN assignment_status VARCHAR(20) DEFAULT "active" AFTER requires_timesheet_approval'
            );
            $this->db->clearColumnCache();
        }

        $this->db->execute(
            "UPDATE project_talent_assignments
             SET assignment_status = CASE
                 WHEN assignment_status IS NULL OR assignment_status = '' THEN 'active'
                 ELSE assignment_status
             END"
        );
    }

    private function ensureAssignmentTimesheetApprovalColumn(): void
    {
        if (!$this->db->tableExists('project_talent_assignments')) {
            return;
        }

        if (!$this->db->columnExists('project_talent_assignments', 'requires_timesheet_approval')) {
            $this->db->execute(
                'ALTER TABLE project_talent_assignments ADD COLUMN requires_timesheet_approval TINYINT(1) DEFAULT 0 AFTER requires_timesheet'
            );
            $this->db->clearColumnCache();
        }

        if ($this->db->columnExists('project_talent_assignments', 'requires_approval')) {
            $this->db->execute(
                'UPDATE project_talent_assignments SET requires_timesheet_approval = requires_approval'
            );
        }
    }

    private function createOutsourcingSettingsTable(): void
    {
        if ($this->db->tableExists('project_outsourcing_settings')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE project_outsourcing_settings (
                project_id INT NOT NULL PRIMARY KEY,
                followup_frequency ENUM("weekly", "biweekly", "monthly") NOT NULL DEFAULT "monthly",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB'
        );
    }

    private function createOutsourcingFollowupsTable(): void
    {
        if ($this->db->tableExists('project_outsourcing_followups')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE project_outsourcing_followups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                document_node_id INT NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                responsible_user_id INT NOT NULL,
                service_status ENUM("green", "yellow", "red") NOT NULL,
                observations TEXT NOT NULL,
                decisions TEXT NOT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (document_node_id) REFERENCES project_nodes(id),
                FOREIGN KEY (responsible_user_id) REFERENCES users(id),
                FOREIGN KEY (created_by) REFERENCES users(id)
            ) ENGINE=InnoDB'
        );
    }

    private function createOutsourcingServicesTable(): void
    {
        if ($this->db->tableExists('outsourcing_services')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE outsourcing_services (
                id INT AUTO_INCREMENT PRIMARY KEY,
                talent_id INT NOT NULL,
                client_id INT NOT NULL,
                project_id INT NULL,
                start_date DATE NOT NULL,
                end_date DATE NULL,
                followup_frequency ENUM("weekly", "monthly") NOT NULL DEFAULT "monthly",
                service_status ENUM("active", "paused", "ended") NOT NULL DEFAULT "active",
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (talent_id) REFERENCES users(id),
                FOREIGN KEY (client_id) REFERENCES clients(id),
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id)
            ) ENGINE=InnoDB'
        );
    }

    private function createOutsourcingServiceFollowupsTable(): void
    {
        if ($this->db->tableExists('outsourcing_followups')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE outsourcing_followups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                service_id INT NOT NULL,
                document_node_id INT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                followup_frequency ENUM("weekly", "monthly") NOT NULL DEFAULT "monthly",
                service_health ENUM("green", "yellow", "red") NOT NULL,
                observations TEXT NOT NULL,
                responsible_user_id INT NOT NULL,
                followup_status ENUM("open", "closed", "observed") NOT NULL DEFAULT "open",
                closed_at TIMESTAMP NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (service_id) REFERENCES outsourcing_services(id) ON DELETE CASCADE,
                FOREIGN KEY (document_node_id) REFERENCES project_nodes(id),
                FOREIGN KEY (responsible_user_id) REFERENCES users(id),
                FOREIGN KEY (created_by) REFERENCES users(id)
            ) ENGINE=InnoDB'
        );
    }

    private function ensureOutsourcingFollowupStatusColumns(): void
    {
        if (!$this->db->tableExists('outsourcing_followups')) {
            return;
        }

        try {
            if (!$this->db->columnExists('outsourcing_followups', 'followup_status')) {
                $this->db->execute(
                    'ALTER TABLE outsourcing_followups ADD COLUMN followup_status ENUM("open", "closed", "observed") NOT NULL DEFAULT "open" AFTER responsible_user_id'
                );
            }

            if (!$this->db->columnExists('outsourcing_followups', 'closed_at')) {
                $this->db->execute(
                    'ALTER TABLE outsourcing_followups ADD COLUMN closed_at TIMESTAMP NULL AFTER followup_status'
                );
            }
        } catch (\PDOException $e) {
            error_log('Error asegurando columnas de estado de seguimiento outsourcing: ' . $e->getMessage());
        }
    }

    private function ensureOutsourcingServiceObservationsColumn(): void
    {
        if (!$this->db->tableExists('outsourcing_services')) {
            return;
        }

        if ($this->db->columnExists('outsourcing_services', 'observations')) {
            return;
        }

        $this->db->execute('ALTER TABLE outsourcing_services ADD COLUMN observations TEXT NULL AFTER service_status');
        $this->db->clearColumnCache();
    }

    private function ensureTalentOutsourcingFlag(): void
    {
        if (!$this->db->columnExists('talents', 'is_outsourcing')) {
            $this->db->execute('ALTER TABLE talents ADD COLUMN is_outsourcing TINYINT(1) DEFAULT 0 AFTER availability');
            $this->db->clearColumnCache();
        }
    }

    private function ensureTalentTimesheetFlags(): void
    {
        if (!$this->db->columnExists('talents', 'requiere_reporte_horas')) {
            $this->db->execute('ALTER TABLE talents ADD COLUMN requiere_reporte_horas TINYINT(1) DEFAULT 0 AFTER availability');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('talents', 'requiere_aprobacion_horas')) {
            $this->db->execute('ALTER TABLE talents ADD COLUMN requiere_aprobacion_horas TINYINT(1) DEFAULT 0 AFTER requiere_reporte_horas');
            $this->db->clearColumnCache();
        }
    }

    private function ensureTalentTimesheetApproverColumn(): void
    {
        if (!$this->db->columnExists('talents', 'timesheet_approver_user_id')) {
            $this->db->execute('ALTER TABLE talents ADD COLUMN timesheet_approver_user_id INT NULL AFTER requiere_aprobacion_horas');
            $this->db->clearColumnCache();
        }

        if (!$this->db->columnExists('talents', 'timesheet_approver_user_id')) {
            return;
        }

        $this->db->execute(
            'UPDATE talents t
             LEFT JOIN users u ON u.id = t.timesheet_approver_user_id
             SET t.timesheet_approver_user_id = NULL
             WHERE t.timesheet_approver_user_id IS NOT NULL
               AND (u.id IS NULL OR u.active = 0)'
        );

        if (!$this->db->foreignKeyExists('talents', 'timesheet_approver_user_id', 'users')) {
            $this->db->execute(
                'ALTER TABLE talents
                 ADD CONSTRAINT fk_talents_timesheet_approver_user_id
                 FOREIGN KEY (timesheet_approver_user_id) REFERENCES users(id)
                 ON DELETE SET NULL'
            );
        }
    }

    private function ensureTimesheetApproverColumn(): void
    {
        if (!$this->db->tableExists('timesheets')) {
            return;
        }

        if (!$this->db->columnExists('timesheets', 'approver_user_id')) {
            $this->db->execute('ALTER TABLE timesheets ADD COLUMN approver_user_id INT NULL AFTER assignment_id');
            $this->db->clearColumnCache();
        }

        if (!$this->db->foreignKeyExists('timesheets', 'approver_user_id', 'users')) {
            $this->db->execute(
                'ALTER TABLE timesheets
                 ADD CONSTRAINT fk_timesheets_approver_user_id
                 FOREIGN KEY (approver_user_id) REFERENCES users(id)
                 ON DELETE SET NULL'
            );
        }
    }

    private function ensureTalentCapacityColumn(): void
    {
        if (!$this->db->columnExists('talents', 'capacidad_horaria')) {
            $this->db->execute('ALTER TABLE talents ADD COLUMN capacidad_horaria DECIMAL(8,2) DEFAULT 0 AFTER requiere_aprobacion_horas');
            $this->db->clearColumnCache();
            if ($this->db->columnExists('talents', 'weekly_capacity')) {
                $this->db->execute('UPDATE talents SET capacidad_horaria = weekly_capacity WHERE capacidad_horaria = 0');
            }
        }
    }

    private function ensureTalentTypeColumn(): void
    {
        if (!$this->db->columnExists('talents', 'tipo_talento')) {
            $this->db->execute("ALTER TABLE talents ADD COLUMN tipo_talento ENUM('interno','externo','otro') NOT NULL DEFAULT 'interno' AFTER capacidad_horaria");
            $this->db->clearColumnCache();
            if ($this->db->columnExists('talents', 'is_outsourcing')) {
                $this->db->execute("UPDATE talents SET tipo_talento = IF(is_outsourcing = 1, 'externo', 'interno') WHERE tipo_talento = 'interno'");
            }
        }
    }

    private function ensureProjectRiskEvaluationsTable(): void
    {
        if ($this->db->tableExists('project_risk_evaluations')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE project_risk_evaluations (
                project_id INT NOT NULL,
                risk_code VARCHAR(80) NOT NULL,
                selected TINYINT(1) DEFAULT 1,
                PRIMARY KEY (project_id, risk_code),
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (risk_code) REFERENCES risk_catalog(code)
            ) ENGINE=InnoDB'
        );
    }

    private function ensureRiskCatalogTable(): void
    {
        if ($this->db->tableExists('risk_catalog')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE risk_catalog (
                code VARCHAR(80) PRIMARY KEY,
                category VARCHAR(60) NOT NULL,
                label VARCHAR(180) NOT NULL,
                applies_to ENUM(\'convencional\',\'scrum\',\'ambos\') NOT NULL,
                impact_scope TINYINT(1) DEFAULT 0,
                impact_time TINYINT(1) DEFAULT 0,
                impact_cost TINYINT(1) DEFAULT 0,
                impact_quality TINYINT(1) DEFAULT 0,
                impact_legal TINYINT(1) DEFAULT 0,
                severity_base TINYINT NOT NULL CHECK (severity_base BETWEEN 1 AND 5),
                active TINYINT(1) DEFAULT 1
            ) ENGINE=InnoDB'
        );
    }

    private function seedRiskCatalog(): void
    {
        if (!$this->db->tableExists('risk_catalog')) {
            return;
        }

        $seeds = $this->riskCatalogSeeds();
        foreach ($seeds as $risk) {
            $this->db->execute(
                'INSERT INTO risk_catalog (code, category, label, applies_to, impact_scope, impact_time, impact_cost, impact_quality, impact_legal, severity_base, active)
                 SELECT :code, :category, :label, :applies_to, :impact_scope, :impact_time, :impact_cost, :impact_quality, :impact_legal, :severity_base, :active
                 WHERE NOT EXISTS (SELECT 1 FROM risk_catalog WHERE code = :code_exists)',
                [
                    ':code' => $risk['code'],
                    ':code_exists' => $risk['code'],
                    ':category' => $risk['category'],
                    ':label' => $risk['label'],
                    ':applies_to' => $risk['applies_to'],
                    ':impact_scope' => $risk['impact_scope'],
                    ':impact_time' => $risk['impact_time'],
                    ':impact_cost' => $risk['impact_cost'],
                    ':impact_quality' => $risk['impact_quality'],
                    ':impact_legal' => $risk['impact_legal'],
                    ':severity_base' => $risk['severity_base'],
                    ':active' => $risk['active'],
                ]
            );
        }
    }

    private function migrateProjectRiskSelections(): void
    {
        if (!$this->db->tableExists('project_risks') || !$this->db->tableExists('project_risk_evaluations')) {
            return;
        }

        $existing = $this->db->fetchOne('SELECT 1 FROM project_risk_evaluations LIMIT 1');
        if ($existing) {
            return;
        }

        $mappings = $this->db->fetchAll('SELECT project_id, risk_code FROM project_risks');
        if (empty($mappings)) {
            return;
        }

        $stmt = $this->db->connection()->prepare(
            'INSERT INTO project_risk_evaluations (project_id, risk_code, selected)
             SELECT :project_id, :risk_code, 1
             WHERE EXISTS (SELECT 1 FROM risk_catalog WHERE code = :risk_code_exists)'
        );

        foreach ($mappings as $row) {
            $stmt->execute([
                ':project_id' => (int) ($row['project_id'] ?? 0),
                ':risk_code' => (string) ($row['risk_code'] ?? ''),
                ':risk_code_exists' => (string) ($row['risk_code'] ?? ''),
            ]);
        }
    }

    private function riskCatalogSeeds(): array
    {
        return [
            ['code' => 'alcance_incompleto', 'category' => 'Alcance', 'label' => 'Requerimientos incompletos o ambiguos', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 4, 'active' => 1],
            ['code' => 'cambios_frecuentes', 'category' => 'Alcance', 'label' => 'Cambios frecuentes de alcance sin control', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 4, 'active' => 1],
            ['code' => 'priorizacion_diffusa', 'category' => 'Alcance', 'label' => 'Falta de priorización de requisitos', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'dependencias_externas', 'category' => 'Alcance', 'label' => 'Dependencias externas no aseguradas', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'alcance_expansion', 'category' => 'Alcance', 'label' => 'Expansión de alcance sin aprobación', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 4, 'active' => 1],
            ['code' => 'estimaciones_inexactas', 'category' => 'Cronograma', 'label' => 'Estimaciones de esfuerzo poco realistas', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 4, 'active' => 1],
            ['code' => 'bloqueos_aprobaciones', 'category' => 'Cronograma', 'label' => 'Aprobaciones tardías o bloqueos de decisión', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'ruta_critica_oculta', 'category' => 'Cronograma', 'label' => 'Ruta crítica no identificada o gestionada', 'applies_to' => 'convencional', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 4, 'active' => 1],
            ['code' => 'retrasos_entregables', 'category' => 'Cronograma', 'label' => 'Entregables clave con retraso recurrente', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'disponibilidad_cliente', 'category' => 'Cronograma', 'label' => 'Baja disponibilidad de usuarios o cliente', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'presupuesto_subestimado', 'category' => 'Costos', 'label' => 'Presupuesto subestimado o sin reservas', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 4, 'active' => 1],
            ['code' => 'incremento_tarifas', 'category' => 'Costos', 'label' => 'Incremento de tarifas de proveedores', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 0, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'costos_no_planificados', 'category' => 'Costos', 'label' => 'Costos no planificados por licencias o insumos', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 0, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'cambio_tipo_cambio', 'category' => 'Costos', 'label' => 'Variaciones del tipo de cambio', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 0, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 2, 'active' => 1],
            ['code' => 'financiamiento_incierto', 'category' => 'Costos', 'label' => 'Financiamiento o pagos del cliente inciertos', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'defectos_recurrentes', 'category' => 'Calidad', 'label' => 'Defectos recurrentes en liberaciones', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 4, 'active' => 1],
            ['code' => 'pruebas_insuficientes', 'category' => 'Calidad', 'label' => 'Cobertura de pruebas insuficiente', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 4, 'active' => 1],
            ['code' => 'deuda_tecnica', 'category' => 'Calidad', 'label' => 'Deuda técnica acumulada', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'estandares_no_definidos', 'category' => 'Calidad', 'label' => 'Estandares de calidad no definidos', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 0, 'impact_cost' => 0, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 2, 'active' => 1],
            ['code' => 'integracion_deficiente', 'category' => 'Calidad', 'label' => 'Integraciones no validadas o frágiles', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'rotacion_equipo', 'category' => 'Recursos', 'label' => 'Rotación alta en el equipo del proyecto', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'habilidades_insuficientes', 'category' => 'Recursos', 'label' => 'Brechas de habilidades o entrenamiento insuficiente', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'sobrecarga_equipo', 'category' => 'Recursos', 'label' => 'Sobrecarga de trabajo en el equipo', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'disponibilidad_limitada', 'category' => 'Recursos', 'label' => 'Disponibilidad parcial de recursos clave', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'conflicto_prioridades', 'category' => 'Recursos', 'label' => 'Conflicto de prioridades con otras iniciativas', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 2, 'active' => 1],
            ['code' => 'patrocinio_debil', 'category' => 'Stakeholders', 'label' => 'Patrocinio débil o ausente', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 4, 'active' => 1],
            ['code' => 'expectativas_no_alineadas', 'category' => 'Stakeholders', 'label' => 'Expectativas no alineadas entre stakeholders', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'comunicacion_fragmentada', 'category' => 'Stakeholders', 'label' => 'Comunicación fragmentada o irregular', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 2, 'active' => 1],
            ['code' => 'resistencia_cambio', 'category' => 'Stakeholders', 'label' => 'Resistencia al cambio en la organización', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'interes_variable', 'category' => 'Stakeholders', 'label' => 'Interés variable o agendas ocultas', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 2, 'active' => 1],
            ['code' => 'tecnologia_inestable', 'category' => 'Tecnología', 'label' => 'Plataforma o tecnología inestable', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 4, 'active' => 1],
            ['code' => 'integraciones_complejas', 'category' => 'Tecnología', 'label' => 'Integraciones complejas con terceros', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'proveedor_unico', 'category' => 'Tecnología', 'label' => 'Dependencia de un único proveedor tecnológico', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'seguridad_vulnerable', 'category' => 'Tecnología', 'label' => 'Vulnerabilidades de seguridad sin mitigar', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 1, 'severity_base' => 5, 'active' => 1],
            ['code' => 'infraestructura_limitada', 'category' => 'Tecnología', 'label' => 'Infraestructura insuficiente para la demanda', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'cumplimiento_regulatorio', 'category' => 'Legal', 'label' => 'Riesgos de cumplimiento regulatorio', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 0, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 1, 'severity_base' => 5, 'active' => 1],
            ['code' => 'datos_personales', 'category' => 'Legal', 'label' => 'Gestión inadecuada de datos personales', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 0, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 1, 'severity_base' => 5, 'active' => 1],
            ['code' => 'propiedad_intelectual', 'category' => 'Legal', 'label' => 'Conflictos de propiedad intelectual', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 0, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 1, 'severity_base' => 4, 'active' => 1],
            ['code' => 'contratos_incompletos', 'category' => 'Legal', 'label' => 'Contratos incompletos o desactualizados', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 1, 'severity_base' => 3, 'active' => 1],
            ['code' => 'permisos_faltantes', 'category' => 'Legal', 'label' => 'Permisos o autorizaciones faltantes', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 1, 'severity_base' => 4, 'active' => 1],
            ['code' => 'procesos_no_documentados', 'category' => 'Operaciones', 'label' => 'Procesos operativos no documentados', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 2, 'active' => 1],
            ['code' => 'soporte_insuficiente', 'category' => 'Operaciones', 'label' => 'Soporte post-implementación insuficiente', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'continuidad_operativa', 'category' => 'Operaciones', 'label' => 'Plan de continuidad operativa incompleto', 'applies_to' => 'ambos', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'capacidad_soporte_negocio', 'category' => 'Operaciones', 'label' => 'Capacidad del negocio para operar la solución', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'gestion_configuracion_debil', 'category' => 'Operaciones', 'label' => 'Gestión de configuración y cambios débil', 'applies_to' => 'ambos', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'backlog_no_refinado', 'category' => 'Metodología Ágil', 'label' => 'Backlog sin refinamiento continuo', 'applies_to' => 'scrum', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'definicion_terminado_ambigua', 'category' => 'Metodología Ágil', 'label' => 'Definición de terminado ambigua', 'applies_to' => 'scrum', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'velocidad_inestable', 'category' => 'Metodología Ágil', 'label' => 'Velocidad del equipo inestable entre sprints', 'applies_to' => 'scrum', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 0, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 2, 'active' => 1],
            ['code' => 'deuda_backlog_defectos', 'category' => 'Metodología Ágil', 'label' => 'Acumulación de defectos en backlog', 'applies_to' => 'scrum', 'impact_scope' => 0, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 1, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
            ['code' => 'dependencia_equipos_externos', 'category' => 'Metodología Ágil', 'label' => 'Dependencia de equipos externos para completar historias', 'applies_to' => 'scrum', 'impact_scope' => 1, 'impact_time' => 1, 'impact_cost' => 1, 'impact_quality' => 0, 'impact_legal' => 0, 'severity_base' => 3, 'active' => 1],
        ];
    }

    private function defaultPmId(): ?int
    {
        $stmt = $this->db->connection()->prepare(
            "SELECT u.id FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.nombre IN ('PMO', 'Administrador', 'Líder de Proyecto') AND u.active = 1
             ORDER BY u.id ASC
             LIMIT 1"
        );

        $stmt->execute();
        $result = $stmt->fetch();

        return $result ? (int) ($result['id'] ?? 0) : null;
    }

    private function createConfigSettingsTable(): void
    {
        if ($this->db->tableExists('config_settings')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE config_settings (
                config_key VARCHAR(120) NOT NULL PRIMARY KEY,
                config_value JSON NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB'
        );
    }

    private function seedDefaultConfig(): void
    {
        if (!$this->db->tableExists('config_settings')) {
            return;
        }

        $exists = $this->db->fetchOne(
            'SELECT 1 FROM config_settings WHERE config_key = :key LIMIT 1',
            [':key' => 'app']
        );

        if ($exists) {
            return;
        }

        $service = new ConfigService($this->db);
        $defaults = $service->getDefaults();

        $this->db->execute(
            'INSERT INTO config_settings (config_key, config_value, updated_at) VALUES (:key, :value, NOW())',
            [
                ':key' => 'app',
                ':value' => json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    private function createProjectRequirementsTable(): void
    {
        if ($this->db->tableExists('project_requirements')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE project_requirements (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                client_id INT NOT NULL,
                created_by INT NOT NULL,
                name VARCHAR(190) NOT NULL,
                description TEXT NULL,
                version VARCHAR(40) NOT NULL DEFAULT "1.0",
                delivery_date DATE NULL,
                approval_date DATE NULL,
                status ENUM("borrador","entregado","aprobado","rechazado") NOT NULL DEFAULT "borrador",
                approved_first_delivery TINYINT(1) NOT NULL DEFAULT 0,
                reprocess_count INT NOT NULL DEFAULT 0,
                is_final_version TINYINT(1) NOT NULL DEFAULT 1,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_requirements_project_date (project_id, delivery_date),
                INDEX idx_requirements_status (status),
                CONSTRAINT fk_requirements_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                CONSTRAINT fk_requirements_client FOREIGN KEY (client_id) REFERENCES clients(id),
                CONSTRAINT fk_requirements_created_by FOREIGN KEY (created_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function createRequirementAuditLogTable(): void
    {
        if ($this->db->tableExists('requirement_audit_log')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE requirement_audit_log (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                requirement_id BIGINT NOT NULL,
                project_id INT NOT NULL,
                changed_by INT NOT NULL,
                from_status VARCHAR(20) NULL,
                to_status VARCHAR(20) NOT NULL,
                notes VARCHAR(255) NULL,
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_requirement_audit_project_date (project_id, changed_at),
                CONSTRAINT fk_requirement_audit_requirement FOREIGN KEY (requirement_id) REFERENCES project_requirements(id) ON DELETE CASCADE,
                CONSTRAINT fk_requirement_audit_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                CONSTRAINT fk_requirement_audit_user FOREIGN KEY (changed_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function createRequirementIndicatorSnapshotsTable(): void
    {
        if ($this->db->tableExists('requirement_indicator_snapshots')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE requirement_indicator_snapshots (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                total_requirements INT NOT NULL DEFAULT 0,
                approved_without_reprocess INT NOT NULL DEFAULT 0,
                indicator_value DECIMAL(5,2) NULL,
                status VARCHAR(20) NOT NULL DEFAULT "no_aplica",
                frozen_at TIMESTAMP NULL DEFAULT NULL,
                calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_requirement_snapshot_project_period (project_id, period_start, period_end),
                CONSTRAINT fk_requirement_snapshot_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureRequirementMetaPermissions(): void
    {
        if (!$this->db->tableExists('users')) {
            return;
        }

        if (!$this->db->columnExists('users', 'can_manage_requirement_target')) {
            $this->db->execute(
                'ALTER TABLE users ADD COLUMN can_manage_requirement_target TINYINT(1) DEFAULT 0 AFTER can_delete_outsourcing_records'
            );
        }

        if (!$this->db->columnExists('users', 'can_delete_requirement_history')) {
            $this->db->execute(
                'ALTER TABLE users ADD COLUMN can_delete_requirement_history TINYINT(1) DEFAULT 0 AFTER can_manage_requirement_target'
            );
        }

        if ($this->db->tableExists('roles')) {
            $this->db->execute(
                "UPDATE users u JOIN roles r ON r.id = u.role_id
                 SET u.can_manage_requirement_target = 1
                 WHERE r.nombre = 'Administrador'"
            );

            $this->db->execute(
                "UPDATE users u JOIN roles r ON r.id = u.role_id
                 SET u.can_delete_requirement_history = 1
                 WHERE r.nombre = 'Administrador'"
            );
        }
    }

    private function ensureProjectBillingColumns(): void
    {
        $columns = [
            'is_billable' => 'ALTER TABLE projects ADD COLUMN is_billable TINYINT(1) NOT NULL DEFAULT 0 AFTER active',
            'billing_type' => 'ALTER TABLE projects ADD COLUMN billing_type ENUM("fixed","hours","milestones","mixed") NOT NULL DEFAULT "fixed" AFTER is_billable',
            'billing_periodicity' => 'ALTER TABLE projects ADD COLUMN billing_periodicity ENUM("monthly","biweekly","deliverable","one_time","custom") NOT NULL DEFAULT "monthly" AFTER billing_type',
            'contract_value' => 'ALTER TABLE projects ADD COLUMN contract_value DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER billing_periodicity',
            'currency_code' => 'ALTER TABLE projects ADD COLUMN currency_code CHAR(3) NOT NULL DEFAULT "USD" AFTER contract_value',
            'billing_start_date' => 'ALTER TABLE projects ADD COLUMN billing_start_date DATE NULL AFTER currency_code',
            'billing_end_date' => 'ALTER TABLE projects ADD COLUMN billing_end_date DATE NULL AFTER billing_start_date',
            'hourly_rate' => 'ALTER TABLE projects ADD COLUMN hourly_rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER billing_end_date',
        ];

        foreach ($columns as $column => $sql) {
            if (!$this->db->columnExists('projects', $column)) {
                $this->db->execute($sql);
            }
        }
    }

    private function ensureProjectInvoicesTable(): void
    {
        if ($this->db->tableExists('project_invoices')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE project_invoices (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                invoice_number VARCHAR(80) NOT NULL,
                issued_at DATE NOT NULL,
                period_start DATE NULL,
                period_end DATE NULL,
                amount DECIMAL(14,2) NOT NULL,
                status ENUM("issued","paid","draft","cancelled") NOT NULL DEFAULT "issued",
                paid_at DATE NULL,
                notes TEXT NULL,
                attachment_path VARCHAR(255) NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_project_invoice_number (project_id, invoice_number),
                INDEX idx_project_invoices_project_date (project_id, issued_at),
                INDEX idx_project_invoices_status (status),
                CONSTRAINT fk_project_invoices_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                CONSTRAINT fk_project_invoices_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureProjectInvoiceTimesheetsTable(): void
    {
        if ($this->db->tableExists('project_invoice_timesheets')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE project_invoice_timesheets (
                invoice_id BIGINT NOT NULL,
                timesheet_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (invoice_id, timesheet_id),
                UNIQUE KEY uq_invoice_timesheet_unique (timesheet_id),
                CONSTRAINT fk_invoice_timesheets_invoice FOREIGN KEY (invoice_id) REFERENCES project_invoices(id) ON DELETE CASCADE,
                CONSTRAINT fk_invoice_timesheets_timesheet FOREIGN KEY (timesheet_id) REFERENCES timesheets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureProjectInvoiceStatusEnum(): void
    {
        if (!$this->db->tableExists('project_invoices')) {
            return;
        }

        $this->db->execute(
            'ALTER TABLE project_invoices
             MODIFY COLUMN status ENUM("issued","paid","draft","cancelled") NOT NULL DEFAULT "issued"'
        );
    }

    private function ensureBillingPermissions(): void
    {
        if (!$this->db->tableExists('permissions') || !$this->db->tableExists('role_permissions') || !$this->db->tableExists('roles')) {
            return;
        }

        $permissions = [
            'project.billing.view' => 'Ver facturación de proyectos',
            'project.billing.manage' => 'Registrar y editar facturas de proyecto',
            'project.billing.mark_paid' => 'Cambiar estado de facturas a pagado',
            'project.billing.void' => 'Anular facturas de proyecto',
        ];

        foreach ($permissions as $code => $name) {
            $this->db->execute(
                'INSERT INTO permissions (code, name)
                 SELECT :code_value, :name
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code_check)',
                [
                    ':code_value' => $code,
                    ':code_check' => $code,
                    ':name' => $name,
                ]
            );
        }

        $grants = [
            'Administrador' => array_keys($permissions),
            'PMO' => ['project.billing.view', 'project.billing.manage'],
            'Líder de Proyecto' => ['project.billing.view', 'project.billing.manage'],
        ];

        foreach ($grants as $roleName => $codes) {
            $role = $this->db->fetchOne('SELECT id FROM roles WHERE nombre = :name LIMIT 1', [':name' => $roleName]);
            if (!$role) {
                continue;
            }
            foreach ($codes as $code) {
                $this->db->execute(
                    'INSERT INTO role_permissions (role_id, permission_id)
                     SELECT :role_id_value, p.id
                     FROM permissions p
                     WHERE p.code = :code_value
                     AND NOT EXISTS (
                        SELECT 1 FROM role_permissions rp
                        WHERE rp.role_id = :role_id_check AND rp.permission_id = p.id
                     )',
                    [
                        ':role_id_value' => (int) $role['id'],
                        ':role_id_check' => (int) $role['id'],
                        ':code_value' => $code,
                    ]
                );
            }
        }
    }


    private function ensureProjectStoppersTable(): void
    {
        if ($this->db->tableExists('project_stoppers')) {
            $this->ensureProjectStoppersTaskColumn();
            return;
        }

        $this->db->execute(
            'CREATE TABLE project_stoppers (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                task_id INT NULL,
                title VARCHAR(190) NOT NULL,
                description TEXT NOT NULL,
                stopper_type ENUM("cliente","tecnico","interno","proveedor","financiero","legal") NOT NULL,
                impact_level ENUM("bajo","medio","alto","critico") NOT NULL,
                affected_area ENUM("tiempo","alcance","costo","calidad") NOT NULL,
                responsible_id INT NOT NULL,
                detected_at DATE NOT NULL,
                estimated_resolution_at DATE NOT NULL,
                status ENUM("abierto","en_gestion","escalado","resuelto","cerrado") NOT NULL DEFAULT "abierto",
                closure_comment TEXT NULL,
                created_by INT NULL,
                updated_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_project_stoppers_project_status (project_id, status),
                INDEX idx_project_stoppers_impact (project_id, impact_level),
                INDEX idx_project_stoppers_project_task (project_id, task_id),
                CONSTRAINT fk_project_stoppers_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                CONSTRAINT fk_project_stoppers_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
                CONSTRAINT fk_project_stoppers_responsible FOREIGN KEY (responsible_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT fk_project_stoppers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_project_stoppers_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->ensureProjectStoppersTaskColumn();
    }

    private function ensureProjectStoppersTaskColumn(): void
    {
        if (!$this->db->tableExists('project_stoppers')) {
            return;
        }

        if (!$this->db->columnExists('project_stoppers', 'task_id')) {
            $this->db->execute('ALTER TABLE project_stoppers ADD COLUMN task_id INT NULL AFTER project_id');
            $this->db->clearColumnCache();
        }

        if (!$this->db->indexExists('project_stoppers', 'idx_project_stoppers_project_task')) {
            $this->db->execute('ALTER TABLE project_stoppers ADD INDEX idx_project_stoppers_project_task (project_id, task_id)');
        }

        if (
            $this->db->tableExists('tasks')
            && !$this->db->foreignKeyExists('project_stoppers', 'task_id', 'tasks')
        ) {
            $this->db->execute(
                'ALTER TABLE project_stoppers
                 ADD CONSTRAINT fk_project_stoppers_task
                 FOREIGN KEY (task_id) REFERENCES tasks(id)
                 ON DELETE SET NULL'
            );
        }
    }

    private function ensureProjectPmoSnapshotsTable(): void
    {
        if ($this->db->tableExists('project_pmo_snapshots')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE project_pmo_snapshots (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                snapshot_date DATE NOT NULL,
                progress_manual DECIMAL(5,2) NULL,
                progress_hours DECIMAL(5,2) NULL,
                progress_tasks DECIMAL(5,2) NULL,
                risk_score INT NOT NULL DEFAULT 0,
                planned_hours DECIMAL(12,2) NULL,
                approved_hours DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_tasks INT NOT NULL DEFAULT 0,
                done_tasks INT NOT NULL DEFAULT 0,
                overdue_tasks INT NOT NULL DEFAULT 0,
                open_blockers INT NOT NULL DEFAULT 0,
                critical_blockers INT NOT NULL DEFAULT 0,
                aged_blockers INT NOT NULL DEFAULT 0,
                blocker_mentions INT NOT NULL DEFAULT 0,
                stale_business_days INT NOT NULL DEFAULT 0,
                payload_json JSON NULL,
                generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_project_pmo_snapshot_date (project_id, snapshot_date),
                INDEX idx_project_pmo_snapshots_project_date (project_id, generated_at),
                CONSTRAINT fk_project_pmo_snapshots_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureProjectPmoAlertsTable(): void
    {
        if ($this->db->tableExists('project_pmo_alerts')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE project_pmo_alerts (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                snapshot_id BIGINT NULL,
                alert_type VARCHAR(80) NOT NULL,
                severity ENUM("green", "yellow", "red") NOT NULL DEFAULT "yellow",
                title VARCHAR(180) NOT NULL,
                message TEXT NOT NULL,
                status ENUM("open", "resolved") NOT NULL DEFAULT "open",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                resolved_at TIMESTAMP NULL,
                INDEX idx_project_pmo_alerts_project_status (project_id, status, created_at),
                CONSTRAINT fk_project_pmo_alerts_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                CONSTRAINT fk_project_pmo_alerts_snapshot FOREIGN KEY (snapshot_id) REFERENCES project_pmo_snapshots(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function ensureWorkCalendarModule(): void
    {
        $this->ensureCalendarHolidaysTable();
        $this->ensureCalendarWorkingDaysConfig();
    }

    private function ensureCalendarHolidaysTable(): void
    {
        if ($this->db->tableExists('calendar_holidays')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE calendar_holidays (
                id INT AUTO_INCREMENT PRIMARY KEY,
                holiday_date DATE NOT NULL,
                name VARCHAR(180) NOT NULL,
                description VARCHAR(255) NULL,
                recurring TINYINT(1) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_calendar_holidays_date (holiday_date),
                INDEX idx_calendar_holidays_active (active, holiday_date),
                CONSTRAINT fk_calendar_holidays_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureCalendarWorkingDaysConfig(): void
    {
        if (!$this->db->tableExists('config_settings')) {
            return;
        }

        $existing = $this->db->fetchOne(
            'SELECT config_key FROM config_settings WHERE config_key = :key LIMIT 1',
            [':key' => 'work_calendar']
        );

        if ($existing) {
            return;
        }

        $defaultConfig = json_encode([
            'working_days' => [
                1 => true,
                2 => true,
                3 => true,
                4 => true,
                5 => true,
                6 => false,
                7 => false,
            ],
            'default_daily_hours' => 8,
            'admin_can_override_holidays' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $this->db->execute(
            'INSERT INTO config_settings (config_key, config_value) VALUES (:key, :value)',
            [':key' => 'work_calendar', ':value' => $defaultConfig]
        );
    }

    private function ensureProjectStoppersPermissions(): void
    {
        if (!$this->db->tableExists('permissions') || !$this->db->tableExists('role_permissions') || !$this->db->tableExists('roles')) {
            return;
        }

        $permissions = [
            'project.stoppers.view' => 'Ver bloqueos de proyectos',
            'project.stoppers.manage' => 'Crear y actualizar bloqueos de proyectos',
            'project.stoppers.close' => 'Cerrar bloqueos de proyectos',
        ];

        foreach ($permissions as $code => $name) {
            $this->db->execute(
                'INSERT INTO permissions (code, name)
                 SELECT :code_value, :name
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code_check)',
                [
                    ':code_value' => $code,
                    ':code_check' => $code,
                    ':name' => $name,
                ]
            );
        }

        $grants = [
            'Administrador' => array_keys($permissions),
            'PMO' => ['project.stoppers.view', 'project.stoppers.manage', 'project.stoppers.close'],
            'Líder de Proyecto' => ['project.stoppers.view', 'project.stoppers.manage'],
            'Talento' => ['project.stoppers.view'],
            'Visualizador' => ['project.stoppers.view'],
        ];

        foreach ($grants as $roleName => $codes) {
            $role = $this->db->fetchOne('SELECT id FROM roles WHERE nombre = :name LIMIT 1', [':name' => $roleName]);
            if (!$role) {
                continue;
            }

            foreach ($codes as $code) {
                $this->db->execute(
                    'INSERT INTO role_permissions (role_id, permission_id)
                     SELECT :role_id_value, p.id
                     FROM permissions p
                     WHERE p.code = :code_value
                     AND NOT EXISTS (
                        SELECT 1 FROM role_permissions rp
                        WHERE rp.role_id = :role_id_check AND rp.permission_id = p.id
                     )',
                    [
                        ':role_id_value' => (int) $role['id'],
                        ':role_id_check' => (int) $role['id'],
                        ':code_value' => $code,
                    ]
                );
            }
        }
    }

}
