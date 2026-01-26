<?php

declare(strict_types=1);

class NotificationService
{
    private ConfigService $configService;
    private NotificationsLogRepository $logRepository;
    private UsersRepository $usersRepository;

    public function __construct(private Database $db)
    {
        $this->configService = new ConfigService($db);
        $this->logRepository = new NotificationsLogRepository($db);
        $this->usersRepository = new UsersRepository($db);
    }

    public function notify(string $eventType, array $payload = [], ?int $actorId = null): void
    {
        $config = $this->configService->getConfig();
        $notifications = $config['notifications'] ?? [];

        if (empty($notifications['enabled'])) {
            return;
        }

        $eventConfig = $notifications['events'][$eventType] ?? null;
        if (!$eventConfig || empty($eventConfig['enabled'])) {
            return;
        }

        $channels = $eventConfig['channels']['email'] ?? [];
        if (!($channels['enabled'] ?? false)) {
            return;
        }

        $recipients = $this->resolveRecipients($eventConfig['recipients'] ?? [], $payload, $actorId);
        if (empty($recipients)) {
            return;
        }

        $smtpConfig = $notifications['smtp'] ?? [];
        $mailer = $this->buildMailer($smtpConfig);
        $eventLabel = NotificationCatalog::events()[$eventType]['label'] ?? $eventType;
        $subject = sprintf('[Notificación] %s', $eventLabel);
        $body = $this->buildBody($eventLabel, $payload, $actorId);

        foreach ($recipients as $recipient) {
            $recipientEmail = $recipient['email'] ?? '';
            if ($recipientEmail === '') {
                continue;
            }

            try {
                $mailer->send(
                    $recipientEmail,
                    $subject,
                    $body,
                    (string) ($smtpConfig['from_email'] ?? ''),
                    (string) ($smtpConfig['from_name'] ?? '')
                );

                $this->logRepository->log([
                    'event_type' => $eventType,
                    'channel' => 'email',
                    'recipient_email' => $recipientEmail,
                    'recipient_user_id' => $recipient['id'] ?? null,
                    'status' => 'sent',
                    'payload' => $payload,
                ]);
            } catch (\Throwable $e) {
                $this->logRepository->log([
                    'event_type' => $eventType,
                    'channel' => 'email',
                    'recipient_email' => $recipientEmail,
                    'recipient_user_id' => $recipient['id'] ?? null,
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                    'payload' => $payload,
                ]);
                error_log('Error al enviar notificación: ' . $e->getMessage());
            }
        }
    }

    public function sendTestEmail(array $smtpConfig, string $recipientEmail): array
    {
        if ($recipientEmail === '') {
            return ['success' => false, 'message' => 'El correo de prueba es obligatorio.'];
        }

        try {
            $mailer = $this->buildMailer($smtpConfig);
            $mailer->send(
                $recipientEmail,
                '[Notificación] Correo de prueba',
                "Este es un correo de prueba de notificaciones.\nFecha: " . date('Y-m-d H:i:s'),
                (string) ($smtpConfig['from_email'] ?? ''),
                (string) ($smtpConfig['from_name'] ?? '')
            );

            $this->logRepository->log([
                'event_type' => 'system.test_email',
                'channel' => 'email',
                'recipient_email' => $recipientEmail,
                'status' => 'sent',
                'payload' => [
                    'from_email' => $smtpConfig['from_email'] ?? null,
                ],
            ]);

            return ['success' => true, 'message' => 'Correo de prueba enviado.'];
        } catch (\Throwable $e) {
            $this->logRepository->log([
                'event_type' => 'system.test_email',
                'channel' => 'email',
                'recipient_email' => $recipientEmail,
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'payload' => [
                    'from_email' => $smtpConfig['from_email'] ?? null,
                ],
            ]);

            return ['success' => false, 'message' => 'No se pudo enviar el correo de prueba.'];
        }
    }

    private function resolveRecipients(array $rules, array $payload, ?int $actorId): array
    {
        $recipientIds = [];

        $roles = $rules['roles'] ?? [];
        if (!empty($roles)) {
            $roleUsers = $this->usersRepository->findByRoleNames($roles);
            foreach ($roleUsers as $user) {
                $recipientIds[$user['id']] = $user;
            }
        }

        if (!empty($rules['include_actor']) && $actorId) {
            $actor = $this->usersRepository->find($actorId);
            if ($actor && !empty($actor['email'])) {
                $recipientIds[$actor['id']] = $actor;
            }
        }

        if (!empty($rules['include_project_manager']) && !empty($payload['project_id'])) {
            $pmId = $this->projectManagerId((int) $payload['project_id']);
            if ($pmId) {
                $pm = $this->usersRepository->find($pmId);
                if ($pm && !empty($pm['email'])) {
                    $recipientIds[$pm['id']] = $pm;
                }
            }
        }

        $related = [
            'include_reviewer' => 'reviewer_id',
            'include_validator' => 'validator_id',
            'include_approver' => 'approver_id',
            'include_target_user' => 'target_user_id',
        ];

        foreach ($related as $flag => $field) {
            if (!empty($rules[$flag]) && !empty($payload[$field])) {
                $user = $this->usersRepository->find((int) $payload[$field]);
                if ($user && !empty($user['email'])) {
                    $recipientIds[$user['id']] = $user;
                }
            }
        }

        return array_values($recipientIds);
    }

    private function buildBody(string $label, array $payload, ?int $actorId): string
    {
        $lines = [
            "Evento: {$label}",
            'Fecha: ' . date('Y-m-d H:i:s'),
        ];

        if ($actorId) {
            $actor = $this->usersRepository->find($actorId);
            if ($actor) {
                $lines[] = 'Usuario: ' . ($actor['name'] ?? $actor['email'] ?? '');
            }
        }

        if (!empty($payload)) {
            $lines[] = '';
            $lines[] = 'Detalle:';
            $lines[] = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return implode("\n", $lines);
    }

    private function projectManagerId(int $projectId): ?int
    {
        $row = $this->db->fetchOne(
            'SELECT pm_id FROM projects WHERE id = :id LIMIT 1',
            [':id' => $projectId]
        );

        return $row && isset($row['pm_id']) ? (int) $row['pm_id'] : null;
    }

    private function buildMailer(array $smtpConfig): SmtpMailer
    {
        $encryption = new EncryptionService();
        $password = $encryption->decrypt((string) ($smtpConfig['password'] ?? ''));

        return new SmtpMailer(
            (string) ($smtpConfig['host'] ?? ''),
            (int) ($smtpConfig['port'] ?? 587),
            (string) ($smtpConfig['security'] ?? 'tls'),
            (string) ($smtpConfig['username'] ?? ''),
            $password
        );
    }
}

class SmtpMailer
{
    private $socket;

    public function __construct(
        private string $host,
        private int $port,
        private string $security,
        private string $username,
        private string $password,
        private int $timeout = 10
    ) {
    }

    public function send(string $to, string $subject, string $body, string $fromEmail, string $fromName): void
    {
        $this->connect();
        $this->expect([220]);
        $this->command('EHLO localhost', [250]);

        if ($this->security === 'tls') {
            $this->command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('No se pudo iniciar TLS.');
            }
            $this->command('EHLO localhost', [250]);
        }

        if ($this->username !== '') {
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($this->username), [334]);
            $this->command(base64_encode($this->password), [235]);
        }

        $fromEmail = $fromEmail !== '' ? $fromEmail : $this->username;
        if ($fromEmail === '') {
            throw new \RuntimeException('Correo remitente no configurado.');
        }

        $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
        $this->command('RCPT TO:<' . $to . '>', [250, 251]);
        $this->command('DATA', [354]);

        $headers = [
            'From: ' . $this->formatFrom($fromEmail, $fromName),
            'To: ' . $to,
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $this->normalizeBody($body) . "\r\n.";
        $this->write($message . "\r\n");
        $this->expect([250]);
        $this->command('QUIT', [221]);
        $this->close();
    }

    private function connect(): void
    {
        $prefix = $this->security === 'ssl' ? 'ssl://' : '';
        $this->socket = fsockopen($prefix . $this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new \RuntimeException('No se pudo conectar al SMTP: ' . $errstr);
        }
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }

    private function command(string $command, array $expectedCodes): void
    {
        $this->write($command . "\r\n");
        $this->expect($expectedCodes);
    }

    private function write(string $data): void
    {
        if (fwrite($this->socket, $data) === false) {
            throw new \RuntimeException('No se pudo escribir en SMTP.');
        }
    }

    private function expect(array $expectedCodes): void
    {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException('SMTP error: ' . trim($response));
        }
    }

    private function formatFrom(string $email, string $name): string
    {
        if ($name === '') {
            return $email;
        }

        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function normalizeBody(string $body): string
    {
        $lines = preg_split("/\\r?\\n/", $body);
        $escaped = array_map(static function (string $line): string {
            return str_starts_with($line, '.') ? '.' . $line : $line;
        }, $lines);

        return implode("\r\n", $escaped);
    }
}
