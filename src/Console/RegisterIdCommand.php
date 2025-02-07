<?php
/**
 * RegisterIdCommand.
 */

namespace Bmatovu\MtnMomo\Console;

use GuzzleHttp\ClientInterface;
use Illuminate\Console\Command;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ConnectException;
use Bmatovu\MtnMomo\Traits\CommandUtilTrait;

/**
 * Class RegisterIdCommand.
 *
 * Register your client application ID with MTN Momo API.
 */
class RegisterIdCommand extends Command
{
    use CommandUtilTrait;

    /**
     * Guzzle HTTP client instance.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $client;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mtn-momo:register-id
                                {--id= : Client APP ID.}
                                {--callback= : Client APP redirect URI.}
                                {--d|debug= : Enable debugging for http requests.}
                                {--l|log=mtn-momo.log : Debug log file.}
                                {--f|force : Force the operation to run when in production.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Register client APP ID; 'apiuser'";

    /**
     * Create a new command instance.
     *
     * @param \GuzzleHttp\ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        parent::__construct();

        $this->client = $client;
    }

    /**
     * Execute the console command.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return void
     */
    public function handle()
    {
        if (! $this->runInProduction()) {
            return;
        }

        $this->printLabels('Client APP ID -> Registration');

        $client_id = $this->option('id');

        if (! $client_id) {
            $client_id = $this->laravel['config']->get('mtn-momo.app.id');

            $client_id = $this->ask('Use client app ID?', $client_id);
        }

        $client_redirect_uri = $this->option('callback');

        if (! $client_redirect_uri) {
            $client_redirect_uri = $this->laravel['config']->get('mtn-momo.app.redirect_uri');

            $client_redirect_uri = $this->ask('Use client app redirect URI?', $client_redirect_uri);
        }

        $is_registered = $this->registerClientId($client_id, $client_redirect_uri);

        if (! $is_registered) {
            return;
        }

        if ($this->confirm('Do you wish to request for the app secret?', true)) {
            $this->call('mtn-momo:request-secret', [
                '--id' => $client_id,
                '--force' => $this->option('force'),
                '--debug' => $this->option('debug'),
                '--log' => $this->option('log'),
            ]);
        }
    }

    /**
     * Register client ID.
     *
     * @param string $client_id
     * @param string $client_redirect_uri
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return bool Is registered.
     */
    protected function registerClientId($client_id, $client_redirect_uri)
    {
        try {
            $response = $this->client->request('POST', $this->laravel['config']->get('mtn-momo.api.client_id_uri'), [
                'headers' => [
                    'X-Reference-Id' => $client_id,
                ],
                'json' => [
                    'providerCallbackHost' => $client_redirect_uri,
                ],
            ]);

            $this->line("\r\nStatus: <fg=green>".$response->getStatusCode().' '.$response->getReasonPhrase().'</>');

            return true;
        } catch (ConnectException $ex) {
            $this->line("\r\n<fg=red>".$ex->getMessage().'</>');
        } catch (ClientException $ex) {
            $response = $ex->getResponse();
            $this->line("\r\nStatus: <fg=yellow>".$response->getStatusCode().' '.$response->getReasonPhrase().'</>');
            $this->line("\r\nBody: <fg=yellow>".$response->getBody()."\r\n</>");
        } catch (ServerException $ex) {
            $response = $ex->getResponse();
            $this->line("\r\nStatus: <fg=red>".$response->getStatusCode().' '.$response->getReasonPhrase().'</>');
            $this->line("\r\nBody: <fg=red>".$response->getBody()."\r\n</>");
        }

        return false;
    }
}
