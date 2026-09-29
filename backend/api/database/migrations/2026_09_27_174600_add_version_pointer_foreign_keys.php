<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreign('published_version_id')
                ->references('id')
                ->on('page_versions')
                ->nullOnDelete();

            $table->foreign('draft_version_id')
                ->references('id')
                ->on('page_versions')
                ->nullOnDelete();
        });

        Schema::table('website_components', function (Blueprint $table) {
            $table->foreign('published_version_id')
                ->references('id')
                ->on('website_component_versions')
                ->nullOnDelete();

            $table->foreign('draft_version_id')
                ->references('id')
                ->on('website_component_versions')
                ->nullOnDelete();
        });

        Schema::table('navigations', function (Blueprint $table) {
            $table->foreign('published_version_id')
                ->references('id')
                ->on('navigation_versions')
                ->nullOnDelete();

            $table->foreign('draft_version_id')
                ->references('id')
                ->on('navigation_versions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('navigations', function (Blueprint $table) {
            $table->dropForeign(['published_version_id']);
            $table->dropForeign(['draft_version_id']);
        });

        Schema::table('website_components', function (Blueprint $table) {
            $table->dropForeign(['published_version_id']);
            $table->dropForeign(['draft_version_id']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['published_version_id']);
            $table->dropForeign(['draft_version_id']);
        });
    }
};
