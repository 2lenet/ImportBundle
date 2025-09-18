<?php

namespace Lle\ImportBundle\Contracts;

interface ReaderInterface
{    
    public function getSupportedFormat(): string;

    public function read(string $path): iterable;
}
