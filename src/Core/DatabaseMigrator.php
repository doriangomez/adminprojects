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
            $this->addClientPriorityColumn();
            $this->addClientHealthColumn();
        } catch (\PDOException $e) {
            error_log('Error ejecutando migración de clients.priority/health: ' . $e->getMessage());
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

    public function ensureAssignmentsTable(): void
    {
        try {
            $this->createAssignmentsTableIfMissing();
        } catch (\PDOException $e) {
            error_log('Error asegurando tabla project_talent_assignments: ' . $e->getMessage());
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
                 SELECT :code, :name
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code)',
                [
                    ':code' => 'projects.manage',
                    ':name' => 'Gestionar proyectos',
                ]
            );

            $roles = $this->db->fetchAll(
                "SELECT id FROM roles WHERE nombre IN ('Administrador', 'PMO')"
            );

            foreach ($roles as $role) {
                $this->db->execute(
                    'INSERT INTO role_permissions (role_id, permission_id)
                     SELECT :roleId, p.id
                     FROM permissions p
                     WHERE p.code = :code
                     AND NOT EXISTS (
                        SELECT 1 FROM role_permissions rp
                        WHERE rp.role_id = :roleId AND rp.permission_id = p.id
                    )',
                    [
                        ':roleId' => (int) $role['id'],
                        ':code' => 'projects.manage',
                    ]
                );
            }
        } catch (\PDOException $e) {
            error_log('Error asegurando permisos de gestión de proyectos: ' . $e->getMessage());
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

    private function addClientPriorityColumn(): void
    {
        if ($this->db->columnExists('clients', 'priority')) {
            return;
        }

        $this->db->execute("ALTER TABLE clients ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT '' AFTER category_code");
        $this->db->clearColumnCache();
    }

    private function addClientHealthColumn(): void
    {
        if ($this->db->columnExists('clients', 'health')) {
            return;
        }

        $this->db->execute("ALTER TABLE clients ADD COLUMN health VARCHAR(20) NOT NULL DEFAULT '' AFTER priority");
        $this->db->clearColumnCache();
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
                document_status VARCHAR(40) NOT NULL DEFAULT 'pendiente_revision',
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
            $this->db->execute('ALTER TABLE project_nodes DROP INDEX project_code');
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
            $this->db->execute("ALTER TABLE project_nodes ADD COLUMN document_status VARCHAR(40) NOT NULL DEFAULT 'pendiente_revision' AFTER approved_at");
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
                requires_approval TINYINT(1) DEFAULT 0,
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
                 WHERE NOT EXISTS (SELECT 1 FROM risk_catalog WHERE code = :code)',
                [
                    ':code' => $risk['code'],
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
             WHERE EXISTS (SELECT 1 FROM risk_catalog WHERE code = :risk_code)'
        );

        foreach ($mappings as $row) {
            $stmt->execute([
                ':project_id' => (int) ($row['project_id'] ?? 0),
                ':risk_code' => (string) ($row['risk_code'] ?? ''),
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
}
