<?php

namespace App\Http\Controllers;

use App\Helpers\TimeHelper;
use App\Http\Controllers\Concerns\BuildsAttendanceViewData;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\User;
use App\Queries\AttendanceListQuery;
use App\Services\AdminAttendUpdateService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminAttendController extends Controller
{
    use BuildsAttendanceViewData;

    public function __construct(
        // 勤怠一覧の取得クエリ。
        private AttendanceListQuery $attendanceListQuery,
        // 管理者の勤怠更新処理。
        private AdminAttendUpdateService $adminAttendUpdateService,
    )
    {
        // DI注入のみ。
    }

    public function index(Request $request)
    {
        // クエリの日付を日初に正規化する。
        $date = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        // 指定日の全ユーザー勤怠を取得する。
        $records = $this->attendanceListQuery->forDay($date);
        // 前後日ナビを生成する。
        $navigation = $this->buildDayNavigation($date, 'admin.dashboard');

        // 管理者向け勤怠一覧を表示する。
        return view('attendance_records_screen', [
            'headerVariant' => 'admin',
            'attendances' => $records,
            'previousUrl' => $navigation['previousUrl'],
            'nextUrl' => $navigation['nextUrl'],
            'currentLabel' => $navigation['currentLabel'],
            'previousLabel' => $navigation['previousLabel'],
            'nextLabel' => $navigation['nextLabel'],
            'firstColumnType' => 'name',
            'detailRouteName' => 'admin.attendance.detail',
            'allowMissingDetail' => false,
            'title' => '勤怠一覧',
        ]);
    }

    public function detail(Attendance $attendance)
    {
        // 対象勤怠の閲覧権限を確認する。
        $this->authorize('view', $attendance);
        // 詳細表示に必要な関連をロードする。
        $attendance->load('user', 'breaks');
        // 休憩行を開始時刻順で固定する。
        $breaks = $attendance->breaks()->orderBy('break_start_at')->get();
        // 詳細フォーム用の表示データを組み立てる。
        $detailFields = $this->buildAttendanceDetailFields(
            $attendance,
            $breaks,
            $breaks->last(),
            false,
            false
        );

        // 管理者用の修正フォーム付き詳細を表示する。
        return view('attendance_detail_screen', [
            'headerVariant' => 'admin',
            'detailFields' => $detailFields,
            'attendance' => $attendance,
            'break' => $breaks->last(),
            'breaks' => $breaks,
            'readonly' => false,
            'formAction' => route('admin.attendance.update', $attendance),
            'formMethod' => 'PUT',
            'submitLabel' => '修正',
        ]);
    }

    public function update(AttendanceCorrectionRequest $request, Attendance $attendance)
    {
        // 対象勤怠の更新権限を確認する。
        $this->authorize('update', $attendance);
        // バリデーション済み入力で更新する。
        $this->adminAttendUpdateService->update($attendance, $request->validated());
        // 更新後は同じ詳細へ戻す。
        return redirect()->route('admin.attendance.detail', $attendance)->with('status', '勤怠を更新しました。');
    }

    public function staff()
    {
        // 一般ユーザーのみを名前順で表示する。
        return view('admin_attendance_staff', [
            'headerVariant' => 'admin',
            'users' => User::where('is_admin', false)->orderBy('name')->get(),
        ]);
    }

    public function staffAttendances(Request $request, User $user)
    {
        // 対象月を月初に正規化する。
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        // 指定スタッフの月次勤怠を取得する。
        $records = $this->attendanceListQuery->forUserMonth(
            userId: (int) $user->id,
            month: $month,
            withUser: true,
        );
        // 前後月ナビを生成する。
        $navigation = $this->buildMonthNavigation($month, 'admin.attendance.list', ['user' => $user->id]);

        // CSVリンク付きの一覧を表示する。
        return view('attendance_records_screen', [
            'headerVariant' => 'admin',
            'attendances' => $records,
            'previousUrl' => $navigation['previousUrl'],
            'nextUrl' => $navigation['nextUrl'],
            'currentLabel' => $navigation['currentLabel'],
            'previousLabel' => $navigation['previousLabel'],
            'nextLabel' => $navigation['nextLabel'],
            'firstColumnType' => 'date',
            'detailRouteName' => 'admin.attendance.detail',
            'allowMissingDetail' => false,
            'csvDownloadUrl' => route('admin.attendance.list.csv', ['user' => $user->id, 'month' => $month->format('Y-m')]),
            'title' => "{$user->name}さんの勤怠一覧",
        ]);
    }

    public function staffCsv(Request $request, User $user)
    {
        // 対象月を月初に正規化する。
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        // CSV対象の勤怠行を取得する。
        $records = $this->attendanceListQuery->forUserMonth(
            userId: (int) $user->id,
            month: $month,
        );

        // ダウンロード名を生成する。
        $filename = sprintf('attendances_%s_%s.csv', preg_replace('/\s+/', '_', $user->name) ?? 'user', $month->format('Y-m'));
        // CSVダウンロード用ヘッダ。
        $headers = ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        // ストリームでCSVを返す。
        return response()->streamDownload(function () use ($records) {
            // 出力ストリームを開く。
            $stream = fopen('php://output', 'w');
            // 失敗時は終了する。
            if (! $stream) {
                return;
            }
            // Excel向けにBOMを付与する。
            fwrite($stream, "\xEF\xBB\xBF");
            // ヘッダー行を出力する。
            fputcsv($stream, ['日付', '出勤', '退勤', '休憩', '合計', '備考']);
            // 勤怠行を出力する。
            foreach ($records as $attendance) {
                fputcsv($stream, [
                    Carbon::parse($attendance->work_date)->format('Y-m-d'),
                    $attendance->check_in_at ? Carbon::parse($attendance->check_in_at)->format('H:i') : '',
                    $attendance->check_out_at ? Carbon::parse($attendance->check_out_at)->format('H:i') : '',
                    TimeHelper::formatSeconds($attendance->calculated_break_seconds ?? 0),
                    ($attendance->check_in_at && $attendance->check_out_at) ? TimeHelper::formatSeconds($attendance->calculated_total_seconds ?? 0) : '',
                    $attendance->remarks ?? '',
                ]);
            }
            // ストリームを閉じる。
            fclose($stream);
        }, $filename, $headers);
    }

}
