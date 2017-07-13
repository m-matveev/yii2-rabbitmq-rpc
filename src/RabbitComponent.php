<?php
namespace mamatveev\yii2rabbitmq;

use PhpAmqpLib\Connection\AbstractConnection;
use yii\base\InvalidConfigException;

class RabbitComponent extends \yii\base\Component
{
    /**
     * @var string
     */
    public $host;

    /**
     * @var int
     */
    public $port;

    /**
     * @var string
     */
    public $user;

    /**
     * @var string
     */
    public $password;

    /**
     * @var string connection class
     */
    public $amqp_connection_class = \PhpAmqpLib\Connection\AMQPLazyConnection::class;

    /**
     * @var string
     */
    public $vhost = '/';

    /**
     * @var bool
     */
    public $insist = false;

    /**
     * @var string
     */
    public $login_method = 'AMQPLAIN';

    /**
     * @var mixed
     */
    public $login_response = null;

    /**
     * @var string
     */
    public $locale = 'en_US';

    /**
     * @var float
     */
    public $connection_timeout = 3.0;

    /**
     * @var float
     */
    public $read_write_timeout = 3.0;

    /**
     * @var null|mixed
     */
    public $context = null;

    /**
     * @var bool
     */
    public $keepalive = false;

    /**
     * @var float
     */
    public $heartbeat = 0;

    /**
     * @var null|AbstractConnection
     */
    protected $amqp_connection = null;

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        foreach (['host', 'user', 'port'] as $configParam)
        {
            if (empty($this->{$configParam})) {
                throw new InvalidConfigException("{$configParam} cannot be empty");
            }
        }

        if (!class_exists($this->amqp_connection_class))
        {
            throw new InvalidConfigException("connection class does not exist");
        }

        parent::init();
    }

    /**
     * init a rpc client
     * @param $exchangeName
     * @return RpcClient
     */
    public function initClient($exchangeName)
    {
        $connection = $this->getConnection();
        $client = new RpcClient($connection);
        $client->initClient($exchangeName);
        return $client;
    }


    /**
     * init a rpc server
     * @param $exchangeName
     * @return RpcServer
     */
    public function initServer($exchangeName)
    {
        $connection = $this->getConnection();
        $server = new RpcServer($connection);
        $server->initServer($exchangeName);
        return $server;
    }


    /**
     * @return null|AbstractConnection
     */
    protected function getConnection()
    {
        if ($this->amqp_connection == null) {
            $this->amqp_connection = new $this->amqp_connection_class(
                $this->host,
                $this->port,
                $this->user,
                $this->password,
                $this->vhost,
                $this->insist,
                $this->login_method,
                $this->login_response,
                $this->locale,
                $this->connection_timeout,
                $this->read_write_timeout,
                $this->context,
                $this->keepalive,
                $this->heartbeat
            );
        }

        return $this->amqp_connection;
    }

}