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

namespace ApiPlatform\Symfony\EventListener;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\State\Util\OperationRequestInitiatorTrait;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class LdpHeadersListener
{
    use OperationRequestInitiatorTrait;

    public function __construct(ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory, private readonly bool $enabled = true)
    {
        $this->resourceMetadataCollectionFactory = $resourceMetadataCollectionFactory;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $operation = $this->initializeOperation($request);

        if (!$operation instanceof HttpOperation) {
            return;
        }

        $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create($operation->getClass() ?? $request->attributes->get('_api_resource_class'));
        $uriTemplate = $operation->getUriTemplate();
        $methods = [];
        $acceptPostValues = [];

        foreach ($resourceMetadataCollection as $resource) {
            foreach ($resource->getOperations() as $candidateOperation) {
                if (!$candidateOperation instanceof HttpOperation || $candidateOperation->getUriTemplate() !== $uriTemplate) {
                    continue;
                }

                if (!$method = $candidateOperation->getMethod()) {
                    continue;
                }

                $method = strtoupper($method);
                $methods[$method] = true;

                if ('POST' !== $method) {
                    continue;
                }

                foreach ($candidateOperation->getInputFormats() ?? [] as $mimeTypes) {
                    foreach ($mimeTypes as $mimeType) {
                        if (!\in_array($mimeType, $acceptPostValues, true)) {
                            $acceptPostValues[] = $mimeType;
                        }
                    }
                }
            }
        }

        if (!$methods) {
            return;
        }

        $response = $event->getResponse();

        $allowedMethods = array_keys($methods);
        sort($allowedMethods);
        $response->headers->set('Allow', implode(', ', $allowedMethods));

        if (isset($methods['POST']) && $acceptPostValues) {
            $response->headers->set('Accept-Post', implode(', ', $acceptPostValues));
        }
    }
}
