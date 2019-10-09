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
    protected $update;
    protected $is_install;
    protected $site_dir;

    /**
     * {inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('deploy:core')
            ->setDescription('Déployer le core')
            // Options
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
        $this->sites = $this->loadSites();

        $this->consoleH1($this->output, 'Installation du core Woody');

        if ($this->env != 'dev') {
            $this->consoleH2($this->output, 'Installation des symlinks de sites');
            foreach ($this->sites as $site_key => $site_val) {
                $this->symlink(sprintf(self::WP_DEPLOY_SITE_DIR, $site_key), sprintf(self::WP_SITE_DIR, $site_key));
                $this->consoleExec($this->output, sprintf($site_key . ' (%s)', sprintf(self::WP_SITE_DIR, $site_key)));
            }
        } else {
            $this->consoleH2($this->output, 'Installation du symlink de config');
            $this->symlink(WP_DEPLOY_DIR . '/shared/config/sites', self::WP_ROOT_DIR . '/config/sites');
            $this->consoleExec($this->output, sprintf('%s >> %s', WP_DEPLOY_DIR . '/shared/config/sites', self::WP_ROOT_DIR . '/config/sites'));
        }
    }
}
