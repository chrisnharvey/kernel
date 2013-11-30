<?php

namespace Encore\Foundation;

use Illuminate\Container\Container;
use Illuminate\Config\FileLoader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Config\Repository as Config;

class Application extends Container
{
    private $wxApp;
    private $appPath;
    private $vendorPath;

    protected $env;

    public function __construct($appPath, $vendorPath)
    {
        $this->appPath = $appPath;
        $this->vendorPath = $vendorPath;

        $config = new Config(new FileLoader(new Filesystem, $this->appPath.'/config'), 'production');

        $this->instance('config', $config);
        $this->singleton('events', function() {
            return new \Illuminate\Events\Dispatcher($this);
        });

        // Register service providers

        // Register aliases
        foreach ($config->get('app.aliases') as $alias => $class) {
            class_alias($class, $alias);
        }

        $this->instance('app', $this);
    }

    public function launching($callback)
    {
        $this['events']->listen('app.launching', $callback);
    }

    public function quitting($callback)
    {
        $this['events']->listen('app.quitting', $callback);
    }

    public function setEnvironment($env)
    {
        $this->environment = $env;

        error_reporting(E_ALL);

        if ($this->environment != 'development') ini_set('display_errors', 0);
    }

    public function run()
    {
        $this->wxApp = new WxApplication;

        \wxApp::SetInstance($this->wxApp);
        \wxApp::SetAppName($config->get('app.name'));
        \wxApp::SetVendorName($config->get('app.vendor'));

        $this['events']->fire('app.launching');
    }

    public function quit()
    {
        $this['events']->fire('app.quitting');

        exit;
    }
}