<?php

declare(strict_types=1);

namespace Ddrv\ServerRequestWizard;

final class FileReader implements RawBodyReader
{

    /**
     * @var resource|null
     */
    private $fh;

    public function __construct(string $file)
    {
        $fh = fopen($file, 'rb');
        $this->fh = is_resource($fh) ? $fh : null;
    }

    public function rewind(): void
    {
        if (is_null($this->fh)) {
            return;
        }
        rewind($this->fh);
    }

    public function eof(): bool
    {
        if (is_null($this->fh)) {
            return true;
        }
        return feof($this->fh);
    }

    public function read(int $length): ?string
    {
        if (is_null($this->fh)) {
            return null;
        }
        $data = fread($this->fh, $length);
        return is_string($data) ? $data : null;
    }

    public function close(): void
    {
        if (is_null($this->fh)) {
            return;
        }
        fclose($this->fh);
    }
}
