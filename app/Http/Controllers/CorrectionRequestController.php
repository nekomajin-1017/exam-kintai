<?php

namespace App\Http\Controllers;

use App\Constants\ApprovalStatusCode;
use App\Http\Controllers\Concerns\BuildsAttendanceViewData;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Support\ActorContext;
use App\Workflows\AttendanceWorkflow;
use Illuminate\Http\Request;

class CorrectionRequestController extends Controller
{
    use BuildsAttendanceViewData;

    public function __construct(private AttendanceWorkflow $attendanceWorkflow)
    {
        // コンストラクタで必要なクラスを受け取るだけ。
    }

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

    public function list(Request $request)
    {
        $context = ActorContext::fromUser($request->user());
        $tab = $request->query('tab', 'pending');

        if (! $context->isAdmin() && ! $request->user()?->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $applications = $context->isAdmin()
            ? AttendanceCorrection::with(['attendance.user', 'requestUser'])->forTab($tab)->latest()->get()
            : AttendanceCorrection::with(['attendance.user'])
                ->where('request_user_id', $request->user()->id)
                ->forTab($tab)
                ->latest('created_at')
                ->get();

        return view('applications_screen', [
            'headerVariant' => $context->headerVariant(),
            'tab' => $tab,
            'applications' => $applications,
            'isAdmin' => $context->isAdmin(),
            'tabRoute' => 'stamp_correction_requests.list',
            'detailRouteName' => $context->isAdmin() ? 'admin.attendance.approve' : 'stamp_correction_request.detail',
        ]);
    }

    public function userDetail(AttendanceCorrection $attendanceCorrection)
    {
        $this->authorize('view', $attendanceCorrection);

        $detail = $this->buildDetailFromCorrection($attendanceCorrection);
        $isApproved = $attendanceCorrection->approval_status_code === ApprovalStatusCode::APPROVED;
        $detailFields = $this->buildAttendanceDetailFields(
            $detail['attendance'],
            $detail['breaks'],
            ! $isApproved,
            ! $isApproved,
        );

        return view('attendance_detail_screen', [
            'headerVariant' => ActorContext::USER->headerVariant(),
            'detailFields' => $detailFields,
            'readonly' => ! $isApproved,
            'plainReadonly' => ! $isApproved,
            'formAction' => $isApproved ? route('attendance.request', $detail['attendance']) : null,
            'formMethod' => 'PUT',
            'submitLabel' => $isApproved ? '修正' : null,
            'statusMessage' => $isApproved ? null : '※承認待ちのため、修正はできません。',
        ]);
    }

    public function adminDetail(AttendanceCorrection $attendanceCorrection)
    {
        $this->authorize('view', $attendanceCorrection);

        $isApproved = $attendanceCorrection->approval_status_code === ApprovalStatusCode::APPROVED;
        $detail = $this->buildDetailFromCorrection($attendanceCorrection);
        $detailFields = $this->buildAttendanceDetailFields(
            $detail['attendance'],
            $detail['breaks'],
            true,
            true,
        );

        return view('attendance_detail_screen', [
            'headerVariant' => ActorContext::ADMIN->headerVariant(),
            'detailFields' => $detailFields,
            'readonly' => true,
            'plainReadonly' => true,
            'formAction' => route('admin.attendance.approve.update', $attendanceCorrection),
            'formMethod' => 'PUT',
            'submitLabel' => $isApproved ? '承認済み' : '承認',
            'submitDisabled' => $isApproved,
        ]);
    }

    public function approve(AttendanceCorrection $attendanceCorrection)
    {
        $this->authorize('approve', $attendanceCorrection);

        if ($attendanceCorrection->approval_status_code === ApprovalStatusCode::APPROVED) {
            return redirect()
                ->route('admin.attendance.approve', $attendanceCorrection)
                ->with('status', 'この申請は既に承認済みです。');
        }

        $this->attendanceWorkflow->approveCorrection(
            $attendanceCorrection,
            (int) auth()->id(),
        );

        return redirect()
            ->route('admin.attendance.approve', $attendanceCorrection)
            ->with('status', '申請を承認しました。');
    }
}
