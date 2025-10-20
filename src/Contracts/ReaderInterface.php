<?php

namespace Lle\ImportBundle\Contracts;

interface ReaderInterface
{
    public function getSupportedMimeTypes(): array;

    public function read(string $path, ?string $encoding = null): iterable;
}
