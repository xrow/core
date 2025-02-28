<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Bundle\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * This behavior is reflected in meta repository's vhost.template so it should
 * not be triggered on recommended nginx/apache setups. It mostly applies to
 * Platform.sh and setups not relying on recommended vhost configuration.
 */
class RejectExplicitFrontControllerRequestsListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', 255],
            ],
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST) {
            return;
        }

        $request = $event->getRequest();
        
        // Not every symfony runtime provides SCRIPT_FILENAME
        if ( ! $request->server->has('SCRIPT_FILENAME') ) {
            return;
        }
        
        $scriptFileName = preg_quote(basename($request->server->get('SCRIPT_FILENAME')), '\\');
        // This pattern has to match with vhost.template files in meta repository
        $pattern = sprintf('<^/([^/]+/)?%s([/?#]|$)>', $scriptFileName);

        if (1 === preg_match($pattern, $request->getRequestUri())) {
            // Trigger generic 404 error to avoid leaking backend technology details.
            throw new NotFoundHttpException();
        }
    }
}

class_alias(RejectExplicitFrontControllerRequestsListener::class, 'eZ\Bundle\EzPublishCoreBundle\EventListener\RejectExplicitFrontControllerRequestsListener');
