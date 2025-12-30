<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ToothChartingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_teeth_rows_and_saves_history(): void
    {
        Storage::fake('public');

        $user = User::create([
            'name' => 'Dentist User',
            'email' => 'dentist.test@example.com',
            'password' => 'password',
            'role' => 'dentist',
        ]);

        $this->actingAsStaff($user);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $patient = Patient::create([
            'full_name' => 'Test Patient',
            'phone' => '09170000000',
        ]);
        $patientId = $patient->id;

        $this->getJson("/api/patients/{$patientId}/teeth?dentition=adult")
            ->assertOk()
            ->assertJsonPath('data.dentition', 'adult')
            ->assertJsonCount(32, 'data.teeth');

        $payload = [
            'dentition' => 'adult',
            'condition' => 'cavity',
            'procedure' => 'filling',
            'notes' => 'Test note',
            'image_before' => UploadedFile::fake()->create('before.png', 10, 'image/png'),
            'image_after' => UploadedFile::fake()->create('after.png', 10, 'image/png'),
        ];

        $this->post("/api/patients/{$patientId}/teeth/11/records", $payload, ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.tooth.tooth_code', '11')
            ->assertJsonPath('data.tooth.condition', 'cavity')
            ->assertJsonPath('data.tooth.procedure', 'filling')
            ->assertJsonPath('data.history.image_before_path', fn ($v) => is_string($v) && $v !== '')
            ->assertJsonPath('data.history.image_after_path', fn ($v) => is_string($v) && $v !== '')
            ->assertJsonPath('data.history.image_before_url', fn ($v) => is_string($v) && $v !== '')
            ->assertJsonPath('data.history.image_after_url', fn ($v) => is_string($v) && $v !== '');

        $this->getJson("/api/patients/{$patientId}/teeth/11/history")
            ->assertOk()
            ->assertJsonCount(1, 'data.history')
            ->assertJsonPath('data.history.0.image_before_url', fn ($v) => is_string($v) && $v !== '');

        Storage::disk('public')->assertExists(
            ltrim((string) $this->getJson("/api/patients/{$patientId}/teeth/11/history")->json('data.history.0.image_before_path'), '/'),
        );
    }

    public function test_it_uploads_and_previews_xray_files(): void
    {
        Storage::fake('local');

        $user = User::create([
            'name' => 'Dentist User',
            'email' => 'dentist.xray.test@example.com',
            'password' => 'password',
            'role' => 'dentist',
        ]);

        $this->actingAsStaff($user);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $patient = Patient::create([
            'full_name' => 'Xray Patient',
            'phone' => '09170000012',
        ]);

        $visit = Visit::create([
            'patient_id' => $patient->id,
            'dentist_id' => null,
            'start_at' => now(),
            'end_at' => null,
            'notes' => null,
            'created_by_user_id' => $user->id,
        ]);

        $uploadRes = $this->post("/api/patients/{$patient->id}/xrays", [
            'file' => UploadedFile::fake()->create('xray.png', 10, 'image/png'),
            'visit_id' => $visit->id,
            'tooth_code' => '11',
            'recorded_at' => now()->subHour()->toDateTimeString(),
        ], ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.visit_id', $visit->id)
            ->assertJsonPath('data.tooth_code', '11')
            ->assertJsonPath('data.preview_url', fn ($v) => is_string($v) && $v !== '');

        $encryptedPath = (string) $uploadRes->json('data.encrypted_path');
        Storage::disk('local')->assertExists($encryptedPath);

        $listRes = $this->getJson("/api/patients/{$patient->id}/xrays?limit=10")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $uploadRes->json('data.id'));

        $previewUrl = (string) $uploadRes->json('data.preview_url');
        $this->get($previewUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }
}
