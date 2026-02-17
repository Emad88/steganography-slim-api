<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Exception\ImageEncodingException;
use Slim\Psr7\Stream;

/**
 * LeastSignificativeBitImageEncoder
 *
 * This encoder will attempt to write 3 bits in each pixel, one in each RGB channel
 */
final class LeastSignificativeBitImageEncoder extends GDImageEncoder
{
    // Append an invalid UTF-8 character to our byte array
    // http://en.wikipedia.org/wiki/UTF-8#Codepage_layout
    const int END_BYTE = 255;

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
        $byteIndex = 0;
        $bitIndex = 0;

        // Loop each pixel
        $width = imagesx($im);
        $height = imagesy($im);

        // Check if the message fits the image early
        $bitSpace = $width * $height * 3;
        if ((count($bytes) + 1) * 8 > $bitSpace) {
            throw new ImageEncodingException('Not enough pixels to write the message.');
        }

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Get the pixel color
                $colorIndex = imagecolorat($im, $x, $y);
                $r = ($colorIndex >> 16) & 0xFF;
                $g = ($colorIndex >> 8) & 0xFF;
                $b = $colorIndex & 0xFF;
                $a = ($colorIndex >> 24) & 0x7F;

                $channels = [&$r, &$g, &$b]; // References to the variables, rather than their values
                foreach ($channels as &$channel) {
                    if ($byteIndex < count($bytes)) {
                        $bit = ($bytes[$byteIndex] >> (7 - $bitIndex)) & 1;
                        $channel = ($channel & ~1) | $bit; // ~1 bit-wise NOT: Masks with 11111110

                        $bitIndex++;

                        if ($bitIndex === 8) {
                            $bitIndex = 0;
                            $byteIndex++;
                        }
                    }
                }

                // Set the color
                $color = imagecolorallocatealpha($im, $r, $g, $b, $a);
                imagesetpixel($im, $x, $y, $color);

                // Check if we have written all the bits
                if ($byteIndex >= $byteCount) {
                    return $this->stream($im);
                }
            }
        }

        throw new ImageEncodingException('Unexpected error.');
    }

    public function decode(string $image): string
    {
        // Load the source image
        $im = $this->loadImage($image);
        imagealphablending($im, false);

        $bytes = [];
        $currentByte = 0;
        $bitCount = 0;

        // Loop each pixel
        $width = imagesx($im);
        $height = imagesy($im);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Get the pixel color
                $colorIndex = imagecolorat($im, $x, $y);
                $channels = [
                    ($colorIndex >> 16) & 0xFF, // Red
                    ($colorIndex >> 8) & 0xFF,  // Green
                    $colorIndex & 0xFF,         // Blue
                ];

                // Add bits to the binary string
                foreach ($channels as $channel) {
                    $bit = $channel & 1;
                    $currentByte = ($currentByte << 1) | $bit;
                    $bitCount++;

                    if ($bitCount == 8) {
                        if ($currentByte === self::END_BYTE) {
                            return pack('C*', ...$bytes);
                        }

                        $bytes[] = $currentByte;
                        $currentByte = 0;
                        $bitCount = 0;
                    }
                }
            }
        }

        throw new ImageEncodingException('No message in image.');
    }
}
