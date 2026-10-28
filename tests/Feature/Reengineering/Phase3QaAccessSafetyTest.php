<?php

namespace Tests\Feature\Reengineering;

use App\Console\Commands\Phase3QaAccess;
use App\Models\Permission;
use App\Models\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Phase3QaAccessSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_qa_permission_command_refuses_non_phase3_databases_without_writing(): void
    {
        $role = Roles::create(['name' => 'Administrator']);
        $before = Permission::count();

        $exit = Artisan::call('phase3:qa-access', ['--grant' => true]);

        $this->assertSame(1, $exit);
        $this->assertSame($before, Permission::count());
        $this->assertSame(0, $role->permissions()->count());
    }
}
