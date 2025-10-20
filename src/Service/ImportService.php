<?php

namespace Lle\ImportBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Lle\ImportBundle\Contracts\ImportHelperInterface;
use Lle\ImportBundle\Contracts\ReaderInterface;
use Lle\ImportBundle\Exception\ImportException;
use Lle\ImportBundle\Exception\ReaderException;
use Lle\ImportBundle\Reader\Reader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class ImportService
{
    protected array $configurations = [];

    public function __construct(
        protected ParameterBagInterface $parameterBag,
        protected ContainerInterface $container,
        protected EntityManagerInterface $em,
        protected Reader $reader,
        protected PropertyAccessorInterface $propertyAccessor,
    ) {
        $this->configurations = $this->parameterBag->get('lle_import.configs');
    }

    /**
     * @throws ImportException
     * @throws ReaderException
     */
    public function import(
        string $path,
        string $configName,
        bool $deleteAfterImport = false,
        array $additionnalData = []
    ): int {
        $this->checkFileExists($path);
        $this->checkFileIsReadable($path);
        $this->checkConfigExists($configName);
        $this->checkConfigEntityExists($this->configurations[$configName]);
        $this->checkConfigMappingsExists($this->configurations[$configName]);

        $config = $this->configurations[$configName];
        $entityClassName = $config['entity'];
        $uniqueKey = null;
        if (array_key_exists('unique_key', $config) && $config['unique_key']) {
            $uniqueKey = $config['unique_key'];
        }

        $importHelper = $this->getImportHelperService($config);
        $mappings = $config['mappings'];

        $repository = $this->em->getRepository($entityClassName);

        if (array_key_exists('clear_entity', $config) && $config['clear_entity']) {
            $repository->createQueryBuilder('root')->delete()->getQuery()->execute();
        }

        $importHelper?->beforeImport($additionnalData);

        $encoding = null;
        if (array_key_exists('encoding', $config) && $config['encoding']) {
            $encoding = $config['encoding'];
        }

        $nb = 0;
        $reader = $this->getReader($config);
        foreach ($reader ? $reader->read($path, $encoding) : $this->reader->read($path, $encoding) as $row) {
            $entity = null;
            if ($uniqueKey) {
                $entity = $repository->findOneBy([$uniqueKey => $this->getValue($mappings[$uniqueKey], $row)]);
            }

            if (!$entity && (!array_key_exists('only_update', $config) || !$config['only_update'])) {
                $entity = new $entityClassName();
            }

            if ($entity) {
                foreach ($mappings as $entityPropertyKey => $filePropertyKey) {
                    if ($value = $this->getValue($filePropertyKey, $row)) {
                        $this->propertyAccessor->setValue($entity, $entityPropertyKey, $value);
                    }
                }

                $importHelper?->completeData($entity, $row, $additionnalData);

                $this->em->persist($entity);
                $nb++;

                if ($nb % 1000 === 0) {
                    $this->em->flush();
                }
            }
        }

        $this->em->flush();

        $importHelper?->afterImport($additionnalData);

        if ($deleteAfterImport) {
            unlink($path);
        }

        return $nb;
    }

    /**
     * @throws ImportException
     */
    public function checkFileExists(string $path): void
    {
        if (!file_exists($path)) {
            throw new ImportException('File "' . $path . '" does not exist.');
        }
    }

    /**
     * @throws ImportException
     */
    public function checkFileIsReadable(string $path): void
    {
        if (!is_readable($path)) {
            throw new ImportException('File "' . $path . '" is not readable.');
        }
    }

    /**
     * @throws ImportException
     */
    public function checkConfigExists(string $config): void
    {
        if (!array_key_exists($config, $this->configurations)) {
            throw new ImportException('Config "' . $config . '" does not exist.');
        }
    }

    /**
     * @throws ImportException
     */
    public function checkConfigEntityExists(array $config): void
    {
        if (!array_key_exists('entity', $config)) {
            throw new ImportException('Config "entity" must be defined.');
        }
    }

    /**
     * @throws ImportException
     */
    public function checkConfigMappingsExists(array $config): void
    {
        if (!array_key_exists('mappings', $config)) {
            throw new ImportException('Config "mappings" must be defined.');
        }
    }

    /**
     * @throws ImportException
     */
    public function getImportHelperService(array $config): ?ImportHelperInterface
    {
        if (array_key_exists('import_helper_service', $config) && $config['import_helper_service']) {
            if (!$this->container->has($config['import_helper_service'])) {
                throw new ImportException('Import helper "' . $config['import_helper_service'] . '" does not exist.');
            }

            $importHelper = $this->container->get($config['import_helper_service']);
            if (!is_a($importHelper, ImportHelperInterface::class)) {
                throw new ImportException('Import helper "' . $config['import_helper_service'] . '" does not implement ImportHelperInterface.');
            }

            return $importHelper;
        }

        return null;
    }

    /**
     * @throws ImportException
     */
    public function getReader(array $config): ?ReaderInterface
    {
        if (array_key_exists('reader', $config) && $config['reader']) {
            if (!$this->container->has($config['reader'])) {
                throw new ImportException('Reader "' . $config['reader'] . '" does not exist.');
            }

            $reader = $this->container->get($config['reader']);
            if (!is_a($reader, ReaderInterface::class)) {
                throw new ImportException('Reader "' . $config['reader'] . '" does not implement ReaderInterface.');
            }

            return $reader;
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
