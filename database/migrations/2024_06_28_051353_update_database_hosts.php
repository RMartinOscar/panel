<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('database_hosts', function (Blueprint $table) {
            $table->json('nodes')->after('node_id')->nullable();
        });

        Schema::table('database_hosts', function (Blueprint $table) {
            DB::statement('UPDATE `database_hosts` SET `nodes` = JSON_ARRAY(node_id)');
        });

        Schema::table('database_hosts', function (Blueprint $table) {
            $table->dropColumn('node_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('database_hosts', function (Blueprint $table) {
            $table->text('node_id')->after('nodes');
        });

        Schema::table('database_hosts', function (Blueprint $table) {
            DB::statement('UPDATE `database_hosts` SET `node_id` = JSON_UNQUOTE(JSON_EXTRACT(nodes, "$[0]"))');
        });

        Schema::table('database_hosts', function (Blueprint $table) {
            $table->dropColumn('nodes');
        });
    }
};
