<?php

declare(strict_types=1);

namespace App\Ui\Http;

use App\Application\ImageEncoder;
use App\Domain\Exception\ImageEncodingException;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface as Response;

final class EncodeImageApiController extends ApiController
{
    public function __construct(private ImageEncoder $encoder) {}

    public function action(): Response
    {
        $parsedBody = $this->request->getParsedBody();
        $uploadedFiles = $this->request->getUploadedFiles();

        $message = $parsedBody['message'] ?? null;
        $image = $uploadedFiles['image'] ?? null;

        // Validation
        if (empty($message) || ! $image || $image->getError() !== UPLOAD_ERR_OK) {
            return $this->respond(new Payload(
                error: 'A valid image and message are required.',
                status: StatusCodeInterface::STATUS_BAD_REQUEST
            ));
        }

        // Encoding
        try {
            $inputPath = $image->getStream()->getMetadata('uri');

            $output = $this->encoder->encode(
                image: $inputPath,
                message: $message
            );

            return $this->respond(new Payload(
                stream: $output,
                status: StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY
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
