<?php

namespace AV\ActivityPubBundle\EventListener;

use Monolog\Logger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    /** @var string */
    private $env;

    /** @var Logger */
    private $logger;

    public function __construct(string $env, Logger $logger)
    {
        $this->env = $env;
        $this->logger = $logger;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        $response = new Response();

        if ($exception instanceof HttpExceptionInterface) {
            $response->setStatusCode($exception->getStatusCode());
            $response->headers->replace($exception->getHeaders());
        } else {
            // Display pretty Symfony errors on dev environment
            if ($this->env === 'dev') {
                return $event->getResponse();
            } else {
                $this->logger->error($exception->getMessage(), [$exception->getTrace()]);

                $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $event->setResponse($response);

        return $response;
    }
}
