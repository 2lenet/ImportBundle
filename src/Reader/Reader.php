<?php

namespace Lle\ImportBundle\Reader;

use Lle\ImportBundle\Contracts\ReaderInterface;
use Lle\ImportBundle\Exception\ReaderException;

class Reader
{
    public const string CSV = 'text/csv';

    public function __construct(
        protected iterable $readers,
    ) {
    }

    public function read(string $path): iterable
    {
        return $this->getReader(mime_content_type($path))->read($path);
    }

    public function getReader(string $format): ReaderInterface
    {
        /** @var ReaderInterface $reader */
        foreach ($this->readers as $reader) {
            if (in_array($format, $reader->getSupportedMimeTypes())) {
                return $reader;
            }
        }

        throw new ReaderException('Import action: format ' . $format . ' is not supported');
    }
}
