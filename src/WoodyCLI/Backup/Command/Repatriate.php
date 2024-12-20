<?php

namespace WoodyCLI\Backup\Command;

use WoodyCLI\WoodyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * Repatriate
 *
 * @author Léo POIROUX <leo@raccourci.fr>
 * @copyright (c) 2021, Raccourci Agency
 * @package woody-cli
 */
class Repatriate extends WoodyCommand
{
    protected $version;

    protected $input;

    protected $output;

    protected $path;

    protected $from;

    protected $version_path;

    protected $latest_path;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('repatriate:site')
            ->setDescription("Rapatriement d'un site vers un serveur de dev/preprod")
            // Options
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Chemin de la sauvegarde')
            ->addOption('backup-now', 'b', InputOption::VALUE_REQUIRED, 'Faire une sauvegarde maintenant', false)
            ->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Site Key')
            ->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'From (ex: admin@server)')
            ->addOption('to', 't', InputOption::VALUE_REQUIRED, 'From (ex: admin@server)');
    }

    /**
     * {inhertidoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->setEnv($input->getOption('env'));
        $this->setSiteKey($input->getOption('site'));
        $this->version = $input->getOption('timestamp');

        // roadmap
        // 1 - faire le backup du site du serveur source (si demandé) => run woody:backup
        // 2 - faire le backup du site du serveur cible (optionel mais rassurant) => run woody:backup
        // 3 - rapatrier le dernier backup du serveur source => run woody:transfer
        // 4 - supprimer les tables de la BDD du serveur cible
        // 5 - importer le dump source dans la BDD cible
        // 6 - idem pour les images ???

        return WoodyCommand::SUCCESS;
    }
}
