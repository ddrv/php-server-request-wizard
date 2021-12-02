<?php

declare(strict_types=1);

use Ddrv\ServerRequestWizard\FileReader;
use Ddrv\ServerRequestWizard\ServerRequestWizard;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\UploadedFileInterface;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$factory = new Psr17Factory();

$wizard = new ServerRequestWizard(
    $factory,
    $factory,
    $factory
);

$input = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'input.json';
$upload = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'upload.txt';

$body = new FileReader($input);
$query = [
    'a' => 'foo',
    'b' => 'bar',
];

$method = 'POST';
$uri = 'https://localhost:8080/api?' . http_build_query($query);
$uriArray = parse_url($uri);

$post = [
    'foo' => 'a',
    'bar' => 'b',
];
$server = [
    'SERVER_PROTOCOL' => 'HTTP/1.1',
    'SERVER_PORT' => $uriArray['port'],
    'QUERY_STRING' => $uriArray['query'],
    'REQUEST_URI' => $uriArray['path'] . '?' . $uriArray['query'],
    'REQUEST_METHOD' => $method,
    'CONTENT_TYPE' => 'application/json',
    'HTTPS' => '1',
    'HTTP_HOST' => $uriArray['host'],
    'SERVER_NAME' => $uriArray['host'],
];
$cookies = [
    'name' => 'value',
];
$files = [
    'file' => [
        'name' => 'test.txt',
        'type' => 'text/plain',
        'tmp_name' => $upload,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($upload),
    ]
];

$request = $wizard->create($query, $post, $server, $cookies, $files, $body);

$errors = [];

if ($request->getMethod() !== $method) {
    $errors[] = 'invalid request method';
}

if ($request->getProtocolVersion() !== '1.1') {
    $errors[] = 'invalid request protocol version';
}

if ($request->getBody()->__toString() !== file_get_contents($input)) {
    $errors[] = 'invalid body content';
}

if ($request->getUri()->__toString() !== $uri) {
    $errors[] = 'invalid request uri';
}

$requestQuery = $request->getQueryParams();
foreach ($query as $key => $value) {
    if (!array_key_exists($key, $requestQuery)) {
        $errors[] = 'request has no contains ' . $key . ' query param';
        continue;
    }
    if ($requestQuery[$key] !== $value) {
        $errors[] = 'request query param ' . $key . ' is not equal with ' . $value;
    }
}

$requestParsedBody = $request->getParsedBody();
foreach ($post as $key => $value) {
    if (!array_key_exists($key, $requestParsedBody)) {
        $errors[] = 'request has no contains ' . $key . ' parsed body param';
        continue;
    }
    if ($requestParsedBody[$key] !== $value) {
        $errors[] = 'request parsed body param ' . $key . ' is not equal with ' . $value;
    }
}

$requestCookies = $request->getCookieParams();
foreach ($cookies as $key => $value) {
    if (!array_key_exists($key, $requestCookies)) {
        $errors[] = 'request has no contains ' . $key . ' cookie';
        continue;
    }
    if ($requestCookies[$key] !== $value) {
        $errors[] = 'request cookie ' . $key . ' is not equal with ' . $value;
    }
}

$requestServerParams = $request->getServerParams();
foreach ($server as $key => $value) {
    if (!array_key_exists($key, $requestServerParams)) {
        $errors[] = 'request has no contains ' . $key . ' server param';
        continue;
    }
    if ($requestServerParams[$key] !== $value) {
        $errors[] = 'request server param ' . $key . ' is not equal with ' . $value;
    }
}

$requestUploads = $request->getUploadedFiles();
foreach ($files as $key => $value) {
    if (!array_key_exists($key, $requestUploads)) {
        $errors[] = 'request has no contains ' . $key . ' uploaded file';
        continue;
    }
    /** @var UploadedFileInterface $uploadedFile */
    $uploadedFile = $requestUploads[$key];
    if ($uploadedFile->getClientFilename() !== $value['name']) {
        $errors[] = 'request uploaded file ' . $key . ' client name is not equal with ' . $value['name'];
    }
    if ($uploadedFile->getClientMediaType() !== $value['type']) {
        $errors[] = 'request uploaded file ' . $key . ' media type is not equal with ' . $value['type'];
    }
    if ($uploadedFile->getError() !== $value['error']) {
        $errors[] = 'request uploaded file ' . $key . ' error is not equal with ' . $value['error'];
    }
    if ($uploadedFile->getSize() !== $value['size']) {
        $errors[] = 'request uploaded file ' . $key . ' size is not equal with ' . $value['size'];
    }
    if ($uploadedFile->getStream()->__toString() !== file_get_contents($value['tmp_name'])) {
        $errors[] = 'request uploaded file ' . $key . ' contents is not equal with file ' . $value['tmp_name'];
    }
}

if (empty($errors)) {
    echo 'ok' . PHP_EOL;
    exit(0);
}

foreach ($errors as $error) {
    echo $error . PHP_EOL;
}
exit(1);
