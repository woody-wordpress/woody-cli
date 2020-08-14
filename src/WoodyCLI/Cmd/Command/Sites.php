<?php

namespace WoodyCLI\Cmd\Command;

use WoodyCLI\WoodyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * Site
 *
 * @author Léo POIROUX <leo@raccourci.fr>
 * @copyright (c) 2017, Raccourci Agency
 * @package woody-cli
 */
class Sites extends WoodyCommand
{
    protected $input;
    protected $output;

    /**
     * {inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('cmd:sites')
            ->setDescription('Commande WP sur tous les sites')
            // Options
            ->addOption('wp', 'wp', InputOption::VALUE_OPTIONAL, 'Commande')
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL, 'Environnement', 'dev');
    }

    /**
     * {inhertidoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $wp = $input->getOption('wp');
        $env = $input->getOption('env');
        $this->setEnv($env);

        $sites = $this->loadSites();

        $this->consoleH1($this->output, 'Woody Command Multi-Site');
        $i = 1;
        $nb_sites = count($sites);
        foreach ($sites as $site_key => $site_config) {
            $this->consoleH2($this->output, sprintf('%s/%s %s', $i, $nb_sites, $site_key));
            $site_config = $this->getSiteConfiguration($site_key);
            $is_cloned = $this->fs->exists(sprintf(self::WP_SITE_DIR, $site_key) . '/style.css');

            // Site access locked
            if (!$is_cloned || (!empty($site_config['WOODY_ACCESS_LOCKED']) && $site_config['WOODY_ACCESS_LOCKED'])) {
                $this->consoleH1($this->output, sprintf('Projet "%s" fermé', $this->site_key));
            } else {
                $this->consoleExec($this->output, sprintf('WP_SITE_KEY=%s wp %s', $site_key, $wp));
                $this->exec(sprintf('WP_SITE_KEY=%s wp %s', $site_key, $wp));
            }
            $i++;
        }
    }
}
