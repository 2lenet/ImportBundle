<?php

namespace ClickAndMortar\ImportBundle\Service;

use ClickAndMortar\ImportBundle\ImportHelper\ImportHelperInterface;
use ClickAndMortar\ImportBundle\Reader\AbstractReader;
use ClickAndMortar\ImportBundle\Reader\Readers\CsvReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Class ImportService
 *
 */
class ImportService
{
    /**
     * Persist 30 per 30 entities in same time
     *
     * @var integer
     */
    const CHUNK_SIZE = 30;

    /**
     * @var AbstractReader
     */
    protected $reader = null;

    /**
     * Import helper
     *
     * @var ImportHelperInterface
     */
    protected $importHelper = null;

    protected $entities = null;

    protected $counts = [];

    protected $em = null;

    /**
     * @var ContainerInterface
     */
    private $container;
    
    public function __construct(CsvReader $reader, EntityManagerInterface $em, $entities, ContainerInterface $container)
    {
      $this->reader = $reader;
      $this->entities = $entities;
      $this->em = $em;
      $this->counts = [];
      $this->container = $container;
    }


    public function check($entity)
    {
        $importHelperService = isset($this->entities[$entity]['import_helper_service']) ? $this->entities[$entity]['import_helper_service'] : null;
        if (!is_null($importHelperService)) {
            if ($this->container->has($importHelperService)) {
                $this->importHelper =  $this->container->get($importHelperService);
            } else {
                $errorMessage = sprintf(
                    '%s import helper does not exist',
                    $entity
                );

                throw new InvalidArgumentException($errorMessage);
            }
        }
    }


    /**
     * Execute import
     *
     *
     * @return int|null|void
     */
    public function import($path, $entity, $delete_after_import=false)
    {
        $entities            = $this->entities;
        $entityConfiguration = $entities[$entity];
        $errors              = array();
        $this->check($entity);

        /** @var EntityManager $entityManager */
        $entityManager   = $this->em;
        $repository      = $entityManager->getRepository($entityConfiguration['repository']);
        $uniqueKey       = isset($entityConfiguration['unique_key']) ? $entityConfiguration['unique_key']: null;
        $mapping         = $entityConfiguration['mappings'];
        $entityClassname = $entityConfiguration['model'];
        $onlyUpdate      = $entityConfiguration['only_update'];
        $truncate        = isset($entityConfiguration['truncate']) ?  $entityConfiguration['truncate']: 0;
        
        if ($truncate) {
            $repository->truncateTable();
            $this->em->flush();
        }
        // Read file
        $rows     = $this->reader->read($path);
        $size     = count($rows);
        $index    = 0;
        $nb    = 0;

        // Create each entity
        foreach ($rows as $row) {
            if ($uniqueKey) {
                $criteria = array(
                    $uniqueKey => trim($row[$mapping[$uniqueKey]]),
                );
                $entity   = $repository->findOneBy($criteria);
            } else {
                $entity = null;
            }
            if (is_null($entity) && $onlyUpdate === false) {
                $entity = new $entityClassname();
            }

            if (!is_null($entity)) {
                // Set fields
                foreach ($mapping as $entityPropertyKey => $filePropertyKey) {
                    $setter = sprintf(
                        'set%s',
                        ucfirst($entityPropertyKey)
                    );
                    $entity->{$setter}(trim($row[$filePropertyKey]));
                }

                // Complete data if necessary
                if (!is_null($this->importHelper)) {
                    $this->importHelper->completeData($entity, $row, $errors);
                }
                if (!is_null($entity)) {
                    $entityManager->persist($entity);
                    $nb++;
                }
            }

            // Persist if necessary
            if (($index % self::CHUNK_SIZE) === 0) {
                $entityManager->flush();
                $entityManager->clear();
            }
            $index++;
        }
        $this->count = ['lines' => $index, 'entities' => $nb];
        $entityManager->flush();
        $entityManager->clear();
        if ($delete_after_import == true) {
            unlink($path);
        }
        return $errors;
    }

    public function getCount()
    {
        return $this->count;
    }
}