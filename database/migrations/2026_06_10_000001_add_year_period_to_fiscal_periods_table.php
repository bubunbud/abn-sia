<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable()->after('name');
            $table->unsignedTinyInteger('period')->nullable()->after('year');
        });

        foreach (DB::table('fiscal_periods')->orderBy('id')->get() as $row) {
            if ($row->start_date) {
                $date = \Carbon\Carbon::parse($row->start_date);
                DB::table('fiscal_periods')->where('id', $row->id)->update([
                    'year' => (int) $date->format('Y'),
                    'period' => (int) $date->format('n'),
                ]);
            }
        }

        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->unique(['year', 'period']);
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->dropUnique(['year', 'period']);
            $table->dropColumn(['year', 'period']);
        });
    }
};
