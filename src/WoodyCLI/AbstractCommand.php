<?php

namespace WoodyCLI;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * RaccourciCommand
 *
 * @author Léo POIROUX <leo@raccourci.fr>
 * @copyright (c) 2017, Raccourci Agency
 * @package woody-cli
 */
abstract class AbstractCommand extends Command
{
    /**
     * List of valid environnements
     * @var array
     */
    protected $envs = array('dev', 'integ', 'preprod', 'prod');

    /**
     * Default command timeout
     * @var int
     */
    protected $timeout = 600;

    /**
     * Wether to mute output or not
     * @var boolean
     */
    protected $mute = false;

    /**
     * Buffer callback process function
     * @var callable|null
     */
    protected $processBufferCallback = null;

    /**
     * __construct()
     * @param string $name
     */
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->setProcessBufferCallback();
    }

    /**
     * Default buffer callback process function
     * @param string $type
     * @param string $buffer
     */
    public static function defaultProcessBufferCallback($type, $buffer)
    {
        echo $buffer;
    }

    /**
     * Set the buffer callback process function
     * @param callable|null A callable
     */
    protected function setProcessBufferCallback($callable = null)
    {
        if (is_null($callable)) {
            $callable = array($this, 'defaultProcessBufferCallback');
        } elseif (!is_callable($callable)) {
            throw new \RuntimeException('You must provied a valide callable');
        }

        $this->processBufferCallback = $callable;
    }

    /**
     * Execute a given command. An exception is thrown if the operation has not
     * been successful, string output otherwise.
     * @param  string            $command The command to be executed
     * @return string            stdout
     * @throws \RuntimeException
     */
    protected function exec($command, $timeout = null, $forcemute = false)
    {
        // Use default Timeout
        if ($timeout == null) {
            $timeout = $this->timeout;
        }

        // if (!is_array($command)) {
        //     $command = explode(' ', $command);
        // }

        $process = new Process([]);
        $process = Process::fromShellCommandline($command);
        $process->setTimeout($timeout);
        $process->run((true === $this->mute || $forcemute) ? null : $this->processBufferCallback);

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        $return = $process->getOutput();

        return trim($return);
    }

    /**
     * Execute a command from a given path
     * @param  string $path    Path where the command is executed
     * @param  string $command COmmand to execute
     * @return string stdout
     */
    protected function execIn($path, $command, $timeout = null)
    {
        return $this->exec(sprintf('cd %s && %s', $path, $command), $timeout);
    }

    /**
     * Create a symbolic link
     * @param  string $src Symlink source
     * @param  string $dst Symlink destination
     * @return string stdout
     */
    protected function symlink($src, $dst)
    {
        return $this->exec(sprintf('ln -sfn %s %s', $src, $dst));
    }

    /**
     * Return the content of a yml configuration file
     * @param  string            $config_path Path to the file
     * @return array             Content of the configuration
     * @throws \RuntimeException
     */
    public function getConfig($config_path)
    {
        $return = '';

        if (file_exists($config_path)) {
            try {
                $return = Yaml::parseFile($config_path);
            } catch (ParseException $e) {
                throw new \RuntimeException(sprintf('Unable to parse the YAML string: %s', $e->getMessage()));
            }
        }

        return $return;
    }

    /**
     * Return the name of a branch according to an env name
     * @param  string $env Env name
     * @return string $return Branch name
     */
    public function getGitBranch($env)
    {
        switch ($env) {
            case 'dev':
                return 'develop';
            case 'integ':
                return 'develop';
            case 'preprod':
                return 'release';
            default:
                return 'master';
        }
    }

    /**
     * Check wether a given env is valid or not
     * @param  string  $env
     * @return boolean
     */
    public function isValidEnv($env)
    {
        return in_array($env, $this->envs);
    }

    /**
     * Return the list of valid envs
     * @return array
     */
    public function getEnvs()
    {
        return $this->envs;
    }

    /**
     * Check wether a directory is empty or not
     * @param string $path Path to the directory to check
     * @return bool
     */
    public function directoryIsEmpty($path)
    {
        $content = scandir($path);
        $content = array_filter($content, fn($entry) => $entry !== '.' && $entry !== '..');

        return count($content) === 0;
    }

    /**
     * From an array of options return a valid CLI string of the options
     * If an option value is set to true, only the key is added
     * @param  array  $options
     * @return string
     */
    protected function arrayToOptions(&$options)
    {
        foreach ($options as $key => &$value) {
            $value = '--' . (true === $value ? $key : $key . '=' . $value);
        }

        $options = implode(' ', $options);
    }

    /**
     * Flattent Array
     * @param array $array
     * @return array
     */
    protected function flattenArray($array)
    {
        return is_array($array) ? array_reduce($array, fn($c, $a) => array_merge($c, $this->flattenArray($a)), []) : [$array];
    }

    ////////////////////

    protected function consoleH1($output, $msg)
    {
        $style = new OutputFormatterStyle('cyan', null, array('bold'));
        $output->getFormatter()->setStyle('h1', $style);

        $output->writeln(sprintf('<h1>----------------------------------------------</>', $msg));
        $output->writeln(sprintf('<h1>%s</>', $msg));
        $output->writeln(sprintf('<h1>----------------------------------------------</>', $msg));
    }

    protected function consoleH2($output, $msg)
    {
        $style = new OutputFormatterStyle('yellow', null, array());
        $output->getFormatter()->setStyle('h2', $style);
        $output->writeln(sprintf('<h2>*** %s</>', mb_strtoupper($msg)));
    }

    protected function consoleList($output, $msg, $current = 0, $max = 0)
    {
        $style = new OutputFormatterStyle('magenta', null, array());
        $output->getFormatter()->setStyle('list', $style);

        if (!empty($current) && !empty($max)) {
            $output->writeln(sprintf('<list># %s/%s %s</>', $current, $max, $msg));
        } else {
            $output->writeln(sprintf('<list># %s</>', $msg));
        }
    }

    protected function consoleExec($output, $msg, $color = 'green')
    {
        $style = new OutputFormatterStyle($color, null, array());
        $output->getFormatter()->setStyle('cmd', $style);
        $output->writeln(sprintf('<cmd>- %s</>', $msg));
    }

    protected function consoleText($output, $msg)
    {
        $output->writeln(sprintf('%s', $msg));
    }
}
