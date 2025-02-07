<?php
/**
 * MtnMomoServiceProvider.
 */

namespace Bmatovu\MtnMomo;

use Monolog\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use Monolog\Handler\StreamHandler;
use Illuminate\Support\ServiceProvider;
use Bmatovu\MtnMomo\Console\BootstrapCommand;
use Bmatovu\MtnMomo\Console\RegisterIdCommand;
use Bmatovu\MtnMomo\Console\ValidateIdCommand;
use Bmatovu\MtnMomo\Console\RequestSecretCommand;

/**
 * Class MtnMomoServiceProvider.
 */
class MtnMomoServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mtn-momo.php' => base_path('config/mtn-momo.php'),
            ], 'config');

            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            $this->commands([
                BootstrapCommand::class,
                RegisterIdCommand::class,
                ValidateIdCommand::class,
                RequestSecretCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mtn-momo.php', 'mtn-momo');

        $this->app->bind('GuzzleHttp\ClientInterface', function () {
            $stack = HandlerStack::create();

            if ($this->app['config']->get('app.debug')) {
                $logger = $this->app['log']->getMonolog();
                $logger->pushHandler(new StreamHandler(storage_path('logs/mtn-momo.log')), Logger::DEBUG);
                // $formatter = new MessageFormatter("\r\n[Request] {request} [Response] \r\n{response}");
                $formatter = new MessageFormatter(MessageFormatter::DEBUG);
                $stack->push(Middleware::log($logger, $formatter));
            }

            return new Client([
                'handler' => $stack,
                'progress' => function () {
                    echo '. ';
                },
                'base_uri' => $this->app['config']->get('mtn-momo.api.base_uri'),
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Ocp-Apim-Subscription-Key' => $this->app['config']->get('mtn-momo.app.product_key'),
                ],
                'json' => [
                    'body',
                ],
            ]);
        });

        $this->app->bind(
            'Bmatovu\OAuthNegotiator\TokenRepositoryInterface',
            'Bmatovu\MtnMomo\Repositories\TokenRepository'
        );
    }
}
