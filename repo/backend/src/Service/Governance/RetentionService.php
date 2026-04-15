<?php

declare(strict_types=1);

namespace App\Service\Governance;

use App\Entity\ContentItem;
use App\Entity\RetentionCase;
use App\Repository\RetentionCaseRepository;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;

class RetentionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RetentionCaseRepository $retentionCaseRepository,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * Scan for entities past their retention period and create ELIGIBLE cases.
     *
     * @return int Number of new eligible cases created.
     */
    public function scanEligible(): int
    {
        $now = new \DateTimeImmutable();
        $defaultRetentionDays = 365;
        $cutoff = $now->modify(sprintf('-%d days', $defaultRetentionDays));

        // Find content items past the retention period with no existing retention case
        $qb = $this->entityManager->createQueryBuilder();
        $subQuery = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(RetentionCase::class, 'rc')
            ->where('rc.entityType = :entityType')
            ->andWhere('rc.entityId = ci.id');

        $contentItems = $qb
            ->select('ci')
            ->from(ContentItem::class, 'ci')
            ->where('ci.createdAt < :cutoff')
            ->andWhere($qb->expr()->not(
                $qb->expr()->exists($subQuery->getDQL()),
            ))
            ->setParameter('cutoff', $cutoff)
            ->setParameter('entityType', 'ContentItem')
            ->getQuery()
            ->getResult();

        $count = 0;

        foreach ($contentItems as $item) {
            $case = new RetentionCase();
            $case->setEntityType('ContentItem');
            $case->setEntityId($item->getId()->toBinary());
            $case->setStatus('ELIGIBLE');
            $case->setRetentionDays($defaultRetentionDays);
            $case->setEligibleAt($now);

            $this->entityManager->persist($case);
            $count++;
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Execute a retention case — delete or anonymize based on policy.
     */
    public function executeCase(RetentionCase $case): void
    {
        $case->setStatus('RUNNING');
        $this->entityManager->flush();

        try {
            $entityType = $case->getEntityType();
            $entityId = $case->getEntityId();

            if ($entityType === 'ContentItem') {
                $this->anonymizeContentItem($entityId);
                $case->setActionTaken('ANONYMIZE');
            } else {
                // Default action for unknown entity types: mark as anonymized
                $case->setActionTaken('ANONYMIZE');
            }

            $case->setStatus('ANONYMIZED');
            $case->setExecutedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->auditService->record(
                action: 'RETENTION_EXECUTED',
                entityType: $entityType,
                entityId: bin2hex($entityId),
                oldValues: null,
                newValues: [
                    'action_taken' => $case->getActionTaken(),
                    'retention_case_id' => $case->getId()->toRfc4122(),
                ],
            );
        } catch (\Throwable $e) {
            $case->setStatus('FAILED');
            $case->setErrorDetail($e->getMessage());
            $this->entityManager->flush();

            throw $e;
        }
    }

    /**
     * Anonymize a content item by clearing PII fields.
     */
    private function anonymizeContentItem(string $entityId): void
    {
        $item = $this->entityManager->getRepository(ContentItem::class)->find($entityId);

        if ($item === null) {
            return;
        }

        $item->setAuthorName('[REDACTED]');
        $item->setBody('[Content removed per retention policy]');
        $item->setStatus('ARCHIVED');
        $item->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }
}
