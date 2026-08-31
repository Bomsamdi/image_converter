<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Convert;

use Psr\Http\Message\ResponseInterface;
use Tests\Fixtures;
use Tests\TestCase;

final class ConvertActionTest extends TestCase
{
    /** @return array<string, mixed> */
    private function json(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        self::assertIsArray($decoded, "not JSON: $body");

        return $decoded;
    }

    public function testConvertsAnUploadedPngAndAnswersWithADataUrl(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createUploadRequest('/api/convert/webp', Fixtures::png());

        $response = $app->handle($request);
        $payload = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('webp', $payload['format']);
        self::assertSame('png', $payload['sourceFormat']);
        self::assertSame(40, $payload['width']);
        self::assertStringStartsWith('data:image/webp;base64,', $payload['base64']);
    }

    public function testDefaultsToWebpWhenNoFormatIsAsked(): void
    {
        $app = $this->getAppInstance();

        $payload = $this->json($app->handle(
            $this->createUploadRequest('/api/convert', Fixtures::png())
        ));

        self::assertSame('webp', $payload['format']);
    }

    public function testConvertsToAvifWhenAsked(): void
    {
        $app = $this->getAppInstance();

        $payload = $this->json($app->handle(
            $this->createUploadRequest('/api/convert/avif', Fixtures::jpeg())
        ));

        self::assertSame('avif', $payload['format']);
        self::assertStringStartsWith('data:image/avif;base64,', $payload['base64']);
    }

    public function testTheDownloadRouteReturnsTheImageItself(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle(
            $this->createUploadRequest('/api/convert-download/webp', Fixtures::png(), 'holiday photo.PNG')
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/webp', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            'attachment; filename="holiday photo.webp"',
            $response->getHeaderLine('Content-Disposition')
        );
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertStringStartsWith('RIFF', (string) $response->getBody());
    }

    public function testAFilenameCannotEscapeTheDownloadHeader(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createUploadRequest(
            '/api/convert-download/webp',
            Fixtures::png(),
            '../../etc/passwd',
        ));

        $disposition = $response->getHeaderLine('Content-Disposition');
        self::assertStringNotContainsString('..', $disposition);
        self::assertStringNotContainsString('/', $disposition);
        self::assertSame('attachment; filename="passwd.webp"', $disposition);
    }

    public function testARequestWithoutAFileSaysSoWithA422(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createRequest('POST', '/api/convert/webp'));
        $payload = $this->json($response);

        // The original answered 200 with {"status": 400} in the body.
        self::assertSame(422, $response->getStatusCode());
        self::assertSame(422, $payload['status']);
        self::assertStringContainsString('no file', $payload['error']);
    }

    public function testAFailedUploadIsReported(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createUploadRequest(
            '/api/convert/webp',
            Fixtures::png(),
            'image.png',
            'file',
            UPLOAD_ERR_INI_SIZE,
        ));

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('did not complete', $this->json($response)['error']);
    }

    public function testAPhpFileNamedPngIsRefused(): void
    {
        // The exact bypass the old check allowed: it read explode('.', $name)[1]
        // and saw "png" in "payload.png.php".
        $app = $this->getAppInstance();
        $response = $app->handle($this->createUploadRequest(
            '/api/convert/webp',
            '<?php echo shell_exec($_GET["c"]); ?>',
            'payload.png.php',
        ));

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('reads the file, not its name', $this->json($response)['error']);
    }

    public function testAFileWithNoExtensionDoesNotCrash(): void
    {
        // The old code did explode('.', $name)[1] and threw on the missing key.
        $app = $this->getAppInstance();
        $response = $app->handle(
            $this->createUploadRequest('/api/convert/webp', Fixtures::png(), 'noextension')
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testAnUnknownOutputFormatIsRefused(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle(
            $this->createUploadRequest('/api/convert/tiff', Fixtures::png())
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('not a format this service writes', $this->json($response)['error']);
    }

    public function testTheOriginalPathsStillWork(): void
    {
        $app = $this->getAppInstance();

        $json = $app->handle($this->createUploadRequest('/convert-to-webp', Fixtures::png()));
        self::assertSame(200, $json->getStatusCode());
        self::assertStringStartsWith('data:image/webp;base64,', $this->json($json)['base64']);

        $download = $app->handle(
            $this->createUploadRequest('/convert-to-webp-download', Fixtures::png())
        );
        self::assertSame('image/webp', $download->getHeaderLine('Content-Type'));
    }

    public function testTheHomePageRenders(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createRequest('GET', '/'));

        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Image converter', $body);
        // The head partial is included through fetch(), which returns rather
        // than prints - so a missing `=` silently drops it.
        self::assertStringContainsString('<meta charset="utf-8">', $body);
        self::assertStringContainsString('/single-file-converter', $body);
        self::assertStringContainsString('/zip-file-converter', $body);
    }

    public function testThePickerPagesOfferBothFormats(): void
    {
        $app = $this->getAppInstance();

        foreach (['/single-file-converter', '/zip-file-converter'] as $path) {
            $body = (string) $app->handle($this->createRequest('GET', $path))->getBody();

            self::assertStringContainsString('value="webp"', $body, $path);
            // The home page used to advertise AVIF with buttons that went
            // nowhere; now the option exists.
            self::assertStringContainsString('value="avif"', $body, $path);
            self::assertStringContainsString('enctype="multipart/form-data"', $body, $path);
        }
    }
}
