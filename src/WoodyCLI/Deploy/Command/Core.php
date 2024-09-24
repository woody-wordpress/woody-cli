<?php

namespace WoodyCLI\Deploy\Command;

use WoodyCLI\WoodyCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * Core
 *
 * @author Léo POIROUX <leo@raccourci.fr>
 * @copyright (c) 2017, Raccourci Agency
 * @package woody-cli
 */
class Core extends WoodyCommand
{
    protected $input;

    protected $output;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('deploy:core')
            ->setDescription('Déployer le core')
            // Options
            ->addOption('core', 'c', InputOption::VALUE_OPTIONAL, 'Core', null)
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
        $this->consoleH1($this->output, 'Installation du core Woody');
        $this->symlinks();

        return WoodyCommand::SUCCESS;
    }

    private function symlinks()
    {
        $this->sites = $this->loadSites();
        $this->consoleH2($this->output, 'Installation des symlinks de sites');

        foreach ($this->sites as $site_key => $site) {
            $core_key = $site['core']['key'];
            if(!empty($this->input->getOption('core')) && $core_key != $this->input->getOption('core')) {
                continue;
            }

            $this->symlink(sprintf('/home/admin/www/themes/%s/current', $site_key), sprintf($this->paths['WP_SITE_DIR'], $core_key, $site_key));
            $this->consoleExec($this->output, sprintf('%s : %s (%s > %s)', $core_key, $site_key, sprintf('/home/admin/www/themes/%s/current', $site_key), sprintf($this->paths['WP_SITE_DIR'], $core_key, $site_key)));
        }

        // // Sur le dev, il faut relier les confs qui sont normalement lier par Capistrano
        // if ($this->env == 'dev') {
        //     $config_dirs = WP_ROOT_DIR . '/config/sites'; //TODO: use other than config_dirs and WP_DEPLOY_DIR
        //     $this->consoleH2($this->output, 'Installation du symlink de config');
        //     $this->symlink(WP_DEPLOY_DIR . '/shared/config/sites', $config_dirs);
        //     $this->consoleExec($this->output, sprintf('%s >> %s', WP_DEPLOY_DIR . '/shared/config/sites', $config_dirs));
        // }
    }
}
