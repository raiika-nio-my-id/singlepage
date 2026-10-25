<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\GameScore;
use App\Services\GameScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GameScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_tenant_returns_an_empty_score_list(): void
    {
        $this->publish('bumerang');

        $this->getJson('http://bumerang.jadidaring.test/api/game/scores')
            ->assertOk()
            ->assertExactJson(['scores' => []]);
    }

    public function test_submitting_a_score_persists_it_for_the_current_tenant(): void
    {
        $profile = $this->publish('bumerang');
        $this->travelTo('2026-10-25 12:00:00');

        $this->postJson('http://bumerang.jadidaring.test/api/game/scores', [
            'name' => '  Ada  Bayu ',
            'score' => 12345,
        ])->assertOk()
            ->assertJsonPath('name', 'Ada Bayu')
            ->assertJsonPath('score', 12345)
            ->assertJsonPath('created_at', '2026-10-25T12:00:00+00:00')
            ->assertJsonStructure(['id', 'name', 'score', 'created_at']);

        $this->assertDatabaseHas('game_scores', [
            'company_profile_id' => $profile->id,
            'name' => 'Ada Bayu',
            'score' => 12345,
        ]);
    }

    public function test_top_list_orders_by_score_then_newest_and_respects_limit(): void
    {
        $profile = $this->publish('bumerang');

        $this->travelTo('2026-10-25 10:00:00');
        GameScore::factory()->for($profile, 'profile')->create(['name' => 'Low', 'score' => 10]);
        GameScore::factory()->for($profile, 'profile')->create(['name' => 'OldHigh', 'score' => 100]);

        $this->travelTo('2026-10-25 10:01:00');
        GameScore::factory()->for($profile, 'profile')->create(['name' => 'NewHigh', 'score' => 100]);
        GameScore::factory()->for($profile, 'profile')->create(['name' => 'Mid', 'score' => 50]);

        $this->travelTo('2026-10-25 10:02:00');
        GameScore::factory()->for($profile, 'profile')->count(51)->sequence(
            fn ($sequence) => ['name' => 'P'.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT), 'score' => 1],
        )->create();

        $this->getJson('http://bumerang.jadidaring.test/api/game/scores')
            ->assertOk()
            ->assertJsonCount(GameScoreService::DEFAULT_LIMIT, 'scores')
            ->assertJsonPath('scores.0.name', 'NewHigh')
            ->assertJsonPath('scores.1.name', 'OldHigh')
            ->assertJsonPath('scores.2.name', 'Mid');

        $this->getJson('http://bumerang.jadidaring.test/api/game/scores?limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'scores')
            ->assertJsonPath('scores.0.name', 'NewHigh')
            ->assertJsonPath('scores.1.name', 'OldHigh');

        $this->getJson('http://bumerang.jadidaring.test/api/game/scores?limit=100')
            ->assertOk()
            ->assertJsonCount(GameScoreService::MAX_LIMIT, 'scores');
    }

    public function test_scores_stay_inside_the_game_subdomain(): void
    {
        $mahjong = $this->publish('mahjong');
        $this->publish('explodingkitten');

        GameScore::factory()->for($mahjong, 'profile')->create([
            'name' => 'Ada',
            'score' => 900,
        ]);

        $this->postJson('http://explodingkitten.jadidaring.test/api/game/scores', [
            'name' => 'Bayu',
            'score' => 50,
        ])->assertOk();

        $this->getJson('http://mahjong.jadidaring.test/api/game/scores')
            ->assertOk()
            ->assertJsonCount(1, 'scores')
            ->assertJsonPath('scores.0.name', 'Ada')
            ->assertJsonPath('scores.0.score', 900);

        $this->getJson('http://explodingkitten.jadidaring.test/api/game/scores')
            ->assertOk()
            ->assertJsonCount(1, 'scores')
            ->assertJsonPath('scores.0.name', 'Bayu')
            ->assertJsonPath('scores.0.score', 50);

        CompanyProfile::factory()->create(['subdomain' => 'hidden']);

        $this->getJson('http://hidden.jadidaring.test/api/game/scores')->assertNotFound();
        $this->postJson('http://hidden.jadidaring.test/api/game/scores', [
            'name' => 'Sneak',
            'score' => 1,
        ])->assertNotFound();
        $this->getJson('http://admin.jadidaring.test/api/game/scores')->assertNotFound();
    }

    public function test_empty_payload_returns_422_for_required_fields(): void
    {
        $this->publish('bumerang');

        $this->postJson('http://bumerang.jadidaring.test/api/game/scores', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'score']);

        $this->assertDatabaseCount('game_scores', 0);
    }

    #[DataProvider('invalidScorePayloads')]
    public function test_invalid_score_payload_returns_422(array $payload, string $field, string $message): void
    {
        $this->publish('bumerang');

        $this->postJson('http://bumerang.jadidaring.test/api/game/scores', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                $field => $message,
            ]);

        $this->assertDatabaseCount('game_scores', 0);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidScorePayloads(): array
    {
        return [
            'blank name' => [
                ['name' => '   ', 'score' => 10],
                'name',
                'The name field is required.',
            ],
            'name too long' => [
                ['name' => 'SixteenCharactersX', 'score' => 10],
                'name',
                'The name field must not be greater than 16 characters.',
            ],
            'name charset' => [
                ['name' => 'Ada<script>', 'score' => 10],
                'name',
                'The name field format is invalid.',
            ],
            'negative score' => [
                ['name' => 'Ada', 'score' => -1],
                'score',
                'The score field must be at least 0.',
            ],
            'score too large' => [
                ['name' => 'Ada', 'score' => GameScoreService::MAX_SCORE + 1],
                'score',
                'The score field must not be greater than 1000000000.',
            ],
        ];
    }

    private function publish(string $subdomain): CompanyProfile
    {
        return CompanyProfile::factory()->published()->create([
            'subdomain' => $subdomain,
            'html' => '<html><body>'.$subdomain.'</body></html>',
        ]);
    }
}
