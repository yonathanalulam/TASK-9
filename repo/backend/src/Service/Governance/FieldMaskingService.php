<?php

declare(strict_types=1);

namespace App\Service\Governance;

use App\Entity\DataClassification;
use App\Entity\FieldAccessPolicy;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use Doctrine\ORM\EntityManagerInterface;

class FieldMaskingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Mask an SSN value, showing only the last 4 digits.
     *
     * Returns "***-**-XXXX" where XXXX are the last 4 characters.
     */
    public function maskSsn(string $value): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $value);
        $lastFour = substr($cleaned, -4);

        return '***-**-' . $lastFour;
    }

    /**
     * Determine whether a field should be masked for the given user.
     *
     * Checks field_access_policies and data_classifications to decide
     * whether the user is allowed to see the unmasked value.
     */
    public function shouldMask(string $entityType, string $fieldName, User $user): bool
    {
        // Check data classification — RESTRICTED or HIGHLY_RESTRICTED fields require masking by default
        $classification = $this->entityManager->getRepository(DataClassification::class)
            ->createQueryBuilder('dc')
            ->where('dc.entityType = :entityType')
            ->andWhere('dc.fieldName = :fieldName')
            ->setParameter('entityType', $entityType)
            ->setParameter('fieldName', $fieldName)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($classification === null) {
            // No classification record — do not mask
            return false;
        }

        $level = $classification->getClassification();

        if (\in_array($level, ['PUBLIC_INTERNAL', 'CONFIDENTIAL'], true)) {
            return false;
        }

        // For RESTRICTED / HIGHLY_RESTRICTED, check if user has explicit read access
        $roleAssignments = $this->entityManager->getRepository(UserRoleAssignment::class)
            ->findBy(['user' => $user]);

        foreach ($roleAssignments as $assignment) {
            $policy = $this->entityManager->getRepository(FieldAccessPolicy::class)
                ->createQueryBuilder('fap')
                ->where('fap.role = :role')
                ->andWhere('fap.entityType = :entityType')
                ->andWhere('fap.fieldName = :fieldName')
                ->andWhere('fap.canRead = :canRead')
                ->setParameter('role', $assignment->getRole())
                ->setParameter('entityType', $entityType)
                ->setParameter('fieldName', $fieldName)
                ->setParameter('canRead', true)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($policy !== null) {
                return false;
            }
        }

        // User has no explicit read access to a restricted field — mask it
        return true;
    }
}
