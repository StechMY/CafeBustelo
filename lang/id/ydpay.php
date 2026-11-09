<?php

return [
    'approve_instant_confirmation' => 'Menyetujui penarikan dan langsung mengirim pembayaran YDPAY nyata. Dana tetap terkunci; hanya status YDPAY 2 terverifikasi yang mengonfirmasi pembayaran.',
    'deposit_statuses' => [
        '0' => '0 · Diinisialisasi (belum dibayar)',
        '1' => '1 · Menunggu pembayaran',
        '2' => '2 · Sudah dibayar',
        '3' => '3 · Sudah dibayar; notifikasi dikonfirmasi',
        '4' => '4 · Dikembalikan — perlu ditinjau',
        '-1' => '-1 · Gagal',
        '-2' => '-2 · Ditutup',
        'create_pending' => 'Pembuatan belum dikonfirmasi — sinkronkan',
        'pending' => 'Menunggu pembayaran',
        'processing' => 'Diproses (belum dibayar)',
        'completed' => 'Sudah dibayar',
        'failed' => 'Gagal',
        'closed' => 'Ditutup',
        'refund_review' => 'Dikembalikan — perlu ditinjau',
        'sandbox_review' => 'Pembayaran uji terdeteksi — perlu ditinjau',
    ],
    'payout_statuses' => [
        '0' => '0 · Menunggu',
        '1' => '1 · Diproses',
        '2' => '2 · Sudah dibayar',
        '3' => '3 · Gagal',
        '4' => '4 · Menunggu tinjauan penyedia',
        '5' => '5 · Menunggu konfirmasi merchant',
        '6' => '6 · Dikembalikan — perlu ditinjau',
    ],
    'review_title' => 'Pembayaran perlu ditinjau',
    'review_help' => 'Penyedia melaporkan pengembalian atau pembayaran uji. Hubungi dukungan; pembayaran belum dapat dikonfirmasi.',
];
