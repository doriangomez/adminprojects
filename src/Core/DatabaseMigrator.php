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
            $this->ensureProjectIsoControls();
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
