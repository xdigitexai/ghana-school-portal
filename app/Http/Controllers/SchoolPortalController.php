<?php

namespace App\Http\Controllers;

use App\Services\AdmissionNumberService;
use App\Services\StudentBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SchoolPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $this->requireRoles($request, ['admin', 'headmaster']);
        $settings = DB::table('school_settings')->first();
        return response()->json([
            'students' => [
                'total' => DB::table('students')->where('active', true)->count(),
                'male' => DB::table('students')->where('active', true)->where('gender', 'MALE')->count(),
                'female' => DB::table('students')->where('active', true)->where('gender', 'FEMALE')->count(),
                'boarders' => DB::table('students')->where('active', true)->where('student_type', 'BOARDER')->count(),
                'day' => DB::table('students')->where('active', true)->where('student_type', 'DAY')->count(),
            ],
            'payments_today' => (float) DB::table('payments')->whereDate('paid_at', now()->toDateString())->sum('amount'),
            'payments_term' => $settings ? (float) DB::table('payments')->where('academic_year', $settings->active_academic_year)->where('term', $settings->active_term)->sum('amount') : 0,
            'recent_payments' => DB::table('payments')->latest('paid_at')->limit(10)->get(),
            'recent_score_submissions' => DB::table('academic_scores')->whereNotNull('submitted_at')->latest('submitted_at')->limit(10)->get(),
        ]);
    }

    public function registerStudent(Request $request, AdmissionNumberService $admissions)
    {
        $this->requireRoles($request, ['admin', 'headmaster']);
        $data = $request->validate([
            'first_name' => 'required|string|max:120', 'middle_name' => 'nullable|string|max:120', 'last_name' => 'required|string|max:120',
            'gender' => ['required', Rule::in(['MALE', 'FEMALE'])], 'date_of_birth' => 'nullable|date',
            'school_class_id' => 'required|exists:school_classes,id', 'student_type' => ['required', Rule::in(['DAY', 'BOARDER'])],
            'guardian_id' => 'nullable|exists:guardians,id', 'address' => 'nullable|string|max:2000', 'admission_date' => 'required|date',
            'photo_path' => 'nullable|string|max:500',
        ]);

        $student = DB::transaction(function () use ($data, $admissions, $request) {
            $id = DB::table('students')->insertGetId([...$data, 'admission_number' => $admissions->next(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('student_status_history')->insert(['student_id' => $id, 'student_type' => $data['student_type'], 'effective_at' => now(), 'changed_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $record = DB::table('students')->where('id', $id)->first();
            $this->audit($request, 'student.created', 'student', $id, null, (array) $record);
            return $record;
        });
        return response()->json($student, 201);
    }

    public function saveScore(Request $request)
    {
        $this->requireRoles($request, ['teacher', 'admin', 'headmaster']);
        $data = $request->validate([
            'student_id' => 'required|exists:students,id', 'school_subject_id' => 'required|exists:school_subjects,id',
            'academic_year' => 'required|string|max:20', 'term' => 'required|string|max:20',
            'class_score' => 'required|numeric|min:0|max:30', 'exam_score' => 'required|numeric|min:0|max:70', 'submit' => 'sometimes|boolean',
        ]);
        $student = DB::table('students')->where('id', $data['student_id'])->first();
        abort_unless($student, 404);
        if ($request->user()->role === 'teacher') {
            abort_unless(DB::table('teacher_assignments')->where('teacher_id', $request->user()->id)->where('school_class_id', $student->school_class_id)->where('school_subject_id', $data['school_subject_id'])->exists(), 403, 'You are not assigned to this class and subject.');
        }
        $existing = DB::table('academic_scores')->where('student_id', $data['student_id'])->where('school_subject_id', $data['school_subject_id'])->where('academic_year', $data['academic_year'])->where('term', $data['term'])->first();
        abort_if($existing?->locked, 423, 'Submitted scores are locked until the Headmaster unlocks them.');
        $payload = [
            'student_id' => $data['student_id'], 'school_class_id' => $student->school_class_id, 'school_subject_id' => $data['school_subject_id'],
            'academic_year' => $data['academic_year'], 'term' => $data['term'], 'class_score' => $data['class_score'], 'exam_score' => $data['exam_score'],
            'total_score' => $data['class_score'] + $data['exam_score'], 'locked' => (bool)($data['submit'] ?? false),
            'submitted_at' => ($data['submit'] ?? false) ? now() : null, 'submitted_by' => ($data['submit'] ?? false) ? $request->user()->id : null, 'updated_at' => now(),
        ];
        if ($existing) { DB::table('academic_scores')->where('id', $existing->id)->update($payload); $id = $existing->id; }
        else { $payload['created_at'] = now(); $id = DB::table('academic_scores')->insertGetId($payload); }
        $current = DB::table('academic_scores')->where('id', $id)->first();
        $this->audit($request, 'score.saved', 'academic_score', $id, $existing ? (array)$existing : null, (array)$current);
        return response()->json($current);
    }

    public function unlockScore(Request $request, int $scoreId)
    {
        $this->requireRoles($request, ['admin', 'headmaster']);
        $score = DB::table('academic_scores')->where('id', $scoreId)->first(); abort_unless($score, 404);
        DB::table('academic_scores')->where('id', $scoreId)->update(['locked' => false, 'unlocked_by' => $request->user()->id, 'unlocked_at' => now(), 'updated_at' => now()]);
        $current = DB::table('academic_scores')->where('id', $scoreId)->first();
        $this->audit($request, 'score.unlocked', 'academic_score', $scoreId, (array)$score, (array)$current);
        return response()->json($current);
    }

    public function publishResults(Request $request)
    {
        $this->requireRoles($request, ['admin', 'headmaster']);
        $data = $request->validate(['school_class_id'=>'required|exists:school_classes,id','academic_year'=>'required|string|max:20','term'=>'required|string|max:20','published'=>'required|boolean']);
        DB::table('result_publications')->updateOrInsert(['school_class_id'=>$data['school_class_id'],'academic_year'=>$data['academic_year'],'term'=>$data['term']], ['published'=>$data['published'],'published_at'=>$data['published']?now():null,'published_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['ok'=>true]);
    }

    public function studentResults(Request $request, int $studentId, StudentBalanceService $balances)
    {
        $student = DB::table('students')->where('id', $studentId)->first(); abort_unless($student, 404);
        $role = $request->user()->role;
        if ($role === 'student') abort_unless((int)$student->user_id === (int)$request->user()->id, 403);
        if ($role === 'parent') {
            $guardian = DB::table('guardians')->where('user_id', $request->user()->id)->first();
            abort_unless($guardian && (int)$student->guardian_id === (int)$guardian->id, 403);
        }
        abort_unless(in_array($role, ['student','parent','admin','headmaster','teacher'], true), 403);
        $settings = DB::table('school_settings')->first(); abort_unless($settings, 404);
        $publication = DB::table('result_publications')->where('school_class_id',$student->school_class_id)->where('academic_year',$settings->active_academic_year)->where('term',$settings->active_term)->first();
        if (in_array($role,['student','parent'],true)) abort_unless($publication?->published, 403, 'Results have not been published.');
        $balance = $balances->balanceFor($studentId, $settings->active_academic_year, $settings->active_term);
        if (in_array($role,['student','parent'],true) && $settings->block_results_for_debtors && $balance > (float)$settings->result_block_balance_threshold) abort(403, 'Results are blocked because of outstanding fees.');
        return response()->json(['student'=>$student,'balance'=>$balance,'scores'=>DB::table('academic_scores as s')->join('school_subjects as sub','sub.id','=','s.school_subject_id')->where('s.student_id',$studentId)->where('s.academic_year',$settings->active_academic_year)->where('s.term',$settings->active_term)->select('sub.name as subject','s.class_score','s.exam_score','s.total_score')->get()]);
    }

    public function recordPayment(Request $request)
    {
        $this->requireRoles($request, ['admin', 'headmaster']);
        $data = $request->validate(['student_id'=>'required|exists:students,id','fee_category_id'=>'nullable|exists:fee_categories,id','academic_year'=>'required|string|max:20','term'=>'required|string|max:20','amount'=>'required|numeric|min:0.01','method'=>['required',Rule::in(['CASH','MOMO','BANK_TRANSFER'])],'external_reference'=>'nullable|string|max:150']);
        do { $receipt='RCP-'.now()->format('Ymd').'-'.strtoupper(Str::random(8)); } while (DB::table('payments')->where('receipt_number',$receipt)->exists());
        $id=DB::table('payments')->insertGetId([...$data,'receipt_number'=>$receipt,'received_by'=>$request->user()->id,'paid_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $payment=DB::table('payments')->where('id',$id)->first(); $this->audit($request,'payment.recorded','payment',$id,null,(array)$payment);
        return response()->json($payment,201);
    }

    public function broadsheet(Request $request)
    {
        $this->requireRoles($request, ['admin','headmaster']);
        $data=$request->validate(['school_class_id'=>'required|exists:school_classes,id','academic_year'=>'required|string|max:20','term'=>'required|string|max:20']);
        return response()->json(DB::table('academic_scores as s')->join('students as st','st.id','=','s.student_id')->join('school_subjects as sub','sub.id','=','s.school_subject_id')->where('s.school_class_id',$data['school_class_id'])->where('s.academic_year',$data['academic_year'])->where('s.term',$data['term'])->select('st.id as student_id','st.admission_number','st.first_name','st.last_name','sub.name as subject','s.class_score','s.exam_score','s.total_score')->orderBy('st.last_name')->orderBy('sub.name')->get());
    }

    private function requireRoles(Request $request, array $roles): void { abort_unless($request->user() && in_array($request->user()->role,$roles,true),403); }
    private function audit(Request $request,string $action,string $type,?int $id,?array $old,?array $new): void
    {
        DB::table('audit_logs')->insert(['user_id'=>$request->user()?->id,'action'=>$action,'record_type'=>$type,'record_id'=>$id,'old_value'=>$old?json_encode($old):null,'new_value'=>$new?json_encode($new):null,'ip_address'=>$request->ip(),'user_agent'=>Str::limit((string)$request->userAgent(),2000,''),'created_at'=>now()]);
    }
}
