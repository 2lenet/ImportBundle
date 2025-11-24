<?php

namespace Lle\ImportBundle\Reader;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelReader extends AbstractReader
{
    public function getSupportedMimeTypes(): array
    {
        return [Reader::XLS, Reader::XLSX];
    }

    public function read(string $path, array $options = []): \Generator
    {
        $spreadsheet = IOFactory::load($path);
        if (array_key_exists('excel_sheet_name', $options)) {
            $sheet = $spreadsheet->getSheetByName($options['excel_sheet_name']);
        } else {
            $sheet = $spreadsheet->getSheet($spreadsheet->getFirstSheetIndex());
        }

        $header = [];
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $header[] = $cell->getValue();
            }

            break;
        }

        foreach ($sheet->getRowIterator(2) as $key => $row) {
            if ($key > $sheet->getHighestDataRow()) {
                break;
            }

            $data = [];
            foreach ($row->getCellIterator() as $cell) {
                $data[] = $cell->getValue();
            }

            yield array_combine($header, $data);
        }
    }
}
