ALTER TABLE project_talent_assignments
    ADD COLUMN assignment_status VARCHAR(20) DEFAULT 'active' AFTER requires_approval;

CREATE TABLE project_outsourcing_settings (
    project_id INT NOT NULL PRIMARY KEY,
    followup_frequency ENUM('weekly', 'biweekly', 'monthly') NOT NULL DEFAULT 'monthly',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE project_outsourcing_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    document_node_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    responsible_user_id INT NOT NULL,
    service_status ENUM('green', 'yellow', 'red') NOT NULL,
    observations TEXT NOT NULL,
    decisions TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (document_node_id) REFERENCES project_nodes(id),
    FOREIGN KEY (responsible_user_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;
