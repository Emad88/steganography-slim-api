<?php

declare(strict_types=1);

namespace App\Ui\Http;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface as Response;

final class FallbackApiController extends ApiController
{
    protected function action(): Response
    {
        return $this->respond(new Payload(
            status: StatusCodeInterface::STATUS_NOT_FOUND
        ));
    }
}
