<?php

declare(strict_types=1);

namespace Tests\Domain\Image;

use App\Domain\Image\ConversionFailed;
use App\Domain\Image\ConversionLimits;
use App\Domain\Image\ImageConverter;
use App\Domain\Image\ImageFormat;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures;

final class ImageConverterTest extends TestCase
{
    private function converter(ConversionLimits $limits = new ConversionLimits()): ImageConverter
    {
        return new ImageConverter($limits);
    }

    public function testConvertsPngToWebp(): void
    {
        $webp = $this->converter()->convert(Fixtures::png(), ImageFormat::Webp);

        $info = $this->converter()->inspect($webp);
        self::assertSame(ImageFormat::Webp, $info->format);
        self::assertSame(40, $info->width);
        self::assertSame(30, $info->height);
    }

    public function testConvertsJpegToWebp(): void
    {
        $webp = $this->converter()->convert(Fixtures::jpeg(), ImageFormat::Webp);

        self::assertSame(ImageFormat::Webp, $this->converter()->inspect($webp)->format);
    }

    public function testConvertsToAvif(): void
    {
        if (!ImageFormat::Avif->isWritable()) {
            self::markTestSkipped('This GD cannot write AVIF.');
        }

        $avif = $this->converter()->convert(Fixtures::png(), ImageFormat::Avif);

        self::assertSame(ImageFormat::Avif, $this->converter()->inspect($avif)->format);
    }

    public function testReadsTheFormatFromTheBytesNotTheName(): void
    {
        // What used to pass the check: a name ending in .png. What passes now:
        // being a PNG.
        $this->expectException(ConversionFailed::class);
        $this->expectExceptionMessage('reads the file, not its name');

        $this->converter()->convert('<?php echo "not an image";', ImageFormat::Webp);
    }

    public function testRefusesAnImageFormatItCannotRead(): void
    {
        // A valid GIF header, and a format this service does not handle.
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAAAAAAALAAAAAABAAEAAAIBRAA7');

        $this->expectException(ConversionFailed::class);
        $this->converter()->convert($gif, ImageFormat::Webp);
    }

    public function testRefusesAFileOverTheByteLimit(): void
    {
        $png = Fixtures::png(200, 200);
        $limits = new ConversionLimits(maxBytes: strlen($png) - 1);

        $this->expectException(ConversionFailed::class);
        $this->expectExceptionMessage('the limit is');

        $this->converter($limits)->convert($png, ImageFormat::Webp);
    }

    public function testRefusesADecompressionBomb(): void
    {
        // 4000x4000 is 16 megapixels of nothing: 33 KB on disk, 64 MB in GD.
        $bomb = Fixtures::hugePng(4000, 4000);
        $limits = new ConversionLimits(maxPixels: 1_000_000);

        self::assertLessThan(200_000, strlen($bomb), 'the fixture must be small on disk');

        $this->expectException(ConversionFailed::class);
        $this->expectExceptionMessage('megapixels');

        $this->converter($limits)->convert($bomb, ImageFormat::Webp);
    }

    public function testRefusesAnOutputFormatItDoesNotWrite(): void
    {
        $this->expectException(ConversionFailed::class);
        $this->expectExceptionMessage('not a format this service writes');

        $this->converter()->convert(Fixtures::png(), ImageFormat::Png);
    }

    public function testRefusesTruncatedBytes(): void
    {
        $png = Fixtures::png();

        $this->expectException(ConversionFailed::class);
        // Half a PNG still has a readable header, so this reaches the decoder.
        $this->converter()->convert(substr($png, 0, (int) (strlen($png) / 2)), ImageFormat::Webp);
    }

    public function testKeepsTransparency(): void
    {
        $webp = $this->converter()->convert(Fixtures::png(withAlpha: true), ImageFormat::Webp);

        $image = imagecreatefromstring($webp);
        self::assertNotFalse($image);
        // The top left quarter was fully transparent before the conversion.
        $alpha = (imagecolorat($image, 2, 2) >> 24) & 0x7F;

        self::assertGreaterThan(100, $alpha, 'the transparent corner came out opaque');
    }

    public function testInspectReportsWhatItRead(): void
    {
        $info = $this->converter()->inspect(Fixtures::jpeg(64, 48));

        self::assertSame(ImageFormat::Jpeg, $info->format);
        self::assertSame(64, $info->width);
        self::assertSame(48, $info->height);
        self::assertSame(3072, $info->pixels());
        self::assertGreaterThan(0, $info->bytes);
    }

    public function testIsSupportedDoesNotThrow(): void
    {
        self::assertTrue($this->converter()->isSupported(Fixtures::png()));
        self::assertFalse($this->converter()->isSupported('nonsense'));
    }
}
