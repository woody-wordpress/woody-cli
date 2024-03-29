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
 * Transfer
 *
 * @author Léo POIROUX <leo@raccourci.fr>
 * @copyright (c) 2021, Raccourci Agency
 * @package woody-cli
 */
class Transfer extends WoodyCommand
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
            ->setName('transfer:site')
            ->setDescription("Transfert d'un backup de site")
            // Options
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Chemin de la sauvegarde')
            ->addOption('timestamp', 't', InputOption::VALUE_REQUIRED, 'Timestamp version', 'latest')
            ->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Site Key')
            ->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'From (ex: admin@server)')
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL, 'Environnement', 'dev');
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

        // From
        $from = $input->getOption('from');
        if (empty($from)) {
            $this->consoleH2($this->output, 'Le serveur source est non spécifié');
            exit();
        } else {
            $this->from = $from;
        }

        // Backup path
        $path = $input->getOption('path');
        if (empty($path)) {
            $this->consoleH2($this->output, 'Le chemin de sauvegarde est non spécifié');
            exit();
        } else {
            $this->path = $path . '/' . $this->site_key;
            $this->latest_path = $this->path . '/latest';

            // Get Real Version
            if ($this->version == 'latest') {
                $this->consoleH2($this->output, 'Trouver la véritable version');
                $cmd = sprintf("ssh %s '%s'", $this->from, 'readlink -f ' . $this->latest_path);
                $this->consoleExec($this->output, $cmd);
                $version = $this->exec($cmd);
                $version = explode('/', $version);
                $version = end($version);
                $this->version = $version;
            }

            $this->version_path = $this->path . '/' . $this->version;
        }

        $this->transfer_init();
        $this->transfer_rsync();
        $this->transfer_end();

        return WoodyCommand::SUCCESS;
    }

    private function transfer_init()
    {
        if (!$this->fs->exists($this->version_path)) {
            $this->consoleH2($this->output, "Création du répertoire du sauvegarde");
            $cmd = sprintf("mkdir -p %s", $this->version_path);
            $this->consoleExec($this->output, $cmd);
            $this->exec($cmd);
        }
    }

    private function transfer_rsync()
    {
        $this->consoleH2($this->output, 'Transfer du backup');
        $cmd = sprintf("rsync --progress -ave ssh %s:%s/ %s/", $this->from, $this->version_path, $this->version_path);
        $this->consoleExec($this->output, $cmd);
        $this->exec($cmd, 3600);
    }

    private function transfer_end()
    {
        $this->consoleH2($this->output, 'Finalisation');

        // Remove latest
        $cmd = sprintf('rm -f %s', $this->latest_path);
        $this->consoleExec($this->output, $cmd);
        $this->exec($cmd);

        // Remove old releases
        $releases = [];
        foreach (glob($this->path . "/*", GLOB_ONLYDIR) as $filename) {
            $releases[$filename] = $filename;
        }

        if (!empty($releases)) {
            krsort($releases);
            $keep_releases = array_slice($releases, 0, 3);
            $del_releases = array_diff($releases, $keep_releases);
            foreach ($del_releases as $del_release) {
                $cmd = sprintf('rm -rf %s', $del_release);
                $this->consoleExec($this->output, $cmd);
                $this->exec($cmd);
            }
        }

        // Create symlink release > latest
        $cmd = sprintf('ln -s %s %s', $this->version, 'latest');
        $this->consoleExec($this->output, $cmd);
        $this->execIn($this->path, $cmd);
    }
}
