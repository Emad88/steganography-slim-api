<?php

declare(strict_types=1);

namespace App\Ui\Http;

use Psr\Http\Message\ResponseInterface as Response;

final class TestApiController extends ApiController
{
    protected function action(): Response
    {
        $data = ['msg' => 'hello '.$this->args['name']];
        $payload = new Payload(data: $data);

        return $this->respond($payload);
    }
}
