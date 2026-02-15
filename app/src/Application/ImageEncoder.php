<?php

declare(strict_types=1);

namespace App\Application;

use Slim\Psr7\Stream;

interface ImageEncoder
{
    public function encode(string $image, string $message): Stream;

    public function decode(string $image): string;
}
