-- Base de datos para Prompt Maestro PMO
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE config_settings (
    config_key VARCHAR(120) NOT NULL PRIMARY KEY,
    config_value JSON NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    active TINYINT(1) DEFAULT 1,
    can_review_documents TINYINT(1) DEFAULT 0,
    can_validate_documents TINYINT(1) DEFAULT 0,
    can_approve_documents TINYINT(1) DEFAULT 0,
    can_update_project_progress TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE priorities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    label VARCHAR(50) NOT NULL
);

CREATE TABLE project_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    label VARCHAR(50) NOT NULL
);

CREATE TABLE project_health (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    label VARCHAR(50) NOT NULL
);

CREATE TABLE client_sectors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL
);

CREATE TABLE client_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL
);

CREATE TABLE client_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL
);

CREATE TABLE client_risk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL
);

CREATE TABLE client_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL
);

CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    sector_code VARCHAR(50) NOT NULL,
    category_code VARCHAR(50) NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT '',
    health VARCHAR(20) NOT NULL DEFAULT '',
    status_code VARCHAR(50) NOT NULL,
    pm_id INT NOT NULL,
    satisfaction INT,
    nps INT,
    risk_code VARCHAR(30),
    tags VARCHAR(255),
    area_code VARCHAR(120),
    logo_path VARCHAR(255),
    feedback_notes TEXT,
    feedback_history TEXT,
    operational_context TEXT,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pm_id) REFERENCES users(id)
);

CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    pm_id INT NOT NULL,
    name VARCHAR(180) NOT NULL,
    status VARCHAR(20) NOT NULL,
    health VARCHAR(20) NOT NULL,
    priority VARCHAR(20) NOT NULL,
    project_type VARCHAR(20) NOT NULL DEFAULT 'convencional',
    methodology VARCHAR(40) NOT NULL DEFAULT 'scrum',
    phase VARCHAR(80) NULL,
    design_inputs_defined TINYINT(1) DEFAULT 0,
    design_review_done TINYINT(1) DEFAULT 0,
    design_verification_done TINYINT(1) DEFAULT 0,
    design_validation_done TINYINT(1) DEFAULT 0,
    client_participation TINYINT(1) DEFAULT 0,
    legal_requirements TINYINT(1) DEFAULT 0,
    change_control_required TINYINT(1) DEFAULT 0,
    budget DECIMAL(12,2) DEFAULT 0,
    actual_cost DECIMAL(12,2) DEFAULT 0,
    planned_hours INT DEFAULT 0,
    actual_hours INT DEFAULT 0,
    progress DECIMAL(5,2) DEFAULT 0,
    start_date DATE,
    end_date DATE,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (pm_id) REFERENCES users(id)
);

CREATE TABLE project_design_inputs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
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
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE project_design_controls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
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
    FOREIGN KEY (performed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE project_design_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
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
    approved_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE project_nodes (
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
    document_tags TEXT NULL,
    document_version VARCHAR(40) NULL,
    document_type VARCHAR(120) NULL,
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
) ENGINE=InnoDB;

CREATE TABLE project_files (
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
) ENGINE=InnoDB;

CREATE TABLE risk_catalog (
    code VARCHAR(80) PRIMARY KEY,
    category VARCHAR(60) NOT NULL,
    label VARCHAR(180) NOT NULL,
    applies_to ENUM('convencional','scrum','ambos') NOT NULL,
    impact_scope TINYINT(1) DEFAULT 0,
    impact_time TINYINT(1) DEFAULT 0,
    impact_cost TINYINT(1) DEFAULT 0,
    impact_quality TINYINT(1) DEFAULT 0,
    impact_legal TINYINT(1) DEFAULT 0,
    severity_base TINYINT NOT NULL CHECK (severity_base BETWEEN 1 AND 5),
    active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE project_risk_evaluations (
    project_id INT NOT NULL,
    risk_code VARCHAR(80) NOT NULL,
    selected TINYINT(1) DEFAULT 1,
    PRIMARY KEY (project_id, risk_code),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (risk_code) REFERENCES risk_catalog(code)
) ENGINE=InnoDB;

CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    assignee_id INT,
    title VARCHAR(180) NOT NULL,
    description TEXT,
    status VARCHAR(20) NOT NULL,
    priority VARCHAR(20) NOT NULL,
    estimated_hours DECIMAL(8,2) DEFAULT 0,
    actual_hours DECIMAL(8,2) DEFAULT 0,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE talents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(150) NOT NULL,
    role VARCHAR(100) NOT NULL,
    seniority VARCHAR(50),
    weekly_capacity INT DEFAULT 0,
    availability INT DEFAULT 0,
    hourly_cost DECIMAL(10,2) DEFAULT 0,
    hourly_rate DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
);

CREATE TABLE talent_skills (
    talent_id INT NOT NULL,
    skill_id INT NOT NULL,
    PRIMARY KEY (talent_id, skill_id),
    FOREIGN KEY (talent_id) REFERENCES talents(id),
    FOREIGN KEY (skill_id) REFERENCES skills(id)
);

CREATE TABLE timesheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    talent_id INT NOT NULL,
    date DATE NOT NULL,
    hours DECIMAL(8,2) NOT NULL,
    status VARCHAR(20) NOT NULL,
    billable TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (talent_id) REFERENCES talents(id)
);

CREATE TABLE project_talent_assignments (
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
    assignment_status VARCHAR(20) DEFAULT 'active',
    active TINYINT(1) DEFAULT 1,
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (talent_id) REFERENCES talents(id)
) ENGINE=InnoDB;

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

CREATE TABLE outsourcing_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    talent_id INT NOT NULL,
    client_id INT NOT NULL,
    project_id INT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    followup_frequency ENUM('weekly', 'monthly') NOT NULL DEFAULT 'monthly',
    service_status ENUM('active', 'paused', 'ended') NOT NULL DEFAULT 'active',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (talent_id) REFERENCES users(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE outsourcing_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    document_node_id INT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    followup_frequency ENUM('weekly', 'monthly') NOT NULL DEFAULT 'monthly',
    service_health ENUM('green', 'yellow', 'red') NOT NULL,
    observations TEXT NOT NULL,
    responsible_user_id INT NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES outsourcing_services(id) ON DELETE CASCADE,
    FOREIGN KEY (document_node_id) REFERENCES project_nodes(id),
    FOREIGN KEY (responsible_user_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    description VARCHAR(180),
    amount DECIMAL(12,2) NOT NULL,
    incurred_at DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE revenues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    description VARCHAR(180),
    amount DECIMAL(12,2) NOT NULL,
    recognized_at DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    entity VARCHAR(100) NOT NULL,
    entity_id INT,
    action VARCHAR(50) NOT NULL,
    payload JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Seeds básicos
INSERT INTO roles (nombre) VALUES ('Administrador'), ('PMO'), ('Líder de Proyecto'), ('Talento'), ('Visualizador');

INSERT INTO permissions (code, name) VALUES
    ('dashboard.view', 'Ver dashboard'),
    ('clients.view', 'Ver clientes'),
    ('clients.manage', 'Gestionar clientes'),
    ('clients.delete', 'Eliminar clientes'),
    ('projects.view', 'Ver proyectos'),
    ('projects.manage', 'Gestionar proyectos'),
    ('can_access_outsourcing', 'Acceder a outsourcing'),
    ('tasks.view', 'Ver tareas'),
    ('talents.view', 'Ver talento'),
    ('timesheets.view', 'Ver timesheets'),
    ('config.manage', 'Administrar configuración');

INSERT INTO config_settings (config_key, config_value) VALUES
('app', '{
    "theme": {
        "logo": "/project/public/uploads/logos/default.svg",
        "primary": "#2563eb",
        "secondary": "#111827",
        "accent": "#f59e0b",
        "background": "#f3f4f6",
        "surface": "#ffffff",
        "font_family": "\"Inter\", system-ui, -apple-system, BlinkMacSystemFont, \"Segoe UI\", sans-serif",
        "login_hero": "Orquesta tus operaciones críticas",
        "login_message": "Diseña flujos, controla riesgos y haz visible el valor de tu PMO."
    },
    "master_files": {
        "data_file": "data/data.json",
        "schema_file": "data/schema.sql"
    },
    "delivery": {
        "methodologies": ["scrum", "cascada", "kanban"],
        "phases": {
            "scrum": ["01-INICIO", "02-BACKLOG", "03-SPRINTS", "04-CIERRE"],
            "cascada": ["01-INICIO", "02-PLANIFICACION", "03-DISEÑO", "04-EJECUCION", "05-SEGUIMIENTO_Y_CONTROL", "06-CIERRE"],
            "kanban": ["01-BACKLOG", "02-EN-CURSO", "03-EN-REVISION", "04-HECHO"]
        },
        "risks": []
    },
    "access": {
        "roles": ["Administrador", "PMO", "Talento"],
        "user_management": {
            "allow_self_registration": false,
            "require_approval": true
        }
    },
    "operational_rules": {
        "semaforization": {
            "progress": {
                "yellow_below": 50,
                "red_below": 25
            },
            "hours": {
                "yellow_above": 0.05,
                "red_above": 0.1
            },
            "cost": {
                "yellow_above": 0.05,
                "red_above": 0.1
            }
        },
        "approvals": {
            "external_talent_requires_approval": true,
            "budget_change_requires_approval": true
        }
    }
}') ON DUPLICATE KEY UPDATE config_value = config_value;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.nombre IN ('Administrador', 'PMO');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.code IN ('dashboard.view', 'clients.view')
WHERE r.nombre = 'Líder de Proyecto';

INSERT INTO risk_catalog (code, category, label, applies_to, impact_scope, impact_time, impact_cost, impact_quality, impact_legal, severity_base, active) VALUES
('alcance_incompleto', 'Alcance', 'Requerimientos incompletos o ambiguos', 'ambos', 1, 1, 1, 1, 0, 4, 1),
('cambios_frecuentes', 'Alcance', 'Cambios frecuentes de alcance sin control', 'ambos', 1, 1, 1, 1, 0, 4, 1),
('priorizacion_diffusa', 'Alcance', 'Falta de priorización de requisitos', 'ambos', 1, 1, 0, 1, 0, 3, 1),
('dependencias_externas', 'Alcance', 'Dependencias externas no aseguradas', 'ambos', 1, 1, 1, 0, 0, 3, 1),
('alcance_expansion', 'Alcance', 'Expansión de alcance sin aprobación', 'ambos', 1, 1, 1, 0, 0, 4, 1),
('estimaciones_inexactas', 'Cronograma', 'Estimaciones de esfuerzo poco realistas', 'ambos', 0, 1, 1, 0, 0, 4, 1),
('bloqueos_aprobaciones', 'Cronograma', 'Aprobaciones tardías o bloqueos de decisión', 'ambos', 0, 1, 1, 0, 0, 3, 1),
('ruta_critica_oculta', 'Cronograma', 'Ruta crítica no identificada o gestionada', 'convencional', 0, 1, 1, 0, 0, 4, 1),
('retrasos_entregables', 'Cronograma', 'Entregables clave con retraso recurrente', 'ambos', 0, 1, 1, 1, 0, 3, 1),
('disponibilidad_cliente', 'Cronograma', 'Baja disponibilidad de usuarios o cliente', 'ambos', 1, 1, 0, 0, 0, 3, 1),
('presupuesto_subestimado', 'Costos', 'Presupuesto subestimado o sin reservas', 'ambos', 0, 1, 1, 0, 0, 4, 1),
('incremento_tarifas', 'Costos', 'Incremento de tarifas de proveedores', 'ambos', 0, 0, 1, 0, 0, 3, 1),
('costos_no_planificados', 'Costos', 'Costos no planificados por licencias o insumos', 'ambos', 0, 0, 1, 0, 0, 3, 1),
('cambio_tipo_cambio', 'Costos', 'Variaciones del tipo de cambio', 'ambos', 0, 0, 1, 0, 0, 2, 1),
('financiamiento_incierto', 'Costos', 'Financiamiento o pagos del cliente inciertos', 'ambos', 0, 1, 1, 0, 0, 3, 1),
('defectos_recurrentes', 'Calidad', 'Defectos recurrentes en liberaciones', 'ambos', 0, 1, 1, 1, 0, 4, 1),
('pruebas_insuficientes', 'Calidad', 'Cobertura de pruebas insuficiente', 'ambos', 0, 1, 1, 1, 0, 4, 1),
('deuda_tecnica', 'Calidad', 'Deuda técnica acumulada', 'ambos', 0, 1, 1, 1, 0, 3, 1),
('estandares_no_definidos', 'Calidad', 'Estandares de calidad no definidos', 'ambos', 0, 0, 0, 1, 0, 2, 1),
('integracion_deficiente', 'Calidad', 'Integraciones no validadas o frágiles', 'ambos', 0, 1, 1, 1, 0, 3, 1),
('rotacion_equipo', 'Recursos', 'Rotación alta en el equipo del proyecto', 'ambos', 0, 1, 1, 1, 0, 3, 1),
('habilidades_insuficientes', 'Recursos', 'Brechas de habilidades o entrenamiento insuficiente', 'ambos', 0, 1, 0, 1, 0, 3, 1),
('sobrecarga_equipo', 'Recursos', 'Sobrecarga de trabajo en el equipo', 'ambos', 0, 1, 0, 1, 0, 3, 1),
('disponibilidad_limitada', 'Recursos', 'Disponibilidad parcial de recursos clave', 'ambos', 0, 1, 1, 0, 0, 3, 1),
('conflicto_prioridades', 'Recursos', 'Conflicto de prioridades con otras iniciativas', 'ambos', 0, 1, 0, 1, 0, 2, 1),
('patrocinio_debil', 'Stakeholders', 'Patrocinio débil o ausente', 'ambos', 1, 1, 1, 0, 0, 4, 1),
('expectativas_no_alineadas', 'Stakeholders', 'Expectativas no alineadas entre stakeholders', 'ambos', 1, 1, 0, 1, 0, 3, 1),
('comunicacion_fragmentada', 'Stakeholders', 'Comunicación fragmentada o irregular', 'ambos', 1, 1, 0, 1, 0, 2, 1),
('resistencia_cambio', 'Stakeholders', 'Resistencia al cambio en la organización', 'ambos', 1, 1, 1, 0, 0, 3, 1),
('interes_variable', 'Stakeholders', 'Interés variable o agendas ocultas', 'ambos', 1, 1, 0, 0, 0, 2, 1),
('tecnologia_inestable', 'Tecnología', 'Plataforma o tecnología inestable', 'ambos', 0, 1, 1, 1, 0, 4, 1),
('integraciones_complejas', 'Tecnología', 'Integraciones complejas con terceros', 'ambos', 0, 1, 1, 1, 0, 3, 1),
('proveedor_unico', 'Tecnología', 'Dependencia de un único proveedor tecnológico', 'ambos', 0, 1, 1, 1, 0, 3, 1),
('seguridad_vulnerable', 'Tecnología', 'Vulnerabilidades de seguridad sin mitigar', 'ambos', 0, 1, 1, 1, 1, 5, 1),
('infraestructura_limitada', 'Tecnología', 'Infraestructura insuficiente para la demanda', 'ambos', 0, 1, 1, 1, 0, 3, 1),
('cumplimiento_regulatorio', 'Legal', 'Riesgos de cumplimiento regulatorio', 'ambos', 0, 0, 1, 0, 1, 5, 1),
('datos_personales', 'Legal', 'Gestión inadecuada de datos personales', 'ambos', 0, 0, 1, 1, 1, 5, 1),
('propiedad_intelectual', 'Legal', 'Conflictos de propiedad intelectual', 'ambos', 0, 0, 1, 0, 1, 4, 1),
('contratos_incompletos', 'Legal', 'Contratos incompletos o desactualizados', 'ambos', 0, 1, 1, 0, 1, 3, 1),
('permisos_faltantes', 'Legal', 'Permisos o autorizaciones faltantes', 'ambos', 0, 1, 1, 0, 1, 4, 1),
('procesos_no_documentados', 'Operaciones', 'Procesos operativos no documentados', 'ambos', 1, 1, 0, 1, 0, 2, 1),
('soporte_insuficiente', 'Operaciones', 'Soporte post-implementación insuficiente', 'ambos', 0, 1, 1, 1, 0, 3, 1),
('continuidad_operativa', 'Operaciones', 'Plan de continuidad operativa incompleto', 'ambos', 0, 1, 1, 1, 0, 3, 1),
('capacidad_soporte_negocio', 'Operaciones', 'Capacidad del negocio para operar la solución', 'ambos', 1, 1, 0, 1, 0, 3, 1),
('gestion_configuracion_debil', 'Operaciones', 'Gestión de configuración y cambios débil', 'ambos', 1, 1, 0, 1, 0, 3, 1),
('backlog_no_refinado', 'Metodología Ágil', 'Backlog sin refinamiento continuo', 'scrum', 1, 1, 0, 1, 0, 3, 1),
('definicion_terminado_ambigua', 'Metodología Ágil', 'Definición de terminado ambigua', 'scrum', 1, 1, 0, 1, 0, 3, 1),
('velocidad_inestable', 'Metodología Ágil', 'Velocidad del equipo inestable entre sprints', 'scrum', 0, 1, 0, 1, 0, 2, 1),
('deuda_backlog_defectos', 'Metodología Ágil', 'Acumulación de defectos en backlog', 'scrum', 0, 1, 1, 1, 0, 3, 1),
('dependencia_equipos_externos', 'Metodología Ágil', 'Dependencia de equipos externos para completar historias', 'scrum', 1, 1, 1, 0, 0, 3, 1);

INSERT INTO users (name, email, password_hash, role_id) VALUES
('Admin', 'admin@example.com', '$2y$12$TEU2ChKY7WJdBOxzBaU52envKOeRT8vosBZZQXAfx/Qm/TLoRHDl.', 1),
('Usuario Demo', 'usuario.demo@example.com', '$2y$12$aKNSIj0oDylU1ZEAcSDMEesIt8xtQWYEzzYBOnCzwKCFKFyThAfTW', 2);

INSERT INTO priorities (code, label) VALUES ('high', 'Alta'), ('medium', 'Media'), ('low', 'Baja');
INSERT INTO project_status (code, label) VALUES ('ideation', 'Ideación'), ('execution', 'Ejecución'), ('closed', 'Cerrado');
INSERT INTO project_health (code, label) VALUES ('on_track', 'En curso'), ('at_risk', 'En riesgo'), ('critical', 'Crítico');

INSERT INTO client_sectors (code, label) VALUES ('tech', 'Tecnología'), ('finance', 'Finanzas'), ('retail', 'Retail');
INSERT INTO client_categories (code, label) VALUES ('enterprise', 'Enterprise'), ('scaleup', 'Scale-Up'), ('public', 'Sector público');
INSERT INTO client_status (code, label) VALUES ('active', 'Activo'), ('on_hold', 'En pausa'), ('prospect', 'Prospecto');
INSERT INTO client_risk (code, label) VALUES ('low', 'Bajo'), ('moderate', 'Moderado'), ('high', 'Alto');
INSERT INTO client_areas (code, label) VALUES ('digital_transformation', 'Transformación Digital'), ('operations', 'Operaciones'), ('marketing', 'Marketing');

INSERT INTO clients (name, sector_code, category_code, priority, health, status_code, pm_id, satisfaction, nps, risk_level, tags, area, logo_path, feedback_notes, feedback_history, operational_context)
VALUES ('Acme Corp', 'tech', 'enterprise', 'high', 'on_track', 'active', 1, 85, 70, 'moderate', 'innovación,cloud', 'digital_transformation', NULL, 'Cliente satisfecho con avances del roadmap.', 'Reunión trimestral positiva, solicita roadmap Q4.', 'Opera en múltiples países, foco en integración omnicanal.');

INSERT INTO projects (client_id, pm_id, name, status, health, priority, budget, actual_cost, planned_hours, actual_hours, progress, start_date)
VALUES (1, 1, 'Onboarding Digital', 'execution', 'on_track', 'high', 120000, 45000, 800, 320, 40, CURDATE());

INSERT INTO talents (user_id, name, role, seniority, weekly_capacity, availability, hourly_cost, hourly_rate)
VALUES (1, 'Patricia Silva', 'Project Manager', 'Senior', 40, 80, 35, 70);

INSERT INTO skills (name) VALUES ('Agile'), ('Scrum'), ('PHP'), ('MySQL');
INSERT INTO talent_skills (talent_id, skill_id) VALUES (1,1), (1,2);

INSERT INTO tasks (project_id, assignee_id, title, description, status, priority, estimated_hours, actual_hours, due_date)
VALUES (1, 1, 'Definir plan de despliegue', 'Plan detallado de despliegue por fases', 'in_progress', 'high', 40, 12, DATE_ADD(CURDATE(), INTERVAL 7 DAY));

INSERT INTO timesheets (task_id, talent_id, date, hours, status, billable)
VALUES (1, 1, CURDATE(), 6, 'approved', 1);

INSERT INTO revenues (project_id, description, amount, recognized_at) VALUES (1, 'Hito 1', 25000, CURDATE());
INSERT INTO costs (project_id, description, amount, incurred_at) VALUES (1, 'Servicios en la nube', 12000, CURDATE());
