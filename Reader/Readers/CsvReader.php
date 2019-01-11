<?php

namespace ClickAndMortar\ImportBundle\Reader\Readers;

use ClickAndMortar\ImportBundle\Reader\AbstractReader;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class CsvReader
 *
 * @package ClickAndMortar\ImportBundle\Reader\Readers
 */
class CsvReader extends AbstractReader
{
    
    private $stream_filter = null;
    
    /**
     * Configure options.
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(
            array(
                'delimiter' => ','
            )
        );
    }
    
    public function setStreamFilter($stream_filter)
    {
        $this->stream_filter = $stream_filter;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \ClickAndMortar\ImportBundle\Reader\ReaderInterface::read()
     */
    public function read($path) :\Generator
    {
        $data = array();

        $header = null;
        $handle = fopen($path, 'r');
        if ($this->stream_filter) {
            stream_filter_append($handle, $this->stream_filter, STREAM_FILTER_READ);
        }

        while ($row = fgetcsv($handle, null, $this->options['delimiter'])) {
            if (is_null($header)) {
                $header = $row;
            } else {
                yield array_combine($header, $row);
            }
        }
        fclose($handle);
    }

    /**
     * Support only csv type
     *
     * @param string $type
     *
     * @return bool
     */
    public function support($type)
    {
        return $type == 'csv';
    }
}
