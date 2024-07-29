<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-console for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-console/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-console/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mvc\Console\View;

use Laminas\Console\Response;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\Test\EventListenerIntrospectionTrait;
use Laminas\Mvc\Application;
use Laminas\Mvc\Console\View\ExceptionStrategy;
use Laminas\Mvc\Console\View\ViewModel;
use Laminas\Mvc\MvcEvent;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use RuntimeException;

class ExceptionStrategyTest extends TestCase
{
    use EventListenerIntrospectionTrait;
    use ProphecyTrait;

    protected $strategy;

    public function setUp() : void
    {
        $this->strategy = new ExceptionStrategy();
    }

    public function testEventListeners()
    {
        $events = new EventManager();
        $this->strategy->attach($events);

        $this->assertListenerAtPriority(
            [$this->strategy, 'prepareExceptionViewModel'],
            1,
            MvcEvent::EVENT_DISPATCH_ERROR,
            $events,
            'MvcEvent::EVENT_DISPATCH_ERROR listener not found'
        );

        $this->assertListenerAtPriority(
            [$this->strategy, 'prepareExceptionViewModel'],
            1,
            MvcEvent::EVENT_RENDER_ERROR,
            $events,
            'MvcEvent::EVENT_RENDER_ERROR listener not found'
        );
    }

    public function testDefaultDisplayExceptions()
    {
        $this->assertTrue($this->strategy->displayExceptions(), 'displayExceptions should be true by default');
    }

    public static function messageTokenProvider()
    {
        return [
            [':className', true],
            [':message', true],
            [':code', false],
            [':file', true],
            [':line', true],
            [':stack', true],
        ];
    }

    /**
     * @dataProvider messageTokenProvider
     */
    public function testMessageTokens($token, $found)
    {
        if ($found) {
            $this->assertStringContainsString(
                $token,
                $this->strategy->getMessage(),
                sprintf('%s token not in message', $token)
            );
        } else {
            $this->assertStringNotContainsString(
                $token,
                $this->strategy->getMessage(),
                sprintf('%s token in message', $token)
            );
        }
    }

    public static function previousMessageTokenProvider()
    {
        return [
            [':className', true],
            [':message', true],
            [':code', false],
            [':file', true],
            [':line', true],
            [':stack', true],
            [':previous', true],
        ];
    }

    /**
     * @dataProvider previousMessageTokenProvider
     */
    public function testPreviousMessageTokens($token, $found)
    {
        if ($found) {
            $this->assertStringContainsString(
                $token,
                $this->strategy->getMessage(),
                sprintf('%s token not in previousMessage', $token)
            );
        } else {
            $this->assertStringNotContainsString(
                $token,
                $this->strategy->getMessage(),
                sprintf('%s token in previousMessage', $token)
            );
        }
    }

    public function testCanSetMessage()
    {
        $this->strategy->setMessage('something else');

        $this->assertEquals('something else', $this->strategy->getMessage());
    }

    public function testCanSetPreviousMessage()
    {
        $this->strategy->setPreviousMessage('something else');

        $this->assertEquals('something else', $this->strategy->getPreviousMessage());
    }

    public function testPrepareExceptionViewModelNoErrorInResultGetsSameResult()
    {
        $event = new MvcEvent(MvcEvent::EVENT_DISPATCH_ERROR);

        $event->setResult('something');
        $this->assertEquals(
            'something',
            $event->getResult(),
            'When no error has been set on the event getResult should not be modified'
        );
    }

    public function testPrepareExceptionViewModelResponseObjectInResultGetsSameResult()
    {
        $event = new MvcEvent(MvcEvent::EVENT_DISPATCH_ERROR);

        $result = new Response();
        $event->setResult($result);
        $this->assertEquals(
            $result,
            $event->getResult(),
            'When a response object has been set on the event getResult should not be modified'
        );
    }

    public function testPrepareExceptionViewModelErrorsThatMustGetSameResult()
    {
        $errors = [
            Application::ERROR_CONTROLLER_NOT_FOUND,
            Application::ERROR_CONTROLLER_INVALID,
            Application::ERROR_ROUTER_NO_MATCH
        ];
        foreach ($errors as $error) {
            $events = new EventManager();
            $this->strategy->attach($events);

            $exception = new \Exception('some exception');
            $event = new MvcEvent(MvcEvent::EVENT_DISPATCH_ERROR, null, ['exception' => $exception]);
            $event->setResult('something');
            $event->setError($error);
            $event->setParams(['exception' => $exception]);

            $events->triggerEvent($event);

            $this->assertEquals(
                'something',
                $event->getResult(),
                sprintf('With an error of %s getResult should not be modified', $error)
            );
        }
    }

    public static function throwables()
    {
        $throwables = ['exception' => [\Exception::class]];

        if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
            $throwables['error'] = [\Error::class];
        }

        return $throwables;
    }

    /**
     * @dataProvider throwables
     */
    public function testPrepareExceptionViewModelErrorException($throwable)
    {
        $errors = [Application::ERROR_EXCEPTION, 'user-defined-error'];

        foreach ($errors as $error) {
            $events = new EventManager();
            $this->strategy->attach($events);

            $exception = new $throwable('message foo');
            $event = new MvcEvent(MvcEvent::EVENT_DISPATCH_ERROR, null, ['exception' => $exception]);

            $event->setError($error);

            $this->strategy->prepareExceptionViewModel($event);

            $this->assertInstanceOf(ViewModel::class, $event->getResult());
            $this->assertNotEquals(
                'something',
                $event->getResult()->getResult(),
                sprintf('With an error of %s getResult should have been modified', $error)
            );
            $this->assertStringContainsString(
                'message foo',
                $event->getResult()->getResult(),
                sprintf('With an error of %s getResult should have been modified', $error)
            );
        }
    }

    public function testPrepareExceptionRendersPreviousMessages()
    {
        $events = new EventManager();
        $this->strategy->attach($events);

        $messages  = ['message foo', 'message bar', 'deepest message'];
        $exception = null;
        $i         = 0;
        do {
            $exception = new \Exception($messages[$i], 0, $exception);
            $i++;
        } while ($i < count($messages));

        $event = new MvcEvent(MvcEvent::EVENT_DISPATCH_ERROR, null, ['exception' => $exception]);
        $event->setError('user-defined-error');

        $events->triggerEvent($event); //$this->strategy->prepareExceptionViewModel($event);

        foreach ($messages as $message) {
            $this->assertStringContainsString(
                $message,
                $event->getResult()->getResult(),
                sprintf('Not all errors are rendered')
            );
        }
    }

    public static function displayExceptionFlags()
    {
        return [
            'true'  => [true],
            'false' => [false],
        ];
    }

    /**
     * @dataProvider displayExceptionFlags
     */
    public function testAllowsUsingCallableMessageForFormatting($expectedFlag)
    {
        $exception = new RuntimeException();
        $messageClosure = function ($e, $displayExceptions) use ($exception, $expectedFlag) {
            $this->assertSame($exception, $e);
            $this->assertSame($expectedFlag, $displayExceptions);
            return 'message';
        };

        $event = $this->prophesize(MvcEvent::class);
        $event->getError()->willReturn(Application::ERROR_EXCEPTION);
        $event->getResult()->willReturn(null);
        $event->getParam('exception')->willReturn($exception);
        $event->setResult(Argument::that(function ($arg) {
            if (! $arg instanceof ViewModel) {
                return false;
            }

            if (1 !== $arg->getErrorLevel()) {
                return false;
            }

            if ('message' !== $arg->getResult()) {
                return false;
            }

            return true;
        }))->shouldBeCalled();

        $this->strategy->setDisplayExceptions($expectedFlag);
        $this->strategy->setMessage($messageClosure);
        $this->assertNull($this->strategy->prepareExceptionViewModel($event->reveal()));
    }

    public function testDoesNotDisplayExceptionDetailsWhenDisplayExceptionsFlagIsFalse()
    {
        $exception = new RuntimeException('SHOULD NOT SEE THIS', -42);
        $event = $this->prophesize(MvcEvent::class);
        $event->getError()->willReturn(Application::ERROR_EXCEPTION);
        $event->getResult()->willReturn(null);
        $event->getParam('exception')->willReturn($exception);
        $event->setResult(Argument::that(function ($arg) {
            if (! $arg instanceof ViewModel) {
                return false;
            }

            if (1 !== $arg->getErrorLevel()) {
                return false;
            }

            $message = $arg->getResult();

            if (strstr($message, 'RuntimeException')
                || strstr($message, 'SHOULD NOT SEE THIS')
                || strstr($message, '-42')
            ) {
                return false;
            }

            return true;
        }))->shouldBeCalled();

        $this->strategy->setDisplayExceptions(false);
        $this->assertNull($this->strategy->prepareExceptionViewModel($event->reveal()));
    }
}
