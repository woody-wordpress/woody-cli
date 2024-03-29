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
 * Library
 *
 * @author Léo POIROUX <leo@raccourci.fr>
 * @copyright (c) 2019, Raccourci Agency
 * @package woody-cli
 */
class Library extends WoodyCommand
{
    protected $input;

    protected $output;

    /**
     * @var string
     */
    public const WP_LIBRARY_DIR = '/tmp/woody-library';

    /**
     * @var string
     */
    public const WP_LIBRARY_DIR_COPY_GIT = '/tmp/woody-library-git';

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('build:library')
            ->setDescription('Build de la version LITE de la Library')
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

        $this->consoleH1($this->output, 'Build de la version LITE de la Library');

        if (file_exists(self::WP_LIBRARY_DIR)) {
            $filesystem->remove(self::WP_LIBRARY_DIR);
        }

        $this->exec('git clone git@github.com:woody-wordpress-pro/woody-library.git ' . self::WP_LIBRARY_DIR);

        $tag = $input->getOption('tag');
        if ($tag != 'latest') {
            $this->execIn(self::WP_LIBRARY_DIR, 'git checkout tags/' . $tag);
        }

        if (file_exists(self::WP_LIBRARY_DIR_COPY_GIT)) {
            $this->consoleH2($this->output, 'Nettoyage woody-library (dossier GIT temporaire)');
            try {
                $filesystem->remove(self::WP_LIBRARY_DIR_COPY_GIT);
            } catch (IOExceptionInterface $ioException) {
                $this->consoleExec($this->output, "Erreur lors de la copie du répertoire " . $ioException->getPath());
            }
        }

        $this->consoleH2($this->output, 'Suppression du .git');
        try {
            $filesystem->remove(self::WP_LIBRARY_DIR . '/.git');
        } catch (IOExceptionInterface $ioException) {
            $this->consoleExec($this->output, "Erreur lors de la suppression du répertoire " . $ioException->getPath());
        }

        $this->consoleH2($this->output, 'Nettoyage du composer.json');
        $file = file_get_contents(self::WP_LIBRARY_DIR . '/composer.json');

        // Extract composer
        $composer = json_decode($file, true, 512, JSON_THROW_ON_ERROR);

        $this->consoleH2($this->output, 'Nettoyage du README.md');
        $file = file_get_contents(self::WP_LIBRARY_DIR . '/README.md');
        $file = str_replace('woody-wordpress-pro', 'woody-wordpress', $file);
        file_put_contents(self::WP_LIBRARY_DIR . '/README.md', $file);

        $this->consoleH2($this->output, 'Nettoyage du WoodyLibrary.php');
        $file = file_get_contents(self::WP_LIBRARY_DIR . '/WoodyLibrary.php');
        $file = str_replace('woody-wordpress-pro', 'woody-wordpress', $file);
        file_put_contents(self::WP_LIBRARY_DIR . '/WoodyLibrary.php', $file);

        $this->consoleH2($this->output, 'Suppression des templates PRO');
        $this->removeProTemplates();

        $this->consoleH2($this->output, 'Suppression de pro_vs_lite');
        $filesystem->remove(self::WP_LIBRARY_DIR . '/pro_vs_lite');

        $this->consoleH2($this->output, 'Initialisation repository GIT');
        $filesystem->mkdir(self::WP_LIBRARY_DIR_COPY_GIT);
        $this->execIn(self::WP_LIBRARY_DIR_COPY_GIT, 'git init');
        $this->execIn(self::WP_LIBRARY_DIR_COPY_GIT, 'git remote add origin git@github.com:woody-wordpress/woody-library.git');
        $this->execIn(self::WP_LIBRARY_DIR_COPY_GIT, 'git pull origin master');
        $this->execIn(self::WP_LIBRARY_DIR_COPY_GIT, 'git fetch --tags --prune origin');
        $this->execIn(self::WP_LIBRARY_DIR_COPY_GIT, 'git config --local user.email "support@woody-wordpress.com"');
        $this->execIn(self::WP_LIBRARY_DIR_COPY_GIT, 'git config --local user.name "Woody Wordpress"');

        $this->consoleH2($this->output, 'Ajout du repository GIT');
        $filesystem->mirror(self::WP_LIBRARY_DIR_COPY_GIT . '/.git', self::WP_LIBRARY_DIR . '/.git');

        $this->consoleH2($this->output, 'Suppression du repository GIT');
        $filesystem->remove(self::WP_LIBRARY_DIR_COPY_GIT);

        $this->consoleH2($this->output, 'Création du commit');
        try {
            $this->execIn(self::WP_LIBRARY_DIR, 'git add .');
            $this->execIn(self::WP_LIBRARY_DIR, 'git status');
            $this->execIn(self::WP_LIBRARY_DIR, sprintf('git commit -am"Version %s - Updated from Woody-Library PRO"', $composer['version']));
        } catch (\RuntimeException $runtimeException) {
            //$this->consoleExec($this->output, $e->getMessage());
        }

        try {
            $this->execIn(self::WP_LIBRARY_DIR, sprintf('git tag -d %s', $composer['version']));
            $this->execIn(self::WP_LIBRARY_DIR, sprintf('git push --delete origin %s', $composer['version']));
        } catch (\RuntimeException $runtimeException) {
            //$this->consoleExec($this->output, $e->getMessage());
        }

        try {
            $this->execIn(self::WP_LIBRARY_DIR, sprintf('git tag %s', $composer['version']));
        } catch (\RuntimeException $runtimeException) {
            //$this->consoleExec($this->output, $e->getMessage());
        }

        try {
            $this->execIn(self::WP_LIBRARY_DIR, 'git push --set-upstream origin master --tags');
        } catch (\RuntimeException $runtimeException) {
            //$this->consoleExec($this->output, $e->getMessage());
        }

        $this->consoleH2($this->output, 'Suppression du répertoire woody-library');
        $filesystem->remove(self::WP_LIBRARY_DIR);

        return WoodyCommand::SUCCESS;
    }

    private function removeProTemplates()
    {
        $finder = new Finder();
        $finder->files()->in(self::WP_LIBRARY_DIR)->name('conf.json')->followLinks();
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $pathinfo = pathinfo($file->getRelativePathname());
                $json = json_decode(file_get_contents($file->getRealPath()), true, 512, JSON_THROW_ON_ERROR);
                if (!empty($json['lib_type']) && $json['lib_type'] == 'pro') {
                    $this->consoleExec($this->output, sprintf('PRO : %s', $pathinfo['dirname']), 'red');
                    $dirname = str_replace('/' . $pathinfo['basename'], '', $file->getRealPath());
                    $this->rmdir($dirname);
                } else {
                    $this->consoleExec($this->output, sprintf('LITE : %s', $pathinfo['dirname']));
                }
            }
        }
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
