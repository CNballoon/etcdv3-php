<?php

namespace Balloon\Tests\Component\Etcd;

use Balloon\Component\Etcd\Client;
use PHPUnit\Framework\TestCase;

abstract class BaseTest extends TestCase
{
    /** @var Client */
    protected $client;

    protected $header = [
        'Host' => 'localhost'
    ];

    protected $dirname = '/phpunit_test';


    protected function setUp(): void
    {
        $this->client = new Client();
        $this->client->setHeader($this->header);
        $this->client->setRoot($this->dirname);
    }

    protected function tearDown(): void
    {
        $this->client->setRoot('/');
    }
}
