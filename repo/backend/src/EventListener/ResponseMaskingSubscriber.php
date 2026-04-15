<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\Governance\FieldMaskingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Intercepts JSON API responses and applies field masking based on data classification
 * and user permissions. Sensitive fields (e.g. SSN) are masked to `***-**-1234` format
 * unless the user has explicit unmasked access.
 */
#[AsEventListener(event: 'kernel.response', priority: -10)]
final class ResponseMaskingSubscriber
{
    private const SENSITIVE_FIELD_PATTERNS = [
        'ssn',
        'social_security',
        'tax_id',
        'national_id',
    ];

    public function __construct(
        private readonly FieldMaskingService $maskingService,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $contentType = $response->headers->get('Content-Type', '');

        if (!str_contains($contentType, 'application/json')) {
            return;
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return;
        }

        $decoded = json_decode($content, true);
        if (!\is_array($decoded) || !isset($decoded['data'])) {
            return;
        }

        $user = $this->getCurrentUser();
        if ($user === null) {
            return;
        }

        $masked = false;
        $decoded['data'] = $this->maskRecursive($decoded['data'], $user, $masked);

        if ($masked) {
            $response->setContent(json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->logger->info('Masked sensitive fields in API response', [
                'uri' => $event->getRequest()->getRequestUri(),
                'user' => $user->getUsername(),
            ]);
        }
    }

    private function maskRecursive(mixed $data, User $user, bool &$masked): mixed
    {
        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                if (\is_string($key) && $this->isSensitiveField($key) && \is_string($value) && $value !== '') {
                    if ($this->maskingService->shouldMask('api_response', $key, $user)) {
                        $data[$key] = $this->maskingService->maskSsn($value);
                        $masked = true;
                    }
                } elseif (\is_array($value)) {
                    $data[$key] = $this->maskRecursive($value, $user, $masked);
                }
            }
        }

        return $data;
    }

    private function isSensitiveField(string $fieldName): bool
    {
        $lower = strtolower($fieldName);
        foreach (self::SENSITIVE_FIELD_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function getCurrentUser(): ?User
    {
        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return null;
        }

        $user = $token->getUser();

        return $user instanceof User ? $user : null;
    }
}
