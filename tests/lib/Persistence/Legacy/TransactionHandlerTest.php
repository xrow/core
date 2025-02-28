<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Tests\Core\Persistence\Legacy;

use Doctrine\DBAL\Connection;
use Exception;
use Ibexa\Core\Persistence\Legacy\Content\Language\CachingHandler;
use Ibexa\Core\Persistence\Legacy\Content\Type\MemoryCachingHandler;
use Ibexa\Core\Persistence\Legacy\TransactionHandler;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Core\Persistence\Legacy\TransactionHandler
 */
class TransactionHandlerTest extends TestCase
{
    /**
     * Transaction handler to test.
     *
     * @var \Ibexa\Core\Persistence\Legacy\TransactionHandler
     */
    protected $transactionHandler;

    /** @var \Doctrine\DBAL\Connection|\PHPUnit\Framework\MockObject\MockObject */
    protected $connectionMock;

    /** @var \Ibexa\Contracts\Core\Persistence\Content\Type\Handler|\PHPUnit\Framework\MockObject\MockObject */
    protected $contentTypeHandlerMock;

    /** @var \Ibexa\Contracts\Core\Persistence\Content\Language\Handler|\PHPUnit\Framework\MockObject\MockObject */
    protected $languageHandlerMock;

    public function testBeginTransaction()
    {
        $handler = $this->getTransactionHandler();
        $this->getConnectionMock()
            ->expects($this->once())
            ->method('beginTransaction');
        $this->getContentTypeHandlerMock()
            ->expects($this->never())
            ->method($this->anything());
        $this->getLanguageHandlerMock()
            ->expects($this->never())
            ->method($this->anything());

        $handler->beginTransaction();
    }

    public function testCommit()
    {
        $handler = $this->getTransactionHandler();
        $this->getConnectionMock()
            ->expects($this->once())
            ->method('commit');
        $this->getContentTypeHandlerMock()
            ->expects($this->never())
            ->method($this->anything());
        $this->getLanguageHandlerMock()
            ->expects($this->never())
            ->method($this->anything());

        $handler->commit();
    }

    public function testCommitException()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('test');

        $handler = $this->getTransactionHandler();
        $this->getConnectionMock()
            ->expects($this->once())
            ->method('commit')
            ->will($this->throwException(new Exception('test')));
        $this->getContentTypeHandlerMock()
            ->expects($this->never())
            ->method($this->anything());
        $this->getLanguageHandlerMock()
            ->expects($this->never())
            ->method($this->anything());

        $handler->commit();
    }

    public function testRollback()
    {
        $handler = $this->getTransactionHandler();
        $this->getConnectionMock()
            ->expects($this->once())
            ->method('rollback');
        $this->getContentTypeHandlerMock()
            ->expects($this->once())
            ->method('clearCache');
        $this->getLanguageHandlerMock()
            ->expects($this->once())
            ->method('clearCache');

        $handler->rollback();
    }

    public function testRollbackException()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('test');

        $handler = $this->getTransactionHandler();
        $this->getConnectionMock()
            ->expects($this->once())
            ->method('rollback')
            ->will($this->throwException(new Exception('test')));
        $this->getContentTypeHandlerMock()
            ->expects($this->never())
            ->method($this->anything());
        $this->getLanguageHandlerMock()
            ->expects($this->never())
            ->method($this->anything());

        $handler->rollback();
    }

    /**
     * Returns a mock object for the Content Gateway.
     *
     * @return \Ibexa\Core\Persistence\Legacy\TransactionHandler
     */
    protected function getTransactionHandler()
    {
        if (!isset($this->transactionHandler)) {
            $this->transactionHandler = new TransactionHandler(
                $this->getConnectionMock(),
                $this->getContentTypeHandlerMock(),
                $this->getLanguageHandlerMock()
            );
        }

        return $this->transactionHandler;
    }

    /**
     * @return \Doctrine\DBAL\Connection|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getConnectionMock(): Connection
    {
        if (!isset($this->connectionMock)) {
            $this->connectionMock = $this->createMock(Connection::class);
        }

        return $this->connectionMock;
    }

    /**
     * Returns a mock object for the Content Type Handler.
     *
     * @return \Ibexa\Core\Persistence\Legacy\Content\Type\MemoryCachingHandler|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getContentTypeHandlerMock()
    {
        if (!isset($this->contentTypeHandlerMock)) {
            $this->contentTypeHandlerMock = $this->createMock(MemoryCachingHandler::class);
        }

        return $this->contentTypeHandlerMock;
    }

    /**
     * Returns a mock object for the Content Language Gateway.
     *
     * @return \Ibexa\Core\Persistence\Legacy\Content\Language\CachingHandler|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getLanguageHandlerMock()
    {
        if (!isset($this->languageHandlerMock)) {
            $this->languageHandlerMock = $this->createMock(CachingHandler::class);
        }

        return $this->languageHandlerMock;
    }
}

class_alias(TransactionHandlerTest::class, 'eZ\Publish\Core\Persistence\Legacy\Tests\TransactionHandlerTest');
