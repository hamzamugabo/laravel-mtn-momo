<?php

namespace Bmatovu\MtnMomo\Tests\Console;

use Bmatovu\MtnMomo\Tests\TestCase;

class RequestSecretCommandTest extends TestCase
{
    /**
     * @test
     */
    public function canRequestSecret()
    {
        $this->artisan('mtn-momo:request-secret')
             ->expectsOutput("\r\nStatus: 201 Created")
             ->expectsOutput("\r\nBody: {\"apiKey\":\"client-secret\"}\r\n")
             ->assertExitCode(0);

        $this->assertEquals($this->app['config']->get('mtn-momo.app.secret'), 'client-secret');

        $dotenv = \Dotenv\Dotenv::create($this->app->environmentPath());
        $dotenv->load();

        $this->assertEquals(getenv('MOMO_CLIENT_SECRET'), 'client-secret');
    }
}
