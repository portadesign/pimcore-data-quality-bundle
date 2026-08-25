<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class PortadesignDataQualityExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('portadesign_data_quality.classification_store_id', $config['classification_store_id']);
        $container->setParameter('portadesign_data_quality.channel_relation_field_name', $config['channel_relation_field_name']);
        $container->setParameter('portadesign_data_quality.category_relation_field_name', $config['category_relation_field_name']);
        $container->setParameter('portadesign_data_quality.note_author_user_id', $config['note_author_user_id']);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(\dirname(__DIR__, 2) . '/Resources/config'),
        );

        $loader->load('services.yaml');
    }
}
