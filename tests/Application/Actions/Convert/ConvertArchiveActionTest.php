<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Convert;

use Tests\Fixtures;
use Tests\TestCase;
use ZipArchive;

final class ConvertArchiveActionTest extends TestCase
{
    /** @return list<string> */
    private function entriesOfBody(string $bytes): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'response-zip');
        file_put_contents($path, $bytes);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($path) === true, 'the response is not a ZIP');
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            self::assertNotFalse($stat);
            $names[] = (string) $stat['name'];
        }
        $zip->close();
        @unlink($path);
        sort($names);

        return $names;
    }

    public function testConvertsAnArchiveAndSaysWhatItDid(): void
    {
        $app = $this->getAppInstance();
        $archive = Fixtures::zip([
            'one.png' => Fixtures::png(),
            'two.jpg' => Fixtures::jpeg(),
            'notes.txt' => 'skip me',
        ]);

        $response = $app->handle(
            $this->createUploadRequest('/api/convert-archive/webp', $archive, 'photos.zip')
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/zip', $response->getHeaderLine('Content-Type'));
        self::assertSame('2', $response->getHeaderLine('X-Converted-Count'));
        self::assertSame('1', $response->getHeaderLine('X-Skipped-Count'));
        self::assertSame(
            'attachment; filename="photos-webp.zip"',
            $response->getHeaderLine('Content-Disposition')
        );
        self::assertSame(['one.webp', 'two.webp'], $this->entriesOfBody((string) $response->getBody()));
    }

    public function testAnArchiveWithNothingToConvertIsA422(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createUploadRequest(
            '/api/convert-archive/webp',
            Fixtures::zip(['notes.txt' => 'nothing here']),
            'empty.zip',
        ));

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('no PNG', (string) $response->getBody());
    }

    public function testSomethingThatIsNotAnArchiveIsA422(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createUploadRequest(
            '/api/convert-archive/webp',
            Fixtures::png(),
            'not-a-zip.png',
        ));

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('not a readable ZIP', (string) $response->getBody());
    }

    public function testTheOriginalArchivePathStillWorks(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createUploadRequest(
            '/convert-to-zip-webp-download',
            Fixtures::zip(['one.png' => Fixtures::png()]),
            'photos.zip',
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/zip', $response->getHeaderLine('Content-Type'));
    }

    public function testNothingIsLeftInTheTemporaryDirectory(): void
    {
        $before = glob(sys_get_temp_dir() . '/image-converter-*') ?: [];

        $app = $this->getAppInstance();
        $app->handle($this->createUploadRequest(
            '/api/convert-archive/webp',
            Fixtures::zip(['one.png' => Fixtures::png()]),
            'photos.zip',
        ));

        self::assertSame($before, glob(sys_get_temp_dir() . '/image-converter-*') ?: []);
    }

    public function testATemporaryDirectoryIsAlsoCleanedAfterAFailure(): void
    {
        $before = glob(sys_get_temp_dir() . '/image-converter-*') ?: [];

        $app = $this->getAppInstance();
        $app->handle($this->createUploadRequest(
            '/api/convert-archive/webp',
            'not a zip at all',
            'broken.zip',
        ));

        self::assertSame($before, glob(sys_get_temp_dir() . '/image-converter-*') ?: []);
    }

    public function testTwoRequestsDoNotDeleteEachOthersWork(): void
    {
        // The old handler emptied the shared upload folder at the start of
        // every request, so a second caller destroyed the first one's files.
        $app = $this->getAppInstance();

        $first = $app->handle($this->createUploadRequest(
            '/api/convert-archive/webp',
            Fixtures::zip(['a.png' => Fixtures::png(20, 20)]),
            'first.zip',
        ));
        $second = $app->handle($this->createUploadRequest(
            '/api/convert-archive/webp',
            Fixtures::zip(['b.png' => Fixtures::png(30, 30)]),
            'second.zip',
        ));

        self::assertSame(['a.webp'], $this->entriesOfBody((string) $first->getBody()));
        self::assertSame(['b.webp'], $this->entriesOfBody((string) $second->getBody()));
    }
}
