<?php

namespace Encore\Kernel;

use Encore\Container\Container;
use Symfony\Component\Debug\Debug;
use Encore\Config\ServiceProvider as ConfigServiceProvider;

class Application extends Container
{
    const VERSION = '0.1';

    const OS_OSX = 'osx';
    const OS_WIN = 'win';
    const OS_LINUX = 'linux';
    const OS_OTHER = 'other';

    private $appPath;
    private $resourcesPath;
    private $vendorPath;

    private $booted = false;
    private $os;

    protected $mode;
    protected $mode;
    protected $serviceProviders = array();
    protected $loadedProviders = array();

    public function __construct($appPath, $resourcesPath, $vendorPath)
    {
        $this->appPath = $appPath;
        $this->resourcesPath = $resourcesPath;
        $this->vendorPath = $vendorPath;

        // First things first... Register this as the app
        $this->bind('app', $this);
    }

    public function launching($callback)
    {
        $this['events']->listen('app.launching', $callback);
    }

    public function quitting($callback)
    {
        $this['events']->listen('app.quitting', $callback);
    }

    public function setMode($mode)
    {
        $this->mode = empty($mode) ? 'dev' : $mode;

        Debug::enable(E_ALL, $this->mode === 'dev');
    }

    public function boot()
    {
        $this->addProvider(new ConfigServiceProvider($this));

        // Register service providers
        foreach ($config->get('app.providers') as $provider) {
            $this->addProvider($provider);
        }

        // Register aliases
        foreach ($config->get('app.aliases') as $alias => $class) {
            class_alias($class, $alias);
        }

        // Now run boot events on service providers
        array_walk($this->serviceProviders, function($p) {
            $p->boot();
        });

        if (file_exists($bootstrap = $this->appPath."/bootstrap/{$this->mode}.php")) {
            require $bootstrap;
        }

        require $this->appPath.'/start.php';

        $this->booted = true;
    }

    public function launch()
    {
        $this['events']->fire('app.launching');
    }

    public function quit()
    {
        $this['events']->fire('app.quitting');

        exit;
    }

    public function get()
    {
        return $this;
    }

    public function path()
    {
        return $this->appPath;
    }

    public function resourcesPath()
    {
        return $this->resourcesPath;
    }

    public function vendorPath()
    {
        return $this->vendorPath;
    }

    public function getOS()
    {
        return isset($this->os) ? $this->os : $this->findOS();
    }

    protected function findOS()
    {
        if (isset($this->os)) return $this->os;

        switch (true) {
            case stristr(PHP_OS, 'DAR'):
                return $this->os = static::OS_OSX;

            case stristr(PHP_OS, 'WIN'):
                return $this->os = static::OS_WIN;

            case stristr(PHP_OS, 'LINUX'):
                return $this->os = static::OS_LINUX;

            default:
                return $this->os = static::OS_OTHER;
        }
    }
}