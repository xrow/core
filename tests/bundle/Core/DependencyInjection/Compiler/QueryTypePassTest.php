<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Core\DependencyInjection\Compiler;

use Ibexa\Bundle\Core\DependencyInjection\Compiler\QueryTypePass;
use Ibexa\Core\QueryType\ArrayQueryTypeRegistry;
use Ibexa\Tests\Bundle\Core\DependencyInjection\Stub\QueryTypeBundle\QueryType\TestQueryType;
use Ibexa\Tests\Bundle\Core\DependencyInjection\Stub\QueryTypeBundle\QueryTypeBundle;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class QueryTypePassTest extends AbstractCompilerPassTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDefinition(ArrayQueryTypeRegistry::class, new Definition());
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new QueryTypePass());
    }

    /**
     * @dataProvider tagsProvider
     */
    public function testRegisterTaggedQueryType(string $tag): void
    {
        $def = new Definition();
        $def->addTag($tag);
        $def->setClass(TestQueryType::class);
        $serviceId = 'test.query_type';
        $this->setDefinition($serviceId, $def);

        $this->compile();
        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            ArrayQueryTypeRegistry::class,
            'addQueryTypes',
            [['Test:Test' => new Reference($serviceId)]]
        );
    }

    /**
     * @dataProvider tagsProvider
     */
    public function testRegisterTaggedQueryTypeWithClassAsParameter(string $tag): void
    {
        $this->setParameter('query_type_class', TestQueryType::class);
        $def = new Definition();
        $def->addTag($tag);
        $def->setClass('%query_type_class%');
        $serviceId = 'test.query_type';
        $this->setDefinition($serviceId, $def);

        $this->compile();
        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            ArrayQueryTypeRegistry::class,
            'addQueryTypes',
            [['Test:Test' => new Reference($serviceId)]]
        );
    }

    /**
     * Tests query type name override using the 'alias' tag attribute.
     *
     * The QueryType class will still be registered, as the aliases are different from the
     * built-in alias of the class.
     *
     * @dataProvider tagsProvider
     */
    public function testTaggedOverride(string $tag): void
    {
        $this->setParameter('kernel.bundles', ['QueryTypeBundle' => QueryTypeBundle::class]);

        $def = new Definition();
        $def->addTag($tag, ['alias' => 'overridden_type']);
        $def->setClass(TestQueryType::class);
        $this->setDefinition('test.query_type_override', $def);

        $def = new Definition();
        $def->addTag($tag, ['alias' => 'other_overridden_type']);
        $def->setClass(TestQueryType::class);
        $this->setDefinition('test.query_type_other_override', $def);

        $this->compile();

        $this->assertContainerBuilderHasService('test.query_type_override');
        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            ArrayQueryTypeRegistry::class,
            'addQueryTypes',
            [
                [
                    'overridden_type' => new Reference('test.query_type_override'),
                    'other_overridden_type' => new Reference('test.query_type_other_override'),
                ],
            ]
        );
    }

    public function tagsProvider(): iterable
    {
        return [
            [QueryTypePass::QUERY_TYPE_SERVICE_TAG],
        ];
    }
}

class_alias(QueryTypePassTest::class, 'eZ\Bundle\EzPublishCoreBundle\Tests\DependencyInjection\Compiler\QueryTypePassTest');
