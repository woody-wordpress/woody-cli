<?php

namespace WoodyCLI;

use WoodyCLI\AbstractCommand;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Woody\Status\Services\StatusManager;

/**
 * Deploy
 *
 * @author Léo POIROUX <leo@raccourci.fr>
 * @copyright (c) 2017, Raccourci Agency
 * @package woody-cli
 */
abstract class WoodyCommand extends AbstractCommand
{
    protected $output;
    protected $sites = [];
    protected $site_key;
    protected $core_key;
    protected $env = 'dev';
    protected $twig;
    protected $fs;
    protected $lock;
    protected $paths;
    protected $multicore;

    /**
     * __construct()
     * @param string $name
     */
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->fs = new Filesystem();

        // On récupère le chemin du core et on le remplace par une chaine avec %s à la place du core_key
        $root_dir = explode('/', WP_ROOT_DIR);
        if (count($root_dir) >= 2) {
            end($root_dir);
            $this->core_path = str_replace(prev($root_dir), '%s',WP_ROOT_DIR);
        }

        print_r($this->core_path);
        exit();

        $this->multicore = (strpos(WP_ROOT_DIR, 'woody_status') !== false);
        $this->paths = [
            'WP_CONFIG_DIRS' => $this->core_path . '/config/sites',
            'WP_THEMES_DIR' => $this->core_path . '/web/app/themes',
            'WP_SITE_DIR' => $this->core_path . '/web/app/themes/%s',
            'WP_SITE_UPLOADS_DIR' => $this->core_path . '/web/app/uploads/%s',
            'WP_CACHE_DIR' => $this->core_path . '/web/app/cache',
            'WP_TIMBER_DIR' => $this->core_path . '/web/app/cache/timber',
            'WP_DEPLOY_SITE_DIR' => WP_DEPLOY_DIR . '/sites/%s/current',
            'WP_SITE_CLI_DIR' => $this->core_path . '/web/app/themes/%s/cli'
        ];
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
        if($this->multicore) {
            return $this->loadSitesFromStatus();
        } else {
            return $this->loadSitesFromCore();
        }
    }

    protected function loadSitesFromStatus()
    {
        $statusManager = new StatusManager();
        $sites = $statusManager->getSites();
        return $sites;
    }

    /**
     * Return the list of available sites (listed in the index.yml file)
     * @return array
     * @throws \RuntimeException
     */
    protected function loadSitesFromCore()
    {
        if (!file_exists($this->paths['WP_CONFIG_DIRS'])) {
            throw new \RuntimeException('Impossible de trouver des sites dans le répertoire de configuration');
        }

        // If empty sites
        $sites = array();

        $finder = new Finder();
        $finder->files()->followLinks()->ignoreDotFiles(false)->in($this->paths['WP_CONFIG_DIRS'])->name('.env');
        foreach ($finder as $site) {
            $sites[$site->getRelativePath()] = $this->getDotEnv($site->getPathName());
        }

        $finder = new Finder();
        $finder->files()->followLinks()->ignoreDotFiles(false)->in($this->paths['WP_THEMES_DIR'])->name('.env');
        foreach ($finder as $site) {
            $path = explode('/', $site->getRelativePath());
            if (!empty($path[0]) && !empty($path[2]) && $path[2] === $this->env) {
                $sites[$path[0]] = array_merge($sites[$path[0]], $this->getDotEnv($site->getPathName()));
            }
        }

        if (empty($sites)) {
            throw new \RuntimeException('Liste des sites vide');
        }

        // Sorting by alphabetical order
        ksort($sites);

        return $sites;
    }

    /**
     * Return the current site configuration
     * @return array|bool
     * @todo Return a default configuration
     */
    protected function getSiteConfiguration($site_key = null)
    {
        $return = [];

        if (empty($this->sites)) {
            $this->sites = $this->loadSites();
        }

        if (!empty($site_key)) {
            $this->site_key = $site_key;
        }

        if (empty($this->site_key) || empty($this->sites[$this->site_key])) {
            throw new \RuntimeException('Aucun site_key défini');
        }

        if ($this->multicore) {
            $this->core_key = $this->sites[$this->site_key]['core']['key'];
            return $this->sites[$this->site_key]['env'];
        } else {
            $root_dir = explode('/', WP_ROOT_DIR);
            if (count($root_dir) >= 2) {
                end($root_dir);
                $this->core_key = prev($root_dir);
            }
            return $this->sites[$this->site_key];
        }

        return $return;
    }

    protected function array_env($env)
    {
        $env = str_replace(array('[', ']', '"', ' '), '', $env);
        $env = (empty($env)) ? [] : explode(',', $env);
        sort($env);
        return array_unique($env);
    }

    protected function getDotEnv($file)
    {
        $env = [];
        $file = file_get_contents($file);
        $file = explode("\n", $file);
        foreach ($file as $line) {
            if (!empty($line)) {
                $line = explode('=', $line);
                $key = $line[0];
                $val = $line[1];
                if (substr($val, 0, 1) == '"' || substr($val, 0, 1) == "'") {
                    $val = substr(substr($val, 1), 0, -1);
                }

                if (substr($val, 0, 1) == '[' && substr($val, -1) == ']') {
                    $val = self::array_env($val);
                } elseif (strpos($val, 'false') !== false) {
                    $val = false;
                } elseif (strpos($val, 'true') !== false) {
                    $val = true;
                }

                $env[$key] = $val;
            }
        }

        return $env;
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
        $start_config[] = 'theme delete twentytwentythree';
        $start_config[] = 'theme delete twentytwentytwo';
        $start_config[] = 'theme delete twentytwentyone';
        $start_config[] = 'theme delete twentytwenty';

        // Init config
        $config = [];
        $config['00_init']['common'] = $start_config;

        // Search Yaml
        if ($this->fs->exists(sprintf($this->paths['WP_SITE_CLI_DIR'], $this->site_key))) {
            $finder = new Finder();
            $finder->files()->in(sprintf($this->paths['WP_SITE_CLI_DIR'], $this->site_key))->name('*.yml')->sortByName();
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

            foreach (array_keys($envs) as $env) {
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
        if ($this->fs->exists(sprintf($this->paths['WP_SITE_UPLOADS_DIR'] . '/woody-cli.lock', $this->site_key))) {
            $lock = $this->getConfig(sprintf($this->paths['WP_SITE_UPLOADS_DIR'] . '/woody-cli.lock', $this->site_key));
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
            $callback = $this->execIn(WP_ROOT_DIR, sprintf('WP_SITE_KEY=%s wp %s --allow-root', $this->site_key, $command));
            return $callback;
        } catch (\Exception $exception) {
            if ($exit_on_fail) {
                // Catch any error that might occure while clearing remote cache
                throw new \RuntimeException('Error : ' . $exception->getMessage(), $exception->getCode(), $exception);
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

        $this->fs->dumpFile(sprintf($this->paths['WP_SITE_UPLOADS_DIR'] . '/woody-cli.lock', $this->site_key), $lock);
    }

    /**
     * Generate wp_lock file
     * @param string $site_key Site key
     * @param string $command    Command to execute
     */
    protected function wp_unlock()
    {
        $this->fs->remove(sprintf($this->paths['WP_SITE_UPLOADS_DIR'], $this->site_key));
    }

    // WP Maintenance ON
    protected function woody_maintenance_on()
    {
        $this->consoleH2($this->output, 'Mode maintenance ON');
        $this->wp('woody_maintenance true');
    }

    // WP Maintenance OFF
    protected function woody_maintenance_off()
    {
        $this->consoleH2($this->output, 'Mode maintenance OFF');
        $this->wp('woody_maintenance false');
    }
}
