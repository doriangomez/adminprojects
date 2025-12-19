<?php

class Database
{
    private string $file;
    private array $data = [];

    public function __construct(string $file)
    {
        $this->file = $file;
        if (!file_exists($file)) {
            file_put_contents($file, json_encode($this->emptyData(), JSON_PRETTY_PRINT));
        }
        $content = file_get_contents($file);
        $this->data = $content ? json_decode($content, true) : $this->emptyData();
    }

    private function emptyData(): array
    {
        return [
            'roles' => [],
            'usuarios' => [],
            'clientes' => [],
            'proyectos' => [],
            'talento' => [],
            'tareas' => [],
            'horas' => [],
            'counters' => [
                'roles' => 0,
                'usuarios' => 0,
                'clientes' => 0,
                'proyectos' => 0,
                'talento' => 0,
                'tareas' => 0,
                'horas' => 0,
            ],
        ];
    }

    public function save(): void
    {
        file_put_contents($this->file, json_encode($this->data, JSON_PRETTY_PRINT));
    }

    public function all(string $table): array
    {
        return $this->data[$table] ?? [];
    }

    public function find(string $table, int $id): ?array
    {
        foreach ($this->data[$table] as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    public function insert(string $table, array $payload): array
    {
        $id = ++$this->data['counters'][$table];
        $payload['id'] = $id;
        $this->data[$table][] = $payload;
        $this->save();
        return $payload;
    }

    public function update(string $table, int $id, array $payload): ?array
    {
        foreach ($this->data[$table] as $index => $row) {
            if ((int) $row['id'] === $id) {
                $this->data[$table][$index] = array_merge($row, $payload, ['id' => $id]);
                $this->save();
                return $this->data[$table][$index];
            }
        }
        return null;
    }

    public function delete(string $table, int $id): bool
    {
        foreach ($this->data[$table] as $index => $row) {
            if ((int) $row['id'] === $id) {
                array_splice($this->data[$table], $index, 1);
                $this->save();
                return true;
            }
        }
        return false;
    }
}
