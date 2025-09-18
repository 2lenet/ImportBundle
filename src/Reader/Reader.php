<?php

namespace Lle\ImportBundle\Reader;

use Lle\ImportBundle\Contracts\ReaderInterface;
use Lle\ImportBundle\Exception\ReaderException;

class Reader
{
    public const string CSV = 'csv';

    public function __construct(
        protected iterable $readers,
    ) {
    }

    public function read(string $path): iterable
    {
        return $this->getReader(pathinfo($path, PATHINFO_EXTENSION))->read($path);
    }

    public function getReader(string $format): ReaderInterface
    {
        /** @var ReaderInterface $reader */
        foreach ($this->readers as $reader) {
            if ($format === $reader->getSupportedFormat()) {
                return $reader;
            }
        }

        throw new ReaderException('Import action: format ' . $format . ' is not supported');
    }
}
