<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('api_platform.listener.response.ldp_headers', 'ApiPlatform\Symfony\EventListener\LdpHeadersListener')
        ->args([
            service('api_platform.metadata.resource.metadata_collection_factory'),
            param('api_platform.ldp.enabled'),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);
};
