<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolPortalSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('school_settings')->updateOrInsert(['id'=>1], ['school_name'=>'Ghana Primary & JHS School','currency'=>'GHS','admission_prefix'=>'ADM','admission_next_number'=>1,'active_academic_year'=>'2026/2027','active_term'=>'Term 1','block_results_for_debtors'=>false,'result_block_balance_threshold'=>0,'created_at'=>now(),'updated_at'=>now()]);
        foreach ([['P1','PRIMARY',1],['P2','PRIMARY',2],['P3','PRIMARY',3],['P4','PRIMARY',4],['P5','PRIMARY',5],['P6','PRIMARY',6],['JHS 1','JHS',7],['JHS 2','JHS',8],['JHS 3','JHS',9]] as [$name,$level,$sort]) {
            DB::table('school_classes')->updateOrInsert(['name'=>$name], ['level'=>$level,'sort_order'=>$sort,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        }
        foreach ([['English Language','ENG'],['Mathematics','MATH'],['Science','SCI'],['Social Studies','SOC'],['Religious and Moral Education','RME'],['Computing / ICT','ICT'],['French','FRE'],['Creative Arts','ART'],['Physical Education','PE']] as [$name,$code]) {
            DB::table('school_subjects')->updateOrInsert(['code'=>$code], ['name'=>$name,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        }
        foreach ([['School Fees',false],['PTA Levy',false],['Examination Fees',false],['Bus Fees',false],['Feeding Fees',false],['Hostel Fees',true]] as [$name,$boardingOnly]) {
            DB::table('fee_categories')->updateOrInsert(['name'=>$name], ['boarding_only'=>$boardingOnly,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        }
    }
}
