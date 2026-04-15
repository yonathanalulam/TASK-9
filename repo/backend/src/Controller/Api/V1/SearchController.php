<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\User;
use App\Service\Authorization\ScopeResolver;
use App\Service\Search\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly ScopeResolver $scopeResolver,
    ) {
    }

    #[Route('/api/v1/search', name: 'api_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::SEARCH_EXECUTE);

        $query = $request->query->get('q', '');

        if (!is_string($query) || trim($query) === '') {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'Query parameter "q" is required.'),
                422,
            );
        }

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));
        $sort = $request->query->get('sort', 'relevance');

        if (!in_array($sort, ['relevance', 'newest', 'most_viewed', 'highest_reply'], true)) {
            $sort = 'relevance';
        }

        $filters = [
            'contentType' => $request->query->get('type'),
            'storeId' => $request->query->get('store'),
            'regionId' => $request->query->get('region'),
            'dateFrom' => $request->query->get('date_from'),
            'dateTo' => $request->query->get('date_to'),
        ];

        // Resolve accessible store and region IDs for the current user.
        /** @var User $user */
        $user = $this->getUser();
        $accessibleStoreIds = $this->scopeResolver->getAccessibleStoreIds($user);
        $accessibleRegionIds = $this->scopeResolver->getAccessibleRegionIds($user);

        // Convert Uuid objects to string IDs for the search service.
        $accessibleStoreIdStrings = null;
        if ($accessibleStoreIds !== null) {
            $accessibleStoreIdStrings = array_map(
                static fn ($uuid) => $uuid->toRfc4122(),
                $accessibleStoreIds,
            );
        }

        $accessibleRegionIdStrings = null;
        if ($accessibleRegionIds !== null) {
            $accessibleRegionIdStrings = array_map(
                static fn ($uuid) => $uuid->toRfc4122(),
                $accessibleRegionIds,
            );
        }

        $result = $this->searchService->search(
            $query,
            $filters,
            $sort,
            $page,
            $perPage,
            $accessibleStoreIdStrings,
            $accessibleRegionIdStrings,
        );

        return new JsonResponse(PaginatedEnvelope::wrap(
            $result['items'],
            $page,
            $perPage,
            $result['total'],
        ));
    }
}
