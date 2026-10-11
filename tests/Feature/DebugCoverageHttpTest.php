<?php
namespace Tests\Feature;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugCoverageHttpTest extends TestCase {
    use RefreshDatabase;
    public function test_dump(): void {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route(\"admin.coverage\"));
        file_put_contents(storage_path(\"debug-coverage.txt\"), $response->status().\"\\n\".substr($response->getContent(), 0, 2000));
        $this->assertTrue(true);
    }
}
