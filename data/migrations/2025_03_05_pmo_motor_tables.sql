-- PMO Motor: tablas para snapshots y alertas automáticas
-- Ejecutar diariamente a las 7:00 a.m. (hora Colombia)

CREATE TABLE IF NOT EXISTS pmo_project_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    snapshot_date DATE NOT NULL,
    progress_manual DECIMAL(5,2),
    progress_hours DECIMAL(5,2),
    progress_tasks DECIMAL(5,2),
    risk_score TINYINT UNSIGNED DEFAULT 0,
    approved_hours DECIMAL(10,2) DEFAULT 0,
    planned_hours DECIMAL(10,2) DEFAULT 0,
    total_tasks INT DEFAULT 0,
    done_tasks INT DEFAULT 0,
    overdue_tasks INT DEFAULT 0,
    open_stoppers INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_project_date (project_id, snapshot_date),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pmo_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    alert_type VARCHAR(60) NOT NULL,
    severity ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
    message TEXT,
    payload JSON,
    acknowledged TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_type (project_id, alert_type),
    INDEX idx_created (created_at),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;
