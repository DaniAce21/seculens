<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ids_alerts', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('created_at')->index();
            $table->string('severity', 20);
            $table->string('alert_type', 100);
            $table->string('source_ip', 45);
            $table->string('destination_ip', 45);
            $table->text('signature')->nullable();
            $table->string('status', 30)->default('Nueva');
        });

        // Añadimos CHECK constraints para PostgreSQL (cumplimiento de requerimientos del proyecto)
        DB::statement("ALTER TABLE ids_alerts ADD CONSTRAINT ids_alerts_severity_check CHECK (severity IN ('critical','high','medium','low'))");
        DB::statement("ALTER TABLE ids_alerts ADD CONSTRAINT ids_alerts_status_check CHECK (status IN ('Nueva','En Investigación','Mitigada'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ids_alerts');
    }
};
