<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AuditEvent;
use App\Entity\AuditEventHash;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
final class AuditImmutabilityListener
{
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof AuditEvent || $entity instanceof AuditEventHash) {
            throw new \LogicException('Audit events are immutable and cannot be updated');
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof AuditEvent || $entity instanceof AuditEventHash) {
            throw new \LogicException('Audit events are immutable and cannot be deleted');
        }
    }
}
