<?php

namespace WoodyCLI\Deploy\Command;

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
    protected function configure()
    {
        $this
            ->setName('deploy:sites')
            ->setDescription('Déployer tous les sites')
            // Options
            ->addOption('options', 'o', InputOption::VALUE_OPTIONAL, 'Options (force,no-gulp,no-twig)')
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL, 'Environnement', 'dev');
    }

    /**
     * {inhertidoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $options = $input->getOption('options');
        if (strpos($options, 'multi-site') !== false) {
            $env = $input->getOption('env');
            $this->setEnv($env);
            $sites = $this->loadSites();

            $this->consoleH1($this->output, 'Woody Deploy Multi-Site');
            $i = 1;
            $nb_sites = count($sites);
            foreach (array_keys($sites) as $site_key) {
                $this->consoleH2($this->output, sprintf('%s/%s %s', $i, $nb_sites, $site_key));
                $this->consoleExec($this->output, sprintf('woody deploy:site -s %s -e %s -o %s', $site_key, $env, $options));
                $this->exec(sprintf('woody deploy:site -s %s -e %s -o %s', $site_key, $env, $options));
                ++$i;
            }
        }

        return WoodyCommand::SUCCESS;
    }
}
