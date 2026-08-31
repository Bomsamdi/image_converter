<?php

declare(strict_types=1);

namespace Tests\Domain\Image;

use App\Domain\Image\ArchiveConverter;
use App\Domain\Image\ConversionFailed;
use App\Domain\Image\ConversionLimits;
use App\Domain\Image\ImageConverter;
use App\Domain\Image\ImageFormat;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures;
use ZipArchive;

final class ArchiveConverterTest extends TestCase
{
    /** @var list<string> */
    private array $temporary = [];

    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) {
            @unlink($path);
        }
        $this->temporary = [];
    }

    private function converter(ConversionLimits $limits = new ConversionLimits()): ArchiveConverter
    {
        return new ArchiveConverter(new ImageConverter($limits), $limits);
    }

    private function file(string $bytes = ''): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'archive-test');
        if ($bytes !== '') {
            file_put_contents($path, $bytes);
        }
        $this->temporary[] = $path;

        return $path;
    }

    /** @return list<string> the entry names of a ZIP */
    private function entriesOf(string $path): array
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            self::assertNotFalse($stat);
            $names[] = (string) $stat['name'];
        }
        $zip->close();
        sort($names);

        return $names;
    }

    public function testConvertsEveryImageAndKeepsTheFolders(): void
    {
        $source = $this->file(Fixtures::zip([
            'photos/one.png' => Fixtures::png(),
            'photos/deep/two.jpg' => Fixtures::jpeg(),
        ]));
        $destination = $this->file();

        $result = $this->converter()->convert($source, $destination, ImageFormat::Webp);

        self::assertSame(2, $result->converted);
        self::assertSame([], $result->skipped);
        self::assertSame(
            ['photos/deep/two.webp', 'photos/one.webp'],
            $this->entriesOf($destination)
        );
    }

    public function testSkipsWhatIsNotAnImageRatherThanFailing(): void
    {
        // An export from a design tool is full of these.
        $source = $this->file(Fixtures::zip([
            'one.png' => Fixtures::png(),
            '.DS_Store' => 'rubbish',
            'notes.txt' => 'hello',
            'fake.png' => '<?php echo "not an image";',
        ]));
        $destination = $this->file();

        $result = $this->converter()->convert($source, $destination, ImageFormat::Webp);

        self::assertSame(1, $result->converted);
        self::assertCount(3, $result->skipped);
        self::assertSame(['one.webp'], $this->entriesOf($destination));
    }

    public function testAnEntryCannotClimbOutOfTheArchive(): void
    {
        // Zip-slip: the old version called extractTo() on this and wrote
        // wherever the entry name pointed.
        $source = $this->file(Fixtures::zip([
            '../../escaped.png' => Fixtures::png(),
            'nested/../../also-escaped.png' => Fixtures::png(),
        ]));
        $destination = $this->file();

        $this->converter()->convert($source, $destination, ImageFormat::Webp);

        foreach ($this->entriesOf($destination) as $name) {
            self::assertStringNotContainsString('..', $name, 'an entry escaped');
            self::assertStringStartsNotWith('/', $name);
        }
        // The `..` segments are dropped and the rest of the path is kept, so
        // the structure the author meant survives without the escape.
        self::assertSame(
            ['escaped.webp', 'nested/also-escaped.webp'],
            $this->entriesOf($destination)
        );
    }

    public function testAnArchiveWithNoImagesIsRefused(): void
    {
        $source = $this->file(Fixtures::zip(['notes.txt' => 'hello']));
        $destination = $this->file();

        $this->expectException(ConversionFailed::class);
        $this->expectExceptionMessage('no PNG, JPEG, WebP or AVIF images');

        $this->converter()->convert($source, $destination, ImageFormat::Webp);
    }

    public function testSomethingThatIsNotAZipIsRefused(): void
    {
        $source = $this->file('this is not a zip');
        $destination = $this->file();

        $this->expectException(ConversionFailed::class);
        $this->expectExceptionMessage('not a readable ZIP');

        $this->converter()->convert($source, $destination, ImageFormat::Webp);
    }

    public function testTooManyEntriesIsRefusedBeforeAnythingIsRead(): void
    {
        $entries = [];
        for ($i = 0; $i < 6; $i++) {
            $entries["image-$i.png"] = Fixtures::png(4, 4);
        }
        $source = $this->file(Fixtures::zip($entries));
        $destination = $this->file();

        $this->expectException(ConversionFailed::class);
        $this->expectExceptionMessage('more than 5 files');

        $this->converter(new ConversionLimits(maxArchiveEntries: 5))
            ->convert($source, $destination, ImageFormat::Webp);
    }

    public function testTheUnpackedSizeIsCapped(): void
    {
        $source = $this->file(Fixtures::zip([
            'one.png' => Fixtures::png(300, 300),
            'two.png' => Fixtures::png(300, 300),
        ]));
        $destination = $this->file();

        $this->expectException(ConversionFailed::class);
        $this->expectExceptionMessage('unpacks to more than');

        $this->converter(new ConversionLimits(maxArchiveBytes: 1024))
            ->convert($source, $destination, ImageFormat::Webp);
    }

    public function testDirectoryEntriesAreIgnored(): void
    {
        $source = $this->file(Fixtures::zip([
            'folder/' => '',
            'folder/one.png' => Fixtures::png(),
        ]));
        $destination = $this->file();

        $result = $this->converter()->convert($source, $destination, ImageFormat::Webp);

        self::assertSame(1, $result->converted);
        self::assertSame(['folder/one.webp'], $this->entriesOf($destination));
    }

    public function testNothingIsWrittenOutsideTheDestination(): void
    {
        $source = $this->file(Fixtures::zip(['one.png' => Fixtures::png()]));
        $destination = $this->file();
        $before = glob(dirname($destination) . '/escaped*') ?: [];

        $this->converter()->convert($source, $destination, ImageFormat::Webp);

        self::assertSame($before, glob(dirname($destination) . '/escaped*') ?: []);
    }
}
