# Plataforma de Gestión de Portafolio (prototipo PHP)

Este repo incluye un prototipo autocontenido para evaluar la plataforma descrita en `prueba.md`. No depende de frameworks externos y utiliza almacenamiento en disco (`data/data.json`) para simplificar la puesta en marcha local.

## Requisitos
- PHP 8.1+ con extensión `json` (habilitada por defecto).

## Ejecución rápida
```bash
php -S 0.0.0.0:8000 -t public
```

Luego visita http://localhost:8000.

## Endpoints principales
- `POST /auth/login` — recibe `{ "email": "admin@example.com", "password": "admin123" }` y devuelve un token JWT HS256 firmado con `APP_KEY`.
- `GET /dashboard` — métricas básicas (requiere encabezado `Authorization: Bearer <token>`).
- `CRUD /clientes`, `CRUD /proyectos`, `CRUD /tareas`, `POST /horas` y `PATCH /tareas/{id}/estado` — operaciones principales con reglas mínimas de validación.

Consulta `src/app.php` para ver todas las rutas y `data/data.json` para los datos de ejemplo.

## Configuración opcional
- Define `APP_KEY` en el entorno para firmar los tokens (por defecto `dev-secret`).
- Ajusta `data/data.json` para precargar clientes, proyectos o tareas.

## Notas
Este código busca ilustrar la arquitectura y los flujos clave mencionados en `prueba.md`; no pretende ser una implementación productiva. Se mantiene deliberadamente simple para favorecer la lectura y las pruebas rápidas.
