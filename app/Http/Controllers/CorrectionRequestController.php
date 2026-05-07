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
        $isAdmin = (bool) $user?->is_admin;
        $tab = $request->query('tab', 'pending');

        if (! $isAdmin && ! $user?->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $applications = $isAdmin
            ? AttendanceCorrection::with(['attendance.user', 'requestUser'])->forTab($tab)->latest()->get()
            : AttendanceCorrection::with(['attendance.user'])
                ->where('request_user_id', $user->id)
                ->forTab($tab)
                ->latest('created_at')
                ->get();

        return view('applications_screen', [
            'headerVariant' => $isAdmin ? 'admin' : 'user',
            'tab' => $tab,
            'applications' => $applications,
            'isAdmin' => $isAdmin,
            'tabRoute' => 'stamp_correction_requests.list',
            'detailRouteName' => $isAdmin ? 'admin.attendance.approve' : 'stamp_correction_request.detail',
        ]);
    }

    // 一般ユーザー向けに申請詳細画面を表示。
    public function userDetail(AttendanceCorrection $attendanceCorrection)
    {
        $this->authorize('view', $attendanceCorrection);

        return $this->renderCorrectionDetail($attendanceCorrection, false);
    }

    // 管理者向けに申請詳細画面を表示。
    public function adminDetail(AttendanceCorrection $attendanceCorrection)
    {
        $this->authorize('view', $attendanceCorrection);

        return $this->renderCorrectionDetail($attendanceCorrection, true);
    }

    // 申請の詳細画面を権限種別ごとの表示設定で描画。
    private function renderCorrectionDetail(AttendanceCorrection $attendanceCorrection, bool $isAdmin): View
    {
        $isApproved = $attendanceCorrection->approval_status_code === ApprovalStatusCode::APPROVED;
        $isReadonly = $isAdmin || ! $isApproved;
        $detail = $this->buildDetailFromCorrection($attendanceCorrection);
        $detailFields = $this->buildAttendanceDetailFields(
            $detail['attendance'],
            $detail['breaks'],
            $isReadonly,
            $isReadonly,
        );

        return view('attendance_detail_screen', [
            'headerVariant' => $isAdmin ? 'admin' : 'user',
            'detailFields' => $detailFields,
            'readonly' => $isReadonly,
            'plainReadonly' => $isReadonly,
            'formAction' => $isAdmin
                ? route('admin.attendance.approve.update', $attendanceCorrection)
                : ($isApproved ? route('attendance.request', $detail['attendance']) : null),
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
