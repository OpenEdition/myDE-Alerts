<?php

namespace MyDigitalEnvironment\AlertsBundle;

use MyDigitalEnvironment\MyDigitalEnvironmentBundle\BundleExtension\MyDigitalEnvironmentApplicationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class MyDigitalEnvironmentAlertsBundle extends AbstractBundle implements MyDigitalEnvironmentApplicationInterface
{
    // public const string TABLE_SCHEMA = 'my_digital_environment_alerts'; // Removing the typed class constant to support php8.2
    public const TABLE_SCHEMA = 'my_digital_environment_alerts';

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');
    }

    public static function getApplicationRouteId(): string
    {
        return 'my_digital_environment_alerts_hub';
    }

    public static function getApplicationDescription(): string
    {
        return 'Alerts';
    }
}