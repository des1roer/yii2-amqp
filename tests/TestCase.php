<?php

namespace simaland\amqp\tests;

use simaland\amqp\Component;
use simaland\amqp\tests\_mock\TestQueueCallback;

/**
 * Base test case class.
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Component
     */
    protected static $component;

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$component = \Yii::createObject([
            'class' => Component::class,
            'id' => 'testAmqp',
            'autoDeclare' => false,
            'connection' => [
                'dsn' => 'amqp://guest:guest@localhost:5672/',
            ],
            'producer' => [
                'logger' => [
                    'class' => _mock\TestLogger::class,
                ],
            ],
            'queues' => [
                [
                    'name' => 'testQueue',
                ],
            ],
            'exchanges' => [
                [
                    'name' => 'srcExchange',
                    'type' => 'direct',
                ],
                [
                    'name' => 'tgtExchange',
                    'type' => 'direct',
                ],
            ],
            'routing' => [
                [
                    'sourceExchange' => 'srcExchange',
                    'targetQueue' => 'testQueue',
                ],
                [
                    'sourceExchange' => 'srcExchange',
                    'targetExchange' => 'tgtExchange',
                ],
            ],
            'consumer' => [
                'callbacks' => [
                    'testQueue' => TestQueueCallback::class,
                ],
                'logger' => [
                    'class' => _mock\TestLogger::class,
                ],
            ],
        ]);
    }
}
