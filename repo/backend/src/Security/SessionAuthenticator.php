<?php

declare(strict_types=1);

namespace App\Security;

use App\Dto\Response\ErrorEnvelope;
use App\Repository\UserRepository;
use App\Service\Auth\SessionManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class SessionAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $authorization = $request->headers->get('Authorization', '');

        return str_starts_with($authorization, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $authorization = $request->headers->get('Authorization', '');
        $token = substr($authorization, 7); // strip "Bearer "

        $session = $this->sessionManager->validateToken($token);

        if ($session === null) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired session');
        }

        $user = $session->getUser();

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier()),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ErrorEnvelope::create('AUTHENTICATION_FAILED', $exception->getMessageKey()),
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
