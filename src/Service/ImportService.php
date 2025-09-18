<?php

namespace Lle\ImportBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Lle\ImportBundle\Contracts\ImportHelperInterface;
use Lle\ImportBundle\Exception\ImportException;
use Lle\ImportBundle\Reader\Reader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ImportService
{
    protected array $configurations = [];

    public function __construct(
        protected ParameterBagInterface $parameterBag,
        protected ContainerInterface $container,
        protected EntityManagerInterface $em,
        protected Reader $reader,
    ) {
        $this->configurations = $this->parameterBag->get('lle_import.configs');
    }

    public function import(string $path, string $config, bool $deleteAfterImport = false): int
    {
        $this->checkFileExists($path);
        $this->checkFileIsReadable($path);
        $this->checkConfigExists($config);

        $config = $this->configurations[$config];
        $entityClassName = $config['entity'];
        $uniqueKey = null;
        if (array_key_exists('unique_key', $config) && $config['unique_key']) {
            $uniqueKey = $config['unique_key'];
        }

        $importHelper = $this->getImportHelperService($config);
        $mappings = $config['mappings'];

        $repository = $this->em->getRepository($entityClassName);

        if ($importHelper) {
            $importHelper->beforeImport();
        }

        $nb = 0;
        foreach ($this->reader->read($path) as $row) {
            $entity = null;
            if ($uniqueKey) {
                $entity = $repository->findOneBy([$uniqueKey => $this->getValue($mappings[$uniqueKey], $row)]);
            }

            if (!$entity) {
                $entity = new $entityClassName();
            }

            foreach ($mappings as $entityPropertyKey => $filePropertyKey) {
                $setter = 'set' . ucfirst($entityPropertyKey);
                if ($value = $this->getValue($filePropertyKey, $row)) {
                    $entity->{$setter}($value);
                }
            }

            if ($importHelper) {
                $importHelper->completeData($entity, $row);
            }

            $this->em->persist($entity);
            $nb++;

            if ($nb % 1000 === 0) {
                $this->em->flush();
            }
        }

        $this->em->flush();

        if ($importHelper) {
            $importHelper->afterImport();
        }

        if ($deleteAfterImport) {
            unlink($path);
        }

        return $nb;
    }

    public function checkFileExists(string $path): void
    {
        if (!file_exists($path)) {
            throw new ImportException('File "' . $path . '" does not exist.');
        }
    }

    public function checkFileIsReadable(string $path): void
    {
        if (!is_readable($path)) {
            throw new ImportException('File "' . $path . '" is not readable.');
        }
    }

    public function checkConfigExists(string $config): void
    {
        if (!array_key_exists($config, $this->configurations)) {
            throw new ImportException('Config "' . $config . '" does not exist.');
        }
    }

    public function getImportHelperService(array $config): ?ImportHelperInterface
    {
        if (array_key_exists('import_helper_service', $config) && $config['import_helper_service']) {
            if (!$this->container->has($config['import_helper_service'])) {
                throw new ImportException('Import helper "' . $config['import_helper_service'] . '" does not exist.');
            }

            return $this->container->get($config['import_helper_service']);
        }

        return null;
    }

    public function getValue(mixed $keys, array $row): string
    {
        if (!is_array($keys)) {
            return trim($row[$keys]);
        }

        $value = '';
        foreach ($keys as $key) {
            $value .= trim($row[$key]);
        }

        return $value;
    }
}
