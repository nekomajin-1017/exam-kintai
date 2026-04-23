<?php

namespace App\Http\Controllers;

use App\Constants\AttendanceStatusCode;
use App\Helpers\TimeHelper;
use App\Http\Controllers\Concerns\BuildsAttendanceViewData;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\User;
use App\Queries\AttendanceListQuery;
use App\Support\ActorContext;
use App\Workflows\AttendanceWorkflow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceScreenController extends Controller
{
    use BuildsAttendanceViewData;

    public function __construct(
        private AttendanceWorkflow $attendanceWorkflow,
        private AttendanceListQuery $attendanceListQuery,
    ) {
        // コンストラクタで必要なクラスを受け取るだけ。
    }

    public function index()
    {
        $user = Auth::user();
        if ($user?->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->where('work_date', now()->toDateString())
            ->first();

        return view('attendance', [
            'headerVariant' => ActorContext::USER->headerVariant(),
            'attendance' => $attendance,
            'statusCode' => $attendance?->attendance_status_code ?? AttendanceStatusCode::OFF,
        ]);
    }

    public function checkIn(Request $request)
    {
        return $this->handleStampAction($request, 'check_in');
    }

    public function checkOut(Request $request)
    {
        return $this->handleStampAction($request, 'check_out');
    }

    public function breakIn(Request $request)
    {
        return $this->handleStampAction($request, 'break_in');
    }

    public function breakOut(Request $request)
    {
        return $this->handleStampAction($request, 'break_out');
    }

    public function userList(Request $request)
    {
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $attendances = $this->attendanceListQuery->forUserMonth(
            userId: (int) $request->user()->id,
            month: $month,
            includeMissingDates: true,
        );
        $monthNavigation = $this->buildMonthNavigation($month, 'attendance.list');

        return view('attendance_records_screen', [
            'headerVariant' => ActorContext::USER->headerVariant(),
            'title' => '勤怠一覧',
            'attendances' => $attendances,
            ...$monthNavigation,
            'firstColumnType' => 'date',
            'detailRouteName' => 'attendance.detail',
            'allowMissingDetail' => true,
        ]);
    }

    public function userDetail(Attendance $attendance)
    {
        $this->authorize('view', $attendance);

        return $this->renderAttendanceDetail(
            context: ActorContext::USER,
            attendance: $attendance,
            formAction: route('attendance.request', $attendance),
            submitLabel: '修正',
            readonly: false,
            plainReadonly: false,
        );
    }

    public function showUserDetailByDate(Request $request, string $date)
    {
        try {
            $workDate = Carbon::createFromFormat('Y-m-d', $date)->toDateString();
        } catch (\Exception $exception) {
            abort(404);
        }

        $attendance = Attendance::query()->firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'work_date' => $workDate,
            ],
            [
                'attendance_status_code' => AttendanceStatusCode::OFF,
            ]
        );

        return $this->userDetail($attendance);
    }

    public function adminDashboard(Request $request)
    {
        $date = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        $dailyAttendances = $this->attendanceListQuery->forDay($date);
        $dayNavigation = $this->buildDayNavigation($date, 'admin.dashboard');

        return view('attendance_records_screen', [
            'headerVariant' => ActorContext::ADMIN->headerVariant(),
            'title' => '勤怠一覧',
            'attendances' => $dailyAttendances,
            ...$dayNavigation,
            'firstColumnType' => 'name',
            'detailRouteName' => 'admin.attendance.detail',
            'allowMissingDetail' => false,
        ]);
    }

    public function adminDetail(Attendance $attendance)
    {
        $this->authorize('view', $attendance);

        return $this->renderAttendanceDetail(
            context: ActorContext::ADMIN,
            attendance: $attendance,
            formAction: route('admin.attendance.update', $attendance),
            submitLabel: '修正',
            readonly: false,
            plainReadonly: false,
        );
    }

    public function adminUpdate(AttendanceCorrectionRequest $request, Attendance $attendance)
    {
        $this->authorize('update', $attendance);
        $this->attendanceWorkflow->updateAttendance($attendance, $request->validated());

        return redirect()->route('admin.attendance.detail', $attendance)->with('status', '勤怠を更新しました。');
    }

    public function adminStaff()
    {
        return view('admin_attendance_staff', [
            'headerVariant' => ActorContext::ADMIN->headerVariant(),
            'users' => User::query()->where('is_admin', false)->orderBy('name')->get(),
        ]);
    }

    public function adminStaffList(Request $request, User $user)
    {
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $staffAttendances = $this->attendanceListQuery->forUserMonth(
            userId: (int) $user->id,
            month: $month,
            withUser: true,
        );
        $monthNavigation = $this->buildMonthNavigation($month, 'admin.attendance.list', ['user' => $user->id]);

        return view('attendance_records_screen', [
            'headerVariant' => ActorContext::ADMIN->headerVariant(),
            'title' => "{$user->name}さんの勤怠一覧",
            'attendances' => $staffAttendances,
            ...$monthNavigation,
            'firstColumnType' => 'date',
            'detailRouteName' => 'admin.attendance.detail',
            'allowMissingDetail' => false,
            'csvDownloadUrl' => route('admin.attendance.list.csv', ['user' => $user->id, 'month' => $month->format('Y-m')]),
        ]);
    }

    public function adminStaffCsv(Request $request, User $user)
    {
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $staffAttendances = $this->attendanceListQuery->forUserMonth(
            userId: (int) $user->id,
            month: $month,
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
                    TimeHelper::formatSeconds($attendance->calculated_break_seconds ?? 0),
                    ($attendance->check_in_at && $attendance->check_out_at) ? TimeHelper::formatSeconds($attendance->calculated_total_seconds ?? 0) : '',
                    $attendance->remarks ?? '',
                ]);
            }
            fclose($stream);
        }, $filename, $headers);
    }

    private function renderAttendanceDetail(
        ActorContext $context,
        Attendance $attendance,
        ?string $formAction,
        ?string $submitLabel,
        bool $readonly,
        bool $plainReadonly,
        bool $submitDisabled = false,
        ?string $statusMessage = null,
    ) {
        $attendance->load('user', 'breaks');
        $breaks = $attendance->breaks()->orderBy('break_start_at')->get();
        $detailFields = $this->buildAttendanceDetailFields(
            $attendance,
            $breaks,
            $readonly,
            $plainReadonly
        );

        return view('attendance_detail_screen', [
            'headerVariant' => $context->headerVariant(),
            'detailFields' => $detailFields,
            'readonly' => $readonly,
            'plainReadonly' => $plainReadonly,
            'formAction' => $formAction,
            'formMethod' => 'PUT',
            'submitLabel' => $submitLabel,
            'submitDisabled' => $submitDisabled,
            'statusMessage' => $statusMessage,
        ]);
    }

    private function handleStampAction(Request $request, string $stampAction)
    {
        $this->attendanceWorkflow->stamp((int) $request->user()->id, $stampAction);

        return redirect()->route('attendance.index');
    }
}
