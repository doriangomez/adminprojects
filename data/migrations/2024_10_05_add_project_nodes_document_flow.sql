ALTER TABLE project_nodes
    ADD COLUMN reviewer_id INT NULL AFTER created_by,
    ADD COLUMN validator_id INT NULL AFTER reviewer_id,
    ADD COLUMN approver_id INT NULL AFTER validator_id,
    ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
    ADD COLUMN validated_at DATETIME NULL AFTER validated_by,
    ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
    ADD COLUMN document_status VARCHAR(40) NOT NULL DEFAULT 'pendiente_revision' AFTER approved_at;

ALTER TABLE project_nodes
    ADD CONSTRAINT fk_project_nodes_reviewer_id FOREIGN KEY (reviewer_id) REFERENCES users(id),
    ADD CONSTRAINT fk_project_nodes_validator_id FOREIGN KEY (validator_id) REFERENCES users(id),
    ADD CONSTRAINT fk_project_nodes_approver_id FOREIGN KEY (approver_id) REFERENCES users(id);
