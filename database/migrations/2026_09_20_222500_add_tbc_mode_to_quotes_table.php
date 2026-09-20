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
        Schema::table('quotes', function (Blueprint $table) {
            $table->boolean('is_tbc')->default(false)->after('period');
            $table->decimal('rate', 10, 2)->nullable()->change();
            $table->decimal('total', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('is_tbc');
            $table->decimal('rate', 10, 2)->change();
            $table->decimal('total', 10, 2)->change();
        });
    }
};