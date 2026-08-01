<?php

namespace Tests\Feature\Settings;

use App\Models\Setting;
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

    public function test_replacing_logo_removes_only_the_previous_logo_file(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'owner']);
        $oldLogo = 'settings/old-logo.png';
        $qr = 'settings/qr.png';
        Storage::disk('public')->put($oldLogo, 'old-logo');
        Storage::disk('public')->put($qr, 'qr');
        Setting::query()->create([
            'branch_type' => 'head_office',
            'logo_image' => $oldLogo,
            'qr_image' => $qr,
        ]);

        $newLogo = UploadedFile::fake()->image('new-logo.png', 200, 200);

        $this->actingAs($owner)
            ->post(route('settings.update'), [
                'branch_type' => 'head_office',
                'logo_image' => $newLogo,
            ])
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($oldLogo);
        Storage::disk('public')->assertExists('settings/'.$newLogo->hashName());
        Storage::disk('public')->assertExists($qr);
    }
}
