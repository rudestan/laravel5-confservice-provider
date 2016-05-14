<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * @package SubconfigServiceProvider
 *
 * Subconfig service provider could merge values from your custom configuration files
 * depending on the subproject (subdomain) and on the environment. It
 * could inject certain config to the root config tree and overwrite
 * or merge the values.
 * To set the environment - use the "APP_ENV" variable in .env file.
 *
 *
 * the structure of the config files could for example:
 *
 * /config
 *      ./env
 *          ./subdomain1
 *              env1.php /* eg. for dev environment
 *              env2.php /* eg. for stage environment
 *              ...
 *              envN.php /* for N environment
 *              common.php /* common config for all environments of project on subdomain1
 *          ./subdoman2
 *              ...
 *              envN.php
 *          ...
 *          ./subdomainN
 *          common.php /* common config for all subdomains and all environments
 */

class SubconfigServiceProvider extends ServiceProvider
{
    /**
     * The default project to read the configs in case we could not choose the right one
     */
    const DEFAULT_CONFIG_SUBPROJECT = 'front';

    /**
     * Subdir in your "/config" to store the environment configurations (could be empty)
     */
    const BASE_CONFIG_KEY = 'env';

    /**
     * Default key name for common configurations of the environments and subdomains
     */
    const COMMON_NAME = 'common';

    /**
     * Extension of configuration files
     */
    const FILE_EXT = 'php';

    protected $subProject = null;

    protected $loadingScenario = [];

    /**
     * Register the Service Provider
     */
	public function register()
	{
        $this->subProject      = $this->_getSubDomain();
        $this->loadingScenario = $this->_generateLoadingScenario();
        $this->_loadConfigs();
	}

    /**
     * Facade method to register provider eg. from console commands
     */
    public static function init()
    {
        $instance = new self(app());
        $instance->register();
    }

    /**
     * Returns the subdomain (the very first part of it) from HTTP_HOST or choose the cli if we are running the cli,
     * or return the default subproject
     *
     * @return string
     */
    private function _getSubDomain() {

        if(php_sapi_name() == 'cli') {
            return 'cli';
        }

        $host = explode('.', $_SERVER['HTTP_HOST']);

        if(is_array($host) && isset($host[0])) {
            return strtolower($host[0]);
        }

        return self::DEFAULT_CONFIG_SUBPROJECT;
    }

    /**
     * Sets config loading order
     *
     * 1. Load common keys (<BASE_CONFIG_KEY>.<COMMON>)
     * 2. Load common keys for the environment (<BASE_CONFIG_KEY>.<SUB_PROJECT>.<COMMON>)
     * 3. Load certain keys for the subproject and environment (<BASE_CONFIG_KEY>.<SUB_PROJECT>.<ENV>)
     *
     * @return array
     */
    protected function _generateLoadingScenario() {
        return [
            [
                self::BASE_CONFIG_KEY,
                self::COMMON_NAME
            ],
            [
                self::BASE_CONFIG_KEY,
                $this->subProject,
                self::COMMON_NAME
            ],
            [
                self::BASE_CONFIG_KEY,
                $this->subProject,
                $this->app->environment()
            ]
        ];
    }

    /**
     * Loads configurations and inject the to the root tree. If there is no configuration by path
     * from the scenario - try to require the file.
     */
    protected function _loadConfigs() {
        foreach($this->loadingScenario as $elements) {

            $path = implode(".", $elements);
            $data = config($path);

            if(!$data) {
                $data = $this->_loadConfigFile($elements);
            }

            if(!$data) {
                continue;
            }

            if(!isset($data['merge_config']) || $data['merge_config'] == false) {
                $this->_appendWithOverwrite($data);
            } else {
                $this->_appendWithMerge($data);
            }
        }
    }

    /**
     * Loads configuration file.
     *
     * @param array $parts
     *
     * @return mixed|null
     */
    protected function _loadConfigFile(array $parts) {
        $fullPath = $this->app->configPath()
                        .DIRECTORY_SEPARATOR
                        .implode(DIRECTORY_SEPARATOR, $parts)
                        .'.'
                        .self::FILE_EXT;

        return file_exists($fullPath) && is_readable($fullPath) ? require($fullPath) : null;
    }
    
    /**
     * Adds new config values to the root config tree and overwrites existing ones in case of match
     *
     * @param array $data
     */
    protected function _appendWithOverwrite(array $data) {
        config($data);
    }

    /**
     * Merges new config values to the root config tree without overwriting.
     * The value becomes an array if it was not before the merge.
     *
     * @param array $data
     */
    protected function _appendWithMerge(array $data) {
        unset($data['merge_config']);

        $config    = app('config');
        $flatArray = $this->_doubleLeveledArray($data);

        foreach($flatArray as $k => $v) {
            $existingElement = $config->get($k);
            if(!$existingElement) {
                $config->set($k, $v);
            } else {
                if(!is_array($existingElement)) {
                    $existingElement = [$existingElement];
                }

                if(!is_array($v)) {
                    $existingElement[] = $v;
                } else {
                    $existingElement = array_merge($existingElement, $v);
                }

                $config->set($k, $existingElement);
            }
        }
    }

    /**
     * Builds double leveled array of values from multi-leveled.
     * E.g.:
     *
     * k1.k2.k3 => v1
     *  or
     * k1.k2.k3 => [v1,
     *              v2,
     *              ...
     *              vn]
     *
     * @param array $arr
     *
     * @return array
     */
    protected function _doubleLeveledArray(array $arr) {
        $recursiveIterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($arr));
        $result            = [];

        foreach ($recursiveIterator as $leafValue) {
            $keys = [];
            foreach (range(0, $recursiveIterator->getDepth()) as $depth) {
                $curKey = $recursiveIterator->getSubIterator($depth)->key();
                if(!is_int($curKey))
                    $keys[] = $curKey;
            }

            $key = implode('.', $keys);
            if(isset($result[$key])) {
                if(!is_array($result[$key])) {
                    $result[$key] = [$result[$key]];
                }
                $result[$key][] = $leafValue;
            } else {
                $result[$key] = $leafValue;
            }
        }

        return $result;
    }
}
