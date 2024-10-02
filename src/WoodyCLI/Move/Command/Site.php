<?php

namespace WoodyCLI\Move\Command;

use WoodyCLI\WoodyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;

/**
 * Site
 *
 * @author Benoit Bouchaud <benoit@raccourci.fr>
 * @copyright (c) 2022, Raccourci Agency
 * @package woody-cli
 */
class Site extends WoodyCommand
{
    protected $input;

    protected $output;

    protected $is_exist;

    protected $is_install;

    protected $is_cloned;

    protected $site_config;

    protected $site_core_key;

    protected $site_core_path;

    protected $target_core_key;

    protected $target_core_path;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('move:site')
            ->setDescription('Déplacer un site entre plusieurs cores')
            // Options
            ->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Site Key')
            ->addOption('core', 'c', InputOption::VALUE_REQUIRED, 'Core Key')
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

        // NOTE $this->setSiteKey() runs $this->loadSites() and verifies $this->siteIsConfigured($site_key)
        $this->site_config = $this->sites[$this->site_key];

        if (!array_key_exists('core', $this->site_config) || !array_key_exists('key', $this->site_config['core']) || !array_key_exists('path', $this->site_config['core'])) {
            throw new \RuntimeException('Configuration core manquante');
        }
        $this->site_core_key = $this->site_config['core']['key'];
        $this->site_core_path = $this->site_config['core']['path'];

        $this->setTargetCore($input->getOption('core'));

        $this->consoleH1($this->output, sprintf("Déplacement du site '%s' du core '%s' vers le core '%s'", $this->site_key, $this->site_core_key, $this->target_core_key));

        $this->consoleH2($this->output, 'Changement de la configuration nginx');
        // $this->change_nginx();

        // Déplacer le .env entre les cores (ex: /home/admin/woody_01/current/config/sites/woody-sandbox/.env)
        $this->consoleH2($this->output, 'Déplacement de la configuration du site');
        // $this->move_site_env();

        // Modifier le cron dans (ex: /etc/cron.d/wp_woody-sandbox)
        $this->consoleH2($this->output, 'Modification des crons');
        // $this->change_cron();

        // Creer le symlink du theme dans le nouveau core
        $this->consoleH2($this->output, 'Déplacement du thème dans le nouveau core');
        $this->move_theme();

        // Modifier le yaml dans /home/admin/www/woody_status/config/woody_01.yml
        $this->consoleH2($this->output, 'Changement de la configuration dans woody-status');

        $this->consoleH2($this->output, 'Nginx service reload');
        // TODO uncomment following line
        // $this->exec('sudo service nginx reload');

        $this->consoleH1($this->output, '!!! Pour que ce changement soit persistant, vous devez modifier la configuration de Puppet');
        // N'oubliez pas de répercuter ça dans puppet

        return WoodyCommand::SUCCESS;
    }

    /**
     * Set the target core
     * @param string $site_key
     */
    protected function setTargetCore($target_core_key)
    {
        $this->target_core_key = $target_core_key;
        if (empty($this->target_core_key)) {
            throw new \RuntimeException('Aucun core défini');
        }
        if ($this->target_core_key == $this->site_core_key) {
            throw new \RuntimeException('Le site est déjà dans ce core.');
        }
        $this->target_core_path = sprintf('%s/%s/current', $this->paths['WP_CORES_PATH'], $this->target_core_key);
        if (!$this->fs->exists($this->target_core_path)) {
            throw new \RuntimeException(sprintf("Le core '%s' n'existe pas", $this->target_core_path));
        }
    }

    /**
     * Change Nginx config for new targeted core
     */
    protected function change_nginx() {
        $finder = new Finder();
        $finder->in('/etc/nginx/sites-available/')->depth(0)->files()->name(sprintf('/\d\d_%s_%s(.+?|)\.conf/', preg_quote($this->site_core_key), preg_quote($this->site_key)))->sortByName();
        if (!$finder->hasResults()) {
            throw new \RuntimeException('Configuration nginx du site introuvable');
        }
        foreach ($finder as $path => $finder_file) {
            $new_path = str_replace($this->site_core_key, $this->target_core_key, $path);
            $this->consoleH3($this->output, "renommage du fichier");
            $cmd = sprintf("sudo mv %s %s", $path, $new_path);
            $this->consoleExec($this->output, $cmd);
            $this->exec($cmd);

            $this->consoleH3($this->output, "mise à jour de la configuration");
            $cmd = sprintf("sudo sed -i 's/%s/%s/g' %s", preg_quote($this->site_core_key), preg_quote($this->target_core_key), $new_path);
            $this->consoleExec($this->output, $cmd);
            $this->exec($cmd);

            $symlink_path = str_replace('/etc/nginx/sites-available/', '/etc/nginx/sites-enabled/', $path);
            $this->consoleH3($this->output, "mise à jour du symlink");
            $cmd = sprintf("sudo rm -f %s", $symlink_path);
            $this->consoleExec($this->output, $cmd);
            $this->exec($cmd);
            $cmd = sprintf("sudo ln -s %s /etc/nginx/sites-enabled/", $new_path);
            $this->consoleExec($this->output, $cmd);
            $this->exec($cmd);
        }
    }

    /**
     * Move site's .env into new targeted core
     */
    protected function move_site_env () {
        $env_dir = sprintf('%s/config/sites/%s/', $this->site_core_path, $this->site_key);
        if (!$this->fs->exists($env_dir)) {
            throw new \RuntimeException("Le .env du site n'existe pas");
        }
        $target_env_dir = sprintf('%s/config/sites/', $this->target_core_path);
        $cmd = sprintf("sudo mv %s %s", $env_dir, $target_env_dir);
        $this->consoleExec($this->output, $cmd);
        $this->exec($cmd);
    }

    /**
     * Change cron config for new targeted core
     */
    protected function change_cron() {
        $cron_file = sprintf('/etc/cron.d/wp_%s', $this->site_key);
        if (!$this->fs->exists($cron_file)) {
            $this->consoleH3($this->output, sprintf("Avertissement : la configuration cron '%s' du site n'existe pas", $cron_file));
            return;
        }
        $cmd = sprintf("sudo sed -i 's/%s/%s/g' %s", preg_quote($this->site_core_key), preg_quote($this->target_core_key), $cron_file);
        $this->consoleExec($this->output, $cmd);
        $this->exec($cmd);
    }

    /**
     * Move site's theme into new targeted core
     */
    protected function move_theme () {
        // TODO WIP
        /*
        $theme_dir = sprintf('%s/config/sites/%s/', $this->site_core_path, $this->site_key);
        if (!$this->fs->exists($env_dir)) {
            throw new \RuntimeException("Le .env du site n'existe pas");
        }
        $target_env_dir = sprintf('%s/config/sites/', $this->target_core_path);
        $cmd = sprintf("sudo mv %s %s", $env_dir, $target_env_dir);
        $this->consoleExec($this->output, $cmd);
        $this->exec($cmd);
        */
    }
}
