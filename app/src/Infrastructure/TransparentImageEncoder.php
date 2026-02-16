<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Exception\ImageEncodingException;
use Slim\Psr7\Stream;

/**
 * TransparentImageEncoder
 *
 * This encoder will attempt to write 3 bytes in each fully transparent pixel, one in each RGB channel
 */
final class TransparentImageEncoder extends GDImageEncoder
{
    // Append an invalid UTF-8 character to our byte array
    // http://en.wikipedia.org/wiki/UTF-8#Codepage_layout
    const int END_BYTE = 255;

    // Fully transparent alpha value
    // https://www.php.net/manual/en/function.imagecolorallocatealpha.php
    const int TRANSPARENT_ALPHA = 127;

    public function encode(string $image, string $message): Stream
    {
        // Load the source image
        $im = $this->loadImage($image);
        imagealphablending($im, false);
        imagesavealpha($im, true);

        // Convert the string into a byte array
        // http://php.net/manual/en/function.unpack.php#103634
        $bytes = array_values(unpack('C*', $message));
        $bytes[] = self::END_BYTE;
        $byteCount = count($bytes);

        // Index of the byte to write
        $index = 0;

        // Loop each pixel
        $width = imagesx($im);
        $height = imagesy($im);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Get the pixel color
                $colorIndex = imagecolorat($im, $x, $y);
                $alpha = ($colorIndex >> 24) & 0x7F;

                // Write only on transparent pixels
                if ($alpha == self::TRANSPARENT_ALPHA) {
                    $r = $bytes[$index];
                    $g = $bytes[$index + 1] ?? 0;
                    $b = $bytes[$index + 2] ?? 0;

                    $color = imagecolorallocatealpha($im, $r, $g, $b, $alpha);
                    imagesetpixel($im, $x, $y, $color);

                    $index += 3;

                    // Check if we have written all the bytes
                    if ($index >= $byteCount) {
                        return $this->stream($im);
                    }
                }
            }
        }

        throw new ImageEncodingException('Not enough transparent pixels to write the message.');
    }

    public function decode(string $image): string
    {
        // Load the source image
        $im = $this->loadImage($image);
        imagealphablending($im, false);

        $bytes = [];

        // Loop each pixel
        $width = imagesx($im);
        $height = imagesy($im);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Get the pixel color
                $colorIndex = imagecolorat($im, $x, $y);
                $alpha = ($colorIndex >> 24) & 0x7F;

                // If transparent add the color values in our byte array until the control character is found
                if ($alpha == self::TRANSPARENT_ALPHA) {
                    $channels = [
                        ($colorIndex >> 16) & 0xFF, // Red
                        ($colorIndex >> 8) & 0xFF,  // Green
                        $colorIndex & 0xFF,         // Blue
                    ];
                    foreach ($channels as $byte) {
                        if ($byte == self::END_BYTE) {
                            // Convert the byte array to an UTF-8 string
                            return pack('C*', ...$bytes);
                        }

                        $bytes[] = $byte;
                    }
                }
            }
        }

        throw new ImageEncodingException('No message in image.');
    }
}
