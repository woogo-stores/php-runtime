<?php

declare(strict_types=1);

/*
 * This file is part of Placeholder PHP Runtime.
 *
 * (c) Carl Alexander <contact@carlalexander.ca>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Placeholder\Runtime\Lambda\Handler;

use Placeholder\Runtime\FastCgi\PhpFpmProcess;
use Placeholder\Runtime\Lambda\InvocationEvent\HttpRequestEvent;
use Placeholder\Runtime\Lambda\InvocationEvent\InvocationEventInterface;

/**
 * Lambda invocation event handler for a specific PHP script.
 */
class PhpScriptLambdaEventHandler extends AbstractPhpFpmRequestEventHandler
{
    /**
     * The path to the PHP script that this event handler uses.
     *
     * @var string
     */
    private $scriptFilePath;

    /**
     * Constructor.
     */
    public function __construct(PhpFpmProcess $process, string $rootDirectory, string $scriptFilename)
    {
        parent::__construct($process, $rootDirectory);

        $this->scriptFilePath = $this->rootDirectory.'/'.ltrim($scriptFilename, '/');
    }

    /**
     * {@inheritdoc}
     */
    public function canHandle(InvocationEventInterface $event): bool
    {
        return parent::canHandle($event)
            && false !== stripos($this->scriptFilePath, '.php')
            && file_exists($this->scriptFilePath);
    }

    /**
     * {@inheritdoc}
     */
    protected function getScriptFilePath(HttpRequestEvent $event): string
    {
        return $this->scriptFilePath;
    }
}
