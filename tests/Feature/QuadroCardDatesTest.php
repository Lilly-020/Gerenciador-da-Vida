<?php

namespace Tests\Feature;

use App\Models\Quadro;
use App\Models\QuadroCartao;
use App\Models\QuadroMembro;
use App\Models\User;
use App\Notifications\QuadroCardDueReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QuadroCardDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dates_save_start_delivery_and_reminder_in_application_timezone(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $this->actingAs($owner)->patchJson(route('quadros.cartoes.update', [$board, $card]), ['starts_on' => '2026-11-18', 'due_at' => '2026-12-18T12:00', 'reminder_minutes' => 1440])->assertOk();
        $card->refresh();
        $this->assertSame('2026-11-18', $card->starts_on->format('Y-m-d'));
        $this->assertSame('2026-12-18 12:00', $card->due_at->format('Y-m-d H:i'));
        $this->assertSame('2026-12-17 12:00', $card->reminder_at->format('Y-m-d H:i'));
        $html = $this->getJson(route('quadros.cartoes.show', [$board, $card]))->assertOk()->json('html');
        $this->assertStringContainsString('Data de início', $html);
        $this->assertStringContainsString('Definir lembrete', $html);
        $this->assertStringContainsString('1 dia antes', $html);
    }

    public function test_invalid_date_ranges_and_reminders_without_delivery_are_rejected(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create(['due_at' => '2026-11-18 12:00']);
        $url = route('quadros.cartoes.update', [$board, $card]);
        $this->actingAs($owner)->patchJson($url, ['starts_on' => '2026-11-20'])->assertUnprocessable()->assertJsonValidationErrors('due_at');
        $this->patchJson($url, ['due_at' => null, 'reminder_minutes' => 60])->assertUnprocessable()->assertJsonValidationErrors('reminder_minutes');
        $this->patchJson($url, ['reminder_minutes' => 123])->assertUnprocessable()->assertJsonValidationErrors('reminder_minutes');
        $this->assertNull($card->fresh()->starts_on);
    }

    public function test_removing_dates_cancels_reminder_and_rescheduling_rearms_it(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create(['due_at' => '2026-12-18 12:00', 'starts_on' => '2026-11-18', 'reminder_minutes' => 1440]);
        $card->reminder_notified_at = now();
        $card->reminder_mailed_at = now();
        $card->save();
        $url = route('quadros.cartoes.update', [$board, $card]);
        $this->actingAs($owner)->patchJson($url, ['due_at' => '2026-12-19T12:00'])->assertOk();
        $this->assertNull($card->fresh()->reminder_notified_at);
        $this->assertNull($card->fresh()->reminder_mailed_at);
        $this->assertSame('2026-12-18 12:00', $card->fresh()->reminder_at->format('Y-m-d H:i'));
        $this->patchJson($url, ['starts_on' => null, 'due_at' => null, 'reminder_minutes' => null])->assertOk();
        $this->assertNull($card->fresh()->reminder_at);
        $this->assertNull($card->fresh()->starts_on);
        $this->assertNull($card->fresh()->reminder_minutes);
    }

    public function test_new_card_creator_receives_each_reminder_channel_once(): void
    {
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(12, 0));
        $owner = User::factory()->create();
        $creator = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        QuadroMembro::factory()->for($board)->for($creator)->create(['status' => 'accepted', 'role' => 'editor']);
        $list = $board->listas()->create(['title' => 'A fazer', 'color' => '#334155']);
        $response = $this->actingAs($creator)->postJson(route('quadros.cartoes.store', $board), ['title' => 'Entrega', 'lista_id' => $list->id, 'due_at' => '2026-11-18T13:00', 'reminder_minutes' => 60])->assertOk();
        $card = QuadroCartao::findOrFail($response->json('id'));
        $this->assertSame($creator->id, $card->created_by);
        $this->artisan('quadros:lembretes')->assertSuccessful();
        $this->artisan('quadros:lembretes')->assertSuccessful();
        Notification::assertSentToTimes($creator, QuadroCardDueReminder::class, 2);
        Notification::assertNotSentTo($owner, QuadroCardDueReminder::class);
        $this->assertNotNull($card->fresh()->reminder_notified_at);
        $this->assertNotNull($card->fresh()->reminder_mailed_at);
    }

    public function test_completed_expired_future_and_revoked_cards_do_not_send_reminders(): void
    {
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(12, 0));
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        QuadroCartao::factory()->for($board)->create(['created_by' => $owner->id, 'due_at' => now()->addHour(), 'reminder_minutes' => 60, 'status' => 'done']);
        QuadroCartao::factory()->for($board)->create(['created_by' => $owner->id, 'due_at' => now()->subHour(), 'reminder_minutes' => 60]);
        QuadroCartao::factory()->for($board)->create(['created_by' => $owner->id, 'due_at' => now()->addDays(2), 'reminder_minutes' => 60]);
        $revoked = User::factory()->create();
        QuadroCartao::factory()->for($board)->create(['created_by' => $revoked->id, 'due_at' => now()->addHour(), 'reminder_minutes' => 60]);
        $this->artisan('quadros:lembretes')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_delivery_time_reminder_allows_scheduler_to_run_seconds_after_delivery(): void
    {
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(12, 0, 30));
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        QuadroCartao::factory()->for($board)->create(['created_by' => $owner->id, 'due_at' => now()->startOfMinute(), 'reminder_minutes' => 0]);
        $this->artisan('quadros:lembretes')->assertSuccessful();
        Notification::assertSentToTimes($owner, QuadroCardDueReminder::class, 2);
    }

    public function test_real_notification_channels_store_one_alert_and_render_one_email(): void
    {
        config(['mail.default' => 'array']);
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(12, 0));
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        QuadroCartao::factory()->for($board)->create(['title' => 'Preparar apresentação', 'created_by' => $owner->id, 'due_at' => now()->addHour(), 'reminder_minutes' => 60]);
        $this->artisan('quadros:lembretes')->assertSuccessful();
        $this->artisan('quadros:lembretes')->assertSuccessful();
        $this->assertSame(1, $owner->notifications()->count());
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('Lembrete: Preparar apresentação', $messages->first()->getOriginalMessage()->getSubject());
    }

    public function test_database_notification_is_visible_only_to_recipient_and_marked_as_read(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create(['title' => 'Entrega importante']);
        $owner->notifyNow(new QuadroCardDueReminder($board->id, $card->id, $card->title, '18/12/2026 12:00'));
        $notification = $owner->notifications()->firstOrFail();
        $this->actingAs($owner)->get(route('perfil', ['aba' => 'notificacoes']))->assertOk()->assertSee('Entrega importante');
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->post(route('perfil.lembretes.read', $notification->id))->assertNotFound();
        $this->actingAs($owner)->post(route('perfil.lembretes.read', $notification->id))->assertRedirect(route('quadros.show', $board, absolute: false).'#card='.$card->id);
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
