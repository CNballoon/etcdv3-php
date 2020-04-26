<?php

namespace Balloon\Component\Etcd;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;
use Balloon\Component\Etcd\Exception\EtcdException;
use Balloon\Component\Etcd\Exception\KeyExistsException;
use Balloon\Component\Etcd\Exception\KeyNotFoundException;
use Exception;
use RecursiveArrayIterator;
use RuntimeException;
use stdClass;

class GoMicroClient extends Client
{

    /**
     * @var string $name
     * @var array|mixed $strategy
     * @description 根据服务名字发现可用节点 -- 对于多个节点,需要一定的选取策略???
     */
    public function discoveryService($name, $strategy = null)
    {
        [$key, $rangeEnd] = $this->buildGoMicroKeyRange($name);
        try {
            $nodes = $this->getNode($key, $rangeEnd);
            $node = $this->chooseNode($nodes, $name);

            //一个 value 可能会有多个 node??? go-micro 中不同节点注册为不同值
            $address = $node['nodes'][0]['address'];
            $id = $node['nodes'][0]['id'];
            // $transport = $node['nodes'][0]['metadata']['transport'];
            return $address;
        } catch (\Exception $e) {
            throw new Exception('discover service fail');
        }
        return $address;
    }

    public function chooseNode(array $nodes, string $name): ?array
    {
        $available = [];
        foreach ($nodes as $key => $node) {
            $meta = json_decode(base64_decode($node['value']), true);

            if (!isset($meta['name']) || $meta['name'] != $name) {
                continue;
            }
            $available[] = $meta;
        }

        if (count($available) === 0) {
            return null;
        }
        return $available[random_int(0, count($available) - 1)];
    }

    public function buildGoMicroKeyRange(string $name): array
    {
        $key = "/micro/registry/$name/$name-00000000-0000-0000-0000-000000000000";
        $rangeEnd = "/micro/registry/$name/$name-ffffffff-ffff-ffff-ffff-ffffffffffff";
        return [$key, $rangeEnd];
    }
}
