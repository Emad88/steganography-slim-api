<?php

declare(strict_types=1);

namespace App\Ui\Http;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class ApiController
{
    protected Request $request;

    protected Response $response;

    protected array $args;

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $this->request = $request;
        $this->response = $response;
        $this->args = $args;

        return $this->action();
    }

    abstract protected function action(): Response;

    protected function respond(Payload $payload)
    {
        if ($payload->stream() !== null) {
            return $this->response
                ->withHeader('Content-Type', 'image/png')
                ->withBody($payload->stream());
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT);
        $this->response->getBody()->write($json);

        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($payload->status());
    }
}
