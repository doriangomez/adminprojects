<?php

declare(strict_types=1);

class DatabaseMigrator
{
    public function __construct(private Database $db)
    {
    }

    public function ensureClientPmIntegrity(): void
    {
        try {
            $this->addPmColumnIfMissing();
            $this->assignMissingProjectManagers();
            $this->enforcePmNotNull();
            $this->addPmForeignKey();
        } catch (\PDOException $e) {
            error_log('Error ejecutando migración de clients.pm_id: ' . $e->getMessage());
        }
    }

    private function addPmColumnIfMissing(): void
    {
        if ($this->db->columnExists('clients', 'pm_id')) {
            return;
        }

        $this->db->execute('ALTER TABLE clients ADD COLUMN pm_id INT NULL AFTER status_code');
        $this->db->clearColumnCache();
    }

    private function assignMissingProjectManagers(): void
    {
        $defaultPmId = $this->defaultPmId();
        if ($defaultPmId === null || !$this->db->columnExists('clients', 'pm_id')) {
            return;
        }

        $this->db->execute(
            'UPDATE clients c
             LEFT JOIN users u ON u.id = c.pm_id
             SET c.pm_id = :defaultPm
             WHERE u.id IS NULL',
            [':defaultPm' => $defaultPmId]
        );
    }

    private function enforcePmNotNull(): void
    {
        if (!$this->db->columnExists('clients', 'pm_id')) {
            return;
        }

        $stmt = $this->db->connection()->prepare(
            'SELECT IS_NULLABLE FROM information_schema.columns
             WHERE table_schema = :schema AND table_name = :table AND column_name = :column'
        );

        $stmt->execute([
            ':schema' => $this->db->databaseName(),
            ':table' => 'clients',
            ':column' => 'pm_id',
        ]);

        $isNullable = $stmt->fetchColumn();

        if ($isNullable === 'YES') {
            $this->db->execute('ALTER TABLE clients MODIFY pm_id INT NOT NULL');
            $this->db->clearColumnCache();
        }
    }

    private function addPmForeignKey(): void
    {
        if (!$this->db->columnExists('clients', 'pm_id')) {
            return;
        }

        if ($this->db->foreignKeyExists('clients', 'pm_id', 'users')) {
            return;
        }

        $this->db->execute(
            'ALTER TABLE clients ADD CONSTRAINT fk_clients_pm_id_users FOREIGN KEY (pm_id) REFERENCES users(id)'
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
