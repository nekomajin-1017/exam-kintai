<?php

namespace App\Http\Controllers;

use App\Constants\AttendanceStatusCode;
use App\Http\Controllers\Concerns\BuildsAttendanceViewData;
use App\Models\Attendance;
use App\Queries\AttendanceListQuery;
use App\Workflows\AttendanceWorkflow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    use BuildsAttendanceViewData;

    public function __construct(
        // 打刻処理の業務ロジック。
        private AttendanceWorkflow $attendanceWorkflow,
        // 勤怠一覧の取得クエリ。
        private AttendanceListQuery $attendanceListQuery,
    )
    {
        // DI注入のみ。
    }

    public function index()
    {
        // ログインユーザーを取得する。
        $user = Auth::user();
        // 管理者が来た場合は管理画面へリダイレクトする。
        if ($user?->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        // 当日勤怠を取得する（未作成なら null）。
        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->where('work_date', now()->toDateString())
            ->first();

        // 打刻トップを表示する。
        return view('attendance', [
            'headerVariant' => $this->headerVariant(),
            'attendance' => $attendance,
            // 未設定時は off としてボタン制御を単純化する。
            'statusCode' => $attendance?->attendance_status_code ?? AttendanceStatusCode::OFF,
        ]);
    }

    public function checkIn(Request $request)
    {
        // 出勤打刻を実行する。
        return $this->stampAndRedirect($request, 'check_in');
    }

    public function checkOut(Request $request)
    {
        // 退勤打刻を実行する。
        return $this->stampAndRedirect($request, 'check_out');
    }

    public function breakIn(Request $request)
    {
        // 休憩入打刻を実行する。
        return $this->stampAndRedirect($request, 'break_in');
    }

    public function breakOut(Request $request)
    {
        // 休憩戻打刻を実行する。
        return $this->stampAndRedirect($request, 'break_out');
    }

    public function list(Request $request)
    {
        // クエリの month を対象月として受け取り、月初に正規化する。
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        // 月次勤怠を欠損日補完ありで取得する。
        $attendances = $this->attendanceListQuery->forUserMonth(
            userId: (int) $request->user()->id,
            month: $month,
            includeMissingDates: true,
        );
        // 前後月ナビを組み立てる。
        $navigation = $this->buildMonthNavigation($month, 'attendance.list');

        // 一覧画面を表示する。
        return view('attendance_records_screen', [
            'headerVariant' => $this->headerVariant(),
            'attendances' => $attendances,
            'previousUrl' => $navigation['previousUrl'],
            'nextUrl' => $navigation['nextUrl'],
            'currentLabel' => $navigation['currentLabel'],
            'previousLabel' => $navigation['previousLabel'],
            'nextLabel' => $navigation['nextLabel'],
            'firstColumnType' => 'date',
            'detailRouteName' => 'attendance.detail',
            'allowMissingDetail' => true,
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

        // 修正フォーム付き詳細を表示する。
        return view('attendance_detail_screen', [
            'headerVariant' => $this->headerVariant(),
            'detailFields' => $detailFields,
            'attendance' => $attendance,
            'break' => $breaks->last(),
            'breaks' => $breaks,
            'readonly' => false,
            'formAction' => route('attendance.request', $attendance),
            'formMethod' => 'PUT',
            'submitLabel' => '修正',
        ]);
    }

    public function detailByDate(Request $request, string $date)
    {
        try {
            // URLの日付文字列を厳密にパースする。
            $workDate = Carbon::createFromFormat('Y-m-d', $date)->toDateString();
        } catch (\Exception $exception) {
            // 不正フォーマットは 404。
            abort(404);
        }

        // 指定日の勤怠がなければ最小データで作成する。
        $attendance = Attendance::query()->firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'work_date' => $workDate,
            ],
            [
                'attendance_status_code' => AttendanceStatusCode::OFF,
            ]
        );

        // 通常の詳細表示へ流す。
        return $this->detail($attendance);
    }

    private function stampAndRedirect(Request $request, string $action)
    {
        // 指定された打刻アクションを実行する。
        $this->attendanceWorkflow->stamp((int) $request->user()->id, $action);
        // 処理後は打刻トップへ戻す。
        return redirect()->route('attendance.index');
    }
}
