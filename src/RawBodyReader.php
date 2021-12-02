<?php

declare(strict_types=1);

namespace Ddrv\ServerRequestWizard;

interface RawBodyReader
{

    public function rewind(): void;

    public function eof(): bool;

    public function read(int $length): ?string;

    public function close(): void;
}
