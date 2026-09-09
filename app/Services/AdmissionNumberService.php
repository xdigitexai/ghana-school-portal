<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AdmissionNumberService
{
    public function next(): string
    {
        return DB::transaction(function () {
            $settings = DB::table('school_settings')->lockForUpdate()->first();
            if (!$settings) {
                $id = DB::table('school_settings')->insertGetId([
                    'school_name' => 'Ghana School', 'currency' => 'GHS', 'admission_prefix' => 'ADM',
                    'admission_next_number' => 1, 'active_academic_year' => now()->year.'/'.(now()->year + 1),
                    'active_term' => 'Term 1', 'created_at' => now(), 'updated_at' => now(),
                ]);
                $settings = DB::table('school_settings')->where('id', $id)->lockForUpdate()->first();
            }
            $number = (int) $settings->admission_next_number;
            $value = sprintf('%s-%d-%04d', $settings->admission_prefix, now()->year, $number);
            DB::table('school_settings')->where('id', $settings->id)->update(['admission_next_number' => $number + 1, 'updated_at' => now()]);
            return $value;
        }, 5);
    }
}
