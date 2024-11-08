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

    protected $theme_current_path;

    protected $theme_repo_path;

    protected $theme_release_path;

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
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL, 'Environnement', 'dev')
            ->addOption('move', 'm', InputOption::VALUE_OPTIONAL, 'Move', false)
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
        $this->git_branch = $input->getOption('branch');

        if($this->env == 'dev') {
            $this->consoleH1($this->output, 'Cette commande ne se lance pas en dev');
            return WoodyCommand::SUCCESS;
        }

        // NOTE $this->setSiteKey() runs $this->loadSites() and verifies $this->siteIsConfigured($site_key)
        $this->site_config = $this->sites[$this->site_key];
        $this->setCoreKey($this->site_config['core']['key']);
        $this->current_core_path = $this->site_config['core']['path'];

        $this->theme_current_path = sprintf($this->paths['WP_THEMES_PATH'], $this->site_key);
        $this->theme_repo_path = str_replace('current', 'repo', $this->theme_current_path);
        $this->theme_release_path = str_replace('current', 'releases', $this->theme_current_path) . '/' . date('YmdHis');

        $this->consoleH1($this->output, sprintf("Pull du site '%s' du core '%s' depuis la branche '%s'", $this->site_key, $this->core_key, $this->git_branch));
        $this->woody_maintenance_on();
        $this->fetch_remote();
        $this->checkout_branch();
        $this->pull_site();
        $this->reset_site();
        $this->create_release();
        $this->symlink_current();

        if($input->getOption('move') == false) {
            $this->woody_maintenance_off();
        }

        return WoodyCommand::SUCCESS;
    }

    /**
     * Fetch Git remote
     */
    protected function fetch_remote()
    {
        $this->consoleH2($this->output, 'Git fetch');
        $cmd = sprintf('git fetch');
        $this->consoleExec($this->output, $cmd);
        $this->execIn($this->theme_repo_path, $cmd);
    }

    /**
     * Checkout Git branch
     */
    protected function checkout_branch()
    {
        $this->consoleH2($this->output, 'Git checkout');
        $cmd = sprintf('git checkout -B %s --track origin/%s', $this->git_branch, $this->git_branch);
        $this->consoleExec($this->output, $cmd);
        $this->execIn($this->theme_repo_path, $cmd);
    }

    /**
     * Pull site
     */
    protected function pull_site()
    {
        $this->consoleH2($this->output, 'Pull du site');
        $cmd = 'git pull';
        $this->consoleExec($this->output, $cmd);
        $this->execIn($this->theme_repo_path, $cmd);
    }

    /**
     * Reset site
     */
    protected function reset_site()
    {
        $this->consoleH2($this->output, 'Reset du site');
        $cmd = 'git reset --hard';
        $this->consoleExec($this->output, $cmd);
        $this->execIn($this->theme_repo_path, $cmd);
    }

    /**
     * Create release
     */
    protected function create_release()
    {
        $this->consoleH2($this->output, 'Create release');
        $cmd = sprintf('rsync -ar --exclude=.git* %s/ %s', $this->theme_repo_path, $this->theme_release_path);
        $this->consoleExec($this->output, $cmd);
        $this->execIn($this->theme_repo_path, $cmd);
    }

    /**
     * Symlink current
     */
    protected function symlink_current()
    {
        $this->consoleH2($this->output, 'Symlink current');
        $cmd = sprintf('rm -rf %s && ln -s %s %s', $this->theme_current_path, $this->theme_release_path, $this->theme_current_path);
        $this->consoleExec($this->output, $cmd);
        $this->execIn($this->theme_repo_path, $cmd);
    }
}
