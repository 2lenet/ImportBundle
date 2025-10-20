<?php

namespace Lle\ImportBundle\Reader;

class CsvReader extends AbstractReader
{
    public function getSupportedMimeTypes(): array
    {
        return [Reader::CSV];
    }

    public function read(string $path, ?string $encoding = null): \Generator
    {
        $file = fopen($path, 'r');
        if ($encoding) {
            stream_filter_append($file, $encoding, STREAM_FILTER_READ);
        }

        $header = (array)fgetcsv($file);
        while (($row = fgetcsv($file)) !== false) {
            yield array_combine($header, $row);
        }

        fclose($file);
    }
}
