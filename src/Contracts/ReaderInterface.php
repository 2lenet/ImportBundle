<?php

namespace Lle\ImportBundle\Contracts;

interface ReaderInterface
{
    public function getSupportedMimeTypes(): array;

    public function read(string $path, array $options = []): iterable;
}
