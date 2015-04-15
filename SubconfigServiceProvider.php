<?php namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class SubconfigServiceProvider extends ServiceProvider {

	/**
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
	 *
	 * @return void
	 */


    /**
     * The default project to read the configs in case we could not choose the right one
     */

    const DEFAULT_CONFIG_SUBPROJECT = 'front';


    /**
     * Subdir in your "/config" to store the environment configurations (could be empty)
     */

    const BASE_CONFIG_KEY           = 'env';


    /**
     * Default key name for common configurations of the environments and subdomains
     */

    const COMMON_NAME               = 'common';


    /**
     * Extension of configuration files
     */

    const FILE_EXT                  = 'php';

    private $subProject = null;
    private $loadingScenario = [];


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

    public static function init() {
        $instance = new self(app());
        $instance->register();
    }


    /**
     * Get the subdomain (the very first part of it) from HTTP_HOST or choose the cli if we are running the cli,
     * or return the default subproject
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
     * Set config loading order
     *
     * 1. Load common keys (<BASE_CONFIG_KEY>.<COMMON>)
     * 2. Load common keys for the environment (<BASE_CONFIG_KEY>.<SUB_PROJECT>.<COMMON>)
     * 3. Load certain keys for the subproject and environment (<BASE_CONFIG_KEY>.<SUB_PROJECT>.<ENV>)
     *
     * @return array
     */

    private function _generateLoadingScenario() {
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
     * Load configurations and inject the to the root tree. If there is no configuration by path
     * from the sceario - try to require the file.
     */

    private function _loadConfigs() {
        foreach($this->loadingScenario as $elements) {

            $path = implode(".", $elements);
            $data = config($path);

            if(!$data) {
                $data = $this->_loadConfigFile($elements);
            }

            if(!isset($data['merge_config']) || $data['merge_config'] == false) {
                $this->_appendWithOverwrite($data);
            } else {
                $this->_appendWithMerge($data);
            }
        }
    }


    /**
     * Load configuration file.
     * @param $parts
     * @return mixed|null
     */

    private function _loadConfigFile($parts) {
        $fullPath = $this->app->configPath()
                        .DIRECTORY_SEPARATOR
                        .implode(DIRECTORY_SEPARATOR, $parts)
                        .'.'
                        .self::FILE_EXT;

        return file_exists($fullPath) && is_readable($fullPath) ? require($fullPath) : null;
    }

    
    /**
     * Method adds new config values to the root config tree and overwrites existing ones in case of match
     * @param $data
     */

    private function _appendWithOverwrite($data) {
        config($data);
    }


    /**
     * Method merges new config values to the root config tree without overwriting.
     * The value becomes an array if it was not before the merge.
     * @param $data
     */

    private function _appendWithMerge($data) {
        unset($data['merge_config']);

        $config = app('config');

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
     * Builds the double leveled array of values from the multileveled.
     * E.g.:
     *
     * k1.k2.k3 => v1
     *  or
     * k1.k2.k3 => [v1,
     *              v2,
     *              ...
     *              vn]
     *
     * @param $arr
     * @return array
     */

    private function _doubleLeveledArray($arr) {
        $recursiveIterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($arr));
        $result = [];
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
