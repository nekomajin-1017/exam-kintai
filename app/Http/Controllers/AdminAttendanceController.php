<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsAttendanceScreenHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\User;
use App\Queries\AttendanceListQuery;
use App\Services\DurationService;
use App\Services\AttendanceCorrectionService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAttendanceController extends Controller
{
    use BuildsAttendanceScreenHelpers;

    public function __construct(
        private AttendanceCorrectionService $attendanceCorrectionService,
        private AttendanceListQuery $attendanceListQuery,
    ) {}

    // 管理者向け日次勤怠一覧を表示。
    public function adminDashboard(Request $request): View
    {
        $date = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        $dailyAttendanceRecords = $this->attendanceListQuery->forDay($date);
        $dayNavigation = $this->buildDayNavigation($date, 'admin.dashboard');

        return view('attendance_records_screen', [
            'title' => "{$dayNavigation['currentLabel']}の勤怠",
            'attendanceRecords' => $dailyAttendanceRecords,
            ...$dayNavigation,
            'firstColumnType' => 'name',
            'detailRouteName' => 'admin.attendance.detail',
            'allowMissingDetail' => false,
        ]);
    }

    // 管理者向け勤怠詳細画面を表示。
    public function showAdminAttendanceDetail(Request $request, Attendance $attendance): View
    {
        $targetAttendance = $attendance;
        $this->authorize('view', $targetAttendance);

        return $this->renderAttendanceDetail(
            $targetAttendance,
            route('admin.attendance.update', $targetAttendance),
            '修正',
        );
    }

    // 指定ユーザー・日付の勤怠詳細を表示し、未作成日は初期作成する。
    public function showAdminAttendanceDetailByDate(Request $request, User $user, string $date): View
    {
        $workDate = $this->validateWorkDateOrAbort($date);
        $targetAttendance = $this->findOrCreateAttendanceForDate((int) $user->id, $workDate);

        return $this->showAdminAttendanceDetail($request, $targetAttendance);
    }

    // 管理者による勤怠修正を反映し、勤怠詳細へ戻す。
    public function adminUpdate(AttendanceCorrectionRequest $request, Attendance $attendance): RedirectResponse
    {
        $targetAttendance = $attendance;
        $this->authorize('update', $targetAttendance);
        $this->attendanceCorrectionService->updateAttendance($targetAttendance, $request->validated());

        return redirect()->route('admin.attendance.detail', $targetAttendance);
    }

    // 管理者向けスタッフ一覧を表示。
    public function adminStaff(): View
    {
        return view('admin_attendance_staff', [
            'users' => User::query()->where('is_admin', false)->orderBy('name')->get(),
        ]);
    }

    // 指定スタッフの月次勤怠一覧を表示。
    public function adminStaffAttendanceList(Request $request, User $user): View
    {
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $staffAttendanceRecords = $this->attendanceListQuery->forUserMonth(
            (int) $user->id,
            $month,
            true,
        );
        $monthNavigation = $this->buildMonthNavigation($month, 'admin.attendance.list', ['user' => $user->id]);

        return view('attendance_records_screen', [
            'title' => "{$user->name}さんの勤怠一覧",
            'attendanceRecords' => $staffAttendanceRecords,
            ...$monthNavigation,
            'firstColumnType' => 'date',
            'detailRouteName' => 'admin.attendance.detail',
            'allowMissingDetail' => true,
            'missingDetailRouteName' => 'admin.attendance.detail.date',
            'missingDetailRouteParams' => ['user' => $user->id],
            'csvDownloadUrl' => route('admin.attendance.list.csv', ['user' => $user->id, 'month' => $month->format('Y-m')]),
        ]);
    }

    // 指定スタッフの月次勤怠CSVを出力。
    public function adminStaffCsv(Request $request, User $user): StreamedResponse
    {
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $staffAttendances = $this->attendanceListQuery->forUserMonth(
            (int) $user->id,
            $month,
            false,
        );

        $filename = sprintf('attendances_%s_%s.csv', preg_replace('/\s+/', '_', $user->name) ?? 'user', $month->format('Y-m'));
        $headers = ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        return response()->streamDownload(function () use ($staffAttendances) {
            $stream = fopen('php://output', 'w');
            if (! $stream) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['日付', '出勤', '退勤', '休憩', '合計', '備考']);
            foreach ($staffAttendances as $attendance) {
                fputcsv($stream, [
                    Carbon::parse($attendance->work_date)->format('Y-m-d'),
                    $attendance->check_in_at ? Carbon::parse($attendance->check_in_at)->format('H:i') : '',
                    $attendance->check_out_at ? Carbon::parse($attendance->check_out_at)->format('H:i') : '',
                    DurationService::formatSeconds($attendance->calculated_break_seconds ?? 0),
                    ($attendance->check_in_at && $attendance->check_out_at) ? DurationService::formatSeconds($attendance->calculated_total_seconds ?? 0) : '',
                    $attendance->remarks ?? '',
                ]);
            }
            fclose($stream);
        }, $filename, $headers);
    }
}
