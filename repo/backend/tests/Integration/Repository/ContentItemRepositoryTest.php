<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\ContentItem;
use App\Repository\ContentItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ContentItemRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ContentItemRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repository = $this->em->getRepository(ContentItem::class);
    }

    public function testPaginationReturnsCorrectSlice(): void
    {
        // Seed 5 content items.
        for ($i = 1; $i <= 5; $i++) {
            $this->createContentItem("PAGINATE_NOTICE_{$i}", 'OPERATIONAL_NOTICE', "Notice {$i}");
        }
        $this->em->flush();

        // Query with pagination: page 1 (offset 0, limit 2).
        $qb = $this->repository->createQueryBuilder('c')
            ->where('c.contentType = :type')
            ->setParameter('type', 'OPERATIONAL_NOTICE')
            ->andWhere('c.title LIKE :prefix')
            ->setParameter('prefix', 'Notice %')
            ->orderBy('c.title', 'ASC');

        // Get total count.
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        self::assertSame(5, $total);

        // Get first page (2 items).
        $page1 = $qb->setFirstResult(0)->setMaxResults(2)->getQuery()->getResult();
        self::assertCount(2, $page1);

        // Get second page (2 items).
        $page2 = $qb->setFirstResult(2)->setMaxResults(2)->getQuery()->getResult();
        self::assertCount(2, $page2);

        // Get third page (1 item).
        $page3 = $qb->setFirstResult(4)->setMaxResults(2)->getQuery()->getResult();
        self::assertCount(1, $page3);

        // No overlap between pages.
        $page1Ids = array_map(static fn (ContentItem $c) => (string) $c->getId(), $page1);
        $page2Ids = array_map(static fn (ContentItem $c) => (string) $c->getId(), $page2);
        $page3Ids = array_map(static fn (ContentItem $c) => (string) $c->getId(), $page3);

        self::assertEmpty(array_intersect($page1Ids, $page2Ids));
        self::assertEmpty(array_intersect($page2Ids, $page3Ids));
        self::assertEmpty(array_intersect($page1Ids, $page3Ids));
    }

    public function testFilterByContentTypeReturnsOnlyMatchingItems(): void
    {
        $this->createContentItem('TYPE_JOB_1', 'JOB_POST', 'Job Posting 1');
        $this->createContentItem('TYPE_JOB_2', 'JOB_POST', 'Job Posting 2');
        $this->createContentItem('TYPE_NOTICE_1', 'OPERATIONAL_NOTICE', 'Operational Notice 1');
        $this->createContentItem('TYPE_BULLETIN_1', 'VENDOR_BULLETIN', 'Vendor Bulletin 1');
        $this->em->flush();

        $jobPosts = $this->repository->findBy(['contentType' => 'JOB_POST']);
        $notices = $this->repository->findBy(['contentType' => 'OPERATIONAL_NOTICE']);
        $bulletins = $this->repository->findBy(['contentType' => 'VENDOR_BULLETIN']);

        self::assertGreaterThanOrEqual(2, count($jobPosts));
        self::assertGreaterThanOrEqual(1, count($notices));
        self::assertGreaterThanOrEqual(1, count($bulletins));

        // Verify every returned job post has the correct content_type.
        foreach ($jobPosts as $item) {
            self::assertSame('JOB_POST', $item->getContentType());
        }

        foreach ($notices as $item) {
            self::assertSame('OPERATIONAL_NOTICE', $item->getContentType());
        }

        foreach ($bulletins as $item) {
            self::assertSame('VENDOR_BULLETIN', $item->getContentType());
        }
    }

    public function testFilterByStatusReturnsOnlyMatchingItems(): void
    {
        $draft = $this->createContentItem('STATUS_DRAFT', 'JOB_POST', 'Draft Item');
        $draft->setStatus('DRAFT');

        $published = $this->createContentItem('STATUS_PUB', 'JOB_POST', 'Published Item');
        $published->setStatus('PUBLISHED');
        $published->setPublishedAt(new \DateTimeImmutable());

        $archived = $this->createContentItem('STATUS_ARC', 'JOB_POST', 'Archived Item');
        $archived->setStatus('ARCHIVED');

        $this->em->flush();

        $draftResults = $this->repository->findBy(['status' => 'DRAFT', 'authorName' => 'Test Author']);
        $publishedResults = $this->repository->findBy(['status' => 'PUBLISHED', 'authorName' => 'Test Author']);
        $archivedResults = $this->repository->findBy(['status' => 'ARCHIVED', 'authorName' => 'Test Author']);

        self::assertGreaterThanOrEqual(1, count($draftResults));
        self::assertGreaterThanOrEqual(1, count($publishedResults));
        self::assertGreaterThanOrEqual(1, count($archivedResults));

        // Verify statuses match.
        foreach ($draftResults as $item) {
            self::assertSame('DRAFT', $item->getStatus());
        }

        foreach ($publishedResults as $item) {
            self::assertSame('PUBLISHED', $item->getStatus());
        }
    }

    public function testQueryBuilderFilterByCombinedCriteria(): void
    {
        $this->createContentItem('COMBO_1', 'JOB_POST', 'Combo Job 1');
        $item2 = $this->createContentItem('COMBO_2', 'JOB_POST', 'Combo Job 2');
        $item2->setStatus('PUBLISHED');
        $item2->setPublishedAt(new \DateTimeImmutable());

        $this->createContentItem('COMBO_3', 'OPERATIONAL_NOTICE', 'Combo Notice 1');
        $this->em->flush();

        $results = $this->repository->createQueryBuilder('c')
            ->where('c.contentType = :type')
            ->andWhere('c.status = :status')
            ->andWhere('c.authorName = :author')
            ->setParameter('type', 'JOB_POST')
            ->setParameter('status', 'PUBLISHED')
            ->setParameter('author', 'Test Author')
            ->getQuery()
            ->getResult();

        self::assertGreaterThanOrEqual(1, count($results));

        foreach ($results as $item) {
            self::assertSame('JOB_POST', $item->getContentType());
            self::assertSame('PUBLISHED', $item->getStatus());
        }
    }

    private function createContentItem(string $titleSuffix, string $contentType, string $title): ContentItem
    {
        $item = new ContentItem();
        $item->setContentType($contentType);
        $item->setTitle($title);
        $item->setBody("Body for {$titleSuffix}");
        $item->setAuthorName('Test Author');

        $this->em->persist($item);

        return $item;
    }
}
