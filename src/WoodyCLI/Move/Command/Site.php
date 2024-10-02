<?php

namespace WoodyCLI\Move\Command;

use WoodyCLI\WoodyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

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
        $this->consoleH3($this->output, 'Note : si une erreur survient au cours de ce processus et que le serveur se retrouve dans un état non souhaité, vous pouvez relancer un puppet_apply pour le remettre dans son état initial.');

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Voulez-vous vraiment déplacer ce site ? ', false);
        if (!$helper->ask($input, $output, $question)) {
            return WoodyCommand::SUCCESS;
        }

        $this->consoleH2($this->output, 'Changement de la configuration nginx');
        $this->change_nginx();

        $this->consoleH2($this->output, 'Déplacement de la configuration du site');
        $this->move_site_env();

        $this->consoleH2($this->output, 'Modification des crons');
        $this->change_cron();

        $this->consoleH2($this->output, 'Déplacement du thème dans le nouveau core');
        $this->move_theme();

        $this->consoleH2($this->output, 'Changement de la configuration woody_status');
        $this->change_woody_status_config();

        $this->consoleH2($this->output, 'Nginx service reload');
        $this->reload_nginx();

        $this->consoleH1($this->output, 'Déplacement terminé');
        $this->consoleH2($this->output, '!!! Pour que ce changement de core soit persistant, vous devez modifier la configuration de Puppet !!!');

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

            $this->consoleH3($this->output, "mise à jour du symlink");
            $symlink_path = str_replace('/etc/nginx/sites-available/', '/etc/nginx/sites-enabled/', $path);
            $cmd = sprintf("sudo rm -f %s", $symlink_path);
            $this->consoleExec($this->output, $cmd);
            $this->exec($cmd);

            $cmd = sprintf("sudo ln -s %s /etc/nginx/sites-enabled/", $new_path);
            $this->consoleExec($this->output, $cmd);
            $this->exec($cmd);

            $target_core_nginx_log_path = sprintf("/var/log/nginx/%s/%s", $this->target_core_key, $this->site_key);
            if (!$this->fs->exists($target_core_nginx_log_path)) {
                $this->consoleH3($this->output, "création du dossier de log");
                $cmd = sprintf("sudo mkdir -p %s", $target_core_nginx_log_path);
                $this->consoleExec($this->output, $cmd);
                $this->exec($cmd);
            }

            $target_core_nginx_access_log_path = sprintf("%s/access.log", $target_core_nginx_log_path);
            if (!$this->fs->exists($target_core_nginx_access_log_path)) {
                $this->consoleH3($this->output, "création du access.log");
                $cmd = sprintf("sudo touch %s", $target_core_nginx_access_log_path);
                $this->consoleExec($this->output, $cmd);
                $this->exec($cmd);
            }

            $target_core_nginx_error_log_path = sprintf("%s/error.log", $target_core_nginx_log_path);
            if (!$this->fs->exists($target_core_nginx_error_log_path)) {
                $this->consoleH3($this->output, "création du error.log");
                $cmd = sprintf("sudo touch %s", $target_core_nginx_error_log_path);
                $this->consoleExec($this->output, $cmd);
                $this->exec($cmd);
            }
        }
    }

    /**
     * Move site's .env into new targeted core
     */
    protected function move_site_env() {
        $site_env_dir = sprintf('%s/config/sites/%s/', $this->site_core_path, $this->site_key);
        if (!$this->fs->exists($site_env_dir)) {
            throw new \RuntimeException("Le .env du site n'existe pas");
        }
        $target_env_dir = sprintf('%s/config/sites/', $this->target_core_path);
        if ($this->fs->exists($target_env_dir)) {
            $this->consoleH3($this->output, sprintf("Avertissement : une configuration est déjà existante à l'emplacement '%s' pour le site - elle n'a pas été remplacée.", $target_env_dir));
            return;
        }
        $cmd = sprintf("sudo mv %s %s", $site_env_dir, $target_env_dir);
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
    protected function move_theme() {
        $cmd = sprintf("sudo rm -f %s/web/app/themes/%s", $this->site_core_path, $this->site_key);
        $this->consoleExec($this->output, $cmd);
        $this->exec($cmd);

        $theme_symlink_source = sprintf("/home/admin/www/themes/%s/current", $this->site_key);
        $theme_symlink_target = sprintf("%s/web/app/themes/%s", $this->target_core_path, $this->site_key);
        if ($this->fs->exists($theme_symlink_target)) {
            $this->consoleH3($this->output, sprintf("Avertissement : le symlink '%s' existe déjà - veuillez vous assurer qu'il pointe bien la ressource '%s'", $theme_symlink_target, $theme_symlink_source));
        } else {
            $cmd = sprintf("sudo ln -s %s %s", $theme_symlink_source, $theme_symlink_target);
            $this->consoleExec($this->output, $cmd);
            $this->exec($cmd);
        }
    }

    /**
     * Change woody_status config for new targeted core
     */
    protected function change_woody_status_config() {

        $site_core_yml_path = sprintf('/home/admin/www/woody_status/shared/config/%s.yml', $this->site_core_key);
        $this->consoleH3($this->output, sprintf("retrait du site de la configuration '%s'", $site_core_yml_path));
        if ($this->fs->exists($site_core_yml_path)) {
            $site_core_config = Yaml::parseFile($site_core_yml_path);
            unset($site_core_config['sites'][array_search($this->site_key, $site_core_config['sites'])]);
            file_put_contents($site_core_yml_path, Yaml::dump($site_core_config));
        } else {
            $this->consoleH3($this->output, sprintf("Avertissement : le fichier de configuration woody_status du site n'existe pas à cet endroit : %s", $site_core_yml_path));
        }

        $target_core_yml_path = sprintf('/home/admin/www/woody_status/shared/config/%s.yml', $this->target_core_key);
        $this->consoleH3($this->output, sprintf("ajout du site à la configuration '%s'", $target_core_yml_path));
        if ($this->fs->exists($target_core_yml_path)) {
            $target_core_config = Yaml::parseFile($target_core_yml_path);
            $target_core_config['sites'][] = $this->site_key;
            sort($target_core_config['sites']);
            file_put_contents($target_core_yml_path, Yaml::dump($target_core_config));
        } else {
            $this->consoleH3($this->output, sprintf("Avertissement : le fichier de configuration woody_status du site n'existe pas à cet endroit : %s", $target_core_yml_path));
        }
    }

    /**
     * Reload nginx to get new configuration
     */
    protected function reload_nginx() {
        $cmd = 'sudo service nginx reload';
        $this->consoleExec($this->output, $cmd);
        $this->exec($cmd);
    }
}
