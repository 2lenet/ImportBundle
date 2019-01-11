<?php

namespace ClickAndMortar\ImportBundle\Reader;

/**
 * Interface ReaderInterface
 *
 * @package ClickAndMortar\ImportBundle\Reader
 */
interface ReaderInterface
{
    /**
     * Read CSV file and return data array
     * 
     * @param string $path
     * @return Generator
     */
    public function read(string $path) :\Generator;

    /**
     * Check if reader support $type
     *
     * @param string $type
     *
     * @return bool
     */
    public function support($type);
}
