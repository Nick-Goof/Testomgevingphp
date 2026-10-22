<?php

declare(strict_types=1);

namespace App;

final class Response
{
    public function __construct(
        public private(set) int $status = 200,
        public private(set) mixed $data = null,
        public private(set) array $headers = [],
    ) {}

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, $data, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function error(string $message, int $status): self
    {
        return self::json(['error' => $message, 'status' => $status], $status);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        if ($this->status !== 204) {
            echo json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }
}
