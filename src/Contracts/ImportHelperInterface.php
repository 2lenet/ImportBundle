<?php

namespace Lle\ImportBundle\Contracts;

interface ImportHelperInterface
{
    public function completeData(object &$entity, array $data): void;

    public function beforeImport(): void;

    public function afterImport(): void;
}
