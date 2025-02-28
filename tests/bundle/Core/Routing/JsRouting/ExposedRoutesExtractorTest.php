<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Core\Routing\JsRouting;

use FOS\JsRoutingBundle\Extractor\ExposedRoutesExtractorInterface;
use Ibexa\Bundle\Core\Routing\JsRouting\ExposedRoutesExtractor;
use Ibexa\Core\MVC\Symfony\SiteAccess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @covers \Ibexa\Bundle\Core\Routing\JsRouting\ExposedRoutesExtractor
 *
 * @internal
 */
final class ExposedRoutesExtractorTest extends TestCase
{
    private const BASE_URL = '/foo';

    public function getDataForTestGetBaseUrl(): iterable
    {
        yield 'CLI' => [
            // no master request in a stack
            null,
            self::BASE_URL,
        ];

        yield 'No SiteAccess' => [
            new Request(),
            self::BASE_URL,
        ];

        $siteAccess = new SiteAccess(
            'test',
            SiteAccess\Matcher\HostText::class,
            new SiteAccess\Matcher\HostText([])
        );
        yield 'SiteAccess w/o URI Lexer matcher' => [
            new Request([], [], ['siteaccess' => $siteAccess]),
            self::BASE_URL,
        ];

        $siteAccess = new SiteAccess(
            'test',
            SiteAccess\Matcher\URIText::class,
            new SiteAccess\Matcher\URIText(['prefix' => 'bar'])
        );
        yield 'SiteAccess with URI Lexer matcher' => [
            new Request([], [], ['siteaccess' => $siteAccess]),
            self::BASE_URL . '/bar/',
        ];
    }

    /**
     * @dataProvider getDataForTestGetBaseUrl
     */
    public function testGetBaseUrl(?Request $masterRequest, string $expectedBaseUrl): void
    {
        $innerExtractor = $this->createMock(ExposedRoutesExtractorInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $innerExtractor->method('getBaseUrl')->willReturn(self::BASE_URL);
        $requestStack->method('getMasterRequest')->willReturn($masterRequest);

        $extractor = new ExposedRoutesExtractor($innerExtractor, $requestStack);

        self::assertSame($expectedBaseUrl, $extractor->getBaseUrl());
    }
}

class_alias(ExposedRoutesExtractorTest::class, 'eZ\Bundle\EzPublishCoreBundle\Tests\Routing\JsRouting\ExposedRoutesExtractorTest');
