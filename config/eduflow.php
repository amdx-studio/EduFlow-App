<?php

return [

    /*
    | Kode undangan registrasi guru (KT-10). Kosong = registrasi guru terbuka (dev).
    */
    'teacher_invite_code' => env('TEACHER_INVITE_CODE') ?: null,

    /*
    | Jumlah maksimum materi remedial per konsep (BR-011).
    */
    'remedial_per_concept' => 2,

    /*
    | Selisih minimum (poin persen) untuk klasifikasi perbandingan (BR-014).
    */
    'comparison_delta' => 10,

];
