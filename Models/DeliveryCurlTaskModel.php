<?php

namespace App\Providers\Delivery\Models;

use App\Services\Curl\CurlTaskAbstract;

class DeliveryCurlTaskModel extends CurlTaskAbstract
{
    /**
     * @var string
     */
    public $key;

    /**
     * @var int
     */
    private $tk_id;

    /**
     * @var int
     */
    public $method;


    /**
     * @param $resource
     * @param int $tk_id
     * @param int $method
     * @param string $key
     */
    public function __construct($resource, int $tk_id, int $method, string $key = '')
    {
        $this->resource = $resource;
        $this->tk_id = $tk_id;
        $this->method = $method;
        $this->key = $key;
    }

}
