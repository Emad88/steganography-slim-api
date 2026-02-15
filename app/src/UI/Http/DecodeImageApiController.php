<?php

declare(strict_types=1);

namespace App\Ui\Http;

use App\Application\ImageEncoder;
use App\Domain\Exception\ImageEncodingException;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface as Response;

final class DecodeImageApiController extends ApiController
{
    public function __construct(private ImageEncoder $encoder) {}

    public function action(): Response
    {
        $uploaded = $this->request->getUploadedFiles();

        // Get Image
        $image = $uploaded['image'] ?? null;

        // Validation
        if (! $image || $image->getError() !== UPLOAD_ERR_OK) {
            return $this->respond(new Payload(
                error: 'A valid image is required.',
                status: StatusCodeInterface::STATUS_BAD_REQUEST
            ));
        }

        // Decode
        try {
            $inputImage = $image->getStream()->getMetadata('uri');

            $output = $this->encoder->decode(
                image: $inputImage
            );

            return $this->respond(new Payload(
                data: ['message' => $output]
            ));
        } catch (ImageEncodingException $e) {
            // Domain Exception Handling
            return $this->respond(new Payload(
                error: $e->getMessage(),
                status: StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY
            ));
        } catch (\Throwable $e) {
            // Unexpected Error Handling
            return $this->respond(new Payload(
                error: 'An internal error occurred: '.$e->getMessage(),
                status: StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            ));
        }
    }
}
