<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'year', 'month']);
        });

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql', 'sqlsrv'], true)) {
            DB::statement(
                'ALTER TABLE accounting_periods ADD CONSTRAINT accounting_periods_month_check CHECK (month BETWEEN 1 AND 12)',
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
