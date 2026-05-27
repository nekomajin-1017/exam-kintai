<?php

namespace App\Http\Controllers;

use App\Constants\AttendanceStatusCode;
use App\Http\Controllers\Concerns\BuildsAttendanceScreenHelpers;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Queries\AttendanceListQuery;
use App\Services\AttendanceStampService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserAttendanceController extends Controller
{
    use BuildsAttendanceScreenHelpers;

    public function __construct(
        private AttendanceStampService $attendanceStampService,
        private AttendanceListQuery $attendanceListQuery,
    ) {}

    // 一般ユーザー向け打刻画面を表示し、管理者は管理画面へリダイレクト。
    public function index(): View|RedirectResponse
    {
        $user = Auth::user();
        if ($user?->can('admin')) {
            return redirect()->route('admin.dashboard');
        }

        $todayAttendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();

        return view('attendance', [
            'todayAttendance' => $todayAttendance,
            'statusCode' => $todayAttendance?->attendance_status_code ?? AttendanceStatusCode::OFF,
        ]);
    }

    // 出勤打刻を実行し、打刻画面へ戻す。
    public function checkIn(Request $request): RedirectResponse
    {
        return $this->handleStampAction($request, 'check_in');
    }

    // 退勤打刻を実行し、打刻画面へ戻す。
    public function checkOut(Request $request): RedirectResponse
    {
        return $this->handleStampAction($request, 'check_out');
    }

    // 休憩開始打刻を実行し、打刻画面へ戻す。
    public function breakIn(Request $request): RedirectResponse
    {
        return $this->handleStampAction($request, 'break_in');
    }

    // 休憩終了打刻を実行し、打刻画面へ戻す。
    public function breakOut(Request $request): RedirectResponse
    {
        return $this->handleStampAction($request, 'break_out');
    }

    // 一般ユーザーの月次勤怠一覧を表示。
    public function listUserAttendances(Request $request): View
    {
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $attendanceRecords = $this->attendanceListQuery->forUserMonth(
            (int) $request->user()->id,
            $month,
            false,
        );
        $monthNavigation = $this->buildMonthNavigation($month, 'attendance.list');

        return view('attendance_records_screen', [
            'title' => '勤怠一覧',
            'attendanceRecords' => $attendanceRecords,
            ...$monthNavigation,
            'firstColumnType' => 'date',
            'detailRouteName' => 'attendance.detail',
            'allowMissingDetail' => true,
        ]);
    }

    // 一般ユーザーの勤怠詳細画面を表示。
    public function showUserAttendanceDetail(Request $request, Attendance $attendance): View
    {
        $targetAttendance = $attendance;
        $this->authorize('view', $targetAttendance);

        return $this->renderAttendanceDetail(
            $targetAttendance,
            route('attendance.request', $targetAttendance),
            '修正',
        );
    }

    // 指定日付の一般ユーザー勤怠詳細を表示し、未作成日は初期作成する。
    public function showUserAttendanceDetailByDate(Request $request, string $date): View
    {
        $workDate = $this->validateWorkDateOrAbort($date);
        $targetAttendance = $this->findOrCreateAttendanceForDate((int) $request->user()->id, $workDate);

        return $this->showUserAttendanceDetail($request, $targetAttendance);
    }

    // 打刻種別に応じた打刻処理を実行し、打刻画面へ戻す。
    private function handleStampAction(Request $request, string $action): RedirectResponse
    {
        $this->attendanceStampService->stamp((int) $request->user()->id, $action);

        return redirect()->route('attendance.index');
    }
}
