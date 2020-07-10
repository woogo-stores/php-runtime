<?php

declare(strict_types=1);

/*
 * This file is part of Ymir PHP Runtime.
 *
 * (c) Carl Alexander <support@ymirapp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ymir\Runtime\Tests\Unit\Lambda\Handler;

use PHPUnit\Framework\TestCase;
use Ymir\Runtime\FastCgi\FastCgiHttpResponse;
use Ymir\Runtime\FastCgi\FastCgiRequest;
use Ymir\Runtime\Lambda\Handler\WordPressLambdaEventHandler;
use Ymir\Runtime\Tests\Mock\HttpRequestEventMockTrait;
use Ymir\Runtime\Tests\Mock\InvocationEventInterfaceMockTrait;
use Ymir\Runtime\Tests\Mock\PhpFpmProcessMockTrait;

/**
 * @covers \Ymir\Runtime\Lambda\Handler\WordPressLambdaEventHandler
 */
class WordPressLambdaEventHandlerTest extends TestCase
{
    use HttpRequestEventMockTrait;
    use InvocationEventInterfaceMockTrait;
    use PhpFpmProcessMockTrait;

    /**
     * @var string
     */
    private $tempDir;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->tempDir = sys_get_temp_dir();
    }

    public function testCanHandleWithIndexAndWpConfigPresent()
    {
        $process = $this->getPhpFpmProcessMock();

        touch($this->tempDir.'/index.php');
        touch($this->tempDir.'/wp-config.php');

        $this->assertTrue((new WordPressLambdaEventHandler($process, $this->tempDir))->canHandle($this->getHttpRequestEventMock()));

        unlink($this->tempDir.'/index.php');
        unlink($this->tempDir.'/wp-config.php');
    }

    public function testCanHandleWithMissingIndex()
    {
        $process = $this->getPhpFpmProcessMock();

        touch($this->tempDir.'/wp-config.php');

        $this->assertFalse((new WordPressLambdaEventHandler($process, $this->tempDir))->canHandle($this->getHttpRequestEventMock()));

        unlink($this->tempDir.'/wp-config.php');
    }

    public function testCanHandleWithMissingWpConfig()
    {
        $process = $this->getPhpFpmProcessMock();

        touch($this->tempDir.'/index.php');

        $this->assertFalse((new WordPressLambdaEventHandler($process, $this->tempDir))->canHandle($this->getHttpRequestEventMock()));

        unlink($this->tempDir.'/index.php');
    }

    public function testCanHandleWrongEventType()
    {
        $process = $this->getPhpFpmProcessMock();

        $this->assertFalse((new WordPressLambdaEventHandler($process, ''))->canHandle($this->getInvocationEventInterfaceMock()));
    }

    public function testHandleCreatesFastCgiHttpResponse()
    {
        $event = $this->getHttpRequestEventMock();
        $process = $this->getPhpFpmProcessMock();

        $event->expects($this->exactly(3))
              ->method('getPath')
              ->willReturn('tmp');

        $process->expects($this->once())
                ->method('handle')
                ->with($this->isInstanceOf(FastCgiRequest::class));

        touch($this->tempDir.'/index.php');
        touch($this->tempDir.'/wp-config.php');

        $this->assertInstanceOf(FastCgiHttpResponse::class, (new WordPressLambdaEventHandler($process, $this->tempDir))->handle($event));

        unlink($this->tempDir.'/index.php');
        unlink($this->tempDir.'/wp-config.php');
    }
}
