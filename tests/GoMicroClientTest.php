<?php

namespace Balloon\Tests\Component\Etcd;

use Balloon\Component\Etcd\GoMicroClient;

class GoMicroClientTest extends BaseTest
{
    /** @var Client */
    protected $client;

    protected $header = [
        'Host' => 'localhost'
    ];

    protected $dirname = '/';

    protected function setUp(): void
    {
        $this->client = new GoMicroClient();
        $this->client->setHeader($this->header);
        $this->client->setRoot($this->dirname);
    }

    protected function tearDown(): void
    {
        $this->client->setRoot('/');
    }

    /**
     * @covers Balloon\Component\Etcd\Client::getVersion
     */
    public function testServiceDiscover()
    {
        $nodeData = $this->client->discoveryService('go.micro.learning.sum');
        $this->assertIsString($nodeData);
    }
}
