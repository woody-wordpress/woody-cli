<?php

namespace WoodyCLI\Pull\Command;

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
 * @author Orphée Besson <orphee.besson@raccourci.fr>
 * @copyright (c) 2024, Raccourci Agency
 * @package woody-cli
 */
class Site extends WoodyCommand
{
    protected $input;

    protected $output;

    protected $site_config;

    protected $current_core_key;

    protected $current_core_path;

    protected $git_branch;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pull:site')
            ->setDescription('Fait un git pull sur un site')
            // Options
            ->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Site Key')
            ->addOption('core', 'c', InputOption::VALUE_REQUIRED, 'Core Key')
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL, 'Environnement', 'dev')
            ->addOption('branch', 'b', InputOption::VALUE_OPTIONAL, 'Branch', 'master');
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
        $this->setCoreKey($input->getOption('core'));

        // NOTE $this->setSiteKey() runs $this->loadSites() and verifies $this->siteIsConfigured($site_key)
        $this->site_config = $this->sites[$this->site_key];

        if (!array_key_exists('core', $this->site_config) || !array_key_exists('key', $this->site_config['core']) || !array_key_exists('path', $this->site_config['core'])) {
            throw new \RuntimeException('Configuration core manquante');
        }

        $this->current_core_key = $this->site_config['core']['key'];
        $this->current_core_path = $this->site_config['core']['path'];
        $this->git_branch = $input->getOption('branch');

        $this->consoleH1($this->output, sprintf("Pull du site '%s' du core '%s' depuis la branche '%s'", $this->site_key, $this->current_core_key, $this->git_branch));

        $this->consoleH2($this->output, 'Git checkout');
        $this->checkout_branch();

        $this->consoleH2($this->output, 'Pull du site');
        $this->pull_site();

        $this->consoleH1($this->output, sprintf("Pull du site '%s' du core '%s' depuis la branche '%s' terminé", $this->site_key, $this->current_core_key, $this->git_branch));

        return WoodyCommand::SUCCESS;
    }

    /**
     * Checkouts Git branch
     */
    protected function checkout_branch()
    {
        $theme_folder = sprintf('%s/web/app/themes/%s', $this->current_core_path, $this->site_key);
        $cmd = sprintf('git checkout -B %s --track origin/%s', $this->git_branch, $this->git_branch);
        $this->consoleExec($this->output, $cmd);
        $this->execIn($theme_folder, $cmd);
    }

    /**
     * Pulls site
     */
    protected function pull_site()
    {
        $theme_folder = sprintf('%s/web/app/themes/%s', $this->current_core_path, $this->site_key);
        $cmd = 'git pull';
        $this->consoleExec($this->output, $cmd);
        $this->execIn($theme_folder, $cmd);
    }
}
