<?php

namespace WoodyCLI\Build\Command;

use WoodyCLI\WoodyCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * Core
 *
 * @author Léo POIROUX <leo@raccourci.fr>
 * @copyright (c) 2019, Raccourci Agency
 * @package woody-cli
 */
class Core extends WoodyCommand
{
    protected $input;

    protected $output;

    /**
     * @var string
     */
    public const WP_CORE_DIR = '/tmp/woody-core';

    /**
     * @var string
     */
    public const WP_CORE_DIR_COPY_GIT = '/tmp/woody-core-git';

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('build:core')
            ->setDescription('Build de la version LITE du Core')
            ->addOption('tag', 't', InputOption::VALUE_OPTIONAL, 'Tag Version', 'latest');
    }

    /**
     * {inhertidoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $filesystem = new Filesystem();

        $this->consoleH1($this->output, 'Build de la version LITE du core');

        if (file_exists(self::WP_CORE_DIR)) {
            $filesystem->remove(self::WP_CORE_DIR);
        }

        $this->exec('git clone git@github.com:woody-wordpress-pro/woody-core.git ' . self::WP_CORE_DIR);

        $tag = $input->getOption('tag');
        if ($tag != 'latest') {
            $this->execIn(self::WP_CORE_DIR, 'git checkout tags/' . $tag);
        }

        if (file_exists(self::WP_CORE_DIR_COPY_GIT)) {
            $this->consoleH2($this->output, 'Nettoyage woody-core (dossier GIT temporaire)');
            try {
                $filesystem->remove(self::WP_CORE_DIR_COPY_GIT);
            } catch (IOExceptionInterface $ioException) {
                $this->consoleExec($this->output, "Erreur lors de la copie du répertoire " . $ioException->getPath());
            }
        }

        $this->consoleH2($this->output, 'Suppression du .git');
        try {
            $filesystem->remove(self::WP_CORE_DIR . '/.git');
        } catch (IOExceptionInterface $ioException) {
            $this->consoleExec($this->output, "Erreur lors de la suppression du répertoire " . $ioException->getPath());
        }

        $this->consoleH2($this->output, 'Nettoyage du composer.json');
        $file = file_get_contents(self::WP_CORE_DIR . '/composer.json');

        // Extract composer
        $composer = json_decode($file, true, 512, JSON_THROW_ON_ERROR);

        // Remove repositories
        $composer['repositories'] = [];
        $composer['repositories'][] = [
            'type' => 'composer',
            'url' => 'https://wpackagist.org',
        ];

        unset($composer['config']['github-oauth']);
        unset($composer['extra']['patches']);
        unset($composer['scripts']['pre-update-cmd']);

        $composer['require']['woody-wordpress/woody-library'] = $composer['require']['woody-wordpress-pro/woody-library'];
        $composer['require']['woody-wordpress/woody-plugin'] = $composer['require']['woody-wordpress-pro/woody-plugin'];

        foreach ($composer['require'] as $key => $val) {
            if (strpos($key, 'woody-wordpress-pro') !== false) {
                unset($composer['require'][$key]);
            }
        }

        unset($composer['require']['woody-wordpress/woody-sso']);

        $file = json_encode($composer, JSON_PRETTY_PRINT);
        $file = str_replace('\/', '/', $file);
        $file = str_replace('\u00e9', 'é', $file);
        file_put_contents(self::WP_CORE_DIR . '/composer.json', $file);

        $filesystem->remove(self::WP_CORE_DIR . '/composer.lock');

        $this->consoleH2($this->output, 'Nettoyage du README.md');
        $file = file_get_contents(self::WP_CORE_DIR . '/README.md');
        $file = str_replace('woody-wordpress-pro', 'woody-wordpress', $file);
        file_put_contents(self::WP_CORE_DIR . '/README.md', $file);

        $this->consoleH2($this->output, 'Initialisation repository GIT');
        $filesystem->mkdir(self::WP_CORE_DIR_COPY_GIT);
        $this->execIn(self::WP_CORE_DIR_COPY_GIT, 'git init');
        $this->execIn(self::WP_CORE_DIR_COPY_GIT, 'git remote add origin git@github.com:woody-wordpress/woody-core.git');
        $this->execIn(self::WP_CORE_DIR_COPY_GIT, 'git pull origin master');
        $this->execIn(self::WP_CORE_DIR_COPY_GIT, 'git fetch --tags --prune origin');
        $this->execIn(self::WP_CORE_DIR_COPY_GIT, 'git config --local user.email "support@woody-wordpress.com"');
        $this->execIn(self::WP_CORE_DIR_COPY_GIT, 'git config --local user.name "Woody Wordpress"');

        $this->consoleH2($this->output, 'Ajout du repository GIT');
        $filesystem->mirror(self::WP_CORE_DIR_COPY_GIT . '/.git', self::WP_CORE_DIR . '/.git');

        $this->consoleH2($this->output, 'Suppression du repository GIT');
        $filesystem->remove(self::WP_CORE_DIR_COPY_GIT);

        $this->consoleH2($this->output, 'Création du commit');
        try {
            $this->execIn(self::WP_CORE_DIR, 'git add .');
            $this->execIn(self::WP_CORE_DIR, 'git status');
            $this->execIn(self::WP_CORE_DIR, sprintf('git commit -am"Version %s - Updated from Woody-Core PRO"', $composer['version']));
        } catch (\RuntimeException $runtimeException) {
            //$this->consoleExec($this->output, $e->getMessage());
        }

        try {
            $this->execIn(self::WP_CORE_DIR, sprintf('git tag -d %s', $composer['version']));
            $this->execIn(self::WP_CORE_DIR, sprintf('git push --delete origin %s', $composer['version']));
        } catch (\RuntimeException $runtimeException) {
            //$this->consoleExec($this->output, $e->getMessage());
        }

        try {
            $this->execIn(self::WP_CORE_DIR, sprintf('git tag %s', $composer['version']));
        } catch (\RuntimeException $runtimeException) {
            //$this->consoleExec($this->output, $e->getMessage());
        }

        try {
            $this->execIn(self::WP_CORE_DIR, 'git push --set-upstream origin master --tags');
        } catch (\RuntimeException $runtimeException) {
            //$this->consoleExec($this->output, $e->getMessage());
        }

        $this->consoleH2($this->output, 'Suppression du répertoire woody-core');
        $filesystem->remove(self::WP_CORE_DIR);

        return WoodyCommand::SUCCESS;
    }

    private function rmdir($dir, $inside_only = true)
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->rmdir($dir . DIRECTORY_SEPARATOR . $item, false)) {
                return false;
            }
        }

        if ($inside_only) {
            return true;
        }

        return rmdir($dir);
    }
}
