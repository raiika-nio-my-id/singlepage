<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreAdminClientRequest;
use App\Http\Requests\Api\Admin\UpdateAdminClientRequest;
use App\Http\Resources\AdminClientResource;
use App\Models\CompanyProfile;
use App\Services\AdminClientService;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    public function store(StoreAdminClientRequest $request, AdminClientService $clients): JsonResponse
    {
        $profile = $clients->create($request->validated());

        return (new AdminClientResource($profile))
            ->additional(['message' => 'Client created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateAdminClientRequest $request,
        CompanyProfile $companyProfile,
        AdminClientService $clients,
    ): JsonResponse {
        $profile = $clients->update($companyProfile, $request->validated());

        return (new AdminClientResource($profile))
            ->additional(['message' => 'Client updated.'])
            ->response();
    }

    public function updateBySubdomain(
        UpdateAdminClientRequest $request,
        string $subdomain,
        AdminClientService $clients,
    ): JsonResponse {
        $profile = $clients->findBySubdomain($subdomain);

        return $this->update($request, $profile, $clients);
    }
}
