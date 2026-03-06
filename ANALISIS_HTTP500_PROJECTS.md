# Análisis HTTP 500 en /projects/{id}

## Resumen Ejecutivo

El endpoint `GET /projects/67` devuelve HTTP 500 debido a consultas SQL que referencian la columna `timesheets.project_id` cuando esta **no existe** en la base de datos actual. El sistema ejecuta migraciones automáticas en cada request (App constructor), pero si la migración falla o la BD tiene un esquema antiguo, los servicios asumen que `project_id` existe y ejecutan consultas que provocan el error.

---

## Flujo del Endpoint /projects/{id}

### 1. Ruta y Controlador

- **Archivo:** `src/Core/App.php` líneas 333-334
- **Patrón:** `#^/projects/(\d+)$#` con método GET
- **Método:** `ProjectsController->show((int) $matches[1])`

### 2. Método show() y projectDetailData()

- **Archivo:** `src/Controllers/ProjectsController.php`
- **Líneas 98-102:** `show()` llama a `projectDetailData($id)` y renderiza la vista
- **Líneas 1836-1932:** `projectDetailData()` es el método que orquesta todos los datos

### 3. Orden de Ejecución en projectDetailData()

| # | Componente | Método | Tablas/Columnas Usadas |
|---|------------|--------|------------------------|
| 1 | ProjectsRepository | findForUser | projects, clients, users |
| 2 | ProjectsRepository | assignmentsForProject | project_talent_assignments |
| 3 | ProjectNodesRepository | treeWithFiles | project_nodes |
| 4 | ProjectTreeService | summarizeProgress | project_nodes |
| 5 | AuditLogRepository | listForEntity | audit_log |
| 6 | ProjectNodesRepository | pendingCriticalNodes | project_nodes |
| 7 | ProjectsRepository | timesheetHoursForProject | timesheets, tasks (JOIN) |
| 8 | ProjectsRepository | dependencySummary | timesheets, tasks (JOIN) |
| 9 | **ProjectService** | **calculateProjectHealthReport** | **timesheets.project_id** ⚠️ |
| 10 | ProjectService | history | project_health_history |
| 11 | ProjectBillingRepository | config, invoices, etc. | project_invoices |
| 12 | ProjectStoppersRepository | metricsForProject, byImpactOpen, forProject | project_stoppers |
| 13 | **PmoAutomationService** | **ensureTodaySnapshotForProject** | **timesheets.project_id** ⚠️ |
| 14 | PmoAutomationService | latestAlertsForProject | project_pmo_alerts |
| 15 | **PmoAutomationService** | **hoursTrendForProject** | **timesheets.project_id** ⚠️ |
| 16 | PmoAutomationService | activeBlockersForProject | project_stoppers |

---

## Causa Raíz del HTTP 500

### Columnas Problemáticas

| Tabla | Columna | Estado en schema.sql | Uso sin verificación |
|-------|---------|----------------------|----------------------|
| **timesheets** | **project_id** | Existe (línea 519) | ProjectService, PmoAutomationService |
| timesheets | phase_name | Existe (línea 528) | TimesheetsRepository (usa columnExists) |
| timesheets | activity_type | Existe (línea 530) | TimesheetsRepository (usa columnExists) |
| tasks | completed_at | Existe (línea 352) | TasksRepository (usa columnExists) |
| project_stoppers | task_id | Existe (línea 658) | ProjectStoppersRepository (usa columnExists) |

### Punto de Fallo Identificado

**ProjectService** y **PmoAutomationService** ejecutan consultas que usan `timesheets.project_id` **sin verificar** que la columna exista. Si la migración `ensureTimesheetSchema()` no ha añadido `project_id` (por fallo previo, BD antigua o error en ALTER TABLE), la consulta falla con:

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'project_id' in 'where clause'
```

### Archivos y Líneas Afectadas (antes de la corrección)

| Archivo | Línea | Consulta Problemática |
|---------|-------|------------------------|
| `src/Services/ProjectService.php` | 218 | `WHERE project_id = :projectId` en dimensionIssues |
| `src/Services/ProjectService.php` | 553 | `WHERE project_id = :projectId` en projectActualHours |
| `src/Services/PmoAutomationService.php` | 248 | `WHERE project_id = :project` en approvedHours |
| `src/Services/PmoAutomationService.php` | 147 | `WHERE project_id = :project` en hoursTrendForProject |
| `src/Services/PmoAutomationService.php` | 325 | `WHERE project_id = :project` en blockerMentions |
| `src/Services/PmoAutomationService.php` | 346 | `WHERE project_id = :project` en staleBusinessDays |

---

## Corrección Implementada

Se añadieron comprobaciones defensivas `columnExists('timesheets', 'project_id')` en todos los puntos afectados. Cuando `project_id` no existe, se usa un **JOIN con tasks** para resolver el proyecto:

```sql
-- Fallback cuando project_id no existe en timesheets
FROM timesheets ts
JOIN tasks t ON t.id = ts.task_id
WHERE t.project_id = :project_id
```

### Archivos Modificados

1. **src/Services/PmoAutomationService.php**
   - `approvedHours()`: verificación + fallback con JOIN
   - `hoursTrendForProject()`: verificación + fallback con JOIN
   - `blockerMentions()`: verificación + fallback con JOIN
   - `staleBusinessDays()`: verificación + fallback con JOIN

2. **src/Services/ProjectService.php**
   - `dimensionIssues()` (dimensión 'horas'): verificación + fallback con JOIN
   - `projectActualHours()`: verificación + fallback con JOIN

---

## Validación de Columnas Críticas

| Campo | Tabla | Verificación en Código | Estado |
|-------|-------|------------------------|-------|
| tasks.completed_at | tasks | TasksRepository usa `columnExists` antes de usar | ✅ Seguro |
| timesheets.phase_name | timesheets | TimesheetsRepository usa `structuredTimesheetSelectColumns` con `columnExists` | ✅ Seguro |
| timesheets.activity_type | timesheets | Idem | ✅ Seguro |
| project_stoppers.task_id | project_stoppers | ProjectStoppersRepository usa `columnExists` en create; TasksRepository en taskStopperSql | ✅ Seguro |
| timesheets.project_id | timesheets | **Corregido** en ProjectService y PmoAutomationService | ✅ Corregido |

---

## Migración de Esquema Recomendada

Si la base de datos no tiene `project_id` en timesheets, ejecutar manualmente:

```sql
ALTER TABLE timesheets ADD COLUMN project_id INT NULL AFTER task_id;

-- Rellenar desde tasks
UPDATE timesheets ts
JOIN tasks t ON t.id = ts.task_id
SET ts.project_id = t.project_id
WHERE ts.project_id IS NULL;

-- Índice para rendimiento
ALTER TABLE timesheets ADD INDEX idx_timesheets_project_week (project_id, date);
```

O ejecutar el migrador desde CLI:

```bash
php pmo_engine.php
```

---

## Consultas SQL de ProjectsRepository (sin cambios)

Las consultas de ProjectsRepository ya usan JOIN con tasks y no dependen de `timesheets.project_id`:

- `timesheetHoursForProject`: `ts JOIN tasks t ON t.id = ts.task_id WHERE t.project_id`
- `dependencySummary`: `timesheets t JOIN tasks tk ON tk.id = t.task_id WHERE tk.project_id`

No requieren modificación.
