<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('page_sections', function (Blueprint $table) {
            $table->uuid('public_id')->nullable()->after('id');
        });

        DB::table('page_sections')
            ->select('id')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('page_sections')
                    ->where('id', $row->id)
                    ->whereNull('public_id')
                    ->update(['public_id' => (string) Str::uuid()]);
            });

        Schema::table('page_sections', function (Blueprint $table) {
            $table->unique(['page_version_id', 'public_id']);
            $table->index('public_id');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE page_sections ALTER COLUMN public_id SET NOT NULL');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE page_sections MODIFY public_id CHAR(36) NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_sections', function (Blueprint $table) {
            $table->dropUnique(['page_version_id', 'public_id']);
            $table->dropIndex(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
