<?php

namespace Balloon\Component\Etcd;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;
use Balloon\Component\Etcd\Exception\EtcdException;
use Balloon\Component\Etcd\Exception\KeyExistsException;
use Balloon\Component\Etcd\Exception\KeyNotFoundException;
use RecursiveArrayIterator;
use RuntimeException;
use stdClass;

class Client
{
    private $server = 'http://127.0.0.1:2379';

    private $guzzleclient;

    private $apiversion;

    private $headers;

    private $root = '';

    public function __construct($server = '', $version = 'v3')
    {
        $server = rtrim($server, '/');

        if ($server) {
            $this->server = $server;
        }

        // echo 'Testing server ' . $this->server . PHP_EOL;

        $this->apiversion = $version;
        $this->guzzleclient = new GuzzleClient(
            array(
                'base_uri' => $this->server
            )
        );
    }

    public static function constructWithGuzzleClient(GuzzleClient $guzzleClient, $server, $version)
    {
        $client = new Client($server, $version);
        $client->setGuzzleClient($guzzleClient);
        return $client;
    }


    /**
     * Set custom GuzzleClient in Client
     * @param GuzzleClient $guzzleClient
     * @return Client
     */
    public function setGuzzleClient(GuzzleClient $guzzleClient)
    {
        $this->guzzleclient = $guzzleClient;
        return $this;
    }

    public function setHeader($headers)
    {
        $this->headers = $headers;
    }

    /**
     * Set the default root directory. the default is `/`
     * If the root is others e.g. /linkorb when you set new key,
     * or set dir, all of the key is under the root
     * e.g.
     * <code>
     *    $client->setRoot('/linkorb');
     *    $client->set('key1, 'value1');
     *    // the new key is /linkorb/key1
     * </code>
     * @param string $root
     * @return Client
     */
    public function setRoot($root)
    {
        if (strpos($root, '/') !== 0) {
            $root = '/' . $root;
        }
        $this->root = rtrim($root, '/');
        return $this;
    }

    /**
     * Build key space operations
     * @param string $key
     * @return string
     */
    private function buildKeyUri($key)
    {
        $uri = '';
        switch ($key) {
            case 'PutRequest':
                $uri = '/v3/kv/put';
                break;
            case 'RangeRequest':
                $uri = '/v3/kv/range';
                break;
            case 'DeleteRangeRequest':
                $uri = '/v3/kv/deleterange';
                break;

            default:
                # code...
                break;
        }
        return $uri;
    }


    /**
     * get server version -- 获取 etcd 版本
     * @param string $uri
     * @return mixed
     */
    public function getVersion($uri)
    {
        $response = $this->guzzleclient->get($uri, ['headers' => $this->headers]);

        $data = json_decode($response->getBody(), true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new RuntimeException('Unable to parse response body into JSON: ' . json_last_error());
        }
        return $data;
    }

    /**
     * Set the value of a key
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @param array $condition
     * @return stdClass
     */
    public function set($key, $value, $ttl = null, $condition = array())
    {

        if ($ttl) {
            // $data['ttl'] = $ttl;
        }

        try {
            $response = $this->guzzleclient->post($this->buildKeyUri('PutRequest'), array(
                'json' => ['key' => base64_encode($key), 'value' => base64_encode($value)]
            ));
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
        }
        $body = json_decode($response->getBody(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new RuntimeException('Unable to parse response body into JSON: ' . json_last_error());
        }
        return $body;
    }

    /**
     * Retrieve the value of a key
     * @param string $key
     * @param array $query the extra query params
     * @return array
     * @throws KeyNotFoundException
     */
    public function getNode($key, $rangeEnd = null)
    {
        try {
            $response = $this->guzzleclient->post(
                $this->buildKeyUri('RangeRequest'),
                ['json' => ['key' => base64_encode($key)] + ($rangeEnd ? ['range_end' => base64_encode($rangeEnd)] : [])]
            );
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
        }
        $body = json_decode($response->getBody(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new RuntimeException('Unable to parse response body into JSON: ' . json_last_error());
        }
        if (!isset($body['count'])) {
            throw new KeyNotFoundException('no value found', 404);
        }
        return $body['kvs'];
    }

    /**
     * Retrieve the value of a key
     * @param string $key
     * @param array $flags the extra query params
     * @return string the value of the key.
     * @throws KeyNotFoundException
     */
    public function get($key, array $flags = array())
    {
        try {
            $node = $this->getNode($key, $flags);
            return base64_decode($node[0]['value']);
        } catch (KeyNotFoundException $ex) {
            throw $ex;
        }
    }

    /**
     * Update an existing key with a given value.
     * @param strint $key
     * @param string $value
     * @param int $ttl
     * @param array $condition The extra condition for updating
     * @return array $body
     * @throws KeyNotFoundException
     */
    public function update($key, $value, $ttl = 0, $condition = array())
    {
        $extra = array('prevExist' => 'true');

        if ($condition) {
            $extra = array_merge($extra, $condition);
        }
        $body = $this->set($key, $value, $ttl, $extra);
        if (isset($body['errorCode'])) {
            throw new KeyNotFoundException($body['message'], $body['errorCode']);
        }
        return $body;
    }

    /**
     * remove a key
     * @param string $key
     * @return array|stdClass
     * @throws EtcdException
     */
    public function rm($key)
    {
        try {
            $response = $this->guzzleclient->post($this->buildKeyUri('DeleteRangeRequest'), $key);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
        }
        $body = json_decode($response->getBody(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new RuntimeException('Unable to parse response body into JSON: ' . json_last_error());
        }

        if (isset($body['errorCode'])) {
            throw new EtcdException($body['message'], $body['errorCode']);
        }

        return $body;
    }

    private $dirs = array();

    private $values = array();

    /**
     * Traversal the directory to get the keys.
     * @param RecursiveArrayIterator $iterator
     * @return array
     */
    private function traversalDir(RecursiveArrayIterator $iterator)
    {
        $key = '';
        while ($iterator->valid()) {
            if ($iterator->hasChildren()) {
                $this->traversalDir($iterator->getChildren());
            } else {
                if ($iterator->key() == 'key' && ($iterator->current() != '/')) {
                    $this->dirs[] = $key = $iterator->current();
                }

                if ($iterator->key() == 'value') {
                    $this->values[$key] = $iterator->current();
                }
            }
            $iterator->next();
        }
        return $this->dirs;
    }
}
