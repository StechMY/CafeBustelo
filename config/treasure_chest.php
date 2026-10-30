<?php

return [
    'prizes' => [
        1 => ['threshold' => 1, 'amount' => 70000, 'keys' => 0, 'discount_percent' => 0],
        2 => ['threshold' => 2, 'amount' => 100000, 'keys' => 0, 'discount_percent' => 0],
        3 => ['threshold' => 4, 'amount' => 150000, 'keys' => 0, 'discount_percent' => 0],
        4 => ['threshold' => 6, 'amount' => 0, 'keys' => 2, 'discount_percent' => 0],
        5 => ['threshold' => 8, 'amount' => 0, 'keys' => 5, 'discount_percent' => 0],
        6 => ['threshold' => 12, 'amount' => 0, 'keys' => 0, 'discount_percent' => 10],
        7 => ['threshold' => 20, 'amount' => 200000, 'keys' => 0, 'discount_percent' => 15],
        8 => ['threshold' => 28, 'amount' => 0, 'keys' => 8, 'discount_percent' => 0],
        9 => ['threshold' => 40, 'amount' => 500000, 'keys' => 20, 'discount_percent' => 20],
    ],
    'products' => [
        ['code' => 'T1', 'price' => 450000, 'keys' => 1],
        ['code' => 'W2', 'price' => 1950000, 'keys' => 2],
        ['code' => 'WS-1', 'price' => 2300000, 'keys' => 3],
        ['code' => 'WP-1', 'price' => 3600000, 'keys' => 4],
        ['code' => 'WS-2', 'price' => 5500000, 'keys' => 6],
        ['code' => 'WP-2', 'price' => 6850000, 'keys' => 8],
        ['code' => 'W3', 'price' => 9550000, 'keys' => 12],
        ['code' => 'W4', 'price' => 19500000, 'keys' => 20],
        ['code' => 'W5', 'price' => 38500000, 'keys' => 40],
    ],
];
