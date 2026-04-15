<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:roles',
    description: 'Seed the canonical platform roles',
)]
final class SeedRolesCommand extends Command
{
    /** @var array<string, array{displayName: string, description: string}> */
    private const ROLES = [
        'store_manager' => [
            'displayName' => 'Store Manager',
            'description' => 'Manages individual store operations',
        ],
        'dispatcher' => [
            'displayName' => 'Dispatcher',
            'description' => 'Manages delivery scheduling and zone windows',
        ],
        'operations_analyst' => [
            'displayName' => 'Operations Analyst',
            'description' => 'Read-only analytics and reporting access',
        ],
        'recruiter' => [
            'displayName' => 'Recruiter',
            'description' => 'Manages driver and staff recruitment',
        ],
        'compliance_officer' => [
            'displayName' => 'Compliance Officer',
            'description' => 'Audit trail review and compliance reporting',
        ],
        'administrator' => [
            'displayName' => 'Administrator',
            'description' => 'Full system administration access',
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $roleRepo = $this->entityManager->getRepository(Role::class);

        foreach (self::ROLES as $name => $meta) {
            $existing = $roleRepo->findOneBy(['name' => $name]);

            if ($existing !== null) {
                $io->text(sprintf('Role already exists: %s', $name));
                continue;
            }

            $role = new Role();
            $role->setName($name);
            $role->setDisplayName($meta['displayName']);
            $role->setDescription($meta['description']);
            $role->setIsSystem(true);

            $this->entityManager->persist($role);
            $io->text(sprintf('Created role: %s', $name));
        }

        $this->entityManager->flush();

        $io->success('Role seeding complete.');

        return Command::SUCCESS;
    }
}
