<?php
/**
 * CommandUtilTrait.
 */

namespace Bmatovu\MtnMomo\Traits;

use Closure;
use Monolog\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use Monolog\Handler\StreamHandler;

/**
 * Trait CommandUtilTrait.
 */
trait CommandUtilTrait
{
    /**
     * Confirm to run command in production.
     *
     * @param  string $warning
     *
     * @return bool
     */
    protected function runInProduction($warning = 'Application In Production!')
    {
        if ($this->getLaravel()->environment() != 'production') {
            return true;
        }

        if ($this->option('force')) {
            return true;
        }

        $this->comment(str_repeat('*', strlen($warning) + 12));
        $this->comment('*     '.$warning.'     *');
        $this->comment(str_repeat('*', strlen($warning) + 12));
        $this->output->writeln('');

        $confirmed = $this->confirm('Do you really wish to proceed?');

        if (! $confirmed) {
            $this->comment('Command Cancelled!');

            return false;
        }

        return true;
    }

    /**
     * Print formatted labels.
     *
     * @param  string  $title
     * @param  array|string  $body
     *
     * @return void
     */
    protected function printLabels($title, $body = null)
    {
        $this->line("<options=bold>{$title}</>");

        if (is_null($body)) {
            return;
        }

        $body = is_array($body) ? $body : [$body];

        foreach ($body as $content) {
            $this->line("{$content}");
        }

        $this->output->writeln('');
    }

    /**
     * @deprecated
     * Print formatted labels.
     *
     * @param  string  $title
     * @param  array|string  $body
     * @param  int $length
     *
     * @return void
     */
    protected function oldPrintLabels($title, $body = null, $length = 74)
    {
        $this->line('|'.str_repeat('-', $length));
        $this->line("| {$title}");
        $this->line('|'.str_repeat('-', $length));

        if (is_null($body)) {
            return;
        }

        $body = is_array($body) ? $body : [$body];

        foreach ($body as $content) {
            $this->line("| {$content}");
        }

        $this->output->writeln('');
    }

    /**
     * Setup guzzle client.
     *
     * @param \Closure|null $progress
     * @param bool|false $debug
     *
     * @throws \Exception
     *
     * @return \GuzzleHttp\Client
     */
    protected function prepareGuzzle(Closure $progress = null, $debug = false)
    {
        $stack = HandlerStack::create();

        if ($debug) {
            // $logger = new Logger('Logger');
            $logger = $this->laravel['log']->getMonolog();
            $logger->pushHandler(new StreamHandler(storage_path('logs/mtn-momo.log')), Logger::DEBUG);
            $stack->push(Middleware::log($logger, new MessageFormatter(MessageFormatter::DEBUG)));
            // $stack->push(Middleware::log($logger, new MessageFormatter("\r\n[Request] >>>>> {request}. [Response] >>>>> \r\n{response}.")));
        }

        return new Client([
            'handler' => $stack,
            'progress' => $progress,
            'base_uri' => $this->laravel['config']->get('mtn-momo.uri.base'),
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Ocp-Apim-Subscription-Key' => $this->laravel['config']->get('mtn-momo.product_key'),
            ],
            'json' => [
                'body',
            ],
        ]);
    }

    /**
     * Determine replacement regex pattern a setting.
     *
     * @param  string $name ENV_VALUE, like; `APP_NAME`
     * @param  string $key Compose setting name, like `app.name`
     *
     * @return string        Regex pattern
     */
    protected function getRegex($name, $key)
    {
        $escaped = preg_quote($this->laravel['config']->get($key), '/');

        return "/^{$name}=[\"']?{$escaped}[\"']?/m";
    }

    /**
     * Write | replace setting in .env file.
     *
     * @param  string $name ENV_VALUE, like; `APP_NAME`
     * @param  string $key Compose setting name, like `app.name`
     * @param  string $value Setting value
     *
     * @return void
     */
    protected function updateSetting($name, $key, $value)
    {
        $env = $this->laravel->environmentFilePath();

        $pattern = $this->getRegex($name, $key);

        if (preg_match($pattern, file_get_contents($env))) {
            file_put_contents($env, preg_replace($pattern, "{$name}=\"{$value}\"", file_get_contents($env)));
        } else {
            $setting = "\r\n{$name}=\"{$value}\"\r\n";
            file_put_contents($env, file_get_contents($env).$setting);
        }

        // Update in memory.
        $this->laravel['config']->set([$key => $value]);
    }
}
