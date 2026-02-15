<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Slim\Psr7\Stream;

final class DoNothingImageEncoder extends GDImageEncoder
{
    public function encode(string $image, string $message): Stream
    {
        $im = $this->loadImage($image);

        return $this->stream($im);
    }

    public function decode(string $image): string
    {
        return '';
    }
}
