<?php

namespace Tests\Feature;

use App\Models\Amizade;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\SocialAudience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SocialTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_uses_the_existing_login_and_renders_the_app(): void
    {
        $this->get('/social')->assertRedirect(route('login'));
        $this->getJson('/social/feed')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->get('/social')->assertOk()->assertSee('social-app');
    }

    public function test_a_user_can_create_edit_and_delete_a_publication_with_private_images(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        $visitor = User::factory()->create();
        $postId = $this->actingAs($author)->postJson('/social/posts', [
            'content' => '<script>alert(1)</script> 🌟',
            'images' => [UploadedFile::fake()->image('first.png'), UploadedFile::fake()->image('second.jpg')],
            'user_id' => $visitor->id,
        ])->assertCreated()->json('id');
        $post = SocialPost::findOrFail($postId);
        $this->assertSame($author->id, $post->user_id);
        $this->assertSame('friends', $post->visibility);
        $this->assertCount(2, $post->media);
        $asset = '/social/media/'.$post->media->first()->id;
        Storage::disk('local')->assertExists($post->media->first()->file_path);
        $this->get($asset)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($visitor)->getJson('/social/feed')->assertJsonCount(0, 'items');
        $this->get($asset)->assertNotFound();
        $this->patchJson('/social/posts/'.$postId, ['content' => 'Outra pessoa', 'visibility' => 'public'])->assertNotFound();

        $this->actingAs($author)->patchJson('/social/posts/'.$postId, ['content' => 'Atualizada', 'visibility' => 'public'])
            ->assertUnprocessable()->assertJsonValidationErrors('visibility');
        $this->patchJson('/social/posts/'.$postId, ['content' => 'Atualizada'])->assertNoContent();
        $this->befriend($author, $visitor);
        $this->actingAs($visitor)->getJson('/social/feed')->assertJsonPath('items.0.content', 'Atualizada');
        $this->get($asset)->assertOk();
        $this->actingAs($author)->deleteJson('/social/posts/'.$postId)->assertNoContent();
        $this->assertSoftDeleted($post);
        $this->get($asset)->assertNotFound();
    }

    public function test_invalid_images_and_empty_posts_are_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create())->postJson('/social/posts', [
            'images' => [UploadedFile::fake()->create('malicious.svg', 1, 'image/svg+xml')],
        ])->assertUnprocessable()->assertJsonValidationErrors('images.0');
        $this->postJson('/social/posts', ['images' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->postJson('/social/posts', ['content' => 'Texto', 'visibility' => 'public'])
            ->assertUnprocessable()->assertJsonValidationErrors('visibility');
        $this->assertDatabaseEmpty('social_posts');
    }

    public function test_friendship_is_unique_in_both_directions_and_controls_friends_only_posts(): void
    {
        $author = User::factory()->create();
        $friend = User::factory()->create();
        $this->actingAs($author)->postJson('/social/friendships', ['user_id' => $author->id])->assertUnprocessable();
        $friendship = $this->postJson('/social/friendships', ['user_id' => $friend->id])->assertCreated()->json('id');
        $post = $this->postJson('/social/posts', ['content' => 'Só amigos', 'visibility' => 'friends'])->assertCreated()->json('id');
        $this->patchJson('/social/friendships/'.$friendship, ['status' => 'accepted'])->assertNotFound();
        $this->actingAs($friend)->postJson('/social/friendships', ['user_id' => $author->id])->assertConflict();
        $this->getJson('/social/feed?post='.$post)->assertNotFound();
        $this->getJson('/social/notifications')->assertJsonPath('unread', 1);
        $this->patchJson('/social/friendships/'.$friendship, ['status' => 'accepted'])->assertNoContent();
        $this->getJson('/social/feed?post='.$post)->assertJsonPath('items.0.id', $post);
        $this->deleteJson('/social/friendships/'.$friendship)->assertNoContent();
        $this->getJson('/social/feed?post='.$post)->assertNotFound();
        $this->assertDatabaseEmpty('amizades');
    }

    public function test_requests_can_be_listed_declined_or_cancelled(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        $request = $this->actingAs($first)->postJson('/social/friendships', ['user_id' => $second->id])->assertCreated()->json('id');
        $this->actingAs($second)->getJson('/social/people?type=requests')->assertJsonPath('items.0.relationship', 'received');
        $this->patchJson('/social/friendships/'.$request, ['status' => 'declined'])->assertNoContent();
        $request = $this->actingAs($first)->postJson('/social/friendships', ['user_id' => $second->id])->assertCreated()->json('id');
        $this->deleteJson('/social/friendships/'.$request)->assertNoContent();
        $this->assertDatabaseEmpty('amizades');
    }

    public function test_likes_are_idempotent_and_authorized_comments_can_be_edited_and_answered(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $this->befriend($author, $reader);
        $post = $this->actingAs($author)->postJson('/social/posts', ['content' => 'Só amigos'])->assertCreated()->json('id');
        $url = '/social/posts/'.$post;
        $this->actingAs($reader)->postJson($url.'/like', ['liked' => true])->assertJsonPath('likes_count', 1);
        $this->postJson($url.'/like', ['liked' => true])->assertJsonPath('likes_count', 1);
        $this->postJson($url.'/like')->assertJsonPath('liked', false);
        $comment = $this->postJson($url.'/comments', ['content' => 'Primeiro'])->assertCreated()->json('id');
        $this->patchJson('/social/comments/'.$comment, ['content' => 'Editado'])->assertNoContent();

        $this->actingAs($author)->patchJson('/social/comments/'.$comment, ['content' => 'Sem permissão'])->assertNotFound();
        $this->postJson($url.'/comments', ['content' => 'Resposta', 'parent_id' => $comment])->assertCreated();
        $this->getJson($url.'/comments')->assertJsonCount(2, 'items')->assertJsonPath('items.0.reply_to', $reader->name);
        $this->getJson('/social/notifications')->assertJsonPath('unread', 2);
        $this->actingAs($reader)->getJson('/social/notifications')->assertJsonPath('unread', 1);
        $this->deleteJson('/social/comments/'.$comment)->assertNoContent();
        $this->getJson($url.'/comments')->assertJsonCount(1, 'items')->assertJsonPath('items.0.reply_to', null);
    }

    public function test_blocks_hide_profiles_posts_and_existing_comments_in_both_directions(): void
    {
        [$author, $blocked, $other] = User::factory()->count(3)->create();
        $this->befriend($author, $blocked);
        $this->befriend($author, $other);
        $this->befriend($blocked, $other);
        $post = $this->actingAs($author)->postJson('/social/posts', ['content' => 'Só amigos'])->assertCreated()->json('id');
        $otherPost = $this->actingAs($other)->postJson('/social/posts', ['content' => 'Só amigos'])->assertCreated()->json('id');
        $this->actingAs($blocked)->postJson('/social/posts/'.$otherPost.'/comments', ['content' => 'Invisível'])->assertCreated();
        $this->actingAs($author)->postJson('/social/blocks/'.$blocked->id)->assertNoContent();
        $this->getJson('/social/profile/'.$blocked->id.'/data')->assertNotFound();
        $this->getJson('/social/posts/'.$otherPost.'/comments')->assertJsonCount(0, 'items');

        $this->actingAs($blocked)->getJson('/social/feed?post='.$post)->assertNotFound();
        $this->postJson('/social/posts/'.$post.'/comments', ['content' => 'Bloqueado'])->assertNotFound();
        $this->postJson('/social/posts/'.$post.'/like')->assertNotFound();
        $this->postJson('/social/friendships', ['user_id' => $author->id])->assertNotFound();
        $this->actingAs($author)->deleteJson('/social/blocks/'.$blocked->id)->assertNoContent();
        $this->actingAs($blocked)->getJson('/social/feed?post='.$post)->assertNotFound();
    }

    public function test_communities_are_isolated_for_discovery_profiles_posts_and_images(): void
    {
        Storage::fake('local');
        [$author, $outside, $inside, $unassigned] = User::factory()->count(4)->create();
        $this->befriend($author, $inside);
        $this->befriend($author, $outside);
        config(['social.communities' => [$author->id => 'empresa-a', $outside->id => 'empresa-b', $inside->id => 'empresa-a']]);
        $this->actingAs($author)->get('/perfil')->assertOk()->assertSee('Salvar perfil social');
        $this->post('/perfil/social', [
            'bio' => 'Perfil público no Social',
            'avatar' => UploadedFile::fake()->image('avatar.png'),
            'cover' => UploadedFile::fake()->image('cover.png'),
        ])->assertRedirect('/perfil#perfil-social');
        $post = $this->postJson('/social/posts', [
            'images' => [UploadedFile::fake()->image('photo.png')],
        ])->assertCreated()->json('id');
        $asset = '/social/media/'.SocialPost::findOrFail($post)->media()->firstOrFail()->id;

        $profile = '/social/profile/'.$author->id;
        $this->actingAs($inside)->getJson($profile.'/data')->assertJsonPath('posts_count', 1);
        $this->get($profile.'/image/avatar')->assertOk();
        $this->get($asset)->assertOk();

        foreach ([$outside, $unassigned] as $visitor) {
            $this->actingAs($visitor)->getJson('/social/people')->assertJsonCount(0, 'items');
            $this->getJson('/social/feed')->assertJsonCount(0, 'items');
            $this->getJson($profile.'/data')->assertNotFound();
            $this->get($profile.'/image/cover')->assertNotFound();
            $this->get($asset)->assertNotFound();
            $this->postJson('/social/friendships', ['user_id' => $author->id])->assertNotFound();
        }
    }

    public function test_only_the_recipient_can_open_a_notification_and_access_is_checked_again(): void
    {
        [$author, $reader, $other] = User::factory()->count(3)->create();
        $this->befriend($author, $reader);
        $post = SocialPost::factory()->for($author, 'user')->create();
        $this->actingAs($reader)->postJson('/social/posts/'.$post->id.'/like', ['liked' => true])->assertOk();
        $notification = $author->notifications()->firstOrFail();
        $url = '/social/notifications/'.$notification->id;

        $this->actingAs($other)->post($url)->assertNotFound();
        $this->actingAs($author)->post($url)->assertRedirect(route('social.index', ['post' => $post->id]));
        $this->getJson('/social/notifications')->assertJsonPath('unread', 0);

        $this->postJson('/social/blocks/'.$reader->id)->assertNoContent();
        $this->post($url)->assertNotFound();
        $this->getJson('/social/notifications')->assertJsonCount(0, 'items');
    }

    public function test_feed_is_paginated_in_reverse_publication_order(): void
    {
        $viewer = User::factory()->create();
        $posts = SocialPost::factory()->count(16)->for($viewer, 'user')->create();
        $first = $this->actingAs($viewer)->getJson('/social/feed')->assertJsonCount(15, 'items')
            ->assertJsonPath('items.0.id', $posts->last()->id)->json('next');
        $this->getJson('/social/feed?cursor='.urlencode($first))->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $posts->first()->id)->assertJsonPath('next', null);
    }

    public function test_live_feed_returns_only_new_visible_posts_and_does_not_skip_a_burst(): void
    {
        [$viewer, $author, $stranger] = User::factory()->count(3)->create();
        $this->befriend($viewer, $author);
        $initial = SocialPost::factory()->for($viewer, 'user')->create();
        $new = SocialPost::factory()->count(16)->for($author, 'user')->create();
        SocialPost::factory()->for($author, 'user')->create(['visibility' => 'private']);
        SocialPost::factory()->for($stranger, 'user')->create(['visibility' => 'public']);

        $batch = $this->actingAs($viewer)->getJson('/social/feed?after='.$initial->id)
            ->assertOk()->assertJsonCount(15, 'items')
            ->assertJsonPath('items.0.id', $new->first()->id)->json('items');
        $this->getJson('/social/feed?after='.$batch[14]['id'])
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $new->last()->id);
        $this->getJson('/social/feed?after='.$new->last()->id)->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_legacy_public_posts_are_limited_to_friends_and_private_posts_remain_private(): void
    {
        [$author, $friend, $stranger] = User::factory()->count(3)->create();
        $this->befriend($author, $friend);
        $legacy = SocialPost::factory()->for($author, 'user')->create(['visibility' => 'public']);
        $private = SocialPost::factory()->for($author, 'user')->create(['visibility' => 'private']);

        $this->actingAs($stranger)->getJson('/social/feed?post='.$legacy->id)->assertNotFound();
        $this->getJson('/social/posts/'.$legacy->id.'/comments')->assertNotFound();
        $this->actingAs($friend)->getJson('/social/feed?post='.$legacy->id)->assertJsonPath('items.0.visibility', 'friends');
        $this->getJson('/social/feed?post='.$private->id)->assertNotFound();
        $this->actingAs($author)->patchJson('/social/posts/'.$private->id, ['content' => 'Editado'])->assertNoContent();
        $this->assertSame('private', $private->fresh()->visibility);
    }

    public function test_comment_preview_uses_reaction_count_then_recency_and_reactions_are_unique(): void
    {
        [$author, $friend, $stranger] = User::factory()->count(3)->create();
        $this->befriend($author, $friend);
        $post = SocialPost::factory()->for($author, 'user')->create();
        $comments = '/social/posts/'.$post->id.'/comments';
        $this->actingAs($author);
        $older = $this->postJson($comments, ['content' => 'Primeiro'])->assertCreated()->json('id');
        $latest = $this->postJson($comments, ['content' => 'Mais recente'])->assertCreated()->json('id');
        $reaction = '/social/comments/'.$older.'/reaction';

        $this->getJson($comments.'?preview=1')->assertOk()->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $latest)->assertJsonPath('has_more', true);
        $this->actingAs($stranger)->putJson($reaction, ['reaction' => 'like'])->assertNotFound();
        $this->actingAs($friend)->putJson($reaction, ['reaction' => 'like'])
            ->assertOk()->assertJsonPath('reactions.like', 1);
        $this->putJson($reaction, ['reaction' => 'like'])->assertJsonPath('reactions.like', 1);
        $this->putJson($reaction, ['reaction' => 'love'])
            ->assertJsonPath('reactions.like', 0)->assertJsonPath('reactions.love', 1)->assertJsonPath('my_reaction', 'love');
        $this->putJson($reaction, ['reaction' => 'unknown'])->assertUnprocessable();
        $this->getJson($comments.'?preview=1')->assertJsonPath('items.0.id', $older);
        $this->getJson($comments)->assertJsonCount(2, 'items')->assertJsonPath('items.0.id', $latest);
        $this->putJson($reaction, ['reaction' => null])->assertJsonPath('my_reaction', null)->assertJsonPath('reactions.love', 0);
        $this->getJson($comments.'?preview=1')->assertJsonPath('items.0.id', $latest);
    }

    public function test_requests_and_accepted_friends_are_shared_between_social_and_profile(): void
    {
        $author = User::factory()->create(['apelido' => 'social_author']);
        $friend = User::factory()->create(['apelido' => 'social_friend']);
        $post = SocialPost::factory()->for($author, 'user')->create();
        $this->actingAs($author)->postJson('/amigos', ['apelido' => $friend->apelido])->assertOk();
        $friendship = Amizade::query()->firstOrFail();

        $this->actingAs($friend)->getJson('/social/people?type=requests')
            ->assertJsonPath('items.0.friendship_id', $friendship->id)
            ->assertJsonPath('items.0.relationship', 'received');
        $this->patchJson('/social/friendships/'.$friendship->id, ['status' => 'accepted'])->assertNoContent();
        $this->get('/perfil?aba=amigos')->assertOk()->assertSee($author->name);
        $this->getJson('/social/feed?post='.$post->id)->assertOk();
        $this->delete('/amigos/'.$friendship->id)->assertRedirect();
        $this->getJson('/social/feed?post='.$post->id)->assertNotFound();

        $new = $this->postJson('/social/friendships', ['user_id' => $author->id])->assertCreated()->json('id');
        $this->actingAs($author)->get('/perfil?aba=amigos')->assertOk()->assertSee($friend->name);
        $this->post('/amigos/'.$new.'/responder', ['accept' => true])->assertRedirect();
        $this->getJson('/social/people?type=friends')->assertJsonPath('items.0.id', $friend->id);
    }

    public function test_migration_merges_existing_social_friendships_without_duplicates_or_blocked_pairs(): void
    {
        [$author, $friend, $pending, $blocked] = User::factory()->count(4)->create();
        $existing = Amizade::factory()->create([
            ...SocialAudience::pair($author->id, $friend->id),
            'requester_id' => $friend->id,
            'status' => 'pending',
        ]);
        foreach ([$friend, $pending, $blocked] as $person) {
            DB::table('social_friendships')->insert([
                ...SocialAudience::pair($author->id, $person->id),
                'requester_id' => $author->id, 'status' => $person->is($pending) ? 'pending' : 'accepted',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('social_blocks')->insert(['blocker_id' => $author->id, 'blocked_id' => $blocked->id]);

        $migration = require database_path('migrations/2026_11_20_111215_merge_social_friendships_into_amizades.php');
        $migration->up();

        $this->assertDatabaseCount('amizades', 2);
        $this->assertSame('accepted', $existing->fresh()->status);
        $this->assertDatabaseHas('amizades', [...SocialAudience::pair($author->id, $pending->id), 'status' => 'pending']);
        $this->assertDatabaseMissing('amizades', SocialAudience::pair($author->id, $blocked->id));
    }

    public function test_formatted_posts_preserve_allowed_formatting_and_strip_unsafe_markup(): void
    {
        $author = User::factory()->create();
        $this->actingAs($author);
        $postId = $this->postJson('/social/posts', [
            'content_html' => '<p><strong onclick="bad()">Destaque</strong> <u>sublinhado</u> <span style="background-color: rgb(254, 240, 138); position: fixed">marcado</span> 😊</p><script>alert(1)</script><img src="https://example.test/pixel">',
        ])->assertCreated()->json('id');
        $this->getJson('/social/feed?post='.$postId)->assertOk()
            ->assertJsonPath('items.0.content', 'Destaque sublinhado marcado 😊')
            ->assertJsonPath('items.0.content_html', '<p><strong>Destaque</strong> <u>sublinhado</u> <mark>marcado</mark> 😊</p>');

        $this->patchJson('/social/posts/'.$postId, [
            'content' => 'Editado',
            'content_html' => '<ul><li><em>Editado</em></li></ul>',
        ])->assertOk()->assertJsonPath('content_html', '<ul><li><em>Editado</em></li></ul>');
        $this->postJson('/social/posts', ['content_html' => '<script>alert(1)</script><div><br></div>'])
            ->assertUnprocessable()->assertJsonValidationErrors('content');
    }

    private function befriend(User $first, User $second): void
    {
        Amizade::factory()->create([
            ...SocialAudience::pair($first->id, $second->id),
            'requester_id' => $first->id,
            'status' => 'accepted',
        ]);
    }
}
