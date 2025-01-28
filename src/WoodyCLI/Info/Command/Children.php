<?php

namespace WoodyCLI\Info\Command;

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
 * @author Sébastien Chandonay <sebastien.chandonay@raccourci.fr>
 * @copyright (c) 2025, Raccourci Agency
 * @package woody-cli
 */
class Children extends WoodyCommand
{
    protected $input;

    protected $output;

    protected $site_config;

    protected $current_core_key;

    protected $current_core_path;

    protected $target_core_key;

    protected $target_core_path;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('info:children')
            ->setDescription('Affiche la liste les sites enfants')
            // Options
            ->addOption('site', 's', InputOption::VALUE_OPTIONAL, 'Site Key');
    }

    /**
     * {inhertidoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $site_key = $input->getOption('site');

        $all_themes_path = $this->paths['WP_CORES_PATH'] . '/themes/';

        $sites = [];
        if (!empty($site_key)) {
            $sites[] = $site_key;
            $this->consoleH1($this->output, sprintf("Recherche des enfants du site '%s'", $site_key));
        } else {
            $sites = $this->loadSites();
            $sites = array_keys($sites);
            $this->consoleH1($this->output, sprintf("Recherche des enfants de %s sites", count($sites)));
        }
        $this->consoleText($this->output, sprintf("Considérez que la recherche se base sur la présence du site_key parent dans les feuilles de styles des thèmes enfants dans %s", $all_themes_path));

        foreach ($sites as $site_key) {
            $finder_grep = "/@import\\s+\"" . $site_key . "\\/src/";
            $finder_children = new Finder();
            $finder_children->files()->followLinks()->ignoreDotFiles(true)->in($all_themes_path)->name('*.scss')->depth(['>= 4', '<= 5'])->contains("/@import\\s+\"" . $site_key . "\\/src/i");
            $matchingFiles = [];
            if ($finder_children->hasResults()) {
                foreach ($finder_children as $file) {
                    $matchingFiles[] = $file->getRealPath();
                }
            }
            if (!empty($matchingFiles)) {
                $this->consoleH2($this->output, sprintf('Enfants du site %s : ', $site_key));
                foreach ($matchingFiles as $matchingFile) {
                    $this->consoleList($this->output, $matchingFile);
                }
            }
        }
        return WoodyCommand::SUCCESS;
    }
}
