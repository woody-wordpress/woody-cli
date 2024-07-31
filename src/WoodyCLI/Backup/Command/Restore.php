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
 * Restore
 *
 * @author Léo POIROUX <leo@raccourci.fr>
 * @copyright (c) 2021, Raccourci Agency
 * @package woody-cli
 */
class Restore extends WoodyCommand
{
    protected $input;

    protected $output;

    protected $is_exist;

    protected $is_cloned;

    protected $version;

    protected $path;

    protected $version_path;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('restore:site')
            ->setDescription("Restaurer un site à partir d'un backup")
            // Options
            ->addOption('options', 'o', InputOption::VALUE_OPTIONAL, 'Options (no-thumbs)')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Chemin de la sauvegarde')
            ->addOption('timestamp', 't', InputOption::VALUE_REQUIRED, 'Timestamp version', 'latest')
            ->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Site Key')
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL, 'Environnement', 'dev');
    }

    /**
     * {inhertidoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $options = $input->getOption('options');
        $options = explode(',', $options);

        $this->setEnv($input->getOption('env'));
        $this->setSiteKey($input->getOption('site'));
        $this->version = $input->getOption('timestamp');

        // Backup path
        $path = $input->getOption('path');
        if (empty($path)) {
            $this->consoleH2($this->output, 'Le chemin de sauvegarde est non spécifié');
            exit();
        } else {
            $this->path = $path . '/' . $this->site_key;
            $this->version_path = $this->path . '/' . $this->version;
            if (!$this->fs->exists($this->version_path)) {
                $this->consoleH2($this->output, "Le chemin de sauvegarde n'existe pas");
            }
        }

        // Is Install
        $this->is_exist = $this->fs->exists(sprintf($this->paths['WP_SITE_DIR'], $this->core_key, $this->site_key) . '/style.css');

        if ($this->is_exist) {
            $this->restore_ungzip();
            $this->woody_maintenance_on();
            $this->restore_bdd();
            $this->restore_uploads($options);
            $this->woody_maintenance_off();
            $this->restore_end();
        } else {
            $this->consoleH2($this->output, sprintf('Le projet "%s" n\'a jamais été déployé', $this->site_key));
        }

        return WoodyCommand::SUCCESS;
    }

    private function restore_ungzip()
    {
        $dump_zip = null;
        $this->consoleH2($this->output, 'Décompression du backup');
        foreach (glob($this->version_path . "/*.tar.gz") as $filename) {
            $dump_zip = $filename;
            break;
        }

        $cmd = sprintf('tar xvzf %s', $dump_zip);
        $this->consoleExec($this->output, $cmd);
        $this->execIn($this->version_path, $cmd);
    }

    private function restore_uploads($options = [])
    {
        $this->consoleH2($this->output, 'Restauration des images');
        if (!file_exists($this->version_path . '/' . $this->site_key)) {
            $this->consoleExec($this->output, 'Opération annulée : répertoire inexistant');
        } else {
            $cmd = sprintf("rsync --ignore-existing --del -avzO %s %s", $this->version_path . '/' . $this->site_key, sprintf($this->paths['WP_SITE_UPLOADS_DIR'], $this->core_key, ''));
            $this->consoleExec($this->output, $cmd);
            $this->exec($cmd);

            if ($this->site_key == 'woody-sandbox' || in_array('no-thumbs', $options)) {
                $this->consoleH2($this->output, 'Nettoyage des images');
                $cmd = 'woody:reset_crops --force';
                $this->consoleExec($this->output, $cmd);
                $this->wp($cmd);
            }
        }
    }

    private function restore_bdd()
    {
        $dump_sql = null;
        $this->consoleH2($this->output, 'Restauration de la BDD');
        foreach (glob($this->version_path . "/*.sql") as $filename) {
            $dump_sql = $filename;
            break;
        }

        if (!file_exists($dump_sql)) {
            $this->consoleExec($this->output, 'Opération annulée : répertoire inexistant');
        } else {
            $cmd = 'db reset --yes';
            $this->consoleExec($this->output, $cmd);
            $this->wp($cmd);

            $cmd = sprintf('db import %s', $dump_sql);
            $this->consoleExec($this->output, $cmd);
            $this->wp($cmd);

            $cmd = sprintf('woody deploy:site -s %s -e %s', $this->site_key, $this->env);
            $this->consoleExec($this->output, $cmd);
            $this->execIn($this->version_path, $cmd);

            if ($this->env == 'dev') {
                $cmd = 'cache flush';
                $this->consoleExec($this->output, $cmd);
                $this->wp($cmd);
            }
        }
    }

    private function restore_end()
    {
        $dump_sql = null;
        $this->consoleH2($this->output, 'Finalisation');

        $cmd = sprintf('rm -rf %s', $this->site_key);
        $this->consoleExec($this->output, $cmd);
        $this->execIn($this->version_path, $cmd);

        foreach (glob($this->version_path . "/*.sql") as $filename) {
            $dump_sql = $filename;
            break;
        }

        if (file_exists($dump_sql)) {
            $cmd = sprintf('rm -rf %s', $dump_sql);
            $this->consoleExec($this->output, $cmd);
            $this->execIn($this->version_path, $cmd);
        }
    }
}
