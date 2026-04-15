<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Dto\Response\ErrorEnvelope;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[AsEventListener(event: ExceptionEvent::class)]
final class ApiExceptionListener
{
    public function __construct(
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $request = $event->getRequest();

        [$statusCode, $errorCode] = match (true) {
            $throwable instanceof NotFoundHttpException => [404, 'NOT_FOUND'],
            $throwable instanceof AccessDeniedHttpException => [403, 'FORBIDDEN'],
            $throwable instanceof AuthenticationException => [401, 'UNAUTHORIZED'],
            $throwable instanceof ConflictHttpException => [409, 'CONFLICT'],
            $throwable instanceof TooManyRequestsHttpException => [429, 'TOO_MANY_REQUESTS'],
            $throwable instanceof HttpException => [$throwable->getStatusCode(), 'HTTP_ERROR'],
            default => [500, 'INTERNAL_ERROR'],
        };

        // Structured logging for security and operational observability
        $logContext = [
            'status' => $statusCode,
            'error_code' => $errorCode,
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'ip' => $request->getClientIp(),
        ];

        if ($statusCode === 403) {
            $this->logger->warning('Authorization denied', $logContext);
        } elseif ($statusCode === 401) {
            $this->logger->notice('Authentication failure', $logContext);
        } elseif ($statusCode >= 500) {
            $this->logger->error('Internal server error: ' . $throwable->getMessage(), $logContext);
        }

        $details = [];

        // Exception details are only exposed in local development environments.
        // The APP_DEBUG kernel flag is the authoritative gate — it is false in
        // production by default even when APP_ENV=dev, and CI/shared test
        // environments can disable it explicitly.
        if ($this->debug) {
            $details['exception_class'] = $throwable::class;
            $details['trace'] = $throwable->getTraceAsString();
        }

        $body = ErrorEnvelope::create($errorCode, $throwable->getMessage(), $details);

        $event->setResponse(new JsonResponse($body, $statusCode));
    }
}
