<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_cannot_upload_a_non_image_setting_file(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->post(route('settings.update'), [
                'logo_image' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo_image');
    }

    public function test_owner_cannot_submit_an_unknown_branch_type(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->post(route('settings.update'), ['branch_type' => 'invalid'])
            ->assertSessionHasErrors('branch_type');
    }

    public function test_owner_can_save_valid_store_data_and_images(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'owner']);
        $logo = UploadedFile::fake()->image('logo.png', 200, 200);
        $qr = UploadedFile::fake()->image('promptpay.jpg', 200, 200);

        $this->actingAs($owner)
            ->post(route('settings.update'), [
                'store_name' => 'ATRILAK Test Store',
                'store_address' => 'Test address',
                'store_phone' => '0123456789',
                'tax_number' => '1234567890123',
                'branch_type' => 'branch',
                'branch_number' => '00001',
                'logo_image' => $logo,
                'qr_image' => $qr,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'store_name' => 'ATRILAK Test Store',
            'branch_type' => 'branch',
            'branch_number' => '00001',
        ]);
        Storage::disk('public')->assertExists('settings/'.$logo->hashName());
        Storage::disk('public')->assertExists('settings/'.$qr->hashName());
    }
}
