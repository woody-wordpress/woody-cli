<?php

namespace WoodyCLI\Move\Command;

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

    protected $site_dir;

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
        $this->sites = $this->loadSites();

        // str_replace de woody_01 par woody_02 dans la conf nginx
        // /etc/nginx/sites-enabled
        // sudo service nginx reload

        // Déplacer le .env entre les cores (ex: /home/admin/woody_01/current/config/sites/woody-sandbox/.env)

        // Modifier le cron dans (ex: /etc/cron.d/wp_woody-sandbox)

        // Creer le symlink du theme dans le nouveau core

        // Modifier le yaml dans /home/admin/www/woody_status/config/woody_01.yml

        // N'oubliez pas de répercuter ça dans puppet

        return WoodyCommand::SUCCESS;
    }
}
