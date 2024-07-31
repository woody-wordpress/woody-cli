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
            ->setName('deploy:site')
            ->setDescription('Déployer un site')
            // Options
            ->addOption('options', 'o', InputOption::VALUE_OPTIONAL, 'Options (force,no-build,no-twig)')
            ->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Site Key')
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
        $options = (empty($options)) ? [] : explode(',', $options);

        $this->setEnv($input->getOption('env'));
        $this->setSiteKey($input->getOption('site'));
        $this->site_config = $this->getSiteConfiguration();

        // Site access locked
        if (!empty($this->site_config['WOODY_ACCESS_LOCKED']) && $this->site_config['WOODY_ACCESS_LOCKED']) {
            $this->consoleH1($this->output, sprintf('Projet "%s" fermé', $this->site_key));
        } else {
            $this->site_dir = sprintf($this->paths['WP_SITE_DIR'], $this->core_key, $this->site_key);

            // Force Delete
            if (in_array('force', $options) && $this->env == 'dev') {
                $this->wp_delete();
            }

            // Is Install
            $this->is_exist = $this->fs->exists(sprintf($this->paths['WP_SITE_DIR'], $this->core_key, $this->site_key) . '/style.css');
            $this->is_install = $this->fs->exists(sprintf($this->paths['WP_SITE_UPLOADS_DIR'] . '/woody-cli.lock', $this->core_key, $this->site_key));

            // Tasks
            if (!$this->is_exist) {
                $this->consoleH1($this->output, sprintf('Installation "%s"', $this->site_key));
                if ($this->env == 'dev') {
                    $this->git();
                } else {
                    $this->link();
                }
            } else {
                $this->consoleH1($this->output, sprintf('Mise à jour "%s" sur "%s"', $this->site_key, $this->core_key));
            }

            // Is Cloned
            $this->is_cloned = $this->fs->exists(sprintf($this->paths['WP_SITE_DIR'], $this->core_key, $this->site_key) . '/style.css');

            if ($this->is_cloned) {
                if (!in_array('no-install', $options) && !in_array('speed', $options) && !in_array('multi-site', $options)) {
                    $this->woody_install();
                }

                if (!in_array('no-updb', $options)) {
                    $this->woody_database_update();
                }

                if (!in_array('no-acf', $options) && !in_array('speed', $options) && !in_array('multi-site', $options)) {
                    $this->woody_acf_sync();
                }

                $this->woody_maintenance_on();

                if (!in_array('no-build', $options) && !in_array('speed', $options)) {
                    $this->woody_assets();
                }

                if (!in_array('no-cache', $options)) {
                    if (!in_array('no-cache-site', $options)) {
                        $this->woody_flush_site();
                    }

                    $this->woody_flush_core();
                }

                if (!in_array('no-warm', $options) && !in_array('speed', $options) && !in_array('multi-site', $options)) {
                    $this->woody_cache_warm();
                }

                if (!in_array('no-twig', $options) && !in_array('multi-site', $options)) {
                    $this->woody_flush_twig();
                }

                $this->woody_maintenance_off();

                if (!empty($this->site_config['WOODY_VARNISH_CACHING_ENABLE']) && !in_array('no-varnish', $options) && !in_array('speed', $options)) {
                    $this->woody_flush_varnish();
                }

                if (!empty($this->site_config['WOODY_CLOUDFLARE_ENABLE']) && !empty($this->site_config['WOODY_CLOUDFLARE_URL'])  && strpos($this->site_config['WOODY_CLOUDFLARE_URL'], 'cloudly.space') !== false && !in_array('no-cdn', $options) && !in_array('speed', $options)) {
                    $this->woody_flush_cdn();
                }

                if (!empty($this->site_config['WOODY_SSO_SECRET_URL']) && !in_array('no-sso', $options) && !in_array('speed', $options) && !in_array('multi-site', $options)) {
                    $this->woody_add_sso_domains();
                }
            } else {
                $this->consoleH2($this->output, sprintf('Le projet "%s" n\'a jamais été déployé', $this->site_key));
            }
        }

        return WoodyCommand::SUCCESS;
    }

    // Git clone
    private function git()
    {
        $site_repository = $this->site_config['WP_GIT_REPOSITORY'];
        $this->consoleH2($this->output, sprintf('Clone du dépôt "%s"', $site_repository));
        $this->exec(sprintf('git clone %s %s', $site_repository, $this->site_dir));
    }

    // Git clone
    private function link()
    {
        $this->consoleH2($this->output, sprintf('Création du symlink "%s"', $this->site_key));
        $this->symlink(sprintf($this->paths['WP_DEPLOY_SITE_DIR'], $this->core_key, $this->site_key), sprintf($this->paths['WP_SITE_DIR'], $this->core_key, $this->site_key));
        $this->consoleExec($this->output, sprintf($this->site_key . ' (%s)', sprintf($this->paths['WP_SITE_DIR'], $this->core_key, $this->site_key)));
    }

    // WP Env file
    private function wp_delete()
    {
        $this->consoleH2($this->output, 'Woody Delete');
        $this->wp_unlock();
    }

    // WP Install
    private function woody_install()
    {
        $this->consoleH2($this->output, 'Woody Install');

        if (!$this->is_install) {
            $password = md5(uniqid());
            $this->consoleList($this->output, 'wp core install');
            $this->wp(sprintf(
                'core install --url=%s --title="%s" --admin_email=%s --admin_user=%s --admin_password=%s --skip-email',
                $this->site_config['WP_HOME'],
                $this->site_key,
                $this->site_config['WOODY_ADMIN_EMAIL'],
                $this->site_config['WOODY_ADMIN_NAME'],
                $password
            ));

            $this->consoleText($this->output, '--------------------------------------');
            $this->consoleText($this->output, sprintf('Utilisateur : %s', $this->site_config['WOODY_ADMIN_NAME']));
            $this->consoleText($this->output, sprintf('Email : %s', $this->site_config['WOODY_ADMIN_EMAIL']));
            $this->consoleText($this->output, sprintf('Mot de passe : %s', $password));
            $this->consoleText($this->output, '--------------------------------------');
        }

        // Theme commands
        $siteWPCommands = $this->getSiteWPCommands();
        foreach ($siteWPCommands as $status => $commands) {
            foreach ($commands as $command) {
                $this->consoleExec($this->output, sprintf('[%s] %s', $status, $command));
                if ($status == 'run') {
                    $this->wp($command, false);
                }

                $this->lock[] = $command;
            }
        }

        $this->consoleList($this->output, 'Génération du fichier woody-cli.lock');
        $this->wp_lock();
    }

    // WP Database Update
    private function woody_database_update()
    {
        // Woody Update Database
        $this->consoleH2($this->output, 'Mise à jour de la BDD');
        $this->wp('core update-db');
        $this->wp('redirection database upgrade');
    }

    // WP ACF Sync
    private function woody_acf_sync()
    {
        // Woody ACF sync
        if ($this->site_key == 'superot' && $this->env == 'dev') {
            $this->consoleH2($this->output, 'ACF sync');
            $this->wp('acf sync');
        } else {
            $this->consoleH2($this->output, 'Suppression des données ACF dans la BDD');
            $acf_field_ids = $this->wp('post list --post_type="acf-field" --post_type="acf-field" --format=ids');
            if (!empty($acf_field_ids)) {
                $this->wp('post delete ' . $acf_field_ids . ' --force');
            } else {
                $this->consoleList($this->output, 'Aucun ACF Field à supprimer');
            }

            $acf_field_group_ids = $this->wp('post list --post_type="acf-field" --post_type="acf-field-group" --format=ids');
            if (!empty($acf_field_group_ids)) {
                $this->wp('post delete ' . $acf_field_group_ids . ' --force');
            } else {
                $this->consoleList($this->output, 'Aucun ACF Field Group à supprimer');
            }
        }
    }

    // WP Assets
    private function woody_assets()
    {
        $this->consoleH2($this->output, 'Compilation des Assets');
        if($this->core_key == 'woody_02') {
            $cmd = 'yarn build -s ' . $this->site_key . ' -e ' . $this->env;
            $this->consoleExec($this->output, $cmd);
            $this->execIn(WP_ROOT_DIR, $cmd);
        } else {
            $cmd = 'yarn build --site ' . $this->site_key . ' --env ' . $this->env;
            $this->consoleExec($this->output, $cmd);
            $this->execIn(WP_ROOT_DIR . '/gulp', $cmd);
        }
    }

    // WP Cache Flush Core
    private function woody_flush_core()
    {
        $this->consoleH2($this->output, 'Nettoyage du CACHE du CORE');
        $this->wp('woody_flush_core');
    }

    // WP Cache Flush Site
    private function woody_flush_site()
    {
        $this->consoleH2($this->output, 'Nettoyage du CACHE du SITE');
        $this->wp('woody_flush_site');
    }

    // WP Cache Warm
    private function woody_cache_warm()
    {
        $this->consoleH2($this->output, 'Génération du CACHE');
        $this->wp('woody_cache_warm');
    }

    // WP Cache Flush Twig
    private function woody_flush_twig()
    {
        $this->consoleH2($this->output, 'Nettoyage du CACHE TWIG');
        $this->wp('woody_flush_twig');
    }

    // WP Varnish Flush
    private function woody_flush_varnish()
    {
        if (!empty($this->site_config['WOODY_CLOUDFLARE_ENABLE']) && !empty($this->site_config['WOODY_CLOUDFLARE_URL'])  && strpos($this->site_config['WOODY_CLOUDFLARE_URL'], 'cloudly.space') !== false) {
            $this->consoleH2($this->output, 'Purge du VARNISH');
        } else {
            $this->consoleH2($this->output, 'Purge du VARNISH + CDN');
        }
        $this->wp('woody:varnish flush');
    }

    // WP CDN CLOUDFLARE Flush
    private function woody_flush_cdn()
    {
        $this->consoleH2($this->output, 'Purge du CDN');
        $this->wp('woody:cdn flush');
    }

    // WP Add SSO Domains
    private function woody_add_sso_domains()
    {
        $this->consoleH2($this->output, 'Autorisation SSO');
        $this->wp('woody_add_sso_domains');
    }
}
