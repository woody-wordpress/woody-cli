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
 * Backup
 *
 * @author Léo POIROUX <leo@raccourci.fr>
 * @copyright (c) 2017, Raccourci Agency
 * @package woody-cli
 */
class Restore extends WoodyCommand
{
    protected $input;
    protected $output;
    protected $is_exist;
    protected $is_install;
    protected $is_cloned;
    protected $site_config;
    protected $version;
    protected $site_key_version;
    protected $path;
    protected $release_path;
    protected $latest_path;

    /**
     * {inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('restore:site')
            ->setDescription('Restaurer un site à partir d\'un backup')
            // Options
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Chemin de la sauvegarde')
            ->addOption('timestamp', 't', InputOption::VALUE_REQUIRED, 'Timestamp version', 'latest')
            ->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Site Key')
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL, 'Environnement', 'dev');
    }

    /**
     * {inhertidoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->setEnv($input->getOption('env'));
        $this->setSiteKey($input->getOption('site'));
        $this->site_config = $this->getSiteConfiguration();
        $this->version = $input->getOption('timestamp');
        $this->site_key_version = $this->site_key . '.' . $this->version;

        // Backup path
        $path = $input->getOption('path');
        if (empty($path)) {
            $this->consoleH2($this->output, 'Le chemin de sauvegarde est non spécifié');
            exit();
        } else {
            $this->path = $path . '/' . $this->site_key;
            $this->version_path = $this->path . '/' . $this->version;
            if (!$this->fs->exists($this->version_path)) {
                $this->consoleH2($this->output, 'Le chemin de sauvegarde n\'existe pas');
            }
        }

        // Is Install
        $this->is_exist = $this->fs->exists(sprintf(self::WP_SITE_DIR, $this->site_key));
        $this->is_cloned = $this->fs->exists(sprintf(self::WP_SITE_DIR, $this->site_key) . '/style.css');

        if ($this->is_exist && $this->is_cloned) {
            $this->restore_uploads();
            $this->restore_bdd();
        } else {
            $this->consoleH2($this->output, sprintf('Le projet "%s" n\'a jamais été déployé', $this->site_key));
        }

        return WoodyCommand::SUCCESS;
    }

    private function restore_uploads()
    {
        $this->consoleH2($this->output, 'Restauration des images');
        $cmd = sprintf("rsync --ignore-existing --del -avzO %s %s", $this->version_path . '/' . $this->site_key, sprintf(self::WP_SITE_UPLOADS_DIR, ''));
        $this->consoleExec($this->output, $cmd);
        $this->exec($cmd);
    }

    private function restore_bdd()
    {
        $this->consoleH2($this->output, 'Restauration de la BDD');
        foreach (glob($this->version_path . "/*.sql") as $filename) {
            $dump_sql = $filename;
            break;
        }

        $cmd = 'db reset --yes';
        $this->consoleExec($this->output, $cmd);
        $this->wp($cmd);

        $cmd = sprintf('db import %s', $dump_sql);
        $this->consoleExec($this->output, $cmd);
        $this->wp($cmd);

        $cmd = 'cli cache clear';
        $this->consoleExec($this->output, $cmd);
        $this->wp($cmd);
    }
}
