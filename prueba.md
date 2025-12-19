# Plataforma de Gestión de Portafolio (PHP + MySQL)

Propuesta integral para implementar la plataforma mostrada en las capturas, optimizada para un stack **PHP 8.2 + MySQL 8** con una arquitectura modular y escalable.

## 1) Arquitectura de la Solución
- **PHP 8.2** con **Laravel/Lumen** o **Slim** (micro) + **Blade/Twig** para vistas.
- **MySQL 8** con **InnoDB**, claves foráneas y particiones por fecha para históricos de horas.
- **Redis** opcional para sesiones y caché de métricas de dashboard.
- **Autenticación JWT + cookies HttpOnly**; roles y permisos en BD con middleware de autorización.
- **Front-end** en Blade + Tailwind/Bootstrap 5 para replicar el look & feel de las pantallas.
- **Docker Compose** para orquestar `php-fpm`, `nginx` y `mysql`.

```
./
├─ public/            # index.php, assets compilados
├─ app/
│  ├─ Http/
│  │  ├─ Controllers/
│  │  ├─ Middleware/
│  │  └─ Requests/
│  ├─ Models/
│  └─ Policies/
├─ resources/views/   # Blade: dashboard, clientes, proyectos...
├─ database/
│  ├─ migrations/
│  └─ seeders/
└─ tests/
```

## 2) Esquema MySQL (extracto clave)
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
```

## 3) Endpoints PHP clave
- `POST /auth/login` → genera JWT; almacena refresh en cookie HttpOnly.
- `GET /dashboard` → métricas cacheadas: proyectos activos, margen, utilización, alertas.
- `CRUD /clientes` → listado con filtros, NPS y total facturado.
- `CRUD /proyectos` → cálculo en SQL del margen: `SUM(ingresos) - SUM(costos_talento)`.
- `CRUD /tareas` + `PATCH /tareas/{id}/estado` para mover en Kanban.
- `POST /horas` y `PATCH /horas/{id}/aprobar` con reglas por rol.
- `GET /rentabilidad` → KPIs por proyecto, comparativos y variación presupuestal.
- `GET /pmo/portafolio` → consolidado de carga y riesgo por proyecto.

## 4) Consultas de Métricas (ejemplos)
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

## 5) Experiencia de Usuario "impactante"
- **Dashboards con tarjetas y badges de riesgo**; alertas en tiempo real con websockets (Pusher/Socket.IO + Redis).
- **Kanban drag & drop** con actualizaciones optimistas y contadores de SLA.
- **Filtros dinámicos** (proyecto, estado, prioridad, rol) y búsqueda instantánea con debounce.
- **Cards de talento** mostrando disponibilidad, tasa y contacto directo.
- **Modo oscuro** y **tema corporativo**; uso de microinteracciones (hover, transitions).
- **Reportes exportables** (CSV/PDF) y **widgets configurables** por rol.

## 6) Flujo de Seguridad y Roles
- Middleware `Auth` valida JWT y estado del usuario.
- Middleware `Role` verifica permisos: Administrador (todo), PMO (operativo sin usuarios), Talento (solo sus tareas/horas), otros roles con scopes por módulo.
- Auditoría: tabla `auditoria` con fecha, usuario y acción.

## 7) Plan de Despliegue Rápido
1. Preparar `.env` con credenciales MySQL y JWT_SECRET.
2. Ejecutar migraciones y seeders iniciales (roles, admin, catálogos).
3. Levantar stack: `docker compose up -d` (nginx + php-fpm + mysql + redis opcional).
4. Compilar assets: `npm install && npm run build`.
5. Cargar datos de prueba con factories para dashboards inmediatos.

## 8) Extensiones Futuras
- BI ligero con Metabase conectado a réplicas MySQL.
- Automatización de alertas por correo/Slack cuando margen < umbral o tareas bloqueadas >48h.
- API pública para integraciones (facturación, HRIS, CRM).
- Módulo de presupuestos con versionado y simulaciones de escenarios.
- IA asistida para estimar horas y detectar riesgos de sobrecarga.

Esta guía resume cómo construir la plataforma de manera sólida con PHP + MySQL, manteniendo una experiencia visual impactante y moderna.
