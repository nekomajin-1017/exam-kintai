<?php

use App\Constants\AttendanceStatusCode;

return [
    'stamp' => [
        'actions' => [
            'check_in' => [
                'requires_attendance' => false,
                'open_break' => false,
                'close_latest_open_break' => false,
                'close_all_open_breaks' => false,
                'set_fields' => [
                    'check_in_at' => 'now',
                ],
                'next_status' => [
                    'code' => AttendanceStatusCode::WORKING,
                    'name' => '出勤中',
                ],
            ],
            'check_out' => [
                'requires_attendance' => true,
                'fallback_open_shift' => true,
                'open_break' => false,
                'close_latest_open_break' => false,
                'close_all_open_breaks' => true,
                'set_fields' => [
                    'check_out_at' => 'now',
                ],
                'next_status' => [
                    'code' => AttendanceStatusCode::FINISHED,
                    'name' => '退勤済',
                ],
            ],
            'break_in' => [
                'requires_attendance' => true,
                'fallback_open_shift' => true,
                'open_break' => true,
                'close_latest_open_break' => false,
                'close_all_open_breaks' => false,
                'set_fields' => [],
                'next_status' => [
                    'code' => AttendanceStatusCode::ON_BREAK,
                    'name' => '休憩中',
                ],
            ],
            'break_out' => [
                'requires_attendance' => true,
                'fallback_open_shift' => true,
                'open_break' => false,
                'close_latest_open_break' => true,
                'close_all_open_breaks' => false,
                'set_fields' => [],
                'next_status' => [
                    'code' => AttendanceStatusCode::WORKING,
                    'name' => '出勤中',
                ],
            ],
        ],
    ],
];
