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

    public function ensureAssignmentsTable(): void
    {
        try {
            $this->createAssignmentsTableIfMissing();
        } catch (\PDOException $e) {
            error_log('Error asegurando tabla project_talent_assignments: ' . $e->getMessage());
        }
    }

    public function ensurePortfoliosTable(): void
    {
        try {
            $this->createPortfolioTableIfMissing();
            $this->enhancePortfolioSchema();
            $this->ensureProjectsHavePortfolio();
        } catch (\PDOException $e) {
            error_log('Error asegurando tabla client_portfolios: ' . $e->getMessage());
        }
    }

    public function ensureProjectPortfolioLink(): void
    {
        if (!$this->db->tableExists('projects')) {
            return;
        }

        try {
            if (!$this->db->columnExists('projects', 'portfolio_id')) {
                $this->db->execute('ALTER TABLE projects ADD COLUMN portfolio_id INT NULL AFTER client_id');
                $this->db->clearColumnCache();
            }

            if ($this->db->tableExists('client_portfolios') && !$this->db->foreignKeyExists('projects', 'portfolio_id', 'client_portfolios')) {
                $this->db->execute(
                    'ALTER TABLE projects ADD CONSTRAINT fk_projects_portfolio FOREIGN KEY (portfolio_id) REFERENCES client_portfolios(id)'
                );
            }
        } catch (\PDOException $e) {
            error_log('Error asegurando relación project->portfolio: ' . $e->getMessage());
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

    private function enhancePortfolioSchema(): void
    {
        if (!$this->db->tableExists('client_portfolios')) {
            return;
        }

        $columns = [
            'projects_included' => "ALTER TABLE client_portfolios ADD COLUMN projects_included TEXT NULL AFTER attachment_path",
            'rules_notes' => "ALTER TABLE client_portfolios ADD COLUMN rules_notes TEXT NULL AFTER projects_included",
            'alerting_policy' => "ALTER TABLE client_portfolios ADD COLUMN alerting_policy TEXT NULL AFTER rules_notes",
            'objective' => "ALTER TABLE client_portfolios ADD COLUMN objective TEXT NULL AFTER name",
            'description' => "ALTER TABLE client_portfolios ADD COLUMN description TEXT NULL AFTER objective",
            'risk_register' => "ALTER TABLE client_portfolios ADD COLUMN risk_register TEXT NULL AFTER alerting_policy",
            'risk_level_text' => "ALTER TABLE client_portfolios ADD COLUMN risk_level_text VARCHAR(60) NULL AFTER risk_register",
            'active' => "ALTER TABLE client_portfolios ADD COLUMN active TINYINT(1) DEFAULT 1 AFTER name",
        ];

        foreach ($columns as $column => $statement) {
            if ($this->db->columnExists('client_portfolios', $column)) {
                continue;
            }

            $this->db->execute($statement);
            $this->db->clearColumnCache();
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

    private function createPortfolioTableIfMissing(): void
    {
        if ($this->db->tableExists('client_portfolios')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE client_portfolios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                name VARCHAR(150) NOT NULL,
                active TINYINT(1) DEFAULT 1,
                objective TEXT NULL,
                description TEXT NULL,
                start_date DATE NULL,
                end_date DATE NULL,
                hours_limit DECIMAL(12,2) NULL,
                budget_limit DECIMAL(14,2) NULL,
                budget_total DECIMAL(14,2) NULL,
                risk_level VARCHAR(20) NULL,
                attachment_path VARCHAR(255) NULL,
                projects_included TEXT NULL,
                rules_notes TEXT NULL,
                alerting_policy TEXT NULL,
                risk_register TEXT NULL,
                risk_level_text VARCHAR(60) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_client_portfolios_client FOREIGN KEY (client_id) REFERENCES clients(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function ensureProjectsHavePortfolio(): void
    {
        if (!$this->db->tableExists('projects') || !$this->db->tableExists('client_portfolios')) {
            return;
        }

        if (!$this->db->columnExists('projects', 'portfolio_id')) {
            $this->db->execute('ALTER TABLE projects ADD COLUMN portfolio_id INT NULL AFTER client_id');
            $this->db->clearColumnCache();
        }

        if (!$this->db->foreignKeyExists('projects', 'portfolio_id', 'client_portfolios')) {
            $this->db->execute(
                'ALTER TABLE projects
                 ADD CONSTRAINT fk_projects_portfolio
                 FOREIGN KEY (portfolio_id) REFERENCES client_portfolios(id)'
            );
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
}
