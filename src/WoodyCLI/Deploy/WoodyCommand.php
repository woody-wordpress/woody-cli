<?php

namespace WoodyCLI\Deploy;

use WoodyCLI\AbstractCommand;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Deploy
 *
 * @author Léo POIROUX <leo@raccourci.fr>
 * @copyright (c) 2017, Raccourci Agency
 * @package woody-cli
 */
abstract class WoodyCommand extends AbstractCommand
{
    /**
     * Path to core directory
     */
    const WP_ROOT_DIR = WP_DEPLOY_DIR . '/current';

    /**
     * Path to the configuration file of a site (with wildcard)
     */
    const WP_CONFIG_DIRS = self::WP_ROOT_DIR . '/config/sites';

    /**
     * Path to site directory
     */
    const WP_THEMES_DIR = self::WP_ROOT_DIR . '/web/app/themes';

    /**
     * Path to site directory
     */
    const WP_SITE_DIR = self::WP_THEMES_DIR . '/%s';

    /**
     * Path to site directory
     */
    const WP_SITE_UPLOADS_DIR = self::WP_ROOT_DIR . '/web/app/uploads/%s';

    /**
     * Path to site directory
     */
    const WP_CACHE_DIR = self::WP_ROOT_DIR . '/web/app/cache';

    /**
     * Path to site directory
     */
    const WP_DEPLOY_SITE_DIR = WP_DEPLOY_DIR . '/sites/%s/current';

    /**
     * Path to the cli commands
     */
    const WP_SITE_CLI_DIR = self::WP_SITE_DIR . '/cli';

    /**
     * Loaded configuration
     * @var array
     */
    protected $sites = null;

    /**
     * Current site Key
     * @var string
     */
    protected $site_key = null;

    /**
     * Current env
     * @var string
     */
    protected $env = 'dev';

    /**
     * Current twig instance
     */
    protected $twig;

    /**
     * Current fs instance
     */
    protected $fs;

    /**
     * Lock status wp_cli
     */
    protected $lock;

    /**
     * __construct()
     * @param string $name
     */
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->fs = new Filesystem();
    }

    /**
     * Set the current site key
     * @param string $site_key
     */
    protected function setSiteKey($site_key)
    {
        if (!$this->siteIsConfigured($site_key)) {
            throw new \RuntimeException(sprintf('Site "%s" inexistant dans la configuration', $site_key));
        }
        $this->site_key = $site_key;
    }

    /**
     * Set the current env
     * @param string $this->env
     */
    protected function setEnv($env)
    {
        if (!$this->isValidEnv($env)) {
            throw new \RuntimeException(sprintf('Environnement "%s" invalide', $env));
        }
        $this->env = $env;
    }

    /**
     * Check wether or not a site is configured
     * @return boolean
     */
    protected function siteIsConfigured($site_key = null)
    {
        if (is_null($site_key)) {
            throw new \RuntimeException('Aucun site_key défini');
        }

        if (empty($this->sites)) {
            $this->sites = $this->loadSites();
        }

        return array_key_exists($site_key, $this->sites);
    }

    /**
     * Return the list of available sites (listed in the index.yml file)
     * @return array
     * @throws \RuntimeException
     */
    protected function loadSites()
    {
        if (!file_exists(self::WP_CONFIG_DIRS)) {
            throw new \RuntimeException('Impossible de trouver des sites dans le répertoire de configuration');
        }

        // If empty sites
        $sites = array();

        $finder = new Finder();
        $finder->files()->followLinks()->ignoreDotFiles(false)->in(self::WP_CONFIG_DIRS)->name('.env');
        foreach ($finder as $site) {
            $sites[$site->getRelativePath()] = $this->getDotEnv($site->getPathName());
        }

        $finder = new Finder();
        $finder->files()->followLinks()->ignoreDotFiles(false)->in(self::WP_THEMES_DIR)->name('.env');
        foreach ($finder as $site) {
            $path = explode('/', $site->getRelativePath());
            if (!empty($path[0]) && !empty($path[2]) && $path[2] == $this->env) {
                $sites[$path[0]] = array_merge($sites[$path[0]], $this->getDotEnv($site->getPathName()));
            }
        }

        if (empty($sites)) {
            throw new \RuntimeException('Liste des sites vide');
        }

        return $sites;
    }

    /**
     * Return the current site configuration
     * @return array|bool
     * @todo Return a default configuration
     */
    protected function getSiteConfiguration()
    {
        $return = [];

        if (empty($this->sites)) {
            $this->sites = $this->loadSites();
        }

        if (empty($this->site_key) || empty($this->sites[$this->site_key])) {
            throw new \RuntimeException('Aucun site_key défini');
        }

        $config = $this->sites[$this->site_key];
        foreach ($config as $key => $val) {
            $val = str_replace("'", '', $val);

            if (strpos($val, '[') !== false) {
                $val = str_replace(array('[', ']', '"', ' '), '', $val);
                $val = (!empty($val)) ? explode(',', $val) : [];
            } else {
                if (strpos($val, 'true') !== false) {
                    $val = true;
                } elseif (strpos($val, 'false') !== false) {
                    $val = false;
                }
            }

            $return[$key] = $val;
        }

        return $return;
    }

    /**
     * Transform .env file to array PHP
     *
     * @param [string] $data
     * @return array
     */
    protected function getDotEnv($path)
    {
        $return = [];

        $data = file_get_contents($path);
        if (!empty($data)) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }
                $line = explode("=", $line);
                $return[$line[0]] = $line[1];
            }
        }

        return $return;
    }

    /**
     * Return the current site configuration
     * @return array|bool
     * @todo Return a default configuration
     */
    protected function getSiteWPCommands()
    {
        $return = [];

        if (empty($this->site_key)) {
            throw new \RuntimeException('Aucun site_key défini');
        }

        $start_config = [];

        // Starting commands
        $start_config[] = 'plugin activate redirection';
        $start_config[] = 'redirection database install';

        $start_config[] = 'language core install fr_FR';
        $start_config[] = 'site switch-language fr_FR';
        $start_config[] = 'plugin activate polylang-pro';

        $start_config[] = 'post delete 1 --force --defer-term-counting';
        $start_config[] = 'post delete 2 --force --defer-term-counting';
        $start_config[] = 'post delete 3 --force --defer-term-counting';

        $start_config[] = 'theme activate ' . $this->site_key;
        $start_config[] = 'theme delete twentynineteen';
        $start_config[] = 'theme delete twentyseventeen';
        $start_config[] = 'theme delete twentysixteen';

        // Init config
        $config = [];
        $config['00_init']['common'] = $start_config;

        // Search Yaml
        if ($this->fs->exists(sprintf(self::WP_SITE_CLI_DIR, $this->site_key))) {
            $finder = new Finder();
            $finder->files()->in(sprintf(self::WP_SITE_CLI_DIR, $this->site_key))->name('*.yml')->sortByName();
            foreach ($finder as $file) {
                $migrate_key = str_replace('.yml', '', $file->getRelativePathname());
                $config[$migrate_key] = $this->getConfig($file->getRealPath());
            }
        }

        // Cleaning array
        foreach ($config as $file => $envs) {
            if (empty($envs)) {
                continue;
            }
            foreach ($envs as $env => $commands) {
                if ($env != $this->env && $env != 'common') {
                    unset($config[$file][$env]);
                    continue;
                }
            }
        }

        // Flatten array
        $config = $this->flattenArray($config);

        // Get Lock
        $lock = array();
        if ($this->fs->exists(sprintf(self::WP_SITE_UPLOADS_DIR . '/woody-cli.lock', $this->site_key))) {
            $lock = $this->getConfig(sprintf(self::WP_SITE_UPLOADS_DIR . '/woody-cli.lock', $this->site_key));
            $lock = $lock['commands'];
        }

        foreach ($config as $command) {
            if (empty($command)) {
                continue;
            }
            if (in_array($command, $lock)) {
                $return['lock'][] = $command;
            } else {
                $return['run'][] = $command;
            }
        }

        return $return;
    }

    /**
     * Execute a WP CLI command
     * @param string $site_key Site key
     * @param string $command    Command to execute
     */
    protected function wp($command, $exit_on_fail = true)
    {
        try {
            $callback = $this->execIn(self::WP_ROOT_DIR, sprintf('WP_SITE_KEY=%s wp %s --allow-root', $this->site_key, $command));
            return $callback;
        } catch (\Exception $e) {
            if ($exit_on_fail) {
                // Catch any error that might occure while clearing remote cache
                throw new \RuntimeException('Error : ' . $e->getMessage());
                exit(1);
            }
        }
    }

    /**
     * Generate wp_lock file
     * @param string $site_key Site key
     * @param string $command    Command to execute
     */
    protected function wp_lock()
    {
        $lock = Yaml::dump(array(
            'datetime' => date('c', time()),
            'commands' => $this->lock
        ));

        $this->fs->dumpFile(sprintf(self::WP_SITE_UPLOADS_DIR . '/woody-cli.lock', $this->site_key), $lock);
    }

    /**
     * Generate wp_lock file
     * @param string $site_key Site key
     * @param string $command    Command to execute
     */
    protected function wp_unlock()
    {
        $this->fs->remove(sprintf(self::WP_SITE_UPLOADS_DIR, $this->site_key));
    }
}
