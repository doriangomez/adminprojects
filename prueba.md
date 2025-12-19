# Plataforma de Gestión de Portafolio (PHP + MySQL)

Diseño completo y accionable para construir la plataforma mostrada en las capturas, lista para que un equipo la implemente con PHP 8.2 y MySQL 8. Se prioriza modularidad, seguridad y una UX moderna centrada en dashboards y tableros Kanban.

## 1) Stack recomendado
- **Backend:** PHP 8.2 + **Laravel 10** (incluye ORM, colas, validaciones, políticas y migraciones). Slim/Lumen es opcional si se busca microservicio.
- **Base de datos:** MySQL 8 (InnoDB, claves foráneas, particiones por fecha para históricos de horas).
- **Cache/colas:** Redis para sesiones, cache de métricas y colas (WebSockets, emails).
- **Frontend:** Blade + **Tailwind** o **Bootstrap 5**. Livewire o Alpine.js para interactividad ligera.
- **Autenticación:** JWT + cookies HttpOnly/SameSite=Lax; roles/permisos via gates/policies.
- **Infra:** Docker Compose (php-fpm, nginx, mysql, redis) + Sail opcional. 

## 2) Arquitectura lógica
```
./
├─ public/            # index.php, assets compilados
├─ app/
│  ├─ Http/
│  │  ├─ Controllers/
│  │  ├─ Middleware/
│  │  └─ Requests/
│  ├─ Models/
│  ├─ Policies/
│  └─ Services/       # cálculos de KPIs y reglas de negocio
├─ resources/views/   # dashboard, clientes, proyectos, kanban, horas
├─ database/
│  ├─ migrations/
│  └─ seeders/
└─ tests/             # Feature y API tests
```

## 3) Modelo de datos (extracto clave)
Tablas mínimas para roles, usuarios, clientes, proyectos, tareas, registro de horas y auditoría.
```sql
CREATE TABLE roles (
    id TINYINT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE usuarios (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol_id TINYINT NOT NULL,
    estado ENUM('activo','inactivo') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id)
);

CREATE TABLE clientes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    industria VARCHAR(120),
    contacto VARCHAR(120),
    email_contacto VARCHAR(120),
    telefono_contacto VARCHAR(50),
    estado ENUM('prospecto','activo','inactivo') DEFAULT 'prospecto',
    nps TINYINT,
    total_facturado DECIMAL(14,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE proyectos (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cliente_id BIGINT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    estado ENUM('planificacion','ejecucion','pausa','cerrado') DEFAULT 'planificacion',
    fecha_inicio DATE,
    fecha_fin DATE,
    presupuesto_plan DECIMAL(14,2) DEFAULT 0,
    presupuesto_real DECIMAL(14,2) DEFAULT 0,
    avance TINYINT DEFAULT 0,
    prioridad ENUM('baja','media','alta','critica') DEFAULT 'media',
    descripcion TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

CREATE TABLE talento (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    usuario_id BIGINT NOT NULL,
    especialidad VARCHAR(120),
    nivel ENUM('junior','middle','senior'),
    tasa_costo DECIMAL(10,2),
    tasa_facturacion DECIMAL(10,2),
    disponibilidad SMALLINT DEFAULT 100,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE tareas (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    proyecto_id BIGINT NOT NULL,
    asignado_id BIGINT,
    titulo VARCHAR(200) NOT NULL,
    estado ENUM('pendiente','progreso','revision','bloqueado','completado') DEFAULT 'pendiente',
    prioridad ENUM('baja','media','alta','urgente') DEFAULT 'media',
    estimado_horas DECIMAL(8,2),
    horas_reales DECIMAL(8,2) DEFAULT 0,
    fecha_vencimiento DATE,
    descripcion TEXT,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id),
    FOREIGN KEY (asignado_id) REFERENCES talento(id)
);

CREATE TABLE horas (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tarea_id BIGINT NOT NULL,
    talento_id BIGINT NOT NULL,
    fecha DATE NOT NULL,
    horas DECIMAL(6,2) NOT NULL,
    descripcion TEXT,
    estado ENUM('borrador','enviada','aprobada','rechazada') DEFAULT 'borrador',
    facturable BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tarea_id) REFERENCES tareas(id),
    FOREIGN KEY (talento_id) REFERENCES talento(id)
);

CREATE TABLE auditoria (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    usuario_id BIGINT,
    accion VARCHAR(120),
    detalle JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 4) Roles, permisos y experiencia
- **Administrador:** crud completo, gestión de usuarios/roles, configuraciones.
- **PMO/Project Manager:** proyectos, tareas, aprobaciones de horas, dashboards de margen y riesgo.
- **Talento:** ve/edita sus tareas y registra horas; Kanban personal y calendario.
- **Cliente (opcional):** sólo lectura de avance, rentabilidad y horas aprobadas.

UX clave: dashboards con tarjetas y badges de riesgo, filtros dinámicos, Kanban drag & drop con actualizaciones optimistas, cards de talento (disponibilidad, tasas, contacto), modo oscuro y reportes exportables (CSV/PDF).

## 5) Endpoints y vistas principales
- `POST /auth/login` (JWT + refresh en cookie HttpOnly) y `POST /auth/refresh`.
- `GET /dashboard` métricas cacheadas (margen, utilización, proyectos activos, alertas SLA).
- `CRUD /clientes` con filtros, NPS y facturación.
- `CRUD /proyectos` con cálculo SQL del margen: `SUM(ingresos) - SUM(costos_talento)`.
- `CRUD /tareas` + `PATCH /tareas/{id}/estado` para mover en Kanban.
- `POST /horas`, `PATCH /horas/{id}/aprobar` con reglas por rol.
- `GET /rentabilidad` → KPIs por proyecto, variación presupuestal.
- `GET /pmo/portafolio` → consolidado de carga y riesgo por proyecto.

### Consultas de métricas (ejemplos)
```sql
-- Utilización por talento (últimos 30 días)
SELECT t.id, u.nombre,
       SUM(CASE WHEN h.facturable THEN h.horas ELSE 0 END) AS horas_facturables,
       SUM(h.horas) AS horas_totales,
       ROUND(SUM(h.horas) / 30 / 8 * 100, 1) AS porcentaje_utilizacion
FROM talento t
JOIN usuarios u ON u.id = t.usuario_id
LEFT JOIN horas h ON h.talento_id = t.id AND h.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY t.id;

-- Rentabilidad por proyecto
SELECT p.id, p.nombre,
       SUM(h.horas * t.tasa_facturacion) AS ingresos,
       SUM(h.horas * t.tasa_costo) AS costos,
       ROUND((SUM(h.horas * t.tasa_facturacion) - SUM(h.horas * t.tasa_costo)) / NULLIF(SUM(h.horas * t.tasa_facturacion),0) * 100, 2) AS margen
FROM proyectos p
JOIN tareas ta ON ta.proyecto_id = p.id
JOIN horas h ON h.tarea_id = ta.id AND h.estado='aprobada'
JOIN talento t ON t.id = h.talento_id
GROUP BY p.id;
```

## 6) Seguridad
- Middleware `auth` valida JWT y estado del usuario.
- Middleware `role`/`can` aplica permisos por ruta (Administrador, PMO, Talento, Cliente).
- Cookies HttpOnly + SameSite=Lax, rotación de refresh tokens, protección CSRF en vistas.
- Auditoría de acciones críticas (aprobaciones, cambios de presupuesto, roles) en tabla `auditoria`.
- Validaciones server-side en `FormRequest`; sanitización y rate limiting por IP/usuario.

## 7) Entrega rápida (local y nube)
1. Crear `.env` (clave `JWT_SECRET`, credenciales MySQL).
2. `docker compose up -d` (nginx, php-fpm, mysql, redis).
3. `php artisan migrate --seed` (roles, admin inicial, catálogos de estados/prioridades).
4. `npm install && npm run build` (Tailwind/Bootstrap + Vite).
5. Cargar factories para clientes, proyectos, tareas y horas de muestra.
6. Monitoreo: healthcheck `/health`, logs con Monolog a stdout y alerta en Slack/Email para errores 5xx.

**Ejemplo docker-compose.yml (resumen):**
```yaml
services:
  app:
    image: php:8.2-fpm
    volumes: [".:/var/www/html"]
    environment:
      - PHP_MEMORY_LIMIT=512M
    depends_on: [db, redis]
  web:
    image: nginx:1.25
    volumes: [".:/var/www/html", "./docker/nginx.conf:/etc/nginx/conf.d/default.conf"]
    ports: ["8080:80"]
    depends_on: [app]
  db:
    image: mysql:8
    environment:
      MYSQL_DATABASE: portafolio
      MYSQL_ROOT_PASSWORD: root
    volumes: ["db_data:/var/lib/mysql"]
  redis:
    image: redis:7
volumes:
  db_data:
```

## 8) Flujos de usuario clave
- **Inicio de sesión:** email + password → JWT (local storage) + refresh en cookie; redirección a dashboard.
- **Gestión de tareas:** Kanban drag & drop; cambios de estado vía `PATCH /tareas/{id}/estado` con feedback optimista.
- **Registro/aprobación de horas:** talento crea horas (borrador → enviada); PMO aprueba/rechaza; cálculo automático de facturable.
- **Dashboard PMO:** cards de margen, utilización, riesgos de SLA; alertas por WebSockets (Redis + Pusher/Socket.IO).
- **Reportes:** export CSV/PDF por rango de fechas, proyecto o cliente; filtros con debounce.

## 9) Calidad y mantenimiento
- Tests: API (auth, roles, CRUD), servicios de KPIs y policies. PHPUnit + Pest opcional.
- CI/CD: lint (phpstan/phpcs), tests y build de assets en cada PR. Despliegue blue/green o contenedores inmutables.
- Observabilidad: logging estructurado, métricas (Prometheus + exporters de Nginx/PHP-FPM), trazas opcionales (OpenTelemetry).

## 10) Roadmap sugerido (4-6 semanas)
1. Semana 1: setup Docker, auth JWT, migraciones básicas y seeders.
2. Semana 2: CRUD clientes/proyectos/tareas, Kanban inicial, registro de horas.
3. Semana 3: dashboards de margen/utilización, aprobaciones de horas, exportes CSV/PDF.
4. Semana 4: notificaciones en tiempo real, auditoría y hardening de seguridad.
5. Semanas 5-6: refinar UX (tema corporativo, modo oscuro), monitoreo y ajustes de performance.

Esta guía sirve como blueprint listo para implementar la plataforma de manera práctica, escalable y con una experiencia visual moderna.
