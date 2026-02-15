<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\ImageEncoder;
use App\Domain\Exception\ImageEncodingException;
use Slim\Psr7\Stream;

abstract class GDImageEncoder implements ImageEncoder
{
    protected function loadImage(string $image): \GDImage
    {
        try {
            // If the image is not a png imagecreatefrompng simply raises a warning
            // We ignore the warning with @, and handle the exception manually
            $im = @imagecreatefrompng($image);

            if ($im === false) {
                throw new ImageEncodingException('GD failed to load the PNG. The file may be corrupt.');
            }

            return $im;
        } catch (\Throwable $e) {
            throw new ImageEncodingException('Invalid image format: '.$e->getMessage());
        }
    }

    protected function stream(\GDImage $im): Stream
    {
        ob_start();
        imagepng($im);
        $imageData = ob_get_clean();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $imageData);
        rewind($stream);

        return new Stream($stream);
    }
}
