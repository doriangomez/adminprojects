<?php

declare(strict_types=1);

class NotificationCatalog
{
    public static function events(): array
    {
        return [
            'timesheet.submitted' => [
                'label' => 'Timesheet registrado',
                'description' => 'Registro de horas enviado para aprobación o auto-aprobado.',
                'default_recipients' => [
                    'roles' => ['Administrador', 'PMO'],
                    'include_target_user' => false,
                ],
            ],
            'timesheet.approved' => [
                'label' => 'Timesheet aprobado',
                'description' => 'Horas aprobadas por un responsable.',
                'default_recipients' => [
                    'roles' => ['Administrador', 'PMO'],
                    'include_target_user' => true,
                ],
            ],
            'timesheet.rejected' => [
                'label' => 'Timesheet rechazado',
                'description' => 'Horas rechazadas con comentario.',
                'default_recipients' => [
                    'roles' => ['Administrador', 'PMO'],
                    'include_target_user' => true,
                ],
            ],
            'project.created' => [
                'label' => 'Proyecto creado',
                'description' => 'Alta de un nuevo proyecto.',
                'default_recipients' => [
                    'roles' => ['Administrador', 'PMO'],
                    'include_project_manager' => true,
                ],
            ],
            'project.closed' => [
                'label' => 'Proyecto cerrado',
                'description' => 'Cierre de proyecto confirmado.',
                'default_recipients' => [
                    'roles' => ['Administrador', 'PMO'],
                    'include_project_manager' => true,
                ],
            ],
            'document.sent_approval' => [
                'label' => 'Documento enviado a aprobación',
                'description' => 'Documento listo para aprobación final.',
                'default_recipients' => [
                    'roles' => ['Administrador', 'PMO'],
                    'include_approver' => true,
                ],
            ],
            'document.approved' => [
                'label' => 'Documento aprobado',
                'description' => 'Documento aprobado dentro del flujo documental.',
                'default_recipients' => [
                    'roles' => ['Administrador', 'PMO'],
                    'include_project_manager' => true,
                ],
            ],
            'document.rejected' => [
                'label' => 'Documento rechazado',
                'description' => 'Documento rechazado en el flujo documental.',
                'default_recipients' => [
                    'roles' => ['Administrador', 'PMO'],
                    'include_project_manager' => true,
                ],
            ],
            'system.file_created' => [
                'label' => 'Archivo cargado',
                'description' => 'Se cargó un archivo al repositorio del proyecto.',
                'default_recipients' => [
                    'roles' => ['Administrador', 'PMO'],
                ],
            ],
            'system.file_deleted' => [
                'label' => 'Archivo eliminado',
                'description' => 'Se eliminó un archivo del repositorio del proyecto.',
                'default_recipients' => [
                    'roles' => ['Administrador', 'PMO'],
                ],
            ],
        ];
    }

    public static function defaultSettings(): array
    {
        $defaults = [];

        foreach (self::events() as $code => $meta) {
            $defaults[$code] = [
                'enabled' => false,
                'channels' => [
                    'email' => [
                        'enabled' => true,
                    ],
                ],
                'recipients' => array_merge([
                    'roles' => [],
                    'include_actor' => false,
                    'include_project_manager' => false,
                    'include_reviewer' => false,
                    'include_validator' => false,
                    'include_approver' => false,
                    'include_target_user' => false,
                ], $meta['default_recipients'] ?? []),
            ];
        }

        return $defaults;
    }
}
