<?php

declare(strict_types=1);

namespace App;

/**
 * Simpele opslag in een JSON-bestand — genoeg voor een PoC.
 */
final class ItemStore
{
    public function __construct(private readonly string $file)
    {
        if (!is_file($this->file)) {
            $this->save([
                ['id' => 1, 'name' => 'Eerste item', 'done' => false],
                ['id' => 2, 'name' => 'Tweede item', 'done' => true],
            ]);
        }
    }

    public function all(): array
    {
        return json_decode((string) file_get_contents($this->file), true) ?: [];
    }

    public function find(int $id): ?array
    {
        // PHP 8.4: array_find()
        return array_find($this->all(), fn(array $item) => $item['id'] === $id);
    }

    public function create(array $data): array
    {
        $items = $this->all();
        $item = ['id' => max([0, ...array_column($items, 'id')]) + 1, ...$data];
        $items[] = $item;
        $this->save($items);

        return $item;
    }

    public function replace(int $id, array $data): ?array
    {
        return $this->modify($id, fn() => ['id' => $id, ...$data]);
    }

    public function patch(int $id, array $data): ?array
    {
        return $this->modify($id, fn(array $item) => [...$item, ...$data, 'id' => $id]);
    }

    public function delete(int $id): bool
    {
        $items = $this->all();
        // PHP 8.4: array_any()
        if (!array_any($items, fn(array $item) => $item['id'] === $id)) {
            return false;
        }
        $this->save(array_values(array_filter($items, fn(array $item) => $item['id'] !== $id)));

        return true;
    }

    public function reset(): void
    {
        unlink($this->file);
        new self($this->file);
    }

    private function modify(int $id, callable $fn): ?array
    {
        $items = $this->all();
        foreach ($items as $i => $item) {
            if ($item['id'] === $id) {
                $items[$i] = $fn($item);
                $this->save($items);

                return $items[$i];
            }
        }

        return null;
    }

    private function save(array $items): void
    {
        file_put_contents($this->file, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
