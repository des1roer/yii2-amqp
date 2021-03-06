<?php

namespace simaland\amqp;

use yii\base\Component as BaseComponent;
use yii\di\Instance;
use Yii;
use function register_shutdown_function;
use function sprintf;
use function is_array;
use function array_merge;
use function array_merge_recursive;
use function uniqid;
use function is_int;
use function array_walk;
use function md5;

/**
 * Yii2 AMQP component
 *
 * @property-read components\Producer                        $producer   Producer component
 * @property-read components\Connection                      $connection Connection component
 * @property-read components\Consumer                        $consumer   Consumer component
 * @property-read collections\Queue|components\Queue[]       $queues     Queues collection
 * @property-read collections\Exchange|components\Exchange[] $exchanges  Exchanges collection
 * @property-read collections\Routing|components\Routing[]   $routing    Routing collection
 */
class Component extends BaseComponent
{
    /**
     * Singleton alias name template
     */
    public const SINGLETON_ALIAS_NAME_TEMPLATE = 'ext.amqp.%s.%s';

    /**
     * Extension configuration default values
     *
     * @var array
     */
    protected const DEFAULTS = [
        'exchanges' => [
            'class' => components\Exchange::class,
        ],
        'queues' => [
            'class' => components\Queue::class,
        ],
        'routing' => [
            'class' => components\Routing::class,
        ],
    ];

    /**
     * @var string AMQP configuration id, autogenerated if null
     */
    public $id;

    /**
     * @var bool Sub-components auto-declaration
     */
    public $autoDeclare = false;

    /**
     * @var components\Message|array Message template
     */
    public $messageDefinition = [
        'class' => components\Message::class,
    ];

    /**
     * @var components\Connection|array
     */
    protected $_connection = [
        'class' => components\Connection::class,
    ];

    /**
     * @var components\Producer|array
     */
    protected $_producer = [
        'class' => components\Producer::class,
    ];

    /**
     * @var components\Consumer|array
     */
    protected $_consumer = [
        'class' => components\Consumer::class,
    ];

    /**
     * @var collections\Queue|components\Queue[]
     */
    protected $_queues = [
        'class' => collections\Queue::class,
    ];

    /**
     * @var collections\Exchange|components\Exchange[]
     */
    protected $_exchanges = [
        'class' => collections\Exchange::class,
    ];

    /**
     * @var collections\Routing|components\Routing[]
     */
    protected $_routing = [
        'class' => collections\Routing::class,
    ];

    /**
     * @inheritdoc
     */
    public function __construct(array $config = [])
    {
        $config = $this->configureComponents($config);
        $this->configureCollections();
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     * @throws exceptions\InvalidConfigException
     */
    public function init(): void
    {
        parent::init();
        if ($this->id === null) {
            $this->id = $this->generateId();
        }
        $this->registerConnection();
        $this->registerProducer();
        $this->registerConsumer();
        $this->registerMessageDefinition();
        $this->registerCollections();
    }

    /**
     * Returns service name specified on component
     *
     * @param string $component Component name
     * @return string
     */
    public function getServiceName(string $component): string
    {
        return sprintf(static::SINGLETON_ALIAS_NAME_TEMPLATE, $this->id, $component);
    }

    /**
     * Get connection component
     *
     * @return components\Connection
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function getConnection(): components\Connection
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Yii::$container->get($this->getServiceName('connection'));
    }

    /**
     * @throws exceptions\InvalidConfigException
     */
    public function setConnection(): void
    {
        throw new exceptions\InvalidConfigException('Setter not allowed for this property.');
    }

    /**
     * Get producer component
     *
     * @return components\Producer
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function getProducer(): components\Producer
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Yii::$container->get($this->getServiceName('producer'));
    }

    /**
     * @throws exceptions\InvalidConfigException
     */
    public function setProducer(): void
    {
        throw new exceptions\InvalidConfigException('Setter not allowed for this property.');
    }

    /**
     * Get consumer component
     *
     * @return components\Consumer
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function getConsumer(): components\Consumer
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Yii::$container->get($this->getServiceName('consumer'));
    }

    /**
     * @throws exceptions\InvalidConfigException
     */
    public function setConsumer(): void
    {
        throw new exceptions\InvalidConfigException('Setter not allowed for this property.');
    }

    /**
     * Returns queue collection
     *
     * @return collections\Queue|components\Queue[]
     */
    public function getQueues(): collections\Queue
    {
        return $this->_queues;
    }

    /**
     * @throws exceptions\InvalidConfigException
     */
    public function setQueues(): void
    {
        throw new exceptions\InvalidConfigException('Setter not allowed for this property.');
    }

    /**
     * Returns exchange collection
     *
     * @return collections\Exchange|components\Exchange[]
     */
    public function getExchanges(): collections\Exchange
    {
        return $this->_exchanges;
    }

    /**
     * @throws exceptions\InvalidConfigException
     */
    public function setExchanges(): void
    {
        throw new exceptions\InvalidConfigException('Setter not allowed for this property.');
    }

    /**
     * Returns routing collection
     *
     * @return collections\Routing|components\Routing[]
     */
    public function getRouting(): collections\Routing
    {
        return $this->_routing;
    }

    /**
     * @throws exceptions\InvalidConfigException
     */
    public function setRouting(): void
    {
        throw new exceptions\InvalidConfigException('Setter not allowed for this property.');
    }

    /**
     * Create new message
     *
     * @param mixed $body Message body
     * @return components\Message
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function createMessage($body): components\Message
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Yii::$container->get($this->getServiceName('message'), [
            $body,
            $this,
        ]);
    }

    /**
     * Register AMQP connection
     */
    protected function registerConnection(): void
    {
        Yii::$container->setSingleton(
            $this->getServiceName('connection'),
            $this->_connection,
            [$this]
        );
    }

    /**
     * Register producer
     */
    protected function registerProducer(): void
    {
        Yii::$container->setSingleton(
            $this->getServiceName('producer'),
            array_merge([
                'autoDeclare' => $this->autoDeclare,
            ], $this->_producer),
            [Instance::of($this->getServiceName('connection')), $this]
        );
    }

    /**
     * Register consumer
     */
    protected function registerConsumer(): void
    {
        Yii::$container->setSingleton(
            $this->getServiceName('consumer'),
            array_merge([
                'autoDeclare' => $this->autoDeclare,
            ], $this->_consumer),
            [Instance::of($this->getServiceName('connection')), $this]
        );
    }

    /**
     * Register message definition
     */
    protected function registerMessageDefinition(): void
    {
        Yii::$container->set(
            $this->getServiceName('message'),
            $this->messageDefinition
        );
    }

    /**
     * Configure components (producer, consumer, connection)
     *
     * @param array $config Configuration array
     * @return array
     */
    protected function configureComponents(array $config = []): array
    {
        $defaultConfig = [];
        $configProperties = array_merge(
            $this->listConfigurationComponents(),
            $this->listConfigurationCollections()
        );
        foreach ($configProperties as $property) {
            $defaultConfig[$property] = $this->{'_' . $property};
        }
        $config = array_merge_recursive($defaultConfig, $config);
        foreach ($configProperties as $property) {
            $this->{'_' . $property} = $config[$property];
            unset($config[$property]);
        }

        return $config;
    }

    /**
     * List components allowed for configure
     *
     * @return array
     */
    protected function listConfigurationComponents(): array
    {
        return [
            'connection',
            'producer',
            'consumer',
        ];
    }

    /**
     * Configure collections (queues, exchanges, routing)
     */
    protected function configureCollections(): void
    {
        $collections = $this->listConfigurationCollections();
        foreach ($collections as $property) {
            if (is_array($propertyConfiguration = $this->{'_' . $property})) {
                $collectionItems = [];
                foreach ($propertyConfiguration as $propertyKey => $propertyValue) {
                    if (is_int($propertyKey)) {
                        $collectionItems[] = $propertyValue;
                        unset($propertyConfiguration[$propertyKey]);
                    }
                }
                $propertyConfiguration['_items'] = $collectionItems;
                $this->{'_' . $property} = $propertyConfiguration;
            }
        }
    }

    /**
     * List collections allowed for configure
     *
     * @return array
     */
    protected function listConfigurationCollections(): array
    {
        return [
            'queues',
            'exchanges',
            'routing',
        ];
    }

    /**
     * Register collections
     *
     * @throws exceptions\InvalidConfigException
     */
    protected function registerCollections(): void
    {
        $collections = $this->listConfigurationCollections();
        try {
            foreach ($collections as $property) {
                if (is_array($propertyConfiguration = $this->{'_' . $property})) {
                    $collectionItems = $propertyConfiguration['_items'] ?? [];
                    unset($propertyConfiguration['_items']);
                    array_walk($collectionItems, function (&$item) use ($property) {
                        if (is_array($item)) {
                            $item = Yii::createObject(array_merge(
                                static::DEFAULTS[$property],
                                [
                                    'autoDeclare' => $this->autoDeclare,
                                ],
                                $item
                            ), [
                                Instance::of($this->getServiceName('connection')),
                                $this,
                            ]);
                        }
                    });
                    $this->{'_' . $property} = Yii::createObject($propertyConfiguration, [
                        $collectionItems,
                    ]);
                }
            }
        } catch (\yii\base\InvalidConfigException $e) {
            throw new exceptions\InvalidConfigException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Generates unique id for component
     *
     * @return string
     */
    private function generateId(): string
    {
        return md5(uniqid(__CLASS__, true));
    }
}
