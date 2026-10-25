<?php

namespace App\Http\Controllers\Game;

use App\Exceptions\GameRoomException;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Services\GameScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameScoreController extends Controller
{
    public function __construct(private readonly GameScoreService $scores) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respond(
            fn (): array => $this->scores->list($this->profile($request), $request->integer('limit', GameScoreService::DEFAULT_LIMIT)),
        );
    }

    public function store(Request $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->scores->submit($this->profile($request), $request->all()));
    }

    private function profile(Request $request, ?string $tenant = null): CompanyProfile
    {
        $profile = $request->attributes->get('tenantProfile');

        abort_unless($profile instanceof CompanyProfile, 404);
        abort_unless($tenant === null || strtolower($tenant) === $profile->subdomain, 404);

        return $profile;
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     */
    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback());
        } catch (GameRoomException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], $exception->status);
        }
    }
}
