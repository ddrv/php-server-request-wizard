# ddrv/server-request-wizard

# Install

```text
composer require ddrv/server-request-wizard:^1.0
```

# Usage

```php
<?php

/**
 * @var Psr\Http\Message\ServerRequestFactoryInterface $serverRequestFactory
 * @var Psr\Http\Message\StreamFactoryInterface $streamFactory
 * @var Psr\Http\Message\UploadedFileFactoryInterface $UploadedFileFactory
 */

$wizard = new Ddrv\ServerRequestWizard\ServerRequestWizard($serverRequestFactory, $streamFactory, $UploadedFileFactory);

$request = $wizard->create($_GET, $_POST, $_SERVER, $_COOKIE, $_FILES);
```
