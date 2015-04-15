##Subconfig Service Provider for Laravel 5

Subconfig Service Provider for Laravel 5 is the class dirved from `Illuminate\Support\ServiceProvider` that 
manages your configuration settings and allows you to use separate configuration settings depending on the environment and subproject (subdomain).
It's main feature is an ability to overwrite or merge the values from your configurations to the main configuration
tree. So you are able to use custom database settings, view paths, session settings etc. depending on the environment and subproject.

###Load Subconfig

This class should be placed in:

`<APP_ROOT_DIR>/Providers`

To load it just add `'App\Providers\SubconfigServiceProvider'` to `'providers'` section of the app.php 

###The environment

To set the environment just set the `APP_ENV` value in your `.env` file.

###Structure of the configs

Let's imagine that you have 3 subprojects of your app. Subprojects in our case are: admin, front and api
parts, organised as domains (eg. admin.yourdomain.com, yourdomain.com and api.yourdomain.com). For each
subproject you have different environments with different parameters of database connection, view paths
etc. For example we have 3 environments: dev (local), stage, prod.

The structure of your configuration files in this case should look like:

```
/config
	./env
    		./admin
     			dev.php /* for dev environment
				stage.php /* for stage environment
				prod.php /* for prod environment
				common.php /* common config for all environments of admin subproject
			./front
     			dev.php /* for dev environment
				stage.php /* for stage environment
				prod.php /* for prod environment
				common.php /* common config for all environments of front subproject
			./api
     			dev.php /* for dev environment
				stage.php /* for stage environment
				prod.php /* for prod environment
				common.php /* common config for all environments of api subproject
```

In case of command line usage, you should also create configs for CLI:

```
			./cli
     			dev.php /* for dev environment
				stage.php /* for stage environment
				prod.php /* for prod environment
				common.php /* common config for all environments of cli subproject
```

All settings from this files will be loaded to the main tree under key 'env' and Subconfig Service Provider class will automatically
take the correspondig settings from that tree and apply it to the root.

###Additional options

By default in case of config key match the SubconfigServiceProvider will replace the existing values in the main config tree to the values from your
configs. So if you define your database configuration in the config file like this:

```php

'database' => [
	'connections' => [
		'mysql' => [....]
	]
]

```

The values in connections array of the main tree will be overwritten by the one that you provided. However if you would like to
add a NEW key to existing section of the config array just use flat array key naming like this (as from previous example):

```php 'database.connections.YOUR_DATABASE_TYPE' => [....] ```

Also there is a way to add an elements to existing INDEXED config array and merge all values from your config to main tree. To do so just add 'merge_config' => true option to your config.

For example we have:

```php

'view' => [
    'paths' => [
        '... some path'
    ]
    ...
]

```


and we would like to add another path to 'paths' array. To do so just create the same section with the value you want to add into your subconfig like this and add a key 'merge_config' => true

```php

...
'merge_config' => true,
'view' => [
    'paths' => [
        'another path'
    ]
]

```


The final result in main config tree will be:

```php

'view' => [
    'paths' => [
    	'... some path',
        'another path'
    ]
]

```

Your new path value will be added to existing one.

###Config loading order

There is a config loading order. The keys and values from the previous loaded config will be overwritten by the next loaded config in case of match and if you have not specified 'merge_config' => true.

The loading order is:

0. The creation of main config tree by Laravel. All configs will be loaded to the tree, including your subprojects configs (your subprojects configs will be stored in subkeys eg. 'env' => ['front'...] etc).
1. Load common config values (file: &lt;root&gt;/config/env/&lt;COMMON&gt;.php)
2. Load common config for the environment (file: &lt;root&gt;/config/env/&lt;SUB_DOMAIN&gt;/&lt;COMMON&gt;.php)
3. Load certain config for the subdoman and environment (file: &lt;root&gt;/config/env/&lt;SUB_DOMAIN&gt;/&lt;ENV&gt;.php)

###Using cli

If you would like your subconfigs to be loaded in CLI project - just call the built-in facade method like this.

```php

...
use App\Providers\SubconfigServiceProvider;

...

ConfigServiceProvider::init();

```



