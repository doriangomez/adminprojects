<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class PermissionsRepository
{
    public function __construct(private Database $db)
    {
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM permissions ORDER BY name');
    }
}
