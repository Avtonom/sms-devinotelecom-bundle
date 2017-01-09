<?php

namespace Avtonom\Sms\DevinoTelecomBundle\Factory;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use KPhoen\SmsSenderBundle\DependencyInjection\Factory\ProviderFactoryInterface;

/**
 * Devino Telecom provider factory
 */
class DevinoTelecomProviderFactory implements ProviderFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function create(ContainerBuilder $container, $id, array $config)
    {
        $container->getDefinition($id)
            ->replaceArgument(1, $config['login'])
            ->replaceArgument(2, $config['password'])
            ;
        ;
        if(isset($config['originators']))
        {
            $container->getDefinition($id)
                ->replaceArgument(3, $config['originators'])
            ;
        } else {
            $container->getDefinition($id)
                ->replaceArgument(3, null)
            ;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getKey()
    {
        return 'devinotelecom';
    }

    /**
     * {@inheritDoc}
     */
    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->children()
                ->scalarNode('login')->isRequired()->end()
                ->scalarNode('password')->isRequired()->end()
                ->arrayNode('originators')
                    ->prototype('scalar')->end()
                    ->defaultValue(array())
                ->end()
            ->end()
        ;
    }
}
