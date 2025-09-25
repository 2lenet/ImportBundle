<?php

namespace Lle\ImportBundle\Reader;

class CsvReader extends AbstractReader
{
    public function getSupportedMimeTypes(): array
    {
        return [Reader::CSV];
    }

    public function read($path): \Generator
    {
        $file = fopen($path, 'r');
        $header = (array)fgetcsv($file);
        while (($row = fgetcsv($file)) !== false) {
            yield array_combine($header, $row);
        }

        fclose($file);
    }
}
