<?php

declare(strict_types=1);

namespace App;

final class Router
{
    public function __construct(private readonly ItemStore $store) {}

    public function handle(Request $request): Response
    {
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;

        if ($method === 'OPTIONS') {
            return new Response(204, headers: ['Allow' => 'GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS']);
        }

        try {
            return match (true) {
                $request->path === '/api' => Response::json($this->routes()),
                $request->path === '/api/echo' => $this->echo($request),
                $request->path === '/api/items' => $this->collection($method, $request),
                (bool) preg_match('#^/api/items/(\d+)$#', $request->path, $m) => $this->item($method, (int) $m[1], $request),
                (bool) preg_match('#^/api/status/(\d{3})$#', $request->path, $m) => $this->status((int) $m[1]),
                $request->path === '/api/reset' && $method === 'POST' => $this->reset(),
                default => Response::error("Route niet gevonden: {$request->path}", 404),
            };
        } catch (\JsonException $e) {
            return Response::error('Ongeldige JSON: ' . $e->getMessage(), 400);
        }
    }

    private function routes(): array
    {
        return [
            'naam' => 'PHP API Testomgeving',
            'php' => PHP_VERSION,
            'routes' => [
                'GET    /api' => 'Dit overzicht',
                'ANY    /api/echo' => 'Stuurt je request terug (method, headers, query, body)',
                'GET    /api/items' => 'Alle items (filter: ?done=true|false)',
                'POST   /api/items' => 'Nieuw item aanmaken',
                'GET    /api/items/{id}' => 'Eén item ophalen',
                'PUT    /api/items/{id}' => 'Item volledig vervangen',
                'PATCH  /api/items/{id}' => 'Item gedeeltelijk bijwerken',
                'DELETE /api/items/{id}' => 'Item verwijderen',
                'ANY    /api/status/{code}' => 'Geeft de opgegeven HTTP-statuscode terug',
                'POST   /api/reset' => 'Zet de testdata terug',
            ],
        ];
    }

    private function echo(Request $request): Response
    {
        return Response::json([
            'method' => $request->method,
            'path' => $request->path,
            'query' => $request->query,
            'headers' => $request->headers,
            'body' => $request->body,
            'timestamp' => date(DATE_ATOM),
        ]);
    }

    private function collection(string $method, Request $request): Response
    {
        return match ($method) {
            'GET' => Response::json($this->filter($this->store->all(), $request->query)),
            'POST' => ($data = $this->payload($request)) instanceof Response
                ? $data
                : Response::json($this->store->create($data), 201),
            default => $this->notAllowed($method),
        };
    }

    private function item(string $method, int $id, Request $request): Response
    {
        if (in_array($method, ['PUT', 'PATCH'], true)) {
            $data = $this->payload($request);
            if ($data instanceof Response) {
                return $data;
            }
        }

        $result = match ($method) {
            'GET' => $this->store->find($id),
            'PUT' => $this->store->replace($id, $data),
            'PATCH' => $this->store->patch($id, $data),
            'DELETE' => $this->store->delete($id) ?: null,
            default => $this->notAllowed($method),
        };

        return match (true) {
            $result instanceof Response => $result,
            $result === null => Response::error("Item $id niet gevonden", 404),
            $result === true => new Response(204),
            default => Response::json($result),
        };
    }

    private function status(int $code): Response
    {
        if ($code < 100 || $code > 599) {
            return Response::error('Statuscode moet tussen 100 en 599 liggen', 400);
        }

        return Response::json(['status' => $code], $code);
    }

    private function reset(): Response
    {
        $this->store->reset();

        return Response::json(['message' => 'Testdata teruggezet', 'items' => $this->store->all()]);
    }

    private function filter(array $items, array $query): array
    {
        if (isset($query['done'])) {
            $done = filter_var($query['done'], FILTER_VALIDATE_BOOL);
            $items = array_values(array_filter($items, fn(array $item) => ($item['done'] ?? false) === $done));
        }

        return $items;
    }

    private function payload(Request $request): array|Response
    {
        $body = $request->body;
        if (!is_array($body) || array_is_list($body) && $body !== []) {
            return Response::error('Body moet een JSON-object of formulierdata zijn', 422);
        }
        unset($body['id']);

        return $body;
    }

    private function notAllowed(string $method): Response
    {
        return Response::error("Methode $method niet toegestaan op deze route", 405);
    }
}
