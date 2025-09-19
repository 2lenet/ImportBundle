<?php

namespace Lle\ImportBundle\Contracts;

interface ImportHelperInterface
{
    public function completeData(object &$entity, array $data, array $additionnalData = []): void;

    public function beforeImport(array $additionnalData = []): void;

    public function afterImport(array $additionnalData = []): void;
}
