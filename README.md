# Steganography API Service

A lightweight, high-performance microservice built on **Slim 4** designed to hide and extract data within PNG-24 image files using **steganography algorithms**.

The project follows **Domain-Driven Design (DDD)** principles to ensure the implementation of the steganography algorithms remains decoupled from the delivery mechanism (HTTP/Slim). This design allows for the addition of new algorithms or image processing libraries without modifying the Controllers that use them.


## How to run

Install Docker Desktop, clone the repository and run:

```
docker compose up --build -d
docker compose exec php composer install
```

## How to call the APIs
The service exposes two endpoints for each algorithm. You can test them using curl or tools like Postman.

### Encode a message
Hide a string inside an image.

**Endpoint:** `POST /(alpha|bit)/encode`

**Payload:** `image` (file), `message` (string)

```
curl -X POST http://localhost:8080/bit/encode \
  -F "image=@examples/bit.png" \
  -F "message=My secret message" \
  --output encoded.png
```

For **Windows PowerShell** users:

```
curl.exe -X POST http://localhost:8080/bit/encode `
  -F "image=@examples/bit.png" `
  -F "message=My secret message" `
  --output encoded.png
```

### Decode a message
Extract a hidden string from a processed image.

**Endpoint:** `POST /(alpha|bit)/encode`

**Payload:** `image` (file)

```
curl -X POST http://localhost:8080/bit/decode \
  -F "image=@examples/bit_encoded.png"
```

For **Windows PowerShell** users:

```
curl.exe -X POST http://localhost:8080/bit/decode `
  -F "image=@examples/bit_encoded.png"
```

## Steganography Algorithms

### Algorithm: `alpha` (Transparent Pixel Substitution)
Leverages the fact that in PNG images, even fully transparent pixels have color channels (RGB) that can store data without affecting the image's appearance.

**Mechanism:** Injects data exclusively into pixels that are fully transparent. It utilizes the 8 bits of each color channel (Red, Green, Blue), allowing for 3 bytes of storage per transparent pixel.

**Pros:** Since only invisible pixels are modified, the visible portion of the image remains 100% identical to the original.

**Cons:** Variable Capacity. Storage is strictly limited by the number of transparent pixels available in the source image.


### Algorithm: `bit` (LSB Substitution)
A technique that modifies the *Least Significant Bit* of each color channel.

**Mechanism:** Replaces the last bit of the Red, Green, and Blue channels with message bits (providing 3 bits of storage per pixel).

**Pros:** High Capacity. Every pixel in the image serves as a potential carrier, regardless of transparency.

**Cons:** While message recovery is lossless, the original pixel values are slightly modified. However, these $\pm 1$ bit changes are imperceptible to the human eye.


## Adding new Steganography APIs

In `src/Infrastructure` create `NewImageEncoder.php`.

```
<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Exception\ImageEncodingException;
use Slim\Psr7\Stream;

final class NewImageEncoder extends GDImageEncoder
{
    public function encode(string $image, string $message): Stream
    {
        $im = $this->loadImage($image);

        // Your encoding algorithm here
        // To have your API return an error, throw a ImageEncodingException

        return $this->stream($im);
    }

    public function decode(string $image): string
    {
        // Your decoding algorithm here
        return '';
    }
}
```

Then in `public/index.php` add the new endpoints injecting your new encoder with the ApiControllers.

```
use App\Infrastructure\NewImageEncoder;

$app->group('/new', function (Group $group) {
    $group->post('/encode', function ($request, $response, $args) {
        $controller = new EncodeImageApiController(new NewImageEncoder);
        return $controller($request, $response, $args);
    });
    $group->post('/decode', function ($request, $response, $args) {
        $controller = new DecodeImageApiController(new NewImageEncoder);
        return $controller($request, $response, $args);
    });
});
```

Test the new endpoints at `http://localhost:8080/new/encode` and `http://localhost:8080/new/decode`.

## Running Unit Tests

The project uses **PHPUnit** to unit tests the encoding algorithms. To run the tests use:

```
docker-compose exec php composer test
```

## Running the linter

The project uses **Pint** to lint the code. To run the linter use:

```
docker-compose exec php composer lint
```

## What's next?

This project served as an exercise in exploring a modern micro-framework (Slim 4) and an architecture focused on *single-responsibility services* rather than a traditional *MVC pattern*. To expand its functionality the following could be implemented:

### API schema

By implementing an OpenAPI schema, we could decouple request validation from the Controllers. This would provide automated validation layers and enable interactive documentation via *Swagger UI*, making the API easier for third-party developers to consume.

### API Authentication

For production environments, I would implement *Static Token Authentication* to keep the service stateless and database-free. A secret key stored in the .env file would be validated against the `X-API-Key` request header.

To scale further, I would transition to *JWT (JSON Web Tokens)*. To maintain clean architecture, I would implement an *AuthenticatorInterface*, allowing the application to switch between Static and JWT implementations via environment configuration without changing the existing code.