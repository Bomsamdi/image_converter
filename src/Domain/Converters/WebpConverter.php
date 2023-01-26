<?php
use App\Domain\Converters\IConverter;
use Psr\Http\Message\ResponseInterface as Response;
class WebpConverter implements IConverter {
    use App\Domain\Converters\TPng;
    use App\Domain\Converters\TJpg;
    use App\Domain\Converters\TImage;

  static function fromPng($path, $file) : string {
    $response = [];
    return json_encode($response);
  }

  static function fromJpg($path, $file) : string {
    $response = [];
    return json_encode($response);
  }

  static function badResponse(Response $response) : Response {
    $resp = [ 'error' => 'bad format' ];
    $response->getBody()->write(json_encode($resp));
    return $response;
  }
}
?>