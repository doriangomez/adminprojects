ALTER TABLE project_requirements
    MODIFY COLUMN status ENUM('borrador','definido','en_revision','aprobado','rechazado','entregado')
    NOT NULL DEFAULT 'borrador';
