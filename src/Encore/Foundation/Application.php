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
    private $booted = false;

    protected $env;
    protected $serviceProviders = array();
    protected $loadedProviders = array();

    public function __construct($appPath, $vendorPath)
    {
        $this->appPath = $appPath;
        $this->vendorPath = $vendorPath;

        $config = new Config(new FileLoader(new Filesystem, $this->appPath.'/config'), 'production');

        $this->instance('config', $config);

        $that = $this;

        $this->singleton('events', function() use ($that) {
            return new \Illuminate\Events\Dispatcher($that);
        });

        // Register service providers
        foreach ($config->get('app.providers') as $provider) {
            $this->register($provider);
        }

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

    public function boot()
    {
        $this->wxApp = new WxApplication;
        $this->wxApp->setApplication($this);

        \wxApp::SetInstance($this->wxApp);

        $this->booted = true;

        WxEntry();
    }

    public function launch()
    {
        $this['events']->fire('app.launching');
    }

    public function quit()
    {
        $this['events']->fire('app.quitting');
    }

    public function register($provider, $options = array())
    {
        // If the given "provider" is a string, we will resolve it, passing in the
        // application instance automatically for the developer. This is simply
        // a more convenient way of specifying your service provider classes.
        if (is_string($provider)) {
                $provider = new $provider($this);
        }

        $provider->register();

        // Once we have registered the service we will iterate through the options
        // and set each of them on the application so they will be available on
        // the actual loading of the service objects and for developer usage.
        foreach ($options as $key => $value) $this[$key] = $value;

        $this->markAsRegistered($provider);

        // If the application has already booted, we will call this boot method on
        // the provider class so it has an opportunity to do its boot logic and
        // will be ready for any usage by the developer's application logics.
        if ($this->booted) $provider->boot();

        return $provider;
    }

    /**
     * Mark the given provider as registered.
     *
     * @param \Illuminate\Support\ServiceProvider
     * @return void
     */
    protected function markAsRegistered($provider)
    {
        $this->serviceProviders[] = $provider;

        $this->loadedProviders[get_class($provider)] = true;
    }
}