<?php

declare(strict_types=1);

class DatabaseMigrator
{
    public function __construct(private Database $db)
    {
    }

    public function ensureClientPmIntegrity(): void
    {
        $this->ensurePmIntegrity('clients', 'status_code');
    }

    public function ensureProjectPmIntegrity(): void
    {
        $this->ensurePmIntegrity('projects', 'client_id');
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

    private function ensureAssignmentsTable(): void
    {
        if ($this->db->tableExists('project_talent_assignments')) {
            return;
        }

        $this->db->execute(
            'CREATE TABLE project_talent_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                talent_id INT NOT NULL,
                role VARCHAR(120) NOT NULL,
                start_date DATE,
                end_date DATE,
                allocation_percent DECIMAL(5,2),
                weekly_hours DECIMAL(8,2),
                cost_type VARCHAR(20) NOT NULL,
                cost_value DECIMAL(12,2) NOT NULL,
                is_external TINYINT(1) DEFAULT 0,
                requires_timesheet TINYINT(1) DEFAULT 0,
                requires_approval TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id),
                FOREIGN KEY (talent_id) REFERENCES talents(id)
            )'
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
