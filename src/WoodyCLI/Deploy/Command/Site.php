<?php

namespace WoodyCLI\Deploy\Command;

use WoodyCLI\Deploy\WoodyCommand;
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
    protected $update;
    protected $is_install;
    protected $site_config;
    protected $site_dir;

    /**
     * {inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('deploy:site')
            ->setDescription('Déployer un site')
            // Options
            ->addOption('options', 'o', InputOption::VALUE_OPTIONAL, 'Options (force,no-gulp,no-twig)')
            ->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Site Key')
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL, 'Environnement', 'dev');
    }

    /**
     * {inhertidoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $options = $input->getOption('options');
        $options = explode(',', $options);

        $this->setEnv($input->getOption('env'));
        $this->setSiteKey($input->getOption('site'));
        $this->site_config = $this->getSiteConfiguration();

        // Site access locked
        if (!empty($this->site_config['WOODY_ACCESS_LOCKED']) && $this->site_config['WOODY_ACCESS_LOCKED']) {
            $this->consoleH1($this->output, sprintf('Projet "%s" fermé', $this->site_key));
        } else {
            $this->site_dir = sprintf(self::WP_SITE_DIR, $this->site_key);

            // Force Delete
            if (in_array('force', $options) && $this->env == 'dev') {
                $this->wp_delete();
            }

            // Is Install
            $this->is_exist = $this->fs->exists(sprintf(self::WP_SITE_DIR, $this->site_key));
            $this->is_install = $this->fs->exists(sprintf(self::WP_SITE_UPLOADS_DIR . '/woody-cli.lock', $this->site_key));

            // Tasks
            if (!$this->is_exist) {
                $this->consoleH1($this->output, sprintf('Installation du projet "%s"', $this->site_key));
                if ($this->env == 'dev') {
                    $this->git();
                } else {
                    $this->link();
                }
            } else {
                $this->consoleH1($this->output, sprintf('Mise à jour du projet "%s"', $this->site_key));
            }

            // Is Cloned
            $this->is_cloned = $this->fs->exists(sprintf(self::WP_SITE_DIR, $this->site_key) . '/style.css');

            if ($this->is_cloned) {
                $this->wp_install();
                if (!in_array('no-gulp', $options)) {
                    $this->wp_assets();
                }

                $this->wp_flush_cache();

                if (!in_array('no-twig', $options)) {
                    $this->wp_flush_timber();
                }

                if (!in_array('no-varnish', $options)) {
                    $this->wp_varnish_flush();
                }
            } else {
                $this->consoleH2($this->output, sprintf('Le projet "%s" n\'a jamais été déployé', $this->site_key));
            }
        }
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
        $this->symlink(sprintf(self::WP_DEPLOY_SITE_DIR, $this->site_key), sprintf(self::WP_SITE_DIR, $this->site_key));
        $this->consoleExec($this->output, sprintf($this->site_key . ' (%s)', sprintf(self::WP_SITE_DIR, $this->site_key)));
    }

    // WP Env file
    private function wp_delete()
    {
        $this->consoleH2($this->output, 'Woody Delete');
        $this->wp_unlock();
    }

    // WP Env file
    private function wp_install()
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
        $config = $this->getSiteWPCommands();
        foreach ($config as $status => $commands) {
            foreach ($commands as $command) {
                $this->consoleExec($this->output, sprintf('[%s] %s', $status, $command));
                if ($status == 'run') {
                    $this->wp($command, false, true);
                }
                $this->lock[] = $command;
            }
        }

        $this->consoleList($this->output, 'Génération du fichier woody-cli.lock');
        $this->wp_lock();

        // Woody Update Database
        $this->consoleH2($this->output, 'Mise à jour de la BDD');
        $this->wp('core update-db');
        $this->wp('redirection database upgrade');

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

    // WP Env file
    private function wp_assets()
    {
        $this->consoleH2($this->output, 'Compilation des Assets');
        $this->execIn(self::WP_ROOT_DIR . '/gulp', 'yarn build --site ' . $this->site_key . ' --env ' . $this->env);
    }

    // WP Cache Flush
    private function wp_flush_cache()
    {
        $this->consoleH2($this->output, 'Nettoyage du CACHE GLOBAL');
        $this->wp('woody_flush_cache');
    }

    // WP Cache Flush Twig
    private function wp_flush_timber()
    {
        $this->consoleH2($this->output, 'Nettoyage du CACHE TWIG');
        $this->wp('woody_flush_timber');
    }

    // WP Varnish Flush
    private function wp_varnish_flush()
    {
        $this->consoleH2($this->output, 'Purge du VARNISH');
        $this->wp('woody_flush_varnish');
    }
}
