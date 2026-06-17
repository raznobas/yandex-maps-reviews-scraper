<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\SaveOrganizationSourceRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\ReviewResource;
use App\Jobs\SyncOrganizationReviews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    public function show(Request $request): OrganizationResource|JsonResponse
    {
        $organization = $request->user()->organization;

        if (! $organization) {
            return response()->json(['data' => null]);
        }

        return new OrganizationResource($organization);
    }

    public function reviews(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $organization = $request->user()->organization;

        if (! $organization) {
            return response()->json(['data' => [], 'meta' => ['total' => 0]]);
        }

        $reviews = $organization->reviews()
            ->latest('publish_date')
            ->paginate(50);

        return ReviewResource::collection($reviews);
    }

    public function store(SaveOrganizationSourceRequest $request): OrganizationResource
    {
        $organization = DB::transaction(function () use ($request) {
            $user = $request->user();
            $organization = $user->organization;
            $newYandexOrganizationId = $request->yandexOrganizationId();
            $organizationChanged = $organization
                && $organization->yandex_organization_id !== $newYandexOrganizationId;

            $organization = $user->organization()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'source_url' => $request->source_url,
                    'normalized_url' => $request->normalizedUrl(),
                    'yandex_organization_id' => $newYandexOrganizationId,
                    ...($organizationChanged ? [
                        'rating' => null,
                        'rating_count' => 0,
                        'review_count' => 0,
                        'last_synced_at' => null,
                        'sync_status' => 'idle',
                        'sync_error' => null,
                        'synced_reviews_count' => 0,
                        'last_sync_started_at' => null,
                        'last_sync_finished_at' => null,
                    ] : []),
                ]
            );

            if ($organizationChanged) {
                $organization->reviews()->delete();
            }

            return $organization;
        });

        return new OrganizationResource($organization);
    }

    public function sync(Request $request): OrganizationResource|JsonResponse
    {
        $organization = $request->user()->organization;

        if (! $organization) {
            return response()->json([
                'message' => 'Сначала сохраните ссылку на организацию в Яндекс.Картах.',
            ], 404);
        }

        if ($organization->sync_status !== 'running') {
            $organization->update([
                'sync_status' => 'running',
                'sync_error' => null,
                'last_sync_started_at' => now(),
                'last_sync_finished_at' => null,
            ]);

            SyncOrganizationReviews::dispatch($organization);
        }

        return (new OrganizationResource($organization->fresh()))
            ->response()
            ->setStatusCode(202);
    }
}
