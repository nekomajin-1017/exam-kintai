<?php

namespace App\Http\Controllers;

use App\Constants\ApprovalStatusCode;
use App\Http\Controllers\Concerns\BuildsAttendanceViewData;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Workflows\AttendanceWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CorrectionRequestController extends Controller
{
    use BuildsAttendanceViewData;

    public function __construct(private AttendanceWorkflow $attendanceWorkflow) {}

    // 勤怠修正申請を作成し、申請詳細画面へ遷移。
    public function store(AttendanceCorrectionRequest $request, Attendance $attendance)
    {
        $this->authorize('store', $attendance);

        $correction = $this->attendanceWorkflow->requestCorrection(
            $attendance,
            (int) $request->user()->id,
            $request->validated(),
        );

        return redirect()->route('stamp_correction_request.detail', $correction);
    }

    // 管理者または一般ユーザー向けに申請一覧を表示。
    public function list(Request $request)
    {
        $user = $request->user();
        $isAdmin = (bool) $user?->can('admin');
        $validated = $request->validate([
            'tab' => ['nullable', 'in:pending,approved'],
        ]);
        $tab = $validated['tab'] ?? 'pending';

        if (! $isAdmin && ! $user?->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $applicationsQuery = AttendanceCorrection::with(['attendance.user', 'requestUser']);
        if ($tab === 'approved') {
            $applicationsQuery->approved();
        } else {
            $applicationsQuery->pending();
        }
        if (! $isAdmin) {
            $applicationsQuery->where('request_user_id', $user->id);
        }
        $applications = $applicationsQuery->latest('created_at')->get();

        return view('applications_screen', [
            'tab' => $tab,
            'applications' => $applications,
            'tabRoute' => 'stamp_correction_requests.list',
        ]);
    }

    // 一般ユーザー向けに申請詳細画面を表示。
    public function detail(Request $request, AttendanceCorrection $attendanceCorrection)
    {
        $this->authorize('view', $attendanceCorrection);
        $isAdmin = (bool) $request->user()?->can('admin');

        return $this->renderCorrectionDetail($attendanceCorrection, $isAdmin);
    }

    // 申請の詳細画面を権限種別ごとの表示設定で描画。
    private function renderCorrectionDetail(AttendanceCorrection $attendanceCorrection, bool $isAdmin): View
    {
        $isApproved = $attendanceCorrection->approval_status_code === ApprovalStatusCode::APPROVED;
        $isReadonly = $isAdmin || ! $isApproved;
        $correctionDetailContext = $this->buildDetailFromCorrection($attendanceCorrection);
        $detailFields = $this->buildAttendanceDetailFields(
            $correctionDetailContext['attendance'],
            $correctionDetailContext['breaks'],
            $isReadonly,
        );

        return view('attendance_detail_screen', [
            'detailFields' => $detailFields,
            'formAction' => $isAdmin
                ? route('admin.attendance.approve.update', $attendanceCorrection)
                : ($isApproved ? route('attendance.request', $correctionDetailContext['attendance']) : null),
            'formMethod' => 'PUT',
            'submitLabel' => $isAdmin
                ? ($isApproved ? '承認済み' : '承認')
                : ($isApproved ? '修正' : null),
            'submitDisabled' => $isAdmin && $isApproved,
            'statusMessage' => $isAdmin || $isApproved
                ? null
                : '※承認待ちのため、修正はできません。',
        ]);
    }

    // 申請を承認し、結果メッセージ付きで詳細画面へ遷移。
    public function approve(AttendanceCorrection $attendanceCorrection)
    {
        $this->authorize('approve', $attendanceCorrection);

        if ($attendanceCorrection->approval_status_code === ApprovalStatusCode::APPROVED) {
            return redirect()
                ->route('admin.attendance.approve', $attendanceCorrection);
        }

        $this->attendanceWorkflow->approveCorrection(
            $attendanceCorrection,
            (int) auth()->id(),
        );

        return redirect()
            ->route('admin.attendance.approve', $attendanceCorrection);
    }
}
