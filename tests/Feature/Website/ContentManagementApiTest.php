<?php

namespace Tests\Feature\Website;

use App\Models\LaundryFaq;
use App\Models\LaundryProcedure;
use App\Models\User;
use App\Support\ProcedureIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /* ------------------------------ Akses ------------------------------ */

    public function test_guests_and_employees_are_rejected(): void
    {
        $calls = [];

        foreach (['procedures', 'faqs'] as $resource) {
            $calls[] = ['getJson', "/api/admin/{$resource}"];
            $calls[] = ['postJson', "/api/admin/{$resource}"];
            $calls[] = ['putJson', "/api/admin/{$resource}/1"];
            $calls[] = ['deleteJson', "/api/admin/{$resource}/1"];
            $calls[] = ['postJson', "/api/admin/{$resource}/reorder"];
        }

        foreach ($calls as [$method, $url]) {
            $this->{$method}($url)->assertUnauthorized();
        }

        $this->actingAs(User::factory()->create());

        foreach ($calls as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    /* ------------------------------ Prosedur ------------------------------ */

    public function test_procedure_lifecycle(): void
    {
        $this->actingAs($this->admin);

        $created = $this->postJson('/api/admin/procedures', [
            'title' => '  <b>Cuci</b> Bersih ',
            'description' => 'Dicuci dengan <i>teliti</i>',
            'icon' => 'droplets',
        ])->assertCreated()
            ->assertJsonPath('message', 'Prosedur berhasil ditambahkan.')
            ->assertJsonPath('data.title', 'Cuci Bersih')
            ->assertJsonPath('data.description', 'Dicuci dengan teliti')
            ->assertJsonPath('data.icon', 'droplets')
            ->assertJsonPath('data.sort_order', 1)
            ->assertJsonPath('data.is_active', true);

        $id = $created->json('data.id');

        $this->postJson('/api/admin/procedures', ['title' => 'Langkah Kedua'])
            ->assertCreated()
            ->assertJsonPath('data.sort_order', 2)
            ->assertJsonPath('data.icon', null);

        $this->getJson('/api/admin/procedures')
            ->assertOk()
            ->assertJsonStructure(['data', 'icons'])
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(count(ProcedureIcons::keys()), 'icons');

        $this->putJson("/api/admin/procedures/{$id}", ['title' => 'Cuci Sangat Bersih'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Cuci Sangat Bersih');

        // Disembunyikan: hilang dari website publik.
        $this->putJson("/api/admin/procedures/{$id}", ['is_active' => false])->assertOk();
        $this->getJson('/api/public/site')->assertJsonCount(1, 'data.procedures');

        $this->deleteJson("/api/admin/procedures/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Prosedur dihapus.');

        $this->assertDatabaseMissing('laundry_procedures', ['id' => $id]);
        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'procedure.created']);
        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'procedure.deleted']);

        $this->deleteJson("/api/admin/procedures/{$id}")->assertNotFound();
    }

    public function test_procedure_validation(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/api/admin/procedures', [])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->postJson('/api/admin/procedures', ['title' => str_repeat('a', 151)])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->postJson('/api/admin/procedures', ['title' => 'Uji', 'icon' => 'rocket'])->assertUnprocessable()->assertJsonValidationErrors('icon');
        $this->postJson('/api/admin/procedures', ['title' => 'Uji', 'description' => str_repeat('a', 501)])->assertUnprocessable()->assertJsonValidationErrors('description');

        $this->assertDatabaseCount('laundry_procedures', 0);
    }

    public function test_procedures_can_be_reordered(): void
    {
        $one = LaundryProcedure::create(['title' => 'Satu', 'sort_order' => 1]);
        $two = LaundryProcedure::create(['title' => 'Dua', 'sort_order' => 2]);
        $three = LaundryProcedure::create(['title' => 'Tiga', 'sort_order' => 3]);

        $this->actingAs($this->admin);

        $this->postJson('/api/admin/procedures/reorder', ['ids' => [$three->id, $one->id, $two->id]])
            ->assertOk()
            ->assertJsonPath('message', 'Urutan prosedur diperbarui.');

        $this->assertSame([$three->id, $one->id, $two->id], LaundryProcedure::query()->ordered()->pluck('id')->all());
        $this->getJson('/api/public/site')->assertJsonPath('data.procedures.0.title', 'Tiga');

        $this->postJson('/api/admin/procedures/reorder', ['ids' => [999999]])->assertUnprocessable();
    }

    /* ------------------------------ FAQ ------------------------------ */

    public function test_faq_lifecycle(): void
    {
        $this->actingAs($this->admin);

        $created = $this->postJson('/api/admin/faqs', [
            'question' => '  Berapa <b>lama</b> pengerjaan? ',
            'answer' => "Sekitar dua hari.\n<script>alert(1)</script>Terima kasih.",
        ])->assertCreated()
            ->assertJsonPath('message', 'FAQ berhasil ditambahkan.')
            ->assertJsonPath('data.question', 'Berapa lama pengerjaan?')
            ->assertJsonPath('data.sort_order', 1)
            ->assertJsonPath('data.is_active', true);

        $this->assertStringNotContainsString('<script', (string) $created->json('data.answer'));

        $id = $created->json('data.id');

        $this->getJson('/api/admin/faqs')->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/admin/faqs/{$id}", ['answer' => 'Jawaban baru'])
            ->assertOk()
            ->assertJsonPath('data.answer', 'Jawaban baru')
            ->assertJsonPath('data.question', 'Berapa lama pengerjaan?');

        $this->putJson("/api/admin/faqs/{$id}", ['is_active' => false])->assertOk();
        $this->getJson('/api/public/site')->assertJsonCount(0, 'data.faqs');

        $this->deleteJson("/api/admin/faqs/{$id}")->assertOk()->assertJsonPath('message', 'FAQ dihapus.');
        $this->assertDatabaseMissing('laundry_faqs', ['id' => $id]);
        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'faq.deleted']);

        $this->deleteJson("/api/admin/faqs/{$id}")->assertNotFound();
    }

    public function test_faq_validation(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/api/admin/faqs', [])->assertUnprocessable()->assertJsonValidationErrors(['question', 'answer']);
        $this->postJson('/api/admin/faqs', ['question' => 'Tanya', 'answer' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('answer');
        $this->postJson('/api/admin/faqs', ['question' => 'Tanya', 'answer' => 'Jawab', 'is_active' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('is_active');

        $this->assertDatabaseCount('laundry_faqs', 0);
    }

    public function test_faqs_can_be_reordered(): void
    {
        $one = LaundryFaq::create(['question' => 'Satu', 'answer' => 'A', 'sort_order' => 1]);
        $two = LaundryFaq::create(['question' => 'Dua', 'answer' => 'B', 'sort_order' => 2]);

        $this->actingAs($this->admin);

        $this->postJson('/api/admin/faqs/reorder', ['ids' => [$two->id, $one->id]])
            ->assertOk()
            ->assertJsonPath('message', 'Urutan FAQ diperbarui.');

        $this->getJson('/api/admin/faqs')->assertJsonPath('data.0.question', 'Dua');
        $this->getJson('/api/public/site')->assertJsonPath('data.faqs.0.question', 'Dua');
    }
}