<?php

namespace ClickAndMortar\ImportBundle\Command;

use ClickAndMortar\ImportBundle\ImportHelper\ImportHelperInterface;
use ClickAndMortar\ImportBundle\Reader\AbstractReader;
use ClickAndMortar\ImportBundle\Reader\Readers\CsvReader;
use ClickAndMortar\ImportBundle\Service\ImportService;

use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ImportCommand
 *
 * @package ClickAndMortar\ImportBundle\Command
 */
class ImportCommand extends ContainerAwareCommand
{
    /**
     * Import helper
     *
     * @var ImportHelperInterface
     */
    protected $importHelper = null;
    protected $importService = null;

    public function __construct(ImportService $importService, $entities)
    {
      $this->importService = $importService;
      $this->entities = $entities;
      parent::__construct();
    }
    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('app:import')
             ->setDescription('Import file to create entities')
             ->addArgument('path', InputArgument::REQUIRED, 'File path (eg. "/home/user/my-data.csv")')
             ->addArgument(
                 'entity',
                 InputArgument::REQUIRED,
                 'Entity name used in your configuration file under click_and_mortar_import.entities node (eg. "customer")'
             )
             ->addOption('delete-after-import', 'd', InputOption::VALUE_NONE, 'To delete file after import');
    }

    /**
     * Check arguments
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        parent::interact($input, $output);

        // Check path argument
        $container = $this->getContainer();
        $path      = $input->getArgument('path');
        if (file_exists($path) && is_readable($path)) {
            // Get reader by extension
            $fileExtension          = pathinfo($path, PATHINFO_EXTENSION);
            $fileExtensionFormatted = strtolower($fileExtension);

        } else {
            $errorMessage = sprintf(
                'File %s does not exist',
                $path
            );

            throw new InvalidArgumentException($errorMessage);
        }

        // Check entity argument
        $entity   = $input->getArgument('entity');
        $entities = $container->getParameter('entities');
        if (!array_key_exists($entity, $entities)) {
            $errorMessage = sprintf(
                '%s entity short name does not exist in your configuration file',
                $entity
            );

            throw new InvalidArgumentException($errorMessage);
        }

        // Check for import helper if necessary
        $importHelperService = isset($entities[$entity]['import_helper_service']) ? $entities[$entity]['import_helper_service'] : null;
        if (!is_null($importHelperService)) {
            if ($container->has($importHelperService)) {
                $this->importHelper = $container->get($importHelperService);
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
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $importService      = $this->importService;
        $path                = $input->getArgument('path');
        $entity              = $input->getArgument('entity');
        $errors              = array();

        $errors = $this->importService->import($path, $entity, $input->getOption('delete-after-import'));

        $count = $this->importService->getCount();
        $output->writeln('Nb lines:'.$count['lines']);
        $output->writeln('Items:'.$count['entities']);
        // Print errors if necessary
        foreach ($errors as $error) {
            $output->writeln(sprintf('<error>%s</error>', $error));
        }
    }
}
