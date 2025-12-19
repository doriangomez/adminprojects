<?php
require_once __DIR__ . '/Database.php';

date_default_timezone_set('UTC');

class App
{
    private Database $db;
    private string $secret;

    public function __construct()
    {
        $this->db = new Database(__DIR__ . '/../data/data.json');
        $this->secret = getenv('APP_KEY') ?: 'dev-secret';
        $this->seed();
    }

    public function handle(): void
    {
        header('Content-Type: application/json');
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        if ($path === '/auth/login' && $method === 'POST') {
            $this->login();
            return;
        }

        $user = $this->authenticate();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        if ($path === '/dashboard' && $method === 'GET') {
            $this->dashboard();
            return;
        }

        if ($this->match('/clientes', $path)) {
            $this->crud('clientes', $method);
            return;
        }

        if ($this->match('/proyectos', $path)) {
            $this->crud('proyectos', $method);
            return;
        }

        if ($this->match('/tareas', $path)) {
            $this->crud('tareas', $method);
            return;
        }

        if ($this->match('/horas', $path)) {
            $this->createHoras($method);
            return;
        }

        if (preg_match('#^/tareas/(\\d+)/estado$#', $path, $matches) && $method === 'PATCH') {
            $this->updateTaskState((int) $matches[1]);
            return;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Ruta no encontrada']);
    }

    private function match(string $segment, string $path): bool
    {
        return $path === $segment || preg_match('#^' . $segment . '/\\d+$#', $path);
    }

    private function login(): void
    {
        $payload = $this->input();
        $email = $payload['email'] ?? '';
        $password = $payload['password'] ?? '';

        foreach ($this->db->all('usuarios') as $user) {
            if ($user['email'] === $email && password_verify($password, $user['password_hash'])) {
                $token = $this->signJwt(['sub' => $user['id'], 'role' => $user['rol'], 'exp' => time() + 3600]);
                echo json_encode(['token' => $token, 'usuario' => ['id' => $user['id'], 'nombre' => $user['nombre'], 'rol' => $user['rol']]]);
                return;
            }
        }

        http_response_code(401);
        echo json_encode(['error' => 'Credenciales inválidas']);
    }

    private function authenticate(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $token = trim(substr($header, 7));
        $payload = $this->decodeJwt($token);
        if (!$payload || ($payload['exp'] ?? 0) < time()) {
            return null;
        }
        return $this->db->find('usuarios', (int) $payload['sub']);
    }

    private function dashboard(): void
    {
        $clientes = $this->db->all('clientes');
        $proyectos = $this->db->all('proyectos');
        $tareas = $this->db->all('tareas');
        $horas = $this->db->all('horas');

        $margen = 0;
        foreach ($horas as $registro) {
            $tarea = $this->db->find('tareas', (int) $registro['tarea_id']);
            if (!$tarea) {
                continue;
            }
            $proyecto = $this->db->find('proyectos', (int) $tarea['proyecto_id']);
            $talento = $this->db->find('talento', (int) $registro['talento_id']);
            $ingreso = $registro['horas'] * ($talento['tasa_facturacion'] ?? 0);
            $costo = $registro['horas'] * ($talento['tasa_costo'] ?? 0);
            $margen += $ingreso - $costo;
        }

        $payload = [
            'clientes_activos' => count($clientes),
            'proyectos_activos' => count(array_filter($proyectos, fn($p) => $p['estado'] !== 'cerrado')),
            'tareas_pendientes' => count(array_filter($tareas, fn($t) => $t['estado'] !== 'completado')),
            'margen_estimado' => round($margen, 2),
        ];

        echo json_encode($payload);
    }

    private function crud(string $table, string $method): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if ($method === 'GET' && preg_match('#/\\d+$#', $path)) {
            $id = (int) basename($path);
            $item = $this->db->find($table, $id);
            if ($item) {
                echo json_encode($item);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'No encontrado']);
            }
            return;
        }

        if ($method === 'GET') {
            echo json_encode($this->db->all($table));
            return;
        }

        if ($method === 'POST') {
            $payload = $this->input();
            $payload['created_at'] = date('c');
            echo json_encode($this->db->insert($table, $payload), JSON_PRETTY_PRINT);
            return;
        }

        if (in_array($method, ['PUT', 'PATCH'], true) && preg_match('#/\\d+$#', $path)) {
            $id = (int) basename($path);
            $payload = $this->input();
            $updated = $this->db->update($table, $id, $payload);
            if ($updated) {
                echo json_encode($updated);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'No encontrado']);
            }
            return;
        }

        if ($method === 'DELETE' && preg_match('#/\\d+$#', $path)) {
            $id = (int) basename($path);
            $deleted = $this->db->delete($table, $id);
            if ($deleted) {
                echo json_encode(['status' => 'ok']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'No encontrado']);
            }
            return;
        }

        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
    }

    private function createHoras(string $method): void
    {
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            return;
        }
        $payload = $this->input();
        if (!$this->db->find('tareas', (int) ($payload['tarea_id'] ?? 0))) {
            http_response_code(422);
            echo json_encode(['error' => 'Tarea inexistente']);
            return;
        }
        if (!$this->db->find('talento', (int) ($payload['talento_id'] ?? 0))) {
            http_response_code(422);
            echo json_encode(['error' => 'Talento inexistente']);
            return;
        }
        $payload['estado'] = $payload['estado'] ?? 'enviada';
        $payload['created_at'] = date('c');
        echo json_encode($this->db->insert('horas', $payload));
    }

    private function updateTaskState(int $id): void
    {
        $payload = $this->input();
        $estado = $payload['estado'] ?? '';
        $allowed = ['pendiente', 'progreso', 'revision', 'bloqueado', 'completado'];
        if (!in_array($estado, $allowed, true)) {
            http_response_code(422);
            echo json_encode(['error' => 'Estado no válido']);
            return;
        }
        $updated = $this->db->update('tareas', $id, ['estado' => $estado]);
        if ($updated) {
            echo json_encode($updated);
            return;
        }
        http_response_code(404);
        echo json_encode(['error' => 'Tarea no encontrada']);
    }

    private function input(): array
    {
        $json = file_get_contents('php://input');
        return $json ? json_decode($json, true) : [];
    }

    private function signJwt(array $claims): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode($claims));
        $signature = hash_hmac('sha256', $header . '.' . $payload, $this->secret, true);
        return $header . '.' . $payload . '.' . base64_encode($signature);
    }

    private function decodeJwt(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $signature] = $parts;
        $expected = base64_encode(hash_hmac('sha256', $header . '.' . $payload, $this->secret, true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }
        return json_decode(base64_decode($payload), true);
    }

    private function seed(): void
    {
        if (count($this->db->all('roles')) > 0) {
            return;
        }

        $adminRole = $this->db->insert('roles', ['nombre' => 'Administrador']);
        $pmoRole = $this->db->insert('roles', ['nombre' => 'PMO']);
        $talentRole = $this->db->insert('roles', ['nombre' => 'Talento']);

        $admin = $this->db->insert('usuarios', [
            'nombre' => 'Admin',
            'email' => 'admin@example.com',
            'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
            'rol' => 'Administrador',
            'rol_id' => $adminRole['id'],
            'estado' => 'activo',
        ]);

        $cliente = $this->db->insert('clientes', [
            'nombre' => 'Acme Corp',
            'industria' => 'Tecnología',
            'estado' => 'activo',
            'nps' => 70,
            'total_facturado' => 120000,
            'created_at' => date('c'),
        ]);

        $proyecto = $this->db->insert('proyectos', [
            'cliente_id' => $cliente['id'],
            'nombre' => 'Onboarding Digital',
            'estado' => 'ejecucion',
            'fecha_inicio' => date('Y-m-d', strtotime('-30 days')),
            'presupuesto_plan' => 50000,
            'presupuesto_real' => 12000,
            'avance' => 35,
            'prioridad' => 'alta',
            'descripcion' => 'Implementación de un onboarding digital multi-dispositivo.',
            'created_at' => date('c'),
        ]);

        $talento = $this->db->insert('talento', [
            'usuario_id' => $admin['id'],
            'especialidad' => 'Project Manager',
            'nivel' => 'senior',
            'tasa_costo' => 30,
            'tasa_facturacion' => 60,
            'disponibilidad' => 80,
            'created_at' => date('c'),
        ]);

        $tarea = $this->db->insert('tareas', [
            'proyecto_id' => $proyecto['id'],
            'asignado_id' => $talento['id'],
            'titulo' => 'Definir plan de despliegue',
            'estado' => 'progreso',
            'prioridad' => 'alta',
            'estimado_horas' => 16,
            'horas_reales' => 8,
            'fecha_vencimiento' => date('Y-m-d', strtotime('+7 days')),
            'descripcion' => 'Plan detallado de despliegue por fases.',
            'created_at' => date('c'),
        ]);

        $this->db->insert('horas', [
            'tarea_id' => $tarea['id'],
            'talento_id' => $talento['id'],
            'fecha' => date('Y-m-d'),
            'horas' => 4,
            'descripcion' => 'Revisión de hitos y riesgos',
            'estado' => 'aprobada',
            'facturable' => true,
            'created_at' => date('c'),
        ]);
    }
}
