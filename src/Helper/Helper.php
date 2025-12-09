<?php

namespace Lle\ImportBundle\Helper;

use Lle\ImportBundle\Contracts\ImportHelperInterface;

class Helper
{
    public function __construct(
        protected iterable $helpers,
    ) {
    }

    /**
     * @param class-string $class
     */
    public function getHelper(string $class): ImportHelperInterface
    {
        /** @var ImportHelperInterface $helper */
        foreach ($this->helpers as $helper) {
            if (get_class($helper) === $class) {
                return $helper;
            }
        }

        throw new HelperException('Import helper: class "' . $class . '" not found');
    }
}