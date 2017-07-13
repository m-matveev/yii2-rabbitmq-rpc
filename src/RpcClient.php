<?php

namespace mamatveev\yii2rabbitmq;


use PhpAmqpLib\Message\AMQPMessage;

class RpcClient extends \Thumper\RpcClient
{
    /**
     * @var string
     */
    protected $exchangeName = null;

    /**
     * count of messages sent to the queue
     * @var int
     */
    protected $requestsCount = 0;

    /**
     * count of received reply's
     * @var int
     */
    protected $repliesCount = 0;

    /**
     * reply's data
     * @var array
     */
    protected $repliesData = [];

    /**
     * flag, did a client starts queue consume
     * @var bool
     */
    protected $isConsumed = false;

    /**
     * queue response callback
     * @var callable|null
     */
    protected $callback = null;

    /**
     * init a exchange client
     * @param $exchangeName
     */
    public function initClient($exchangeName)
    {
        $this->queueName = $this->getConsumerTag().'-queue';
        $this->channel->queue_declare($this->queueName, false, false, true, true);
        $this->exchangeName = $exchangeName;
        $this->requestsCount = 0;
        $this->repliesCount = 0;
        $this->repliesData = [];
    }

    /**
     * Send a message to exchange
     * @param mixed $msgBody
     * @param null|mixed $correlationId
     * @param string $routingKey
     */
    public function addRequest($msgBody, $routingKey = '', $correlationId = null)
    {
        if ($correlationId === null)
        {
            $correlationId =  uniqid();
        }

        $msg = new AMQPMessage(serialize($msgBody),
            [
                'content_type' => 'text/plain',
                'reply_to' => $this->queueName,
                'correlation_id' => $correlationId
            ]
        );


        $this->channel->basic_publish($msg, $this->exchangeName . '-exchange', $routingKey);
        $this->requestsCount++;
    }


    /**
     * get a reply's from rpc server
     * if callback set reply data will be empty
     * @param callable|null $callback a reply handler
     * @return array
     */
    public function getReplies(callable $callback = null)
    {
        $this->consume([$this, 'processMessage'], $callback);
        $this->waitReply();
        return $this->repliesData;
    }

    /**
     * wait a messages execution
     * @return bool
     */
    public function waitExecution()
    {
        $this->consume([$this, 'ignoreMessage']);
        $this->waitReply();
        return true;
    }

    /**
     * rpc server messages consumer
     * save message data or call user specify callback
     * @param AMQPMessage $msg
     */
    public function processMessage($msg)
    {
        $this->repliesCount++;
        $reply = unserialize($msg->body);

        if ($this->callback === null) {
            $this->repliesData[] = $reply;
            return;
        }

        call_user_func($this->callback, $reply);
    }

    /**
     * rpc server messages callback
     * ignore response data
     * @param AMQPMessage $msg
     */
    public function ignoreMessage($msg)
    {
        $this->repliesCount++;
    }

    /**
     * @param callable $consumeHandler
     * @param callable|null $replyHandler
     */
    protected function consume(callable $consumeHandler, callable $replyHandler = null)
    {
        if ($this->isConsumed)
        {
            $this->channel->basic_cancel($this->queueName);
            $this->channel->queue_declare($this->queueName, false, false, true, true);
        }

        $this->callback = $replyHandler;

        $this->channel->basic_consume(
            $this->queueName,
            $this->queueName,
            false,
            true,
            false,
            false,
            $consumeHandler
        );

        $this->isConsumed = true;
    }

    /**
     * wait a reply's from rpc server
     */
    protected function waitReply()
    {
        while ($this->repliesCount < $this->requestsCount)
        {
            $this->channel->wait(null, null, $this->requestTimeout);
        }

        $this->requestsCount = 0;
        $this->repliesCount = 0;
        $this->callback = null;
    }


    /**
     * return a count of messages sent to the queue
     * @return int
     */
    public function getRequestCount()
    {
        return $this->requestsCount;
    }

    /**
     * return a count of received reply's
     * @return int
     */
    public function getReplyCount()
    {
        return $this->repliesCount;
    }
}