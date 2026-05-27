<?php

namespace App\Http\Controllers;

use App\Constants\ApprovalStatusCode;
use App\Http\Controllers\Concerns\BuildsAttendanceViewData;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Services\AttendanceCorrectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AttendanceCorrectionController extends Controller
{
    use BuildsAttendanceViewData;

    public function __construct(private AttendanceCorrectionService $attendanceCorrectionService) {}

    // 勤怠修正申請を作成し、申請詳細画面へ遷移。
    public function store(AttendanceCorrectionRequest $request, Attendance $attendance)
    {
        $this->authorize('store', $attendance);

        $correction = $this->attendanceCorrectionService->requestCorrection(
            $attendance,
            (int) $request->user()->id,
            $request->validated(),
        );

        return redirect()->route('stamp_correction_request.detail', $correction);
    }

    // 管理者または一般ユーザー向けに申請一覧を表示。
    public function listCorrectionRequests(Request $request)
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

        $correctionRequestsQuery = AttendanceCorrection::with(['attendance.user', 'requestUser']);
        if ($tab === 'approved') {
            $correctionRequestsQuery->approved();
        } else {
            $correctionRequestsQuery->pending();
        }
        if (! $isAdmin) {
            $correctionRequestsQuery->where('request_user_id', $user->id);
        }
        $correctionRequests = $correctionRequestsQuery->latest('created_at')->get();

        return view('applications_screen', [
            'tab' => $tab,
            'correctionRequests' => $correctionRequests,
            'tabRoute' => 'stamp_correction_requests.list',
        ]);
    }

    // 一般ユーザー向けに申請詳細画面を表示。
    public function showCorrectionRequestDetail(Request $request, AttendanceCorrection $attendanceCorrection)
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

        $this->attendanceCorrectionService->approveCorrection(
            $attendanceCorrection,
            (int) auth()->id(),
        );

        return redirect()
            ->route('admin.attendance.approve', $attendanceCorrection);
    }
}
