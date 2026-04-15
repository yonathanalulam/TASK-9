<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<User>
 */
class UserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findOneBy(['username' => $identifier]);

        if ($user === null) {
            $exception = new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UserNotFoundException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $refreshed = $this->userRepository->find($user->getId());

        if ($refreshed === null) {
            $exception = new UserNotFoundException(sprintf('User with ID "%s" not found.', $user->getId()));
            $exception->setUserIdentifier($user->getUserIdentifier());

            throw $exception;
        }

        return $refreshed;
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class || is_subclass_of($class, User::class);
    }
}
