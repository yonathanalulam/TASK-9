<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\DeliveryWindow;
use App\Entity\DeliveryZone;
use App\Entity\DeliveryZoneVersion;
use App\Entity\MdmRegion;
use App\Entity\MdmRegionVersion;
use App\Entity\Role;
use App\Entity\Store;
use App\Entity\StoreVersion;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\DayOfWeek;
use App\Enum\ScopeType;
use App\Enum\StoreType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed:demo',
    description: 'Seed demo data (users, regions, stores, zones, windows)',
)]
final class SeedDemoDataCommand extends Command
{
    private const PASSWORD = 'Demo#Password1!';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // ── 1. Seed roles first ─────────────────────────────────────────
        $seedRolesCommand = $this->getApplication()?->find('app:seed:roles');
        if ($seedRolesCommand !== null) {
            $seedRolesCommand->run(new ArrayInput([]), $output);
        }

        // ── 2. Idempotency check ────────────────────────────────────────
        $userRepo = $this->entityManager->getRepository(User::class);
        $existingAdmin = $userRepo->findOneBy(['username' => 'admin']);

        if ($existingAdmin !== null) {
            $io->warning('Demo data already exists, skipping.');
            return Command::SUCCESS;
        }

        // ── 3. Load role map ────────────────────────────────────────────
        $roleRepo = $this->entityManager->getRepository(Role::class);
        $roles = [];
        foreach ($roleRepo->findAll() as $role) {
            $roles[$role->getName()] = $role;
        }

        // ── 4. Regions ──────────────────────────────────────────────────
        $io->section('Creating regions');

        $regionData = [
            ['code' => 'NA',    'name' => 'North America',      'parent' => null, 'effectiveFrom' => '2026-01-01', 'level' => 0],
            ['code' => 'NORTH', 'name' => 'Northern Division',  'parent' => 'NA', 'effectiveFrom' => '2026-01-15', 'level' => 1],
            ['code' => 'SOUTH', 'name' => 'Southern Division',  'parent' => 'NA', 'effectiveFrom' => '2026-01-15', 'level' => 1],
            ['code' => 'EAST',  'name' => 'Eastern Division',   'parent' => 'NA', 'effectiveFrom' => '2026-02-01', 'level' => 1],
        ];

        /** @var array<string, MdmRegion> $regions */
        $regions = [];

        foreach ($regionData as $rd) {
            $region = new MdmRegion();
            $region->setCode($rd['code']);
            $region->setName($rd['name']);
            $region->setHierarchyLevel($rd['level']);
            $region->setEffectiveFrom(new \DateTimeImmutable($rd['effectiveFrom']));

            if ($rd['parent'] !== null) {
                $region->setParent($regions[$rd['parent']]);
            }

            $this->entityManager->persist($region);
            $regions[$rd['code']] = $region;
            $io->text(sprintf('  Created region: %s (%s)', $rd['code'], $rd['name']));
        }

        $this->entityManager->flush();

        // ── 5. Users ────────────────────────────────────────────────────
        $io->section('Creating users');

        $userData = [
            ['username' => 'admin',     'displayName' => 'Admin User',      'role' => 'administrator',       'scopeType' => ScopeType::GLOBAL, 'scopeRegion' => null],
            ['username' => 'mgr_north', 'displayName' => 'North Manager',   'role' => 'store_manager',       'scopeType' => ScopeType::REGION, 'scopeRegion' => 'NORTH'],
            ['username' => 'mgr_south', 'displayName' => 'South Manager',   'role' => 'store_manager',       'scopeType' => ScopeType::REGION, 'scopeRegion' => 'SOUTH'],
            ['username' => 'dispatch1', 'displayName' => 'Dispatcher One',  'role' => 'dispatcher',          'scopeType' => ScopeType::GLOBAL, 'scopeRegion' => null],
            ['username' => 'analyst1',  'displayName' => 'Analyst One',     'role' => 'operations_analyst',  'scopeType' => ScopeType::GLOBAL, 'scopeRegion' => null],
            ['username' => 'recruit1',  'displayName' => 'Recruiter One',   'role' => 'recruiter',           'scopeType' => ScopeType::REGION, 'scopeRegion' => 'NORTH'],
            ['username' => 'comply1',   'displayName' => 'Compliance One',  'role' => 'compliance_officer',  'scopeType' => ScopeType::GLOBAL, 'scopeRegion' => null],
        ];

        /** @var array<string, User> $users */
        $users = [];

        foreach ($userData as $ud) {
            $user = new User();
            $user->setUsername($ud['username']);
            $user->setDisplayName($ud['displayName']);
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, self::PASSWORD));

            $this->entityManager->persist($user);
            $users[$ud['username']] = $user;
            $io->text(sprintf('  Created user: %s', $ud['username']));
        }

        $this->entityManager->flush();

        // ── 6. Region versions (need admin user as changedBy) ───────────
        $io->section('Creating region versions');
        $adminUser = $users['admin'];

        foreach ($regions as $code => $region) {
            $rv = new MdmRegionVersion();
            $rv->setRegion($region);
            $rv->setVersionNumber(1);
            $rv->setChangeType('CREATED');
            $rv->setSnapshot([
                'code' => $region->getCode(),
                'name' => $region->getName(),
                'hierarchyLevel' => $region->getHierarchyLevel(),
            ]);
            $rv->setChangedBy($adminUser);
            $rv->setChangeReason('Initial demo seed');

            $this->entityManager->persist($rv);
            $io->text(sprintf('  Created region version: %s v1', $code));
        }

        $this->entityManager->flush();

        // ── 7. Role assignments ─────────────────────────────────────────
        $io->section('Creating role assignments');
        $effectiveFrom = new \DateTimeImmutable('2026-01-01');

        foreach ($userData as $ud) {
            $assignment = new UserRoleAssignment();
            $assignment->setUser($users[$ud['username']]);
            $assignment->setRole($roles[$ud['role']]);
            $assignment->setScopeType($ud['scopeType']);
            $assignment->setEffectiveFrom($effectiveFrom);
            $assignment->setGrantedBy($adminUser);

            if ($ud['scopeRegion'] !== null) {
                $assignment->setScopeId($regions[$ud['scopeRegion']]->getId()->toBinary());
            }

            $this->entityManager->persist($assignment);
            $io->text(sprintf('  Assigned %s -> %s (%s)', $ud['username'], $ud['role'], $ud['scopeType']->value));
        }

        $this->entityManager->flush();

        // ── 8. Stores ───────────────────────────────────────────────────
        $io->section('Creating stores');

        $storeData = [
            ['code' => 'STORE-NYC-001', 'name' => 'NYC Downtown',      'type' => StoreType::STORE,      'region' => 'NORTH', 'tz' => 'America/New_York'],
            ['code' => 'STORE-NYC-002', 'name' => 'NYC Midtown Dark',  'type' => StoreType::DARK_STORE, 'region' => 'NORTH', 'tz' => 'America/New_York'],
            ['code' => 'STORE-MIA-001', 'name' => 'Miami Beach',       'type' => StoreType::STORE,      'region' => 'SOUTH', 'tz' => 'America/New_York'],
            ['code' => 'STORE-ATL-001', 'name' => 'Atlanta Central',   'type' => StoreType::STORE,      'region' => 'EAST',  'tz' => 'America/New_York'],
        ];

        /** @var array<string, Store> $stores */
        $stores = [];

        foreach ($storeData as $sd) {
            $store = new Store();
            $store->setCode($sd['code']);
            $store->setName($sd['name']);
            $store->setStoreType($sd['type']);
            $store->setRegion($regions[$sd['region']]);
            $store->setTimezone($sd['tz']);

            $this->entityManager->persist($store);
            $stores[$sd['code']] = $store;
            $io->text(sprintf('  Created store: %s (%s)', $sd['code'], $sd['name']));
        }

        $this->entityManager->flush();

        // ── 9. Store versions ───────────────────────────────────────────
        $io->section('Creating store versions');

        foreach ($stores as $code => $store) {
            $sv = new StoreVersion();
            $sv->setStore($store);
            $sv->setVersionNumber(1);
            $sv->setChangeType('CREATED');
            $sv->setSnapshot([
                'code' => $store->getCode(),
                'name' => $store->getName(),
                'storeType' => $store->getStoreType()->value,
                'region' => $store->getRegion()->getCode(),
                'timezone' => $store->getTimezone(),
            ]);
            $sv->setChangedBy($adminUser);
            $sv->setChangeReason('Initial demo seed');

            $this->entityManager->persist($sv);
            $io->text(sprintf('  Created store version: %s v1', $code));
        }

        $this->entityManager->flush();

        // ── 10. Delivery zones (2 per non-dark store) ───────────────────
        $io->section('Creating delivery zones');

        /** @var DeliveryZone[] $allZones */
        $allZones = [];

        foreach ($stores as $code => $store) {
            if ($store->getStoreType() === StoreType::DARK_STORE) {
                $io->text(sprintf('  Skipping dark store: %s', $code));
                continue;
            }

            foreach (['Zone A', 'Zone B'] as $zoneName) {
                $zone = new DeliveryZone();
                $zone->setStore($store);
                $zone->setName($zoneName);
                $zone->setMinOrderThreshold('25.00');
                $zone->setDeliveryFee('3.99');

                $this->entityManager->persist($zone);
                $allZones[] = $zone;
                $io->text(sprintf('  Created zone: %s - %s', $code, $zoneName));
            }
        }

        $this->entityManager->flush();

        // ── 11. Delivery zone versions ──────────────────────────────────
        $io->section('Creating delivery zone versions');

        foreach ($allZones as $zone) {
            $dzv = new DeliveryZoneVersion();
            $dzv->setZone($zone);
            $dzv->setVersionNumber(1);
            $dzv->setChangeType('CREATED');
            $dzv->setSnapshot([
                'name' => $zone->getName(),
                'store' => $zone->getStore()->getCode(),
                'minOrderThreshold' => $zone->getMinOrderThreshold(),
                'deliveryFee' => $zone->getDeliveryFee(),
            ]);
            $dzv->setChangedBy($adminUser);
            $dzv->setChangeReason('Initial demo seed');

            $this->entityManager->persist($dzv);
            $io->text(sprintf('  Created zone version: %s - %s v1', $zone->getStore()->getCode(), $zone->getName()));
        }

        $this->entityManager->flush();

        // ── 12. Delivery windows (14 per zone: 7 days x 2 slots) ───────
        $io->section('Creating delivery windows');

        $morningStart = new \DateTimeImmutable('09:00');
        $morningEnd   = new \DateTimeImmutable('12:00');
        $eveningStart = new \DateTimeImmutable('16:00');
        $eveningEnd   = new \DateTimeImmutable('20:00');

        $windowCount = 0;

        foreach ($allZones as $zone) {
            foreach (DayOfWeek::cases() as $day) {
                // Morning window
                $am = new DeliveryWindow();
                $am->setZone($zone);
                $am->setDayOfWeek($day->value);
                $am->setStartTime($morningStart);
                $am->setEndTime($morningEnd);
                $this->entityManager->persist($am);

                // Evening window
                $pm = new DeliveryWindow();
                $pm->setZone($zone);
                $pm->setDayOfWeek($day->value);
                $pm->setStartTime($eveningStart);
                $pm->setEndTime($eveningEnd);
                $this->entityManager->persist($pm);

                $windowCount += 2;
            }

            $io->text(sprintf('  Created 14 windows for zone: %s - %s', $zone->getStore()->getCode(), $zone->getName()));
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Demo seed complete: %d regions, %d users, %d stores, %d zones, %d windows.',
            \count($regions),
            \count($users),
            \count($stores),
            \count($allZones),
            $windowCount,
        ));

        return Command::SUCCESS;
    }
}
