<?php

namespace Lle\ImportBundle\Reader;

use Lle\ImportBundle\Contracts\ReaderInterface;
use Lle\ImportBundle\Exception\ReaderException;

class CsvReader implements ReaderInterface
{
    public function getSupportedMimeTypes(): array
    {
        return [Reader::CSV];
    }

    public function read(string $path, array $options = []): \Generator
    {
        $file = fopen($path, 'r');
        if (!$file) {
            throw new ReaderException('Import action: an error occurred while opening file: ' . $path);
        }

        if (array_key_exists('encoding', $options)) {
            stream_filter_append($file, $options['encoding'], STREAM_FILTER_READ);
        }

        $header = (array)fgetcsv($file);
        while (($row = fgetcsv($file)) !== false) {
            yield array_combine($header, $row);
        }

        fclose($file);
    }
}
