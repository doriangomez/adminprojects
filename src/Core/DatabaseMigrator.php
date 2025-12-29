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
            $this->ensureProjectRisksTable();
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
            $this->ensureCascade('timesheets', 'task_id', 'tasks', 'fk_timesheets_task_id_tasks');
            $this->ensureCascade('project_talent_assignments', 'project_id', 'projects', 'fk_project_talent_assignments_project_id_projects');
            $this->ensureCascade('costs', 'project_id', 'projects', 'fk_costs_project_id_projects');
            $this->ensureCascade('revenues', 'project_id', 'projects', 'fk_revenues_project_id_projects');
            $this->ensureCascade('contracts', 'client_id', 'clients', 'fk_contracts_client_id_clients');
        } catch (\PDOException $e) {
            error_log('Error asegurando eliminaciones en cascada de clientes: ' . $e->getMessage());
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

    private function ensureProjectRisksTable(): void
    {
        if ($this->db->tableExists('project_risks')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE project_risks (
                project_id INT NOT NULL,
                risk_code VARCHAR(80) NOT NULL,
                PRIMARY KEY (project_id, risk_code),
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB'
        );
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
