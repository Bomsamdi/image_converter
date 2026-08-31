# image_converter

A small HTTP service that converts PNG and JPEG images to **WebP** or **AVIF** —
one file at a time, or every image inside a ZIP. There is a web page for people
and a JSON API for scripts.

Built on Slim 4 and PHP's GD. No database, no accounts, nothing kept: bytes come
in, bytes go out, and the temporary directory is removed before the response is
sent.

## Running it

```sh
docker compose up --build     # http://localhost:8080
```

or, with PHP 8.2+ and the `gd` and `zip` extensions:

```sh
composer install
composer start                # http://localhost:8080
```

Check that your GD can actually write the formats you want — `AVIF Support` is
absent from a lot of distribution builds:

```sh
php -r 'print_r(gd_info());'
```

## The API

Every endpoint takes a `multipart/form-data` body with the file in a field
called `file`. The output format comes from the path, or from a `format` field
or query parameter; it defaults to `webp`.

| endpoint | answers with |
|---|---|
| `POST /api/convert[/{format}]` | JSON, with the image as a data URL |
| `POST /api/convert-download[/{format}]` | the converted image itself |
| `POST /api/convert-archive[/{format}]` | a ZIP of the converted images |

```sh
curl -F "file=@photo.png" http://localhost:8080/api/convert/webp
```

```json
{
  "status": 200,
  "format": "webp",
  "width": 320,
  "height": 200,
  "sourceFormat": "png",
  "sourceBytes": 1275,
  "bytes": 1470,
  "base64": "data:image/webp;base64,UklGRrYFAABXRUJQ..."
}
```

```sh
curl -F "file=@photo.png" -o photo.avif \
     http://localhost:8080/api/convert-download/avif

curl -F "file=@photos.zip" -o converted.zip \
     http://localhost:8080/api/convert-archive/webp
```

An archive response also carries `X-Converted-Count` and `X-Skipped-Count`, so a
caller knows what happened without unpacking it. Inside the archive, folders are
kept and anything that is not an image is left out — an export from a design
tool is full of `.DS_Store` files, and failing the whole request over one would
be useless.

A failure is a **422** with `{"status": 422, "error": "..."}`, and the message is
meant to be shown:

```json
{ "status": 422, "error": "The file is not a PNG, JPEG, WebP or AVIF image. The check reads the file, not its name." }
```

The three original paths — `/convert-to-webp`, `/convert-to-webp-download` and
`/convert-to-zip-webp-download` — still work and still mean WebP.

## Limits

Set through the environment; the defaults are for a service open to the public.

| variable | default | what it stops |
|---|---|---|
| `MAX_IMAGE_BYTES` | 20 MB | an upload that fills the disk |
| `MAX_IMAGE_PIXELS` | 40 Mpx | a decompression bomb: a 33 KB PNG can decode to 16 megapixels, and GD wants four bytes for each one |
| `MAX_ARCHIVE_ENTRIES` | 500 | an archive with a million tiny files |
| `MAX_ARCHIVE_BYTES` | 200 MB | an archive that unpacks to a terabyte |
| `OUTPUT_QUALITY` | 82 | — |
| `APP_DEBUG` | `false` | error details in the response |

PHP's own `upload_max_filesize` and `post_max_size` come first, whatever these
say. The Docker image sets them to 32 MB and 64 MB.

## How a file is checked

The format is decided by **reading the file**, never by its name. That matters
more than it sounds: the earlier version of this service took the format from
`explode('.', $filename)[1]`, so `payload.png.php` was accepted as a PNG — and
the same filename was used to build the path it was written to, inside a folder
the web server served. Uploading executable code was two lines of curl.

Now: the client's filename decides what a download is called and nothing else,
the bytes decide the format, work happens in a private temporary directory that
is removed in a `finally`, and an archive is read entry by entry through the ZIP
extension rather than extracted — so there is no path for a `../../` entry to
escape along.

## Development

```sh
composer check     # phpcs, phpstan (level 8), phpunit
composer test
```

The suite has four parts: unit tests for the converters, HTTP tests through the
Slim application, a handler test, and `tests/EndToEnd`, which starts a real PHP
server and talks to it over the network. That last one exists because a bug hid
everywhere else: a suppressed warning made the shutdown handler write a 500 on
top of a finished ZIP, and a test that calls `$app->handle()` never reaches
shutdown. It reported 200 while a browser got 500.

## License

MIT.
