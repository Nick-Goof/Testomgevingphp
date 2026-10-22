<?php

declare(strict_types=1);

namespace App;

final class Request
{
    // PHP 8.4: asymmetric visibility — publiek leesbaar, alleen intern schrijfbaar
    public private(set) string $method;
    public private(set) string $path;
    public private(set) array $query;
    public private(set) array $headers;
    public private(set) string $rawBody;

    // PHP 8.4: property hook — body wordt lui gedecodeerd op basis van Content-Type
    public mixed $body {
        get {
            $type = strtolower($this->headers['content-type'] ?? '');

            if (str_contains($type, 'application/json')) {
                return $this->rawBody === '' ? null : json_decode($this->rawBody, true, flags: JSON_THROW_ON_ERROR);
            }
            if (str_contains($type, 'application/x-www-form-urlencoded')) {
                parse_str($this->rawBody, $parsed);
                return $parsed;
            }

            return $this->rawBody === '' ? null : $this->rawBody;
        }
    }

    public static function fromGlobals(): self
    {
        $request = new self();
        $request->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $request->path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
        $request->query = $_GET;
        $request->headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $request->rawBody = (string) file_get_contents('php://input');

        return $request;
    }
}
