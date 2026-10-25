<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\GameScore;
use Illuminate\Support\Facades\Validator;

class GameScoreService
{
    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 50;

    public const MAX_SCORE = 1_000_000_000;

    /**
     * @return array{scores: list<array{id: int, name: string, score: int, created_at: string|null}>}
     */
    public function list(CompanyProfile $profile, int $limit = self::DEFAULT_LIMIT): array
    {
        $scores = GameScore::query()
            ->whereBelongsTo($profile, 'profile')
            ->orderByDesc('score')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($this->limit($limit))
            ->get();

        return [
            'scores' => $scores->map(fn (GameScore $score): array => $this->present($score))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{id: int, name: string, score: int, created_at: string|null}
     */
    public function submit(CompanyProfile $profile, array $input): array
    {
        $data = $this->validate($input);

        $score = GameScore::query()->create([
            'company_profile_id' => $profile->id,
            'name' => $data['name'],
            'score' => $data['score'],
        ]);

        return $this->present($score);
    }

    /**
     * @return array{id: int, name: string, score: int, created_at: string|null}
     */
    private function present(GameScore $score): array
    {
        return [
            'id' => $score->id,
            'name' => $score->name,
            'score' => $score->score,
            'created_at' => $score->created_at?->toIso8601String(),
        ];
    }

    private function limit(int $limit): int
    {
        if ($limit < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min(self::MAX_LIMIT, $limit);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{name: string, score: int}
     */
    private function validate(array $input): array
    {
        if (array_key_exists('name', $input) && is_string($input['name'])) {
            $input['name'] = trim((string) preg_replace('/\s+/', ' ', $input['name']));
        }

        /** @var array{name: string, score: int} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'min:1', 'max:16', 'regex:/^(?=.*[\p{L}\p{N}])[\p{L}\p{N} ._\'-]+$/u'],
            'score' => ['required', 'integer', 'min:0', 'max:'.self::MAX_SCORE],
        ])->validate();

        return $data;
    }
}
