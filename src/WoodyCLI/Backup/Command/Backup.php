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
 * @copyright (c) 2021, Raccourci Agency
 * @package woody-cli
 */
class Backup extends WoodyCommand
{
    protected $input;

    protected $output;

    protected $is_exist;

    protected $is_install;

    protected $is_cloned;

    protected $version;

    protected $site_key_version;

    protected $path;

    protected $release_path;

    protected $latest_path;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('backup:site')
            ->setDescription('Sauvegarde un site')
            // Options
            ->addOption('options', 'o', InputOption::VALUE_OPTIONAL, 'Options (no-upload,no-bdd,no-thumbs)')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Chemin de la sauvegarde')
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
        $this->site_config = $this->sites[$this->site_key];
        $this->setCoreKey($this->site_config['core']['key']);

        $this->version = time();
        $this->site_key_version = $this->site_key . '.' . $this->version;

        // Backup path
        $path = $input->getOption('path');
        if (empty($path)) {
            $this->consoleH2($this->output, 'Le chemin de sauvegarde est non spécifié');
            exit();
        } else {
            $this->path = $path . '/' . $this->site_key;
            $this->release_path = $this->path . '/' . $this->version;
            $this->latest_path = $this->path . '/latest';
        }

        // Is Install
        $this->is_exist = $this->fs->exists(sprintf($this->paths['WP_SITE_DIR'], $this->core_key, $this->site_key) . '/style.css');
        $this->is_install = $this->fs->exists(sprintf($this->paths['WP_SITE_UPLOADS_DIR'] . '/woody-cli.lock', $this->core_key, $this->site_key));

        if ($this->is_exist && $this->is_install) {
            $this->woody_maintenance_on();
            $this->backup_init();

            // Disable backup Upload
            if (!in_array('no-upload', $options)) {
                $this->backup_uploads($options);
            }

            // Disable backup BDD
            if (!in_array('no-bdd', $options)) {
                $this->backup_bdd();
            }

            $this->woody_maintenance_off();
            $this->backup_gzip();
            $this->backup_end();
        } else {
            $this->consoleH2($this->output, sprintf('Le projet "%s" n\'a jamais été déployé', $this->site_key));
        }

        return WoodyCommand::SUCCESS;
    }

    private function backup_init()
    {
        if (!$this->fs->exists($this->release_path)) {
            $this->consoleH2($this->output, "Création du répertoire du sauvegarde");
            $cmd = sprintf("mkdir -p %s", $this->release_path);
            $this->consoleExec($this->output, $cmd);
            $this->exec($cmd);
        }
    }

    private function backup_uploads($options = [])
    {
        // Disable backup Upload
        if ($this->site_key == 'woody-sandbox') {
            $this->consoleH2($this->output, 'Nettoyage des images');
            $cmd = 'woody:reset_crops --force';
            $this->consoleExec($this->output, $cmd);
            $this->wp($cmd);
        }

        $this->consoleH2($this->output, 'Sauvegarde des images');
        if (in_array('no-thumbs', $options)) {
            $cmd = sprintf("rsync -avz --exclude=thumbs %s %s", sprintf($this->paths['WP_SITE_UPLOADS_DIR'], $this->core_key, $this->site_key), $this->release_path);
        } else {
            $cmd = sprintf("rsync -avz %s %s", sprintf($this->paths['WP_SITE_UPLOADS_DIR'], $this->core_key, $this->site_key), $this->release_path);
        }

        $this->consoleExec($this->output, $cmd);
        $this->exec($cmd);
    }

    private function backup_bdd()
    {
        $this->consoleH2($this->output, 'Sauvegarde de la BDD');
        $cmd = sprintf('db export %s/%s.sql', $this->release_path, $this->site_key_version);
        $this->consoleExec($this->output, $cmd);
        $this->wp($cmd);
    }

    private function backup_gzip()
    {
        $this->consoleH2($this->output, 'Compression du backup');
        $files = [];
        if (file_exists($this->release_path . '/' . $this->site_key)) {
            $files[] = $this->site_key;
        }

        if (file_exists($this->release_path . '/' . $this->site_key_version . '.sql')) {
            $files[] = $this->site_key_version . '.sql';
        }

        if (!empty($files)) {
            $cmd = sprintf('tar zcvf %s.tar.gz %s', $this->site_key_version, implode(' ', $files));
            $this->consoleExec($this->output, $cmd);
            $this->execIn($this->release_path, $cmd);
        }

        foreach ($files as $file) {
            $cmd = sprintf('rm -rf %s', $file);
            $this->consoleExec($this->output, $cmd);
            $this->execIn($this->release_path, $cmd);
        }
    }

    private function backup_end()
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
