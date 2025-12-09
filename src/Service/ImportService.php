<?php

namespace Lle\ImportBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Lle\ImportBundle\Contracts\ImportHelperInterface;
use Lle\ImportBundle\Contracts\ReaderInterface;
use Lle\ImportBundle\Exception\ImportException;
use Lle\ImportBundle\Exception\ReaderException;
use Lle\ImportBundle\Helper\Helper;
use Lle\ImportBundle\Reader\Reader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class ImportService
{
    protected array $configurations = [];

    protected int $nbLinesImported = 0;

    public function __construct(
        protected ParameterBagInterface $parameterBag,
        protected ContainerInterface $container,
        protected EntityManagerInterface $em,
        protected Reader $reader,
        protected Helper $helper,
        protected PropertyAccessorInterface $propertyAccessor,
    ) {
        /** @var array $config */
        $config = $this->parameterBag->get('lle_import.configs');
        $this->configurations = $config;
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

        $config = $this->configurations[$configName];
        $importHelper = $this->getImportHelperService($config);

        $importHelper?->beforeImport($additionnalData);

        $options = [];
        if (array_key_exists('options', $config) && $config['options']) {
            $options = $config['options'];
        }

        $reader = $this->getReader($config);
        foreach ($reader ? $reader->read($path, $options) : $this->reader->read($path, $options) as $row) {
            $this->create($row, $configName, additionnalData: $additionnalData);

            if ($this->nbLinesImported % 1000 === 0) {
                $this->em->flush();
            }
        }

        $this->em->flush();

        $importHelper?->afterImport($additionnalData);

        if ($deleteAfterImport) {
            unlink($path);
        }

        return $this->nbLinesImported;
    }

    public function create(
        array $row,
        string $configName,
        ?string $subPropertyKey = null,
        ?object $previousEntity = null,
        array $additionnalData = []
    ): void {
        $this->checkConfigExists($configName);
        $this->checkConfigEntityExists($this->configurations[$configName]);
        $this->checkConfigMappingsExists($this->configurations[$configName]);

        $config = $this->configurations[$configName];
        /** @var class-string $entityClassName */
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

        $encoding = null;
        if (array_key_exists('encoding', $config) && $config['encoding']) {
            $options['encoding'] = $config['encoding'];
        }

        $subMappings = [];
        if (array_key_exists('sub_mappings', $config) && $config['sub_mappings']) {
            $subMappings = $config['sub_mappings'];
        }


        $hasValue = false;
        foreach ($mappings as $entityPropertyKey => $filePropertyKey) {
            if ($this->getValue($filePropertyKey, $row) !== '') {
                $hasValue = true;
            }
        }

        if (!$hasValue) {
            return;
        }


        $entity = null;
        if ($uniqueKey) {
            $entity = $repository->findOneBy([$uniqueKey => $this->getValue($mappings[$uniqueKey], $row)]);
        }

        if (!$entity && (!array_key_exists('only_update', $config) || !$config['only_update'])) {
            $entity = new $entityClassName();
        }

        if ($entity) {
            if ($subPropertyKey) {
                $this->propertyAccessor->setValue($previousEntity, $subPropertyKey, $entity);
            }

            foreach ($mappings as $entityPropertyKey => $filePropertyKey) {
                if ($value = $this->getValue($filePropertyKey, $row)) {
                    $this->propertyAccessor->setValue($entity, $entityPropertyKey, $value);
                }
            }

            $this->em->persist($entity);

            $importHelper?->completeData($entity, $row, $additionnalData);

            if (!$subPropertyKey) {
                $this->nbLinesImported++;
            }

            foreach ($subMappings as $subPropertyKey => $subConfigName) {
                $this->create($row, $subConfigName, $subPropertyKey, $entity, $additionnalData);
            }
        }
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
            return $this->helper->getHelper($config['import_helper_service']);
        }

        return null;
    }

    /**
     * @throws ImportException
     */
    public function getReader(array $config): ?ReaderInterface
    {
        if (array_key_exists('reader', $config) && $config['reader']) {
            return $this->reader->getReader($config['reader']);
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
