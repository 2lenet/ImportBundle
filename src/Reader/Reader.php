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

    /**
     * @throws ReaderException
     */
    public function read(string $path, ?string $encoding = null): iterable
    {
        return $this->getReader(mime_content_type($path))->read($path, $encoding);
    }

    /**
     * @throws ReaderException
     */
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
