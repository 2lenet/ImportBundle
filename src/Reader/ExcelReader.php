<?php

namespace Lle\ImportBundle\Reader;

use Lle\ImportBundle\Exception\ReaderException;
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
            if (!$sheet) {
                throw new ReaderException(
                    'Import action: sheet name ' . $options['excel_sheet_name'] . ' not found'
                );
            }
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

        $nbHeaders = count($header);
        foreach ($sheet->getRowIterator(2) as $key => $row) {
            if ($key > $sheet->getHighestDataRow()) {
                break;
            }

            $data = [];
            foreach ($row->getCellIterator() as $cell) {
                $data[] = $cell->getValue();

                if (count($data) === $nbHeaders) {
                    break;
                }
            }

            yield array_combine($header, $data);
        }
    }
}
