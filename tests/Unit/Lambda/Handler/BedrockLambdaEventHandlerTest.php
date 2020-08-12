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
use Ymir\Runtime\Lambda\Handler\BedrockLambdaEventHandler;
use Ymir\Runtime\Lambda\Response\NotFoundHttpResponse;
use Ymir\Runtime\Tests\Mock\HttpRequestEventMockTrait;
use Ymir\Runtime\Tests\Mock\InvocationEventInterfaceMockTrait;
use Ymir\Runtime\Tests\Mock\PhpFpmProcessMockTrait;

/**
 * @covers \Ymir\Runtime\Lambda\Handler\BedrockLambdaEventHandler
 */
class BedrockLambdaEventHandlerTest extends TestCase
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

        if (!file_exists($this->tempDir.'/composer')) {
            mkdir($this->tempDir.'/composer', 0777, true);
        }
        if (!file_exists($this->tempDir.'/config')) {
            mkdir($this->tempDir.'/config', 0777, true);
        }
        if (!file_exists($this->tempDir.'/tmp')) {
            mkdir($this->tempDir.'/tmp');
        }
        if (!file_exists($this->tempDir.'/web/app/mu-plugins')) {
            mkdir($this->tempDir.'/web/app/mu-plugins', 0777, true);
        }
        if (!file_exists($this->tempDir.'/web/wp')) {
            mkdir($this->tempDir.'/web/wp', 0777, true);
        }
        if (!file_exists($this->tempDir.'/web/wp/wp-admin')) {
            mkdir($this->tempDir.'/web/wp/wp-admin', 0777, true);
        }
        if (!file_exists($this->tempDir.'/web/wp/tmp')) {
            mkdir($this->tempDir.'/web/wp/tmp', 0777, true);
        }
    }

    public function inaccessibleFilesProvider(): array
    {
        return [
            ['/composer.json'],
            ['/composer.lock'],
            ['/composer/installed.json'],
            ['/wp-cli.local.yml'],
            ['/wp-cli.yml'],
        ];
    }

    public function testCanHandleWithApplicationAndWpConfigPresent()
    {
        $process = $this->getPhpFpmProcessMock();

        touch($this->tempDir.'/config/application.php');
        touch($this->tempDir.'/web/wp-config.php');

        $this->assertTrue((new BedrockLambdaEventHandler($process, $this->tempDir))->canHandle($this->getHttpRequestEventMock()));

        @unlink($this->tempDir.'/config/application.php');
        @unlink($this->tempDir.'/web/wp-config.php');
    }

    public function testCanHandleWithBedrockAutoloaderPresent()
    {
        $process = $this->getPhpFpmProcessMock();

        $handler = new BedrockLambdaEventHandler($process, $this->tempDir);

        touch($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');

        $this->assertTrue($handler->canHandle($this->getHttpRequestEventMock()));

        @unlink($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
    }

    public function testCanHandleWithMissingApplicationConfig()
    {
        $process = $this->getPhpFpmProcessMock();

        touch($this->tempDir.'/web/wp-config.php');

        $this->assertFalse((new BedrockLambdaEventHandler($process, $this->tempDir))->canHandle($this->getHttpRequestEventMock()));

        @unlink($this->tempDir.'/web/wp-config.php');
    }

    public function testCanHandleWithMissingWpConfig()
    {
        $process = $this->getPhpFpmProcessMock();

        touch($this->tempDir.'/config/application.php');

        $this->assertFalse((new BedrockLambdaEventHandler($process, $this->tempDir))->canHandle($this->getHttpRequestEventMock()));

        @unlink($this->tempDir.'/config/application.php');
    }

    public function testCanHandleWithNoBedrockAutoloaderOrApplicationOrWordPressConfig()
    {
        $process = $this->getPhpFpmProcessMock();

        $this->assertFalse((new BedrockLambdaEventHandler($process, $this->tempDir))->canHandle($this->getHttpRequestEventMock()));
    }

    public function testCanHandleWrongEventType()
    {
        $process = $this->getPhpFpmProcessMock();

        $this->assertFalse((new BedrockLambdaEventHandler($process, ''))->canHandle($this->getInvocationEventInterfaceMock()));
    }

    public function testHandleCreatesFastCgiRequestToFolderIndexPhpIfFileExists()
    {
        $event = $this->getHttpRequestEventMock();
        $process = $this->getPhpFpmProcessMock();

        $event->expects($this->exactly(3))
              ->method('getPath')
              ->willReturn('tmp/');

        $process->expects($this->once())
                ->method('handle')
                ->with($this->callback(function (FastCgiRequest $request) {
                    return $request->getScriptFilename() === $this->tempDir.'/tmp/index.php';
                }));

        touch($this->tempDir.'/config/application.php');
        touch($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        touch($this->tempDir.'/tmp/index.php');

        $this->assertInstanceOf(FastCgiHttpResponse::class, (new BedrockLambdaEventHandler($process, $this->tempDir))->handle($event));

        @unlink($this->tempDir.'/config/application.php');
        @unlink($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        @unlink($this->tempDir.'/tmp/index.php');
    }

    public function testHandleCreatesFastCgiRequestToRootIndexPhpByDefault()
    {
        $event = $this->getHttpRequestEventMock();
        $process = $this->getPhpFpmProcessMock();

        $event->expects($this->exactly(3))
              ->method('getPath')
              ->willReturn('tmp');

        $process->expects($this->once())
                ->method('handle')
                ->with($this->callback(function (FastCgiRequest $request) {
                    return $request->getScriptFilename() === $this->tempDir.'/web/index.php';
                }));

        touch($this->tempDir.'/config/application.php');
        touch($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');

        $this->assertInstanceOf(FastCgiHttpResponse::class, (new BedrockLambdaEventHandler($process, $this->tempDir))->handle($event));

        @unlink($this->tempDir.'/config/application.php');
        @unlink($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
    }

    public function testHandleCreatesFastCgiRequestToWebDirectoryWithWpPaths()
    {
        $event = $this->getHttpRequestEventMock();
        $process = $this->getPhpFpmProcessMock();

        $event->expects($this->exactly(3))
              ->method('getPath')
              ->willReturn('wp/tmp');

        $process->expects($this->once())
                ->method('handle')
                ->with($this->callback(function (FastCgiRequest $request) {
                    return $request->getScriptFilename() === $this->tempDir.'/web/wp/tmp/index.php';
                }));

        touch($this->tempDir.'/config/application.php');
        touch($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        touch($this->tempDir.'/web/wp/tmp/index.php');

        $this->assertInstanceOf(FastCgiHttpResponse::class, (new BedrockLambdaEventHandler($process, $this->tempDir))->handle($event));

        @unlink($this->tempDir.'/config/application.php');
        @unlink($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        @unlink($this->tempDir.'/web/wp/tmp/index.php');
    }

    /**
     * @dataProvider inaccessibleFilesProvider
     */
    public function testHandleReturnsNotFoundHttpResponseForInaccessibleFiles(string $filePath)
    {
        $event = $this->getHttpRequestEventMock();
        $process = $this->getPhpFpmProcessMock();

        $event->expects($this->exactly(1))
              ->method('getPath')
              ->willReturn($filePath);

        touch($this->tempDir.'/config/application.php');
        touch($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        touch($this->tempDir.$filePath);

        $this->assertInstanceOf(NotFoundHttpResponse::class, (new BedrockLambdaEventHandler($process, $this->tempDir))->handle($event));

        @unlink($this->tempDir.'/config/application.php');
        @unlink($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        @unlink($this->tempDir.$filePath);
    }

    public function testHandleRewritesWpAdminUrl()
    {
        $event = $this->getHttpRequestEventMock();
        $process = $this->getPhpFpmProcessMock();

        $event->expects($this->exactly(3))
              ->method('getPath')
              ->willReturn('/wp-admin/');

        $process->expects($this->once())
                ->method('handle')
                ->with($this->callback(function (FastCgiRequest $request) {
                    return $request->getScriptFilename() === $this->tempDir.'/web/wp/wp-admin/index.php';
                }));

        touch($this->tempDir.'/config/application.php');
        touch($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        touch($this->tempDir.'/web/wp/wp-admin/index.php');

        $this->assertInstanceOf(FastCgiHttpResponse::class, (new BedrockLambdaEventHandler($process, $this->tempDir))->handle($event));

        @unlink($this->tempDir.'/config/application.php');
        @unlink($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        @unlink($this->tempDir.'/web/wp/wp-admin/index.php');
    }

    public function testHandleRewritesWpAdminUrlWithSubdirectoryMultisite()
    {
        $event = $this->getHttpRequestEventMock();
        $process = $this->getPhpFpmProcessMock();

        $event->expects($this->exactly(3))
              ->method('getPath')
              ->willReturn('/subdirectory/wp-admin/');

        $process->expects($this->once())
                ->method('handle')
                ->with($this->callback(function (FastCgiRequest $request) {
                    return $request->getScriptFilename() === $this->tempDir.'/web/wp/wp-admin/index.php';
                }));

        touch($this->tempDir.'/config/application.php');
        touch($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        touch($this->tempDir.'/web/wp/wp-admin/index.php');

        file_put_contents($this->tempDir.'/config/application.php', 'Config::define(\'MULTISITE\', true);');

        $this->assertInstanceOf(FastCgiHttpResponse::class, (new BedrockLambdaEventHandler($process, $this->tempDir))->handle($event));

        @unlink($this->tempDir.'/config/application.php');
        @unlink($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        @unlink($this->tempDir.'/web/wp/wp-admin/index.php');
    }

    public function testHandleRewritesWpLoginUrl()
    {
        $event = $this->getHttpRequestEventMock();
        $process = $this->getPhpFpmProcessMock();

        $event->expects($this->exactly(3))
              ->method('getPath')
              ->willReturn('/wp-login.php');

        $process->expects($this->once())
                ->method('handle')
                ->with($this->callback(function (FastCgiRequest $request) {
                    return $request->getScriptFilename() === $this->tempDir.'/web/wp/wp-login.php';
                }));

        touch($this->tempDir.'/config/application.php');
        touch($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        touch($this->tempDir.'/web/wp/wp-login.php');

        $this->assertInstanceOf(FastCgiHttpResponse::class, (new BedrockLambdaEventHandler($process, $this->tempDir))->handle($event));

        @unlink($this->tempDir.'/config/application.php');
        @unlink($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        @unlink($this->tempDir.'/web/wp/wp-login.php');
    }

    public function testHandleRewritesWpLoginUrlWithSubdirectoryMultisite()
    {
        $event = $this->getHttpRequestEventMock();
        $process = $this->getPhpFpmProcessMock();

        $event->expects($this->exactly(3))
              ->method('getPath')
              ->willReturn('/subdirectory/wp-login.php');

        $process->expects($this->once())
                ->method('handle')
                ->with($this->callback(function (FastCgiRequest $request) {
                    return $request->getScriptFilename() === $this->tempDir.'/web/wp/wp-login.php';
                }));

        touch($this->tempDir.'/config/application.php');
        touch($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        touch($this->tempDir.'/web/wp/wp-login.php');

        file_put_contents($this->tempDir.'/config/application.php', 'Config::define(\'MULTISITE\', true);');

        $this->assertInstanceOf(FastCgiHttpResponse::class, (new BedrockLambdaEventHandler($process, $this->tempDir))->handle($event));

        @unlink($this->tempDir.'/config/application.php');
        @unlink($this->tempDir.'/web/app/mu-plugins/bedrock-autoloader.php');
        @unlink($this->tempDir.'/web/wp/wp-login.php');
    }
}
