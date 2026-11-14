<?php

namespace Tests\Feature;

use App\Helpers\SecurityHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->viewer = User::factory()->create([
            'role' => 'viewer',
        ]);
    }

    public function test_sample_php_scripts_are_removed_from_public_directory()
    {
        $vulnerablePaths = [
            public_path('assets/admin/data/jquery-file-upload/server/php/UploadHandler.php'),
            public_path('assets/admin/data/jquery-file-upload/server/php/index.php'),
            public_path('assets/admin/data/jquery-ui/search.php'),
            public_path('assets/admin/data/lobilist/insert.php'),
            public_path('assets/admin/data/fullcalendar/php/get-events.php'),
            public_path('assets/admin/data/fullcalendar/php/get-timezones.php'),
            public_path('assets/admin/data/fullcalendar/php/utils.php'),
            public_path('assets/admin/images/datatables/resources/examples.php'),
            public_path('assets/admin/vendors/js/editors/ckeditor/samples/old/assets/posteddata.php'),
            public_path('assets/admin/vendors/js/editors/ckeditor/samples/old/sample_posteddata.php'),
        ];

        foreach ($vulnerablePaths as $path) {
            $this->assertFileDoesNotExist($path, "Vulnerable PHP script still exists at: {$path}");
        }
    }

    public function test_csv_formula_injection_is_properly_neutralized()
    {
        $payloads = [
            '=1+1' => "'=1+1",
            '+cmd|"/c calc"!A0' => "'+cmd|\"/c calc\"!A0",
            '-10+20' => "'-10+20",
            '@SUM(A1:A10)' => "'@SUM(A1:A10)",
            "\tTAB_INJECT" => "'\tTAB_INJECT",
            "\rCR_INJECT" => "'\rCR_INJECT",
        ];

        foreach ($payloads as $dangerous => $expectedSafe) {
            $this->assertEquals($expectedSafe, SecurityHelper::sanitizeCsvCell($dangerous));
            $this->assertEquals($expectedSafe, sanitize_csv_cell($dangerous));
        }

        // Test safe string and numeric values are unchanged
        $this->assertEquals('Normal Text', SecurityHelper::sanitizeCsvCell('Normal Text'));
        $this->assertEquals(12345, SecurityHelper::sanitizeCsvCell(12345));
        $this->assertEquals(99.95, SecurityHelper::sanitizeCsvCell(99.95));

        // Test whole row sanitization
        $row = ['Normal', '=cmd|calc', 450, '+dangerous'];
        $sanitizedRow = sanitize_csv_row($row);

        $this->assertEquals(['Normal', "'=cmd|calc", 450, "'+dangerous"], $sanitizedRow);
    }

    public function test_admin_cannot_demote_themselves()
    {
        $this->actingAs($this->admin);

        $response = $this->put(route('admin.users.update', $this->admin), [
            'name' => 'Admin Name',
            'email' => $this->admin->email,
            'role' => 'viewer',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('role');

        $this->admin->refresh();
        $this->assertEquals('admin', $this->admin->role);
    }

    public function test_viewer_cannot_execute_state_modifying_routes()
    {
        $this->actingAs($this->viewer);

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Hacker User',
            'email' => 'hacker@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->assertEquals(403, $response->status());
    }
}
