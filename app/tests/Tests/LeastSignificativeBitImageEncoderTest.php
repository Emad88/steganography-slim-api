<?php

declare(strict_types=1);

namespace Tests;

use App\Application\ImageEncoder;
use App\Domain\Exception\ImageEncodingException;
use App\Infrastructure\LeastSignificativeBitImageEncoder;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class LeastSignificativeBitImageEncoderTest extends TestCase
{
    private ImageEncoder $encoder;

    private ?string $inputTempFile = null;

    private ?string $outputTempFile = null;

    protected function setUp(): void
    {
        $this->encoder = new LeastSignificativeBitImageEncoder;
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
        $im = imagecreatetruecolor(200, 200);
        imagealphablending($im, false);
        imagesavealpha($im, true);

        // Fill it red
        $red = imagecolorallocatealpha($im, 255, 0, 0, 0);
        imagefill($im, 0, 0, $red);
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
        $this->expectExceptionMessage('Not enough pixels to write the message.');

        // Create a 1x1 image with alpha channel
        $this->inputTempFile = __DIR__.'/test.png';
        $im = imagecreatetruecolor(1, 1);
        imagealphablending($im, false);
        imagesavealpha($im, true);

        // Fill it red
        $red = imagecolorallocatealpha($im, 255, 0, 0, 0);
        imagefill($im, 0, 0, $red);
        imagepng($im, $this->inputTempFile);

        // Encode message
        $this->encoder->encode($this->inputTempFile, 'Short Message');
    }


    #[TestDox('Alpha channels remain completely untouched during encoding')]
    public function test_alpha_channels_are_untouched(): void
    {
        // Create a 10x10 image with alpha channel
        $this->inputTempFile = __DIR__.'/test.png';
        $im = imagecreatetruecolor(10, 10);
        imagealphablending($im, false);
        imagesavealpha($im, true);

        // Fill it transparent
        $soliSolor = imagecolorallocate($im, 90, 91, 92);
        imagefill($im, 0, 0, $soliSolor);

        // Color some pixels
        $transparentColor = imagecolorallocatealpha($im, 120, 150, 220, 127);
        $semitrasparentColor = imagecolorallocatealpha($im, 0, 255, 100, 30);
        imagesetpixel($im, 1, 0, $transparentColor);
        imagesetpixel($im, 3, 0, $semitrasparentColor);
        imagepng($im, $this->inputTempFile);

        // Encode message
        $stream = $this->encoder->encode($this->inputTempFile, '1A');
        $this->outputTempFile = __DIR__.'/encoded.png';
        file_put_contents($this->outputTempFile, $stream->getContents());

        // Read manually each pixel
        $im = imagecreatefrompng($this->outputTempFile);
        imagealphablending($im, false);

        $exptectedValues = [
            [ord('1') >> 7 & 1, ord('1') >> 6 & 1, ord('1') >> 5 & 1, 0],
            [ord('1') >> 4 & 1, ord('1') >> 3 & 1, ord('1') >> 2 & 1, 127],
            [ord('1') >> 1 & 1, ord('1') & 1, ord('A') >> 7 & 1, 0],
            [ord('A') >> 6 & 1, ord('A') >> 5 & 1, ord('A') >> 4 & 1, 30],
            [ord('A') >> 3 & 1, ord('A') >> 2 & 1, ord('A') >> 1 & 1, 0],
            [ord('A') & 1, 1, 1, 0],
            [1, 1, 1, 0],
            [1, 1, 1, 0],
        ];
        for ($x = 0; $x < count($exptectedValues); $x++) {
            $colorIndex = imagecolorat($im, $x, 0);
            $r = ($colorIndex >> 16) & 0xFF;
            $g = ($colorIndex >> 8) & 0xFF;
            $b = $colorIndex & 0xFF;
            $a = ($colorIndex >> 24) & 0x7F;

            $this->assertEquals($r & 1, $exptectedValues[$x][0], "$x R value doesn't match");
            $this->assertEquals($g & 1, $exptectedValues[$x][1], "$x G value doesn't match");
            $this->assertEquals($b & 1, $exptectedValues[$x][2], "$x B value doesn't match");
            $this->assertEquals($a, $exptectedValues[$x][3], "$x A value doesn't match");
        }

        // Check that the first pixel after the message is untouched
        $colorIndex = imagecolorat($im, 8, 0);
        $r = ($colorIndex >> 16) & 0xFF;
        $g = ($colorIndex >> 8) & 0xFF;
        $b = $colorIndex & 0xFF;
        $a = ($colorIndex >> 24) & 0x7F;
        $this->assertEquals($r, 90);
        $this->assertEquals($g, 91);
        $this->assertEquals($b, 92);
        $this->assertEquals($a, 0);
    }
}
