<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Bundle\IO\DependencyInjection\ConfigurationFactory\MetadataHandler;

use Ibexa\Bundle\IO\DependencyInjection\ConfigurationFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition as ServiceDefinition;
use Symfony\Component\DependencyInjection\Reference;

class LegacyDFSCluster implements ConfigurationFactory
{
    public function getParentServiceId()
    {
        return \Ibexa\Core\IO\IOMetadataHandler\LegacyDFSCluster::class;
    }

    public function configureHandler(ServiceDefinition $definition, array $config)
    {
        $definition->replaceArgument(0, new Reference($config['connection']));
    }

    public function addConfiguration(ArrayNodeDefinition $node)
    {
        $node
            ->info(
                'A MySQL based handler, compatible with the legacy DFS one, that stores metadata in the ezdfsfile table'
            )
            ->children()
                ->scalarNode('connection')
                    ->info('Doctrine connection service')
                    ->example('doctrine.dbal.cluster_connection')
                ->end()
            ->end();
    }
}

class_alias(LegacyDFSCluster::class, 'eZ\Bundle\EzPublishIOBundle\DependencyInjection\ConfigurationFactory\MetadataHandler\LegacyDFSCluster');
