<?php

namespace Lle\ImportBundle\Reader;

class CsvReader extends AbstractReader
{
    public function getSupportedMimeTypes(): array
    {
        return [Reader::CSV];
    }

    public function read(string $path, array $options = []): \Generator
    {
        $file = fopen($path, 'r');
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
