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
    budget DECIMAL(12,2) DEFAULT 0,
    actual_cost DECIMAL(12,2) DEFAULT 0,
    planned_hours INT DEFAULT 0,
    actual_hours INT DEFAULT 0,
    progress DECIMAL(5,2) DEFAULT 0,
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (pm_id) REFERENCES users(id)
);

CREATE TABLE project_risks (
    project_id INT NOT NULL,
    risk_code VARCHAR(80) NOT NULL,
    PRIMARY KEY (project_id, risk_code),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
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
    FOREIGN KEY (project_id) REFERENCES projects(id)
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
    FOREIGN KEY (task_id) REFERENCES tasks(id),
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
    active TINYINT(1) DEFAULT 1,
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (talent_id) REFERENCES talents(id)
) ENGINE=InnoDB;

CREATE TABLE costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    description VARCHAR(180),
    amount DECIMAL(12,2) NOT NULL,
    incurred_at DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE revenues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    description VARCHAR(180),
    amount DECIMAL(12,2) NOT NULL,
    recognized_at DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id)
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
            "scrum": ["descubrimiento", "backlog listo", "sprint", "deploy"],
            "cascada": ["inicio", "planificación", "ejecución", "cierre"],
            "kanban": ["por hacer", "en curso", "en revisión", "hecho"]
        },
        "risks": [
            {"code": "scope_creep", "label": "Desviación de alcance"},
            {"code": "budget_overrun", "label": "Sobrepaso de presupuesto"},
            {"code": "timeline_slip", "label": "Desviación en cronograma"}
        ]
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
