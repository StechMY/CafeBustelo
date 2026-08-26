<?php

return [
    /*
    |--------------------------------------------------------------------------
    | QC168-supported Vietnam banks
    |--------------------------------------------------------------------------
    |
    | Keys are the bank names shown to customers. Values are the numeric bank
    | codes QC168 requires in the payout `to_bank_name` field.
    |
    */
    'banks' => [
        'ABBank' => '970425',
        'ACB' => '970416',
        'Agribank' => '970405',
        'Bac A Bank' => '970409',
        'BIDV' => '970418',
        'BVBank / Viet Capital Bank' => '970454',
        'Eximbank' => '970431',
        'GPBank' => '970408',
        'HDBank' => '970437',
        'HSBC Vietnam' => '458761',
        'Indovina Bank (IVB)' => '970434',
        'KienlongBank' => '970452',
        'LPBank' => '970449',
        'MBBank' => '970422',
        'MSB' => '970426',
        'Nam A Bank' => '970428',
        'NCB' => '970419',
        'OCB' => '970448',
        'PGBank' => '970430',
        'PVcomBank' => '970412',
        'Sacombank' => '970403',
        'SaigonBank' => '970400',
        'SCB' => '970429',
        'SeABank' => '970440',
        'SHB' => '970443',
        'Shinhan Bank Vietnam' => '970424',
        'Techcombank' => '970407',
        'TPBank' => '970423',
        'VIB' => '970441',
        'VietABank' => '970427',
        'Vietcombank' => '970436',
        'VietinBank' => '970415',
        'VPBank' => '970432',
    ],

    // Exact historical/provider names that can be mapped without guessing.
    'aliases' => [
        'An Binh Bank' => '970425',
        'Asia Commercial Bank' => '970416',
        'Indovina Bank' => '970434',
        'Kienlongbank' => '970452',
        'LienVietPostBank' => '970449',
        'Maritime Bank' => '970426',
        'MB Bank' => '970422',
        'National Citizen Bank' => '970419',
        'Orient Commercial Joint Stock Bank' => '970448',
        'PG Bank' => '970430',
        'Public Vietnam Commercial Bank' => '970412',
        'Saigon Commercial Bank' => '970429',
        'Saigon–Hanoi Bank' => '970443',
        'Shinhan Bank' => '970424',
        'Tien Phong Bank' => '970423',
        'Viet Capital Bank' => '970454',
        'Vietinbank' => '970415',
        'Vietnam Export Import Bank' => '970431',
        'Vietnam International Bank' => '970441',
        'Vietnam Prosperity Bank' => '970432',
    ],
];
