<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-console for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-console/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-console/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mvc\Console\View;

use Laminas\EventManager\EventManager;
use Laminas\EventManager\Test\EventListenerIntrospectionTrait;
use Laminas\Mvc\Console\View\CreateViewModelListener;
use Laminas\Mvc\Console\View\ViewModel as ConsoleModel;
use Laminas\Mvc\MvcEvent;
use PHPUnit\Framework\TestCase;

class CreateViewModelListenerTest extends TestCase
{
    use EventListenerIntrospectionTrait;

    public function setUp() : void
    {
        $this->listener = new CreateViewModelListener();
    }

    public function testAttachesListenersAtExpectedPriorities()
    {
        $events = new EventManager();
        $this->listener->attach($events);

        $this->assertListenerAtPriority(
            [$this->listener, 'createViewModelFromString'],
            -80,
            MvcEvent::EVENT_DISPATCH,
            $events,
            'View model from string listener not found'
        );

        $this->assertListenerAtPriority(
            [$this->listener, 'createViewModelFromArray'],
            -80,
            MvcEvent::EVENT_DISPATCH,
            $events,
            'View model from array listener not found'
        );

        $this->assertListenerAtPriority(
            [$this->listener, 'createViewModelFromNull'],
            -80,
            MvcEvent::EVENT_DISPATCH,
            $events,
            'View model from null listener not found'
        );
    }

    public function testCanDetachListenersFromEventManager()
    {
        $events = new EventManager();
        $this->listener->attach($events);

        $listeners = $this->getArrayOfListenersForEvent(MvcEvent::EVENT_DISPATCH, $events);
        $this->assertCount(3, $listeners);

        $this->listener->detach($events);
        $listeners = $this->getArrayOfListenersForEvent(MvcEvent::EVENT_DISPATCH, $events);
        $this->assertCount(0, $listeners);
    }

    public function testCanCreateViewModelFromStringResult()
    {
        $event = new MvcEvent();
        $event->setResult('content');
        $this->listener->createViewModelFromString($event);

        $result = $event->getResult();
        $this->assertInstanceOf(ConsoleModel::class, $result);
        $this->assertSame('content', $result->getVariable(ConsoleModel::RESULT));
    }

    public function testCanCreateViewModelFromArrayResult()
    {
        $expected = ['foo' => 'bar'];
        $event = new MvcEvent();
        $event->setResult($expected);
        $this->listener->createViewModelFromArray($event);

        $result = $event->getResult();
        $this->assertInstanceOf(ConsoleModel::class, $result);
        $this->assertSame($expected, $result->getVariables());
    }

    public function testCanCreateViewModelFromNullResult()
    {
        $event = new MvcEvent();
        $this->listener->createViewModelFromNull($event);

        $result = $event->getResult();
        $this->assertInstanceOf(ConsoleModel::class, $result);
    }

    protected $listener;
}
