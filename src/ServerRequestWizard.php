<?php

declare(strict_types=1);

namespace Ddrv\ServerRequestWizard;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;

final class ServerRequestWizard
{

    /**
     * @var ServerRequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @var UploadedFileFactoryInterface
     */
    private $fileFactory;

    public function __construct(
        ServerRequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        UploadedFileFactoryInterface $fileFactory
    ) {
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->fileFactory = $fileFactory;
    }

    /**
     * @param array $queryParams
     * @param array $parsedBody
     * @param array $serverParams
     * @param array $cookies
     * @param array $uploadedFiles
     * @param resource|null $bodyResource
     * @return ServerRequestInterface
     */
    public function create(
        array $queryParams,
        array $parsedBody,
        array $serverParams,
        array $cookies,
        array $uploadedFiles,
        $bodyResource = null
    ): ServerRequestInterface {
        $method = array_key_exists('REQUEST_METHOD', $serverParams) ? $serverParams['REQUEST_METHOD'] : 'GET';
        $https = array_key_exists('HTTPS', $serverParams);
        $host = array_key_exists('HTTP_HOST', $serverParams) ? $serverParams['HTTP_HOST'] : null;
        if (!$host && array_key_exists('SERVER_NAME', $serverParams)) {
            $host = $serverParams['SERVER_NAME'];
        }
        $host = (string)$host;
        $port = null;
        if (preg_match('/^(?<host>\[[a-fA-F0-9:.]+])(:(?<port>\d+))?(\z|$)/', $host, $matches)) {
            $host = $matches['host'];
            if (array_key_exists('port', $matches)) {
                $port = (int)$matches['port'];
            }
        } else {
            $pos = mb_strpos($host, ':');
            if ($pos !== false) {
                $port = (int)mb_substr($host, $pos + 1);
                $host = mb_substr($host, 0, $pos);
            }
        }
        if (is_null($port)) {
            if (array_key_exists('SERVER_PORT', $serverParams)) {
                $port = (int)$serverParams['SERVER_PORT'];
            } else {
                $port = $https ? 443 : 80;
            }
        }
        $uri = 'http';
        if ($https) {
            $uri .= 's';
        }
        if (!$host) {
            $host = 'localhost';
        }
        $uri .= '://' . $host;
        if (($port !== 80 && !$https) || ($port !== 443 && $https)) {
            $uri .= ':' . $port;
        }
        if (array_key_exists('REQUEST_URI', $serverParams)) {
            $uri .= '/' . ltrim($serverParams['REQUEST_URI'], '/');
        }
        $version = '1.1';
        if (array_key_exists('SERVER_PROTOCOL', $serverParams)) {
            $pos = strpos($serverParams['SERVER_PROTOCOL'], '/');
            if ($pos !== false) {
                $version = substr($serverParams['SERVER_PROTOCOL'], $pos);
            }
        }

        $request = $this->requestFactory
            ->createServerRequest($method, $uri, $serverParams)
            ->withProtocolVersion($version)
            ->withQueryParams($queryParams)
            ->withCookieParams($cookies)
        ;

        if (!is_resource($bodyResource)) {
            rewind($bodyResource);
            $temp = fopen('php://temp', 'wb+');
            $input = fopen('php://input', 'r');
            stream_copy_to_stream($input, $temp);
            fclose($input);
            rewind($temp);
            $request = $request->withBody(
                $this->streamFactory->createStreamFromResource($temp)
            );
        }

        $cookiesHeader = [];
        foreach ($cookies as $name => $value) {
            $cookiesHeader[] = urlencode($name) . '=' . urlencode($value);
        }
        if ($cookiesHeader) {
            $serverParams['HTTP_COOKIE'] = implode(',', $cookiesHeader);
        }
        if (!array_key_exists('HTTP_AUTHORIZATION', $serverParams)) {
            if (array_key_exists('REDIRECT_HTTP_AUTHORIZATION', $serverParams)) {
                $serverParams['HTTP_AUTHORIZATION'] = $serverParams['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (array_key_exists('PHP_AUTH_USER', $serverParams)) {
                $auth = $serverParams['PHP_AUTH_USER'] . ':';
                if (array_key_exists('PHP_AUTH_PW', $serverParams)) {
                    $auth .= $serverParams['PHP_AUTH_PW'];
                }
                $serverParams['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode($auth);
            } elseif (array_key_exists('PHP_AUTH_DIGEST', $serverParams)) {
                $serverParams['HTTP_AUTHORIZATION'] = $serverParams['PHP_AUTH_DIGEST'];
            }
        }
        $specificHeaders = ['CONTENT_TYPE', 'CONTENT_LENGTH'];
        foreach ($specificHeaders as $specificHeader) {
            $headerServerParam = 'HTTP_' . $specificHeader;
            if (!array_key_exists($specificHeader, $serverParams)) {
                continue;
            }
            if (array_key_exists($headerServerParam, $serverParams)) {
                continue;
            }
            $serverParams[$headerServerParam] = $serverParams[$specificHeader];
        }

        foreach ($serverParams as $key => $value) {
            if (strpos($key, 'HTTP_') !== 0) {
                continue;
            }
            $header = strtolower(substr($key, 5));
            $header = str_replace('_', '-', $header);
            $values = array_map('trim', explode(',', $value));
            $request = $request->withHeader($header, $values);
        }
        if (!$request->hasHeader('Content-Type') && array_key_exists('CONTENT_TYPE', $serverParams)) {
            $request = $request->withHeader('Content-Type', $serverParams['CONTENT_TYPE']);
        }
        if ($request->hasHeader('Content-Type')) {
            $contentType = trim(explode(';', $request->getHeaderLine('Content-Type'))[0]);
            if (in_array($contentType, ['application/x-www-form-urlencoded', 'multipart/form-data'])) {
                $request = $request->withParsedBody($parsedBody);
            }
        }
        if ($uploadedFiles) {
            $request = $request->withUploadedFiles($this->getUploadedFiles($uploadedFiles));
        }
        return $request;
    }

    private function getUploadedFiles(array $files): array
    {
        $uploads = [];
        foreach ($files as $field => $file) {
            if (!isset($file['error'])) {
                if (is_array($file)) {
                    $uploads[$field] = $this->getUploadedFiles($file);
                }
                continue;
            }

            $uploads[$field] = [];
            if (is_array($file['error'])) {
                $subArray = [];
                foreach ($file['error'] as $fileIdx => $error) {
                    $subArray[$fileIdx]['name'] = $file['name'][$fileIdx];
                    $subArray[$fileIdx]['type'] = $file['type'][$fileIdx];
                    $subArray[$fileIdx]['tmp_name'] = $file['tmp_name'][$fileIdx];
                    $subArray[$fileIdx]['error'] = $file['error'][$fileIdx];
                    $subArray[$fileIdx]['size'] = $file['size'][$fileIdx];
                    $uploads[$field] = $this->getUploadedFiles($subArray);
                }
            } else {
                $uploads[$field] = $this->fileFactory->createUploadedFile(
                    $this->streamFactory->createStreamFromFile($file['tmp_name']),
                    array_key_exists('size', $file) ? (int)$file['size'] : null,
                    $file['error'],
                    array_key_exists('name', $file) ? (string)$file['name'] : null,
                    array_key_exists('type', $file) ? (string)$file['type'] : null
                );
            }
        }
        return $uploads;
    }
}
