<?php

namespace App\Workflows;

use App\Constants\ApprovalStatusCode;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\AttendanceCorrection;
use App\Services\BreakRowNormalizer;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class AttendanceWorkflow
{
    public function __construct(private BreakRowNormalizer $breakRowNormalizer)
    {
        // 休憩行の正規化処理を受け取る。
    }

    public function stamp(int $userId, string $action): void
    {
        // 打刻種別に対応する処理定義を取得する。
        $definition = $this->stampDefinition($action);

        // 同一処理内で時刻がぶれないように現在時刻を固定する。
        $now = CarbonImmutable::now();
        // 当日キー。
        $workDate = $now->toDateString();

        // アクション定義に応じて対象勤怠を取得する。
        $attendance = $this->findStampAttendance(
            $userId,
            $workDate,
            (bool) ($definition['fallback_open_shift'] ?? false)
        );

        // 勤怠必須アクションで対象が無ければ何もしない。
        if (($definition['requires_attendance'] ?? true) && ! $attendance) {
            return;
        }

        // 必要なら当日勤怠を新規作成する。
        $attendance = $this->ensureStampAttendance($attendance, $userId, $workDate);

        // 休憩関連の副作用を先に適用する。
        $this->applyBreakActions($attendance->id, $definition, $now);

        // 勤怠本体の更新項目を組み立てる。
        $update = $this->buildUpdatePayload($definition, $now);
        // 次ステータスが指定されていれば code を保存する。
        $nextStatus = $definition['next_status'] ?? null;
        if (is_array($nextStatus) && isset($nextStatus['code'])) {
            $update['attendance_status_code'] = $nextStatus['code'];
        }

        // 更新項目があるときだけDB更新する。
        if (! empty($update)) {
            $attendance->update($update);
        }
    }

    private function stampDefinition(string $action): array
    {
        // 打刻種別に対応する処理定義を設定から取得する。
        $definition = config("attendance_flow.stamp.actions.{$action}");
        // 定義が無ければ想定外アクションとして例外にする。
        if (! is_array($definition)) {
            throw new InvalidArgumentException("Unknown stamp action: {$action}");
        }

        return $definition;
    }

    private function findStampAttendance(int $userId, string $workDate, bool $fallbackOpenShift): ?Attendance
    {
        // まず当日勤怠を取得する。
        $attendance = Attendance::query()
            ->where('user_id', $userId)
            ->where('work_date', $workDate)
            ->first();

        // フォールバック不要、または当日勤怠ありならそのまま返す。
        if ($attendance || ! $fallbackOpenShift) {
            return $attendance;
        }

        // 未退勤の直近勤務へフォールバックする。
        return Attendance::query()
            ->where('user_id', $userId)
            ->whereNull('check_out_at')
            ->latest('work_date')
            ->first();
    }

    private function ensureStampAttendance(?Attendance $attendance, int $userId, string $workDate): Attendance
    {
        // 既存勤怠があればそのまま使う。
        if ($attendance) {
            return $attendance;
        }

        // なければ当日勤怠を新規作成する。
        return Attendance::create([
            'user_id' => $userId,
            'work_date' => $workDate,
        ]);
    }

    public function requestCorrection(Attendance $attendance, int $requestUserId, array $payload): AttendanceCorrection
    {
        // 勤務日を基準日に固定する。
        $baseDate = $this->baseDate($attendance);
        // 休憩入力を正規化して日時へ変換する。
        $breakRows = $this->requestBreakRows($baseDate, $payload);
        // 勤怠修正申請を作成する。
        $correction = $this->createCorrection($attendance, $requestUserId, $payload, $baseDate);
        // 休憩修正行があれば子レコードを作成する。
        $this->createBreakCorrections($correction, $breakRows);

        return $correction;
    }

    public function approveCorrection(AttendanceCorrection $correction, int $adminUserId): void
    {
        // 承認反映に必要な関連をロードする。
        $correction->load('attendance');
        // 対象勤怠。
        $attendance = $correction->attendance;

        // 申請値を勤怠本体へ反映する。
        $this->applyAttendanceCorrection($attendance, $correction);
        // 休憩修正がある場合だけ休憩データを差し替える。
        $this->replaceBreaksFromCorrection($attendance, $correction);

        // 申請ステータスを承認済みに更新し、承認者情報を保存する。
        $correction->update([
            'approval_status_code' => ApprovalStatusCode::APPROVED,
            'approved_by' => $adminUserId,
            'approved_at' => now(),
        ]);
    }

    public function updateAttendance(Attendance $attendance, array $payload): void
    {
        // 勤務日を基準日に固定する。
        $baseDate = $this->baseDate($attendance);

        // 出退勤時刻と備考を更新する。
        $attendance->update([
            'check_in_at' => ! empty($payload['start_time'])
                ? CarbonImmutable::parse($baseDate . ' ' . $payload['start_time'])
                : null,
            'check_out_at' => ! empty($payload['end_time'])
                ? CarbonImmutable::parse($baseDate . ' ' . $payload['end_time'])
                : null,
            'remarks' => $payload['reason'] ?? null,
        ]);

        // 休憩入力を正規化して日時へ変換する。
        $breakRows = $this->requestBreakRows($baseDate, $payload);

        // 既存休憩を削除して再作成する。
        $attendance->breaks()->delete();
        if (! empty($breakRows)) {
            $attendance->breaks()->createMany($breakRows);
        }
    }

    private function baseDate(Attendance $attendance): string
    {
        // 勤務日を基準日に固定する。
        return CarbonImmutable::parse($attendance->work_date)->format('Y-m-d');
    }

    private function requestBreakRows(string $baseDate, array $payload): array
    {
        // 休憩入力を正規化して日時へ変換する。
        return $this->breakRowNormalizer->toDateTimeRows(
            $baseDate,
            $this->breakRowNormalizer->fromRequest($payload)
        );
    }

    private function createCorrection(
        Attendance $attendance,
        int $requestUserId,
        array $payload,
        string $baseDate
    ): AttendanceCorrection {
        // 勤怠修正申請を作成する。
        return AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'request_user_id' => $requestUserId,
            'requested_check_in_at' => isset($payload['start_time'])
                ? CarbonImmutable::parse($baseDate . ' ' . $payload['start_time'])
                : $attendance->check_in_at,
            'requested_check_out_at' => isset($payload['end_time'])
                ? CarbonImmutable::parse($baseDate . ' ' . $payload['end_time'])
                : $attendance->check_out_at,
            'reason' => $payload['reason'] ?? null,
            'approval_status_code' => ApprovalStatusCode::PENDING,
        ]);
    }

    private function createBreakCorrections(AttendanceCorrection $correction, array $breakRows): void
    {
        // 休憩修正行があれば子レコードを作成する。
        if (! empty($breakRows)) {
            $correction->breakCorrections()->createMany($breakRows);
        }
    }

    private function applyAttendanceCorrection(Attendance $attendance, AttendanceCorrection $correction): void
    {
        // 申請値を勤怠本体へ反映する。
        $attendance->update([
            'check_in_at' => $correction->requested_check_in_at ?? $attendance->check_in_at,
            'check_out_at' => $correction->requested_check_out_at ?? $attendance->check_out_at,
            'remarks' => $correction->reason ?? $attendance->remarks,
        ]);
    }

    private function replaceBreaksFromCorrection(Attendance $attendance, AttendanceCorrection $correction): void
    {
        // 休憩修正がない場合は差し替え処理を行わない。
        if (! $correction->breakCorrections()->exists()) {
            return;
        }

        // 基準日へ合わせて申請休憩行を日時へ変換する。
        $breakRows = $this->breakRowNormalizer->toDateTimeRows(
            $this->baseDate($attendance),
            $this->breakRowNormalizer->fromCorrections(
                $correction->breakCorrections()->orderBy('break_start_at')->get()
            )
        );

        // 既存休憩を削除し、申請内容で再構築する。
        $attendance->breaks()->delete();

        // 休憩行がある場合だけ再作成する。
        if (! empty($breakRows)) {
            $attendance->breaks()->createMany($breakRows);
        }
    }

    private function applyBreakActions(int $attendanceId, array $definition, CarbonImmutable $now): void
    {
        // 未終了休憩をすべて閉じる指定。
        if (($definition['close_all_open_breaks'] ?? false) === true) {
            AttendanceBreak::query()
                ->where('attendance_id', $attendanceId)
                ->whereNull('break_end_at')
                ->update(['break_end_at' => $now]);
        }

        // 休憩開始指定。未終了休憩がなければ作成する。
        if (($definition['open_break'] ?? false) === true) {
            AttendanceBreak::firstOrCreate(
                ['attendance_id' => $attendanceId, 'break_end_at' => null],
                ['break_start_at' => $now]
            );
        }

        // 最新の未終了休憩を1件だけ閉じる指定。
        if (($definition['close_latest_open_break'] ?? false) === true) {
            $openBreak = AttendanceBreak::query()
                ->where('attendance_id', $attendanceId)
                ->whereNull('break_end_at')
                ->latest('break_start_at')
                ->first();

            // 対象がある場合だけ更新する。
            if ($openBreak) {
                $openBreak->update(['break_end_at' => $now]);
            }
        }
    }

    private function buildUpdatePayload(array $definition, CarbonImmutable $now): array
    {
        // 勤怠本体の更新配列。
        $payload = [];
        // set_fields を取得する。
        $setFields = $definition['set_fields'] ?? [];

        // 各フィールドを更新配列へ展開する。
        foreach ($setFields as $field => $value) {
            // "now" は固定済み現在時刻に置換する。
            $payload[$field] = $value === 'now' ? $now : $value;
        }

        // 更新配列を返す。
        return $payload;
    }

}
