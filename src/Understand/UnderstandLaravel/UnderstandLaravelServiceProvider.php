<?php

namespace Understand\UnderstandLaravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class UnderstandLaravelServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('understand/understand-laravel');

        if ($this->app['config']->get('understand-laravel::config.log_types.eloquent_log.enabled'))
        {
            $this->listenEloquentEvents();
        }

        if ($this->app['config']->get('understand-laravel::config.log_types.laravel_log.enabled'))
        {
            $this->listenLaravelEvents();
        }

        if ($this->app['config']->get('understand-laravel::config.log_types.exception_log.enabled'))
        {
            $this->listenExceptionEvents();
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerFieldProvider();
        $this->registerTokenProvider();
        $this->registerLogger();
        $this->registerModelEventListenerProvider();
        $this->registerExceptionEncoder();
    }

    /**
     * Register the Understand field provider
     *
     * @return void
     */
    protected function registerFieldProvider()
    {
        $this->app->bindShared('understand.field-provider', function($app)
        {
            $fieldProvider = new FieldProvider();

            $fieldProvider->setSessionStore($app['session.store']);
            $fieldProvider->setRouter($app['router']);
            $fieldProvider->setRequest($app['request']);
            $fieldProvider->setEnvironment($app->environment());
            $fieldProvider->setTokenProvider($app['understand.token-provider']);

            return $fieldProvider;
        });

        $this->app->booting(function()
        {
            $loader = AliasLoader::getInstance();
            $loader->alias('UnderstandFieldProvider', 'Understand\UnderstandLaravel\Facades\UnderstandFieldProvider');
        });
    }

    /**
     * Register token generator class
     *
     * @return void
     */
    protected function registerTokenProvider()
    {
        $this->app->singleton('understand.token-provider', function()
        {
            return new TokenProvider();
        });
    }

    /**
     * Register exception encoder
     *
     * @return void
     */
    protected function registerExceptionEncoder()
    {
        $this->app->bindShared('understand.exception-encoder', function()
        {
            return new ExceptionEncoder;
        });
    }

    /**
     * Register understand logger
     *
     * @return void
     */
    protected function registerLogger()
    {
        $this->app->singleton('understand.logger', function($app)
        {
            $fieldProvider = $app['understand.field-provider'];
            $handler = $this->resolveHandler($app);

            return new Logger($fieldProvider, $handler);
        });

        $this->app->booting(function()
        {
            $loader = AliasLoader::getInstance();
            $loader->alias('UnderstandLogger', 'Understand\UnderstandLaravel\Facades\UnderstandLogger');
        });
    }

    /**
     * Return default handler
     *
     * @param type $app
     * @return \Understand\UnderstandLaravel\Handlers\SyncHandler|\Understand\UnderstandLaravel\Handlers\LaravelQueueHandler
     * @throws \ErrorException
     */
    protected function resolveHandler($app)
    {
        $inputToken = $app['config']->get('understand-laravel::config.token');
        $apiUrl = $app['config']->get('understand-laravel::config.url', 'https://api.understand.io');
        $silent = $app['config']->get('understand-laravel::config.silent');
        $handlerType = $app['config']->get('understand-laravel::config.handler');

        if ($handlerType == 'sync')
        {
            return new Handlers\SyncHandler($inputToken, $apiUrl, $silent);
        }

        if ($handlerType == 'queue')
        {
            return new Handlers\LaravelQueueHandler($inputToken, $apiUrl, $silent);
        }

        throw new \ErrorException('understand-laravel handler misconfiguration:' . $handlerType);
    }

    /**
     * Register model event listener provider
     *
     * @return void
     */
    protected function registerModelEventListenerProvider()
    {
        $this->app->bindShared('understand.model-event-listener-provider', function($app)
        {
            $logger = $this->app['understand.logger'];
            $additional = $this->app['config']->get('understand-laravel::config.additional.model_log', []);

            return new ModelEventListener($logger, $additional);
        });
    }

    /**
     * Listen Laravel exception logs
     */
    protected function listenExceptionEvents()
    {
        $this->app['events']->listen('illuminate.log', function($level, $message, $context)
        {
            if ($message instanceof Exceptions\HandlerException)
            {
                return;
            }

            if (!$message instanceof \Exception)
            {
                return;
            }

            $log = $this->app['understand.exception-encoder']->exceptionToArray($message);

            if ($context)
            {
                $log['context'] = $context;
            }

            $log['tags'] = ['exception_log'];
            $log['level'] = $level;

            $additional = $this->app['config']->get('understand-laravel::config.log_types.exception_log.meta', []);
            $this->app['understand.logger']->log($log, $additional);
        });
    }

    /**
     * Listen Laravel logs
     *
     * @return void
     */
    protected function listenLaravelEvents()
    {
        $this->app['events']->listen('illuminate.log', function($level, $message, $context)
        {
            if ($message instanceof Exceptions\HandlerException)
            {
                return;
            }

            if ($message instanceof \Exception)
            {
                return;
            }

            if (is_string($message))
            {
                $log['message'] = $message;
            }
            else
            {
                $log = $message;
            }

            if ($context)
            {
                $log['context'] = $context;
            }

            $log['tags'] = ['laravel_log'];
            $log['level'] = $level;

            $additional = $this->app['config']->get('understand-laravel::config.log_types.laravel_log.meta', []);
            $this->app['understand.logger']->log($log, $additional);
        });
    }

    /**
     * Listen eloquent model events and log them
     *
     * @return void
     */
    protected function listenEloquentEvents()
    {
        $modelLogger = $this->app['understand.model-event-listener-provider'];

        $events = [
            'eloquent.created*' => 'created',
            'eloquent.updated*' => 'updated',
            'eloquent.deleted*' => 'deleted',
            'eloquent.restored*' => 'restored',
        ];

        foreach ($events as $listenerName => $eventName)
        {
            $this->app['events']->listen($listenerName, function($model) use($modelLogger, $eventName)
            {
                $modelLogger->logModelEvent($eventName, $model);
            });
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('understand.field-provider', 'understand.logger');
    }

}
