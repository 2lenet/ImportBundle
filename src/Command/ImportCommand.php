<?php

namespace Lle\ImportBundle\Command;

use Lle\ImportBundle\Service\ImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('lle:import:import')]
class ImportCommand extends Command
{
    public function __construct(
        protected ImportService $importService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED)
            ->addArgument('config', InputArgument::REQUIRED)
            ->addOption('delete-after-import', InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        $config = $input->getArgument('config');
        $deleteAfterImport = $input->getOption('delete-after-import');

        $result = $this->importService->import($path, $config, $deleteAfterImport);
        $output->writeln($result . ' items imported');

        return Command::SUCCESS;
    }
}
