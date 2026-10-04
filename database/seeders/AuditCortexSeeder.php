<?php

namespace Database\Seeders;

use App\Http\Controllers\AgentEngineController;
use App\Services\AgentEngineClient;
use Illuminate\Database\Seeder;

class AuditCortexSeeder extends Seeder
{
    public function run(): void
    {
        $controller = new AgentEngineController(new AgentEngineClient());
        $controller->seedSamplePRs();
    }
}
