# Prompt Maestro – Sistema de Gestión de Proyectos (PHP 8 + MySQL)

Este repo contiene una base MVC ligera (sin frameworks) para un PMO/ERP de proyectos. Incluye control de acceso por roles, vistas HTML corporativas y un esquema MySQL completo con datos de ejemplo.

## Requisitos
- PHP 8.1+ con extensiones `pdo_mysql` y `mysqli` habilitadas.
- MySQL 8.

## Configuración de base de datos
Las credenciales viven en `src/config.php` y se leen desde variables de entorno. Puedes exportarlas antes de levantar el servidor o definirlas en tu gestor de servicios:

```bash
export DB_HOST=localhost
export DB_PORT=3306
export DB_NAME=pmo
export DB_USER=pmo_user
export DB_PASSWORD=secret
export APP_KEY=cambia-esta-clave
```

Si no defines variables, se usarán los valores por defecto indicados en `src/config.php`.

## Crear la base de datos
Ejecuta el script SQL incluido para crear tablas y datos semilla:

```bash
mysql -u "$DB_USER" -p"$DB_PASSWORD" -h "$DB_HOST" -P "$DB_PORT" "$DB_NAME" < data/schema.sql
```

> Asegúrate de crear previamente la base (`CREATE DATABASE pmo CHARACTER SET utf8mb4;`) o usa otro nombre configurándolo en `DB_NAME`.

## Ejecutar en local
Levanta el servidor embebido de PHP:

```bash
php -S 0.0.0.0:8000 -t public
```

Luego visita `http://localhost:8000`. El login usa las cuentas semilla definidas en `data/schema.sql` (por ejemplo `admin@example.com` / `password`).

## Estructura relevante
- `public/index.php`: front controller y router.
- `src/Core`: núcleo (App, Router, Controller base, Auth, Database).
- `src/Controllers`: controladores por módulo.
- `src/Repositories`: consultas a MySQL por módulo.
- `src/Services`: lógica de negocio (por ejemplo KPIs de dashboard).
- `src/Views`: vistas HTML con layout corporativo.
- `data/schema.sql`: definición completa del esquema y seeds.

## Notas de seguridad
- Las contraseñas de ejemplo usan `password_hash`; cambia las credenciales y `APP_KEY` en producción.
- Usa HTTPS y configura tu servidor web (Nginx/Apache) para entornos reales.
