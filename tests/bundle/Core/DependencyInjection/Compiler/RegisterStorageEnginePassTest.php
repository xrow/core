<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Tests\Bundle\Core\DependencyInjection\Compiler;

use Ibexa\Bundle\Core\ApiLoader\StorageEngineFactory;
use Ibexa\Bundle\Core\DependencyInjection\Compiler\RegisterStorageEnginePass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RegisterStorageEnginePassTest extends AbstractCompilerPassTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setDefinition(StorageEngineFactory::class, new Definition());
        $this->container->setParameter('ibexa.api.storage_engine.default', 'default_storage_engine');
    }

    /**
     * Register the compiler pass under test, just like you would do inside a bundle's load()
     * method:.
     *
     *   $container->addCompilerPass(new MyCompilerPass());
     */
    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterStorageEnginePass());
    }

    public function testRegisterStorageEngine()
    {
        $storageEngineDef = new Definition();
        $storageEngineIdentifier = 'i_am_a_storage_engine';
        $storageEngineDef->addTag('ibexa.storage', ['alias' => $storageEngineIdentifier]);
        $serviceId = 'storage_engine_service';
        $this->setDefinition($serviceId, $storageEngineDef);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            StorageEngineFactory::class,
            'registerStorageEngine',
            [$serviceId, $storageEngineIdentifier]
        );
    }

    public function testRegisterDefaultStorageEngine()
    {
        $storageEngineDef = new Definition();
        $storageEngineIdentifier = 'i_am_a_storage_engine';

        $this->container->setParameter('ibexa.api.storage_engine.default', $storageEngineIdentifier);
        $storageEngineDef->addTag('ibexa.storage', ['alias' => $storageEngineIdentifier]);
        $serviceId = 'storage_engine_service';
        $this->setDefinition($serviceId, $storageEngineDef);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            StorageEngineFactory::class,
            'registerStorageEngine',
            [new Reference($serviceId), $storageEngineIdentifier]
        );
    }

    public function testRegisterStorageEngineNoAlias()
    {
        $this->expectException(\LogicException::class);

        $storageEngineDef = new Definition();
        $storageEngineIdentifier = 'i_am_a_storage_engine';
        $storageEngineDef->addTag('ibexa.storage');
        $serviceId = 'storage_engine_service';
        $this->setDefinition($serviceId, $storageEngineDef);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            StorageEngineFactory::class,
            'registerStorageEngine',
            [$serviceId, $storageEngineIdentifier]
        );
    }
}

class_alias(RegisterStorageEnginePassTest::class, 'eZ\Bundle\EzPublishCoreBundle\Tests\DependencyInjection\Compiler\RegisterStorageEnginePassTest');
