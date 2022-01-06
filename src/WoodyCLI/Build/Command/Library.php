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
class Library extends WoodyCommand
{
    protected $input;
    protected $output;

    public const WP_LIBRARY_DIR = WP_VENDOR_DIR . '/woody-wordpress-pro/woody-library';
    public const WP_LIBRARY_DIR_COPY = WP_VENDOR_DIR . '/woody-wordpress/woody-library';
    public const WP_LIBRARY_DIR_COPY_GIT = WP_VENDOR_DIR . '/woody-wordpress/woody-library-git';

    /**
     * {inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('build:library')
            ->setDescription('Build de la version LITE de la library');
    }

    /**
     * {inhertidoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $fs = new Filesystem();

        $this->consoleH1($this->output, 'Build de la version LITE de la library');

        if (file_exists(self::WP_LIBRARY_DIR)) {
            if (file_exists(self::WP_LIBRARY_DIR_COPY)) {
                $this->consoleH2($this->output, sprintf('Nettoyage woody-library'));
                try {
                    $fs->remove(self::WP_LIBRARY_DIR_COPY);
                } catch (IOExceptionInterface $exception) {
                    $this->consoleExec($this->output, "Erreur lors de la copie du répertoire " . $exception->getPath());
                }
            }

            if (file_exists(self::WP_LIBRARY_DIR_COPY_GIT)) {
                $this->consoleH2($this->output, sprintf('Nettoyage woody-library (dossier GIT temporaire)'));
                try {
                    $fs->remove(self::WP_LIBRARY_DIR_COPY_GIT);
                } catch (IOExceptionInterface $exception) {
                    $this->consoleExec($this->output, "Erreur lors de la copie du répertoire " . $exception->getPath());
                }
            }

            $this->consoleH2($this->output, sprintf('Copie du répertoire woody-library'));
            try {
                $fs->mirror(self::WP_LIBRARY_DIR, self::WP_LIBRARY_DIR_COPY);
            } catch (IOExceptionInterface $exception) {
                $this->consoleExec($this->output, "Erreur lors de la copie du répertoire " . $exception->getPath());
            }

            $this->consoleH2($this->output, sprintf('Suppression du .git'));
            try {
                $fs->remove(self::WP_LIBRARY_DIR_COPY . '/.git');
            } catch (IOExceptionInterface $exception) {
                $this->consoleExec($this->output, "Erreur lors de la suppression du répertoire " . $exception->getPath());
            }

            $this->consoleH2($this->output, sprintf('Suppression du répertoire pro_vs_lite'));
            try {
                $fs->remove(self::WP_LIBRARY_DIR_COPY . '/pro_vs_lite');
            } catch (IOExceptionInterface $exception) {
                $this->consoleExec($this->output, "Erreur lors de la suppression du répertoire " . $exception->getPath());
            }

            $this->consoleH2($this->output, sprintf('Nettoyage du composer.json'));
            $file = file_get_contents(self::WP_LIBRARY_DIR_COPY . '/composer.json');
            $file = str_replace('woody-wordpress-pro', 'woody-wordpress', $file);
            file_put_contents(self::WP_LIBRARY_DIR_COPY . '/composer.json', $file);

            // Extract composer
            $composer = json_decode($file, true);

            $this->consoleH2($this->output, sprintf('Nettoyage du README.md'));
            $file = file_get_contents(self::WP_LIBRARY_DIR_COPY . '/README.md');
            $file = str_replace('woody-wordpress-pro', 'woody-wordpress', $file);
            file_put_contents(self::WP_LIBRARY_DIR_COPY . '/README.md', $file);

            $this->consoleH2($this->output, sprintf('Nettoyage du woody-library.php'));
            $file = file_get_contents(self::WP_LIBRARY_DIR_COPY . '/woody-library.php');
            $file = str_replace('woody-wordpress-pro', 'woody-wordpress', $file);
            file_put_contents(self::WP_LIBRARY_DIR_COPY . '/woody-library.php', $file);

            $this->consoleH2($this->output, sprintf('Suppression des templates PRO'));
            $this->removeProTemplates();

            $this->consoleH2($this->output, sprintf('Initialisation repository GIT'));
            $fs->mkdir(self::WP_LIBRARY_DIR_COPY_GIT);
            $this->execIn(self::WP_LIBRARY_DIR_COPY_GIT, 'git init');
            $this->execIn(self::WP_LIBRARY_DIR_COPY_GIT, 'git remote add origin git@github.com:woody-wordpress/woody-library.git');
            $this->execIn(self::WP_LIBRARY_DIR_COPY_GIT, 'git pull origin master');
            $this->execIn(self::WP_LIBRARY_DIR_COPY_GIT, 'git fetch --tags --prune origin');
            $this->execIn(self::WP_LIBRARY_DIR_COPY_GIT, 'git config --local user.email "support@woody-wordpress.com"');
            $this->execIn(self::WP_LIBRARY_DIR_COPY_GIT, 'git config --local user.name "Woody Wordpress"');

            $this->consoleH2($this->output, sprintf('Ajout du repository GIT'));
            $fs->mirror(self::WP_LIBRARY_DIR_COPY_GIT . '/.git', self::WP_LIBRARY_DIR_COPY . '/.git');

            $this->consoleH2($this->output, sprintf('Suppression du repository GIT'));
            $fs->remove(self::WP_LIBRARY_DIR_COPY_GIT);

            $this->consoleH2($this->output, sprintf('Création du commit'));
            try {
                $this->execIn(self::WP_LIBRARY_DIR_COPY, 'git add .');
                $this->execIn(self::WP_LIBRARY_DIR_COPY, 'git status');
                $this->execIn(self::WP_LIBRARY_DIR_COPY, sprintf('git commit -am"Version %s - Updated from Woody-Library PRO"', $composer['version']));
            } catch (\RuntimeException $e) {
                //$this->consoleExec($this->output, $e->getMessage());
            }

            try {
                $this->execIn(self::WP_LIBRARY_DIR_COPY, sprintf('git tag -d %s', $composer['version']));
                $this->execIn(self::WP_LIBRARY_DIR_COPY, sprintf('git push --delete origin %s', $composer['version']));
            } catch (\RuntimeException $e) {
                //$this->consoleExec($this->output, $e->getMessage());
            }

            try {
                $this->execIn(self::WP_LIBRARY_DIR_COPY, sprintf('git tag %s', $composer['version']));
            } catch (\RuntimeException $e) {
                //$this->consoleExec($this->output, $e->getMessage());
            }

            try {
                $this->execIn(self::WP_LIBRARY_DIR_COPY, 'git push --set-upstream origin master');
            } catch (\RuntimeException $e) {
                //$this->consoleExec($this->output, $e->getMessage());
            }

            $this->consoleH2($this->output, sprintf('Suppression du répertoire woody-library'));
            $fs->remove(self::WP_LIBRARY_DIR_COPY);
        }

        return WoodyCommand::SUCCESS;
    }

    private function removeProTemplates()
    {
        $finder = new Finder();
        $finder->files()->in(self::WP_LIBRARY_DIR_COPY)->name('conf.json')->followLinks();
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $pathinfo = pathinfo($file->getRelativePathname());
                $json = json_decode(file_get_contents($file->getRealPath()), true);
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
