<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Bundle\Core\DependencyInjection\Compiler;

use Ibexa\Bundle\Core\ApiLoader\SearchEngineFactory;
use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * This compiler pass will register Ibexa search engines.
 */
class RegisterSearchEnginePass implements CompilerPassInterface
{
    public const SEARCH_ENGINE_SERVICE_TAG = 'ibexa.search.engine';

    /**
     * Container service id of the SearchEngineFactory.
     *
     * @see \Ibexa\Bundle\Core\ApiLoader\SearchEngineFactory
     *
     * @var string
     */
    protected $factoryId = SearchEngineFactory::class;

    /**
     * Registers all found search engines to the SearchEngineFactory.
     *
     * @throws \LogicException
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->factoryId)) {
            return;
        }

        $searchEngineFactoryDefinition = $container->getDefinition($this->factoryId);

        $serviceTags = $container->findTaggedServiceIds(self::SEARCH_ENGINE_SERVICE_TAG);
        foreach ($serviceTags as $serviceId => $attributes) {
            foreach ($attributes as $attribute) {
                if (!isset($attribute['alias'])) {
                    throw new LogicException(
                        sprintf(
                            'Service "%s" tagged with "%s" needs an "alias" attribute to identify the search engine',
                            $serviceId,
                            self::SEARCH_ENGINE_SERVICE_TAG
                        )
                    );
                }

                // Register the search engine with the search engine factory
                $searchEngineFactoryDefinition->addMethodCall(
                    'registerSearchEngine',
                    [
                        new Reference($serviceId),
                        $attribute['alias'],
                    ]
                );
            }
        }
    }
}

class_alias(RegisterSearchEnginePass::class, 'eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Compiler\RegisterSearchEnginePass');
