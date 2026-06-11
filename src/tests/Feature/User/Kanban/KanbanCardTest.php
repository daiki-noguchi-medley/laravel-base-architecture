<?php

declare(strict_types=1);

namespace Tests\Feature\User\Kanban;

use App\Auth\User\UserAuth;
use App\Enum\Kanban\KanbanLane;
use Demo\User\Repository\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class KanbanCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/kanban')
            ->assertRedirect('/login');

        $this->getJson('/kanban/cards')
            ->assertUnauthorized();
    }

    public function test_kanban_page_renders_with_lanes(): void
    {
        $this->actingAsUser()
            ->get('/kanban')
            ->assertOk()
            ->assertSee('TODO')
            ->assertSee('DOING')
            ->assertSee('REVIEW')
            ->assertSee('DONE');
    }

    public function test_user_can_list_own_cards_only(): void
    {
        $userId = $this->createUser('alice@example.com');
        $otherId = $this->createUser('bob@example.com');

        DB::table('kanban_card')->insert([
            ['user_id' => $userId,  'title' => 'mine A',   'body' => '', 'lane' => 'todo',  'position' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $userId,  'title' => 'mine B',   'body' => '', 'lane' => 'doing', 'position' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $otherId, 'title' => 'other X',  'body' => '', 'lane' => 'todo',  'position' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAsUserId($userId)
            ->getJson('/kanban/cards')
            ->assertOk()
            ->assertJsonCount(2, 'cards')
            ->assertJsonFragment(['title' => 'mine A'])
            ->assertJsonFragment(['title' => 'mine B'])
            ->assertJsonMissing(['title' => 'other X']);
    }

    public function test_create_card_lands_in_todo_lane(): void
    {
        $userId = $this->createUser('alice@example.com');

        $this->actingAsUserId($userId)
            ->postJson('/kanban/cards', [
                'title' => 'first card',
                'body'  => 'こんにちは',
            ])
            ->assertCreated()
            ->assertJsonFragment(['title' => 'first card', 'lane' => 'todo', 'position' => 0]);

        $row = DB::table('kanban_card')->where('user_id', $userId)->first();
        $this->assertNotNull($row);
        $this->assertSame('todo', $row->lane);
    }

    public function test_create_card_appends_to_end_of_todo_lane(): void
    {
        $userId = $this->createUser('alice@example.com');
        DB::table('kanban_card')->insert([
            ['user_id' => $userId, 'title' => 'first', 'body' => '', 'lane' => 'todo', 'position' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $userId, 'title' => 'second', 'body' => '', 'lane' => 'todo', 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAsUserId($userId)
            ->postJson('/kanban/cards', ['title' => 'third', 'body' => ''])
            ->assertCreated()
            ->assertJsonFragment(['position' => 2]);
    }

    public function test_update_card_title_and_body(): void
    {
        $userId = $this->createUser('alice@example.com');
        $cardId = DB::table('kanban_card')->insertGetId([
            'user_id' => $userId, 'title' => 'old', 'body' => 'old body', 'lane' => 'todo', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAsUserId($userId)
            ->patchJson("/kanban/cards/{$cardId}", [
                'title' => 'new title',
                'body'  => 'new body',
            ])
            ->assertOk()
            ->assertJsonFragment(['title' => 'new title', 'body' => 'new body']);
    }

    public function test_cannot_update_other_users_card(): void
    {
        $userId  = $this->createUser('alice@example.com');
        $otherId = $this->createUser('bob@example.com');
        $cardId  = DB::table('kanban_card')->insertGetId([
            'user_id' => $otherId, 'title' => 'theirs', 'body' => '', 'lane' => 'todo', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAsUserId($userId)
            ->patchJson("/kanban/cards/{$cardId}", [
                'title' => 'hacked',
                'body'  => '',
            ])
            ->assertStatus(500); // Service が InvalidArgumentException

        $this->assertSame('theirs', DB::table('kanban_card')->where('id', $cardId)->value('title'));
    }

    public function test_move_card_to_another_lane(): void
    {
        $userId = $this->createUser('alice@example.com');
        $cardId = DB::table('kanban_card')->insertGetId([
            'user_id' => $userId, 'title' => 'mv', 'body' => '', 'lane' => 'todo', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAsUserId($userId)
            ->patchJson("/kanban/cards/{$cardId}/move", [
                'lane'     => 'review',
                'position' => 2,
            ])
            ->assertOk()
            ->assertJsonFragment(['lane' => 'review', 'position' => 2]);
    }

    public function test_move_with_invalid_lane_is_rejected(): void
    {
        $userId = $this->createUser('alice@example.com');
        $cardId = DB::table('kanban_card')->insertGetId([
            'user_id' => $userId, 'title' => 'x', 'body' => '', 'lane' => 'todo', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAsUserId($userId)
            ->patchJson("/kanban/cards/{$cardId}/move", [
                'lane'     => 'invalid_lane',
                'position' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lane');
    }

    public function test_delete_card_soft_deletes(): void
    {
        $userId = $this->createUser('alice@example.com');
        $cardId = DB::table('kanban_card')->insertGetId([
            'user_id' => $userId, 'title' => 'doomed', 'body' => '', 'lane' => 'done', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAsUserId($userId)
            ->deleteJson("/kanban/cards/{$cardId}")
            ->assertNoContent();

        $row = DB::table('kanban_card')->where('id', $cardId)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->deleted_at);
    }

    public function test_cannot_delete_other_users_card(): void
    {
        $userId  = $this->createUser('alice@example.com');
        $otherId = $this->createUser('bob@example.com');
        $cardId  = DB::table('kanban_card')->insertGetId([
            'user_id' => $otherId, 'title' => 'theirs', 'body' => '', 'lane' => 'todo', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAsUserId($userId)
            ->deleteJson("/kanban/cards/{$cardId}")
            ->assertStatus(500);

        // 削除されていない
        $this->assertNull(DB::table('kanban_card')->where('id', $cardId)->value('deleted_at'));
    }

    private function createUser(string $email): int
    {
        return (int) DB::table('user')->insertGetId([
            'name'       => 'U:'.$email,
            'email'      => $email,
            'password'   => Hash::make('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function actingAsUser(): self
    {
        $id = $this->createUser('default@example.com');
        return $this->actingAsUserId($id);
    }

    private function actingAsUserId(int $userId): self
    {
        $row = app(UserRepository::class)->findById($userId);
        return $this->actingAs(new UserAuth($row), 'user');
    }
}
