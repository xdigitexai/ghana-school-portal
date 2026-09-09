<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StudentBalanceService
{
    public function dueFor(int $studentId, string $academicYear, string $term): float
    {
        $student = DB::table('students')->where('id', $studentId)->first();
        if (!$student) return 0;
        return (float) DB::table('fee_assignments as fa')
            ->join('fee_categories as fc', 'fc.id', '=', 'fa.fee_category_id')
            ->where('fa.school_class_id', $student->school_class_id)
            ->where('fa.academic_year', $academicYear)->where('fa.term', $term)
            ->where(fn ($q) => $q->where('fa.student_type', 'ALL')->orWhere('fa.student_type', $student->student_type))
            ->where(function ($q) use ($student) {
                $q->where('fc.boarding_only', false);
                if ($student->student_type === 'BOARDER') $q->orWhere('fc.boarding_only', true);
            })->sum('fa.amount');
    }

    public function paidFor(int $studentId, string $academicYear, string $term): float
    {
        return (float) DB::table('payments')->where('student_id', $studentId)->where('academic_year', $academicYear)->where('term', $term)->sum('amount');
    }

    public function balanceFor(int $studentId, string $academicYear, string $term): float
    {
        return max(0, $this->dueFor($studentId, $academicYear, $term) - $this->paidFor($studentId, $academicYear, $term));
    }
}
