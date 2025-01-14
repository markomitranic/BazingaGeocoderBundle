<?php

declare(strict_types=1);

/*
 * This file is part of the BazingaGeocoderBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Bazinga\GeocoderBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Whether to use the debug mode.
     *
     * @see https://github.com/doctrine/DoctrineBundle/blob/v1.5.2/DependencyInjection/Configuration.php#L31-L41
     *
     * @var bool
     */
    private $debug;

    /**
     * @param bool $debug
     */
    public function __construct($debug)
    {
        $this->debug = (bool) $debug;
    }

    /**
     * Proxy to get root node for Symfony < 4.2.
     *
     * @return ArrayNodeDefinition
     */
    protected function getRootNode(TreeBuilder $treeBuilder, string $name)
    {
        if (\method_exists($treeBuilder, 'getRootNode')) {
            return $treeBuilder->getRootNode();
        } else {
            return $treeBuilder->root($name);
        }
    }

    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('bazinga_geocoder');

        $this->getRootNode($treeBuilder, 'bazinga_geocoder')
            ->children()
            ->append($this->getProvidersNode())
            ->arrayNode('profiling')
                ->addDefaultsIfNotSet()
                ->treatFalseLike(['enabled' => false])
                ->treatTrueLike(['enabled' => true])
                ->treatNullLike(['enabled' => $this->debug])
                ->info('Extend the debug profiler with information about requests.')
                ->children()
                    ->booleanNode('enabled')
                        ->info('Turn the toolbar on or off. Defaults to kernel debug mode.')
                        ->defaultValue($this->debug)
                    ->end()
                ->end()
            ->end()
            ->arrayNode('fake_ip')
                ->beforeNormalization()
                ->ifString()
                    ->then(function ($value) {
                        return ['ip' => $value];
                    })
                ->end()
                ->canBeEnabled()
                ->children()
                    ->scalarNode('ip')->defaultNull()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * @return ArrayNodeDefinition
     */
    private function getProvidersNode()
    {
        $treeBuilder = new TreeBuilder('providers');

        return $this->getRootNode($treeBuilder, 'providers')
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->fixXmlConfig('plugin')
                ->children()
                    ->scalarNode('factory')->isRequired()->cannotBeEmpty()->end()
                    ->variableNode('options')->defaultValue([])->end()
                    ->scalarNode('cache')->defaultNull()->end()
                    ->scalarNode('cache_lifetime')->defaultNull()->end()
                    ->scalarNode('cache_precision')
                        ->defaultNull()
                        ->info('Precision of the coordinates to cache.')
                        ->end()
                    ->scalarNode('limit')->defaultNull()->end()
                    ->scalarNode('locale')->defaultNull()->end()
                    ->scalarNode('logger')->defaultNull()->end()
                    ->arrayNode('aliases')
                        ->scalarPrototype()->end()
                    ->end()
                    ->append($this->createClientPluginNode())
                ->end()
            ->end();
    }

    /**
     * Create plugin node of a client.
     *
     * @return ArrayNodeDefinition The plugin node
     */
    private function createClientPluginNode()
    {
        $builder = new TreeBuilder('plugins');
        $node = $this->getRootNode($builder, 'plugins');

        /** @var ArrayNodeDefinition $pluginList */
        $pluginList = $node
            ->info('A list of plugin service ids. The order is important.')
            ->arrayPrototype()
        ;
        $pluginList
            // support having just a service id in the list
            ->beforeNormalization()
                ->always(function ($plugin) {
                    if (is_string($plugin)) {
                        return [
                            'reference' => [
                                'enabled' => true,
                                'id' => $plugin,
                            ],
                        ];
                    }

                    return $plugin;
                })
            ->end()
        ;

        $pluginList
            ->children()
                ->arrayNode('reference')
                    ->canBeEnabled()
                    ->info('Reference to a plugin service')
                    ->children()
                        ->scalarNode('id')
                            ->info('Service id of a plugin')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $node;
    }
}
