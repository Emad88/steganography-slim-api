<?php

declare(strict_types=1);

namespace Tests;

use App\Application\ImageEncoder;
use App\Domain\Exception\ImageEncodingException;
use App\Infrastructure\TransparentImageEncoder;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class TransparentImageEncoderTest extends TestCase
{
    private ImageEncoder $encoder;

    private ?string $inputTempFile = null;

    private ?string $outputTempFile = null;

    protected function setUp(): void
    {
        $this->encoder = new TransparentImageEncoder;
    }

    protected function tearDown(): void
    {
        if ($this->inputTempFile && file_exists($this->inputTempFile)) {
            unlink($this->inputTempFile);
        }
        if ($this->outputTempFile && file_exists($this->outputTempFile)) {
            unlink($this->outputTempFile);
        }
    }

    #[TestDox('Encoded messages including multi-byte UTF-8 characters are recovered identically')]
    public function test_encoder_preserves_integrity_of_utf8_messages(): void
    {
        // Get message
        $fixturePath = __DIR__.'/../Fixtures/special_chars.txt';
        if (! file_exists($fixturePath)) {
            $this->markTestSkipped('Fixture file missing.');
        }
        $message = file_get_contents($fixturePath);

        // Create a 100x100 image with alpha channel
        $this->inputTempFile = __DIR__.'/test.png';
        $im = imagecreatetruecolor(100, 100);
        imagealphablending($im, false);
        imagesavealpha($im, true);

        // Fill it transparent
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparent);
        imagepng($im, $this->inputTempFile);

        // Encode message
        $stream = $this->encoder->encode($this->inputTempFile, $message);
        $this->outputTempFile = __DIR__.'/encoded.png';
        file_put_contents($this->outputTempFile, $stream->getContents());

        // Decode Message
        $decodedMessage = $this->encoder->decode($this->outputTempFile);

        // 4. Assertions
        $this->assertEquals($message, $decodedMessage);
    }

    #[TestDox('Encoding fails with an exception if the image provides insufficient pixel capacity')]
    public function test_encoder_validates_sufficient_image_size(): void
    {
        $this->expectException(ImageEncodingException::class);
        $this->expectExceptionMessage('Not enough transparent pixels to write the message.');

        // Create a 100x100 image with alpha channel
        $this->inputTempFile = __DIR__.'/test.png';
        $im = imagecreatetruecolor(100, 100);
        imagealphablending($im, false);
        imagesavealpha($im, true);

        // Fill it red
        $red = imagecolorallocate($im, 255, 0, 0);
        imagefill($im, 0, 0, $red);
        imagepng($im, $this->inputTempFile);

        // Encode message
        $this->encoder->encode($this->inputTempFile, 'Short Message');
    }

    #[TestDox('Encoding fails with an exception if the image provides insufficient transparent pixel capacity')]
    public function test_encoder_validates_sufficient_transparent_pixels(): void
    {
        $this->expectException(ImageEncodingException::class);
        $this->expectExceptionMessage('Not enough transparent pixels to write the message.');

        // Create a 1x1 image with alpha channel
        $this->inputTempFile = __DIR__.'/test.png';
        $im = imagecreatetruecolor(1, 1);
        imagealphablending($im, false);
        imagesavealpha($im, true);

        // Fill it transparent
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparent);
        imagepng($im, $this->inputTempFile);

        // Encode message
        $this->encoder->encode($this->inputTempFile, 'Short Message');
    }

    #[TestDox('Transparent pixels remain completely untouched during encoding')]
    public function test_not_transparent_pixels_are_untouched(): void
    {
        // Create a 10x10 image with alpha channel
        $this->inputTempFile = __DIR__.'/test.png';
        $im = imagecreatetruecolor(10, 10);
        imagealphablending($im, false);
        imagesavealpha($im, true);

        // Fill it transparent
        $transparent = imagecolorallocatealpha($im, 90, 91, 92, 127);
        imagefill($im, 0, 0, $transparent);

        // Color some pixels
        $solid_color = imagecolorallocate($im, 255, 200, 0);
        $semitrasparent_color = imagecolorallocatealpha($im, 0, 255, 100, 30);
        imagesetpixel($im, 1, 0, $solid_color);
        imagesetpixel($im, 3, 0, $semitrasparent_color);
        imagepng($im, $this->inputTempFile);

        // Encode message
        $stream = $this->encoder->encode($this->inputTempFile, '123456789A');
        $this->outputTempFile = __DIR__.'/encoded.png';
        file_put_contents($this->outputTempFile, $stream->getContents());

        // Read manually each pixel of the encoded image
        $im = imagecreatefrompng($this->outputTempFile);
        imagealphablending($im, false);

        $exptectedValues = [
            [ord('1'), ord('2'), ord('3'), 127],
            [255, 200, 0, 0],
            [ord('4'), ord('5'), ord('6'), 127],
            [0, 255, 100, 30],
            [ord('7'), ord('8'), ord('9'), 127],
            [ord('A'), 255, 0, 127],
        ];

        for ($x = 0; $x < count($exptectedValues); $x++) {
            $colorIndex = imagecolorat($im, $x, 0);
            $r = ($colorIndex >> 16) & 0xFF;
            $g = ($colorIndex >> 8) & 0xFF;
            $b = $colorIndex & 0xFF;
            $a = ($colorIndex >> 24) & 0x7F;

            $this->assertEquals($r, $exptectedValues[$x][0], "$x R value doesn't match");
            $this->assertEquals($g, $exptectedValues[$x][1], "$x G value doesn't match");
            $this->assertEquals($b, $exptectedValues[$x][2], "$x B value doesn't match");
            $this->assertEquals($a, $exptectedValues[$x][3], "$x A value doesn't match");
        }
    }
}
