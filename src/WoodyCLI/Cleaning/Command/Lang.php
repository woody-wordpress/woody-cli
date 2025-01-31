<?php

namespace WoodyCLI\Cleaning\Command;

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
class Lang extends WoodyCommand
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
            ->setName('cleaning:lang')
            ->setDescription('Supprimer une langue et tous ses contenus')
            // Options
            ->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Site Key')
            ->addOption('lang', 'l', InputOption::VALUE_REQUIRED, 'Langue');
    }

    /**
     * {inhertidoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->setSiteKey($input->getOption('site'));
        $this->site_config = $this->getSiteConfiguration();

        $langs = $input->getOption('lang');

        if (is_null($langs)) {
            throw new \RuntimeException('Aucune langue définie');
        }

        if (empty($this->site_config)) {
            throw new \RuntimeException('Aucune configuration pour ce site');
        }

        $langs = explode(',', $langs);
        $this->setSiteKey($input->getOption('site'));

        $sitekey = $input->getOption('site');

        foreach ($langs as $lang) {
            $this->deleteContents($lang, $sitekey);

            if (in_array('roadbook', $this->site_config['WOODY_OPTIONS'])) {
                $this->deleteRoadbookContents($lang, $sitekey);
            }

            if (in_array('deals', $this->site_config['WOODY_OPTIONS'])) {
                $this->deleteDealsContents($lang, $sitekey);
            }

            if (in_array('topics', $this->site_config['WOODY_OPTIONS'])) {
                $this->deleteTopicsContents($lang, $sitekey);
            }
        }

        return WoodyCommand::SUCCESS;
    }

    private function deleteContents($lang, $sitekey)
    {
        $this->consoleH2($this->output, 'Suppression des images ' . $lang);
        $this->wp('post delete $(WP_SITE_KEY=' . $sitekey . ' wp post list --post_type=attachment --format=ids --lang="'. $lang .'") --force');

        $this->consoleH2($this->output, 'Suppression des pages '  . $lang);
        $this->wp('post delete $(WP_SITE_KEY=' . $sitekey . ' wp post list --post_type=page --format=ids --lang="'. $lang .'") --force');

        $this->consoleH2($this->output, 'Suppression des fiches SIT '  . $lang);
        $this->wp('post delete $(WP_SITE_KEY=' . $sitekey . ' wp post list --post_type=touristic_sheet --format=ids --lang="'. $lang .'") --force');

        $this->consoleH2($this->output, 'Suppression des liens rapides '  . $lang);
        $this->wp('post delete $(WP_SITE_KEY=' . $sitekey . ' wp post list --post_type=short_link --format=ids --lang="'. $lang .'") --force');

        $this->consoleH2($this->output, 'Suppression des termes de taxonomies'  . $lang);
        $this->wp('term delete $(WP_SITE_KEY=' . $sitekey . ' wp term list places --format=ids --lang="'. $lang .'")');
        $this->wp('term delete $(WP_SITE_KEY=' . $sitekey . ' wp term list seasons --format=ids --lang="'. $lang .'")');
        $this->wp('term delete $(WP_SITE_KEY=' . $sitekey . ' wp term list attachment_types --format=ids --lang="'. $lang .'")');
        $this->wp('term delete $(WP_SITE_KEY=' . $sitekey . ' wp term list attachment_categories --format=ids --lang="'. $lang .'")');
        $this->wp('term delete $(WP_SITE_KEY=' . $sitekey . ' wp term list attachment_hashtags --format=ids --lang="'. $lang .'")');
        $this->wp('term delete $(WP_SITE_KEY=' . $sitekey . ' wp term list expression_category --format=ids --lang="'. $lang .'")');
        $this->wp('term delete $(WP_SITE_KEY=' . $sitekey . ' wp term list topic_category --format=ids --lang="'. $lang .'")');
    }

    public function deleteRoadbookContents($lang, $sitekey)
    {
        $this->consoleH2($this->output, 'Suppression des termes de taxonomies roadbook '  . $lang);
        $this->wp('term delete $(WP_SITE_KEY=' . $sitekey . ' wp term list themes --format=ids --lang="'. $lang .'")');

        $this->consoleH2($this->output, 'Suppression des contenus roadbook '  . $lang);
        $this->wp('post delete $(WP_SITE_KEY=' . $sitekey . ' wp post list --post_type=woody_rdbk_leaflets,woody_rdbk_feeds --format=ids --lang="'. $lang .'") --force');
    }

    public function deleteDealsContents($lang, $sitekey)
    {
        $this->wp('post delete $(WP_SITE_KEY=' . $sitekey . ' wp post list --post_type=deal --format=ids --lang="'. $lang .'") --force');
        $this->wp('term delete $(WP_SITE_KEY=' . $sitekey . ' wp term list deals_category --format=ids --lang="'. $lang .'")');
    }

    public function deleteTopicsContents($lang, $sitekey)
    {
        $this->wp('post delete $(WP_SITE_KEY=' . $sitekey . ' wp post list --post_type=woody_topic --format=ids --lang="'. $lang .'") --force');
        $this->wp('term delete $(WP_SITE_KEY=' . $sitekey . ' wp term list topic_category --format=ids --lang="'. $lang .'")');
    }
}
