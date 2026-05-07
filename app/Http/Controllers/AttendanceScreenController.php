<?php

namespace App\Http\Controllers;

use App\Constants\AttendanceStatusCode;
use App\Http\Controllers\Concerns\BuildsAttendanceViewData;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\User;
use App\Queries\AttendanceListQuery;
use App\Services\DurationService;
use App\Workflows\AttendanceWorkflow;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceScreenController extends Controller
{
    use BuildsAttendanceViewData;

    public function __construct(
        private AttendanceWorkflow $attendanceWorkflow,
        private AttendanceListQuery $attendanceListQuery,
    ) {}

    // 一般ユーザーの打刻画面を表示し、管理者アクセス時は管理画面へリダイレクト。
    public function index(): View|RedirectResponse
    {
        $user = Auth::user();
        if ($user?->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();

        return view('attendance', [
            'headerVariant' => 'user',
            'attendance' => $attendance,
            'statusCode' => $attendance?->attendance_status_code ?? AttendanceStatusCode::OFF,
        ]);
    }

    // 出勤打刻の処理を実行。
    public function checkIn(Request $request): RedirectResponse
    {
        return $this->handleStampAction($request, 'check_in');
    }

    // 退勤打刻の処理を実行。
    public function checkOut(Request $request): RedirectResponse
    {
        return $this->handleStampAction($request, 'check_out');
    }

    // 休憩入打刻の処理を実行。
    public function breakIn(Request $request): RedirectResponse
    {
        return $this->handleStampAction($request, 'break_in');
    }

    // 休憩戻打刻の処理を実行。
    public function breakOut(Request $request): RedirectResponse
    {
        return $this->handleStampAction($request, 'break_out');
    }

    // ユーザー本人の月次勤怠一覧を表示。
    public function userList(Request $request): View
    {
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $attendances = $this->attendanceListQuery->forUserMonth(
            (int) $request->user()->id,
            $month,
            false,
            true,
        );
        $monthNavigation = $this->buildMonthNavigation($month, 'attendance.list');

        return view('attendance_records_screen', [
            'headerVariant' => 'user',
            'title' => '勤怠一覧',
            'attendances' => $attendances,
            ...$monthNavigation,
            'firstColumnType' => 'date',
            'detailRouteName' => 'attendance.detail',
            'allowMissingDetail' => true,
        ]);
    }

    // ユーザー本人の勤怠詳細画面を表示。
    public function userDetail(Attendance $attendance): View
    {
        $this->authorize('view', $attendance);

        return $this->renderAttendanceDetail(
            'user',
            $attendance,
            route('attendance.request', $attendance),
            '修正',
            false,
            false,
        );
    }

    // 日付指定で本人勤怠を取得し、未作成なら作成して詳細画面を表示。
    public function showUserDetailByDate(Request $request, string $date): View
    {
        $workDate = $this->parseWorkDateOrAbort($date);
        $attendance = $this->findOrCreateAttendanceForDate((int) $request->user()->id, $workDate);

        return $this->userDetail($attendance);
    }

    // 管理者向けの日次勤怠一覧を表示。
    public function adminDashboard(Request $request): View
    {
        $date = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        $dailyAttendances = $this->attendanceListQuery->forDay($date);
        $dayNavigation = $this->buildDayNavigation($date, 'admin.dashboard');

        return view('attendance_records_screen', [
            'headerVariant' => 'admin',
            'title' => '勤怠一覧',
            'attendances' => $dailyAttendances,
            ...$dayNavigation,
            'firstColumnType' => 'name',
            'detailRouteName' => 'admin.attendance.detail',
            'allowMissingDetail' => false,
        ]);
    }

    // 管理者向けの勤怠詳細画面を表示。
    public function adminDetail(Attendance $attendance): View
    {
        $this->authorize('view', $attendance);

        return $this->renderAttendanceDetail(
            'admin',
            $attendance,
            route('admin.attendance.update', $attendance),
            '修正',
            false,
            false,
        );
    }

    // 管理者がユーザーと日付を指定して勤怠詳細を表示。
    public function adminDetailByDate(User $user, string $date): View
    {
        $workDate = $this->parseWorkDateOrAbort($date);
        $attendance = $this->findOrCreateAttendanceForDate((int) $user->id, $workDate);

        return $this->adminDetail($attendance);
    }

    // 管理者の入力で勤怠を更新し、詳細画面へ遷移。
    public function adminUpdate(AttendanceCorrectionRequest $request, Attendance $attendance): RedirectResponse
    {
        $this->authorize('update', $attendance);
        $this->attendanceWorkflow->updateAttendance($attendance, $request->validated());

        return redirect()->route('admin.attendance.detail', $attendance);
    }

    // 管理者向けのスタッフ一覧画面を表示。
    public function adminStaff(): View
    {
        return view('admin_attendance_staff', [
            'headerVariant' => 'admin',
            'users' => User::query()->where('is_admin', false)->orderBy('name')->get(),
        ]);
    }

    // 指定スタッフの月次勤怠一覧を表示。
    public function adminStaffList(Request $request, User $user): View
    {
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $staffAttendances = $this->attendanceListQuery->forUserMonth(
            (int) $user->id,
            $month,
            true,
            true,
        );
        $monthNavigation = $this->buildMonthNavigation($month, 'admin.attendance.list', ['user' => $user->id]);

        return view('attendance_records_screen', [
            'headerVariant' => 'admin',
            'title' => "{$user->name}さんの勤怠一覧",
            'attendances' => $staffAttendances,
            ...$monthNavigation,
            'firstColumnType' => 'date',
            'detailRouteName' => 'admin.attendance.detail',
            'allowMissingDetail' => true,
            'missingDetailRouteName' => 'admin.attendance.detail.date',
            'missingDetailRouteParams' => ['user' => $user->id],
            'csvDownloadUrl' => route('admin.attendance.list.csv', ['user' => $user->id, 'month' => $month->format('Y-m')]),
        ]);
    }

    // 指定スタッフの月次勤怠をCSV形式でダウンロード。
    public function adminStaffCsv(Request $request, User $user): StreamedResponse
    {
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $staffAttendances = $this->attendanceListQuery->forUserMonth(
            (int) $user->id,
            $month,
            false,
            true,
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

    // 勤怠と休憩情報を詳細画面フォーム向けに整形して表示。
    private function renderAttendanceDetail(
        string $headerVariant,
        Attendance $attendance,
        ?string $formAction,
        ?string $submitLabel,
        bool $readonly,
        bool $plainReadonly,
        bool $submitDisabled = false,
        ?string $statusMessage = null,
    ): View {
        $attendance->load('user', 'breaks');
        $breaks = $attendance->breaks()->orderBy('break_start_at')->get();
        $detailFields = $this->buildAttendanceDetailFields(
            $attendance,
            $breaks,
            $readonly,
            $plainReadonly
        );

        return view('attendance_detail_screen', [
            'headerVariant' => $headerVariant,
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

    // 打刻アクションをワークフローへ渡して、打刻画面へ遷移。
    private function handleStampAction(Request $request, string $action): RedirectResponse
    {
        $this->attendanceWorkflow->stamp((int) $request->user()->id, $action);

        return redirect()->route('attendance.index');
    }

    // URLの日付文字列を検証し、無効なら404を返す。
    private function parseWorkDateOrAbort(string $date): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $date)->toDateString();
        } catch (\Exception $exception) {
            abort(404);
        }
    }

    // 指定ユーザー・日付の勤怠を取得し、なければ初期状態で作成する。
    private function findOrCreateAttendanceForDate(int $userId, string $workDate): Attendance
    {
        return Attendance::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'work_date' => $workDate,
            ],
            [
                'attendance_status_code' => AttendanceStatusCode::OFF,
            ]
        );
    }
}
