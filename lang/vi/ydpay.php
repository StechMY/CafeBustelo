<?php

return [
    'approve_instant_confirmation' => 'Duyệt yêu cầu và gửi thanh toán YDPAY thực ngay. Tiền vẫn bị khóa; chỉ trạng thái YDPAY 2 đã xác minh mới xác nhận thanh toán.',
    'deposit_statuses' => [
        '0' => '0 · Đã khởi tạo (chưa trả)',
        '1' => '1 · Chờ thanh toán',
        '2' => '2 · Đã thanh toán',
        '3' => '3 · Đã trả; thông báo đã xác nhận',
        '4' => '4 · Đã hoàn tiền — cần đối soát',
        '-1' => '-1 · Thất bại',
        '-2' => '-2 · Đã đóng',
        'create_pending' => 'Chưa xác nhận tạo đơn — cần đồng bộ',
        'pending' => 'Chờ thanh toán',
        'processing' => 'Đang xử lý (chưa trả)',
        'completed' => 'Đã thanh toán',
        'failed' => 'Thất bại',
        'closed' => 'Đã đóng',
        'refund_review' => 'Đã hoàn tiền — cần đối soát',
        'sandbox_review' => 'Phát hiện thanh toán thử — cần kiểm tra',
    ],
    'payout_statuses' => [
        '0' => '0 · Chờ xử lý',
        '1' => '1 · Đang xử lý',
        '2' => '2 · Đã thanh toán',
        '3' => '3 · Thất bại',
        '4' => '4 · Chờ nền tảng duyệt',
        '5' => '5 · Chờ người bán xác nhận',
        '6' => '6 · Đã hoàn tiền — cần đối soát',
    ],
    'review_title' => 'Thanh toán cần kiểm tra',
    'review_help' => 'Nhà cung cấp báo hoàn tiền hoặc thanh toán thử. Vui lòng liên hệ hỗ trợ; chưa thể xác nhận thanh toán.',
];
