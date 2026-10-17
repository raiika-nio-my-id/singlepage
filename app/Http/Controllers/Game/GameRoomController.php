<?php

namespace App\Http\Controllers\Game;

use App\Exceptions\GameRoomException;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Services\GameRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameRoomController extends Controller
{
    public function __construct(private readonly GameRoomService $rooms) {}

    public function config(Request $request): JsonResponse
    {
        return response()->json($this->rooms->describe($request, $this->profile($request)));
    }

    public function index(Request $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->rooms->list($this->profile($request)));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->rooms->create($this->profile($request), $request->all()));
    }

    public function join(Request $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->rooms->join($this->profile($request), $request->all()));
    }

    public function match(Request $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->rooms->match($this->profile($request), $request->all()));
    }

    public function show(Request $request, string $tenant, string $code): JsonResponse
    {
        $since = $request->query('since');

        return $this->respond(fn (): array => $this->rooms->show(
            $this->profile($request, $tenant),
            $code,
            $this->rooms->token($request),
            is_numeric($since) ? (int) $since : null,
        ));
    }

    public function watch(Request $request, string $tenant, string $code): JsonResponse
    {
        return $this->respond(fn (): array => $this->rooms->watch($this->profile($request, $tenant), $code, $request->all()));
    }

    public function act(Request $request, string $tenant, string $code): JsonResponse
    {
        return $this->respond(fn (): array => $this->rooms->act(
            $this->profile($request, $tenant),
            $code,
            $this->rooms->token($request),
            $request->all(),
        ));
    }

    public function leave(Request $request, string $tenant, string $code): JsonResponse
    {
        return $this->respond(fn (): array => $this->rooms->leave(
            $this->profile($request, $tenant),
            $code,
            $this->rooms->token($request),
        ));
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
