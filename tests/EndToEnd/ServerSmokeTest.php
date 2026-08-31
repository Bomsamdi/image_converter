<?php

declare(strict_types=1);

namespace Tests\EndToEnd;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures;

/**
 * Drives a real PHP server over HTTP.
 *
 * Everything else in this suite calls `$app->handle()`, which never emits the
 * response and never reaches shutdown - and that is precisely where a bug hid:
 * a suppressed warning made the shutdown handler write a 500 on top of a
 * finished ZIP. `handle()` said 200 and the browser got 500.
 */
#[Group('e2e')]
final class ServerSmokeTest extends TestCase
{
    private const PORT = 8123;

    /** @var resource|null */
    private static $server = null;

    public static function setUpBeforeClass(): void
    {
        $descriptors = [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
        $process = proc_open(
            sprintf('exec php -S 127.0.0.1:%d -t public', self::PORT),
            $descriptors,
            $pipes,
            dirname(__DIR__, 2),
        );

        if (!is_resource($process)) {
            self::markTestSkippedBeforeClass('could not start a PHP server');
        }
        self::$server = $process;

        // The server needs a moment; ten tries at 100 ms is generous.
        for ($i = 0; $i < 10; $i++) {
            $socket = @fsockopen('127.0.0.1', self::PORT, $errno, $error, 0.2);
            if (is_resource($socket)) {
                fclose($socket);
                return;
            }
            usleep(100_000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    private static function markTestSkippedBeforeClass(string $why): never
    {
        self::markTestSkipped($why);
    }

    /**
     * @param array<string, string> $fields
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function post(string $path, string $fileBytes, string $fileName, array $fields = []): array
    {
        $boundary = '----' . bin2hex(random_bytes(8));
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= "--$boundary\r\n"
                . "Content-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
        }
        $body .= "--$boundary\r\n"
            . "Content-Disposition: form-data; name=\"file\"; filename=\"$fileName\"\r\n"
            . "Content-Type: application/octet-stream\r\n\r\n"
            . $fileBytes . "\r\n--$boundary--\r\n";

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: multipart/form-data; boundary=$boundary",
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 20,
        ]]);

        $response = @file_get_contents(
            sprintf('http://127.0.0.1:%d%s', self::PORT, $path),
            false,
            $context,
        );
        if ($response === false) {
            self::markTestSkipped('the local server did not answer');
        }

        $status = 0;
        $headers = [];
        /** @var list<string> $http_response_header */
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
                continue;
            }
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[strtolower(trim($name))] = trim($value);
        }

        return ['status' => $status, 'headers' => $headers, 'body' => $response];
    }

    public function testConvertsOneImageOverHttp(): void
    {
        $response = $this->post('/api/convert/webp', Fixtures::png(), 'photo.png');

        self::assertSame(200, $response['status']);
        $payload = json_decode($response['body'], true);
        self::assertIsArray($payload);
        self::assertSame('webp', $payload['format']);
    }

    public function testAnArchiveWithSkippedEntriesStillReturnsAZip(): void
    {
        // The regression: skipping `notes.txt` records a suppressed warning,
        // and the shutdown handler used to turn that into a 500 - after the
        // ZIP had already been sent.
        // The short entry matters: GD reads a twelve byte header, and only
        // complains out loud when there is not one - which is what put an
        // entry in error_get_last() and tripped the shutdown handler. A longer
        // non-image fails silently and would not reproduce it.
        $archive = Fixtures::zip([
            'photos/one.png' => Fixtures::png(),
            'photos/.DS_Store' => "\x00\x01\x42",
            'photos/broken.png' => '<?php echo "nope";',
        ]);

        $response = $this->post('/api/convert-archive/webp', $archive, 'photos.zip');

        self::assertSame(200, $response['status'], 'body: ' . substr($response['body'], 0, 200));
        self::assertSame('application/zip', $response['headers']['content-type'] ?? '');
        self::assertSame('1', $response['headers']['x-converted-count'] ?? '');
        self::assertSame('2', $response['headers']['x-skipped-count'] ?? '');
        self::assertStringStartsWith("PK\x03\x04", $response['body']);
    }

    public function testTheDownloadIsTheImageAndNothingElse(): void
    {
        $response = $this->post('/api/convert-download/webp', Fixtures::png(), 'photo.png');

        self::assertSame(200, $response['status']);
        self::assertSame('image/webp', $response['headers']['content-type'] ?? '');
        self::assertStringStartsWith('RIFF', $response['body']);
    }
}
