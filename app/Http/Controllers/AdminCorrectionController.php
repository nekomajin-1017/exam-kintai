<?php

namespace App\Http\Controllers;

use App\Constants\ApprovalStatusCode;
use App\Http\Controllers\Concerns\BuildsAttendanceViewData;
use App\Models\AttendanceCorrection;
use App\Workflows\AttendanceWorkflow;
use Illuminate\Http\Request;

class AdminCorrectionController extends Controller
{
    use BuildsAttendanceViewData;

    public function __construct(
        // 承認処理の業務ロジック。
        private AttendanceWorkflow $attendanceWorkflow,
    )
    {
        // DI注入のみ。
    }

    public function list(Request $request)
    {
        // タブは pending/approved を受け取る（既定: pending）。
        $tab = $request->query('tab', 'pending');
        // 管理者向けに全申請を取得する。
        $applications = AttendanceCorrection::with(['attendance.user', 'requestUser'])
            ->forTab($tab)
            ->latest()
            ->get();

        // 申請一覧を表示する。
        return view('applications_screen', [
            'headerVariant' => 'admin',
            'applications' => $applications,
            'tab' => $tab,
            'isAdmin' => true,
            'tabRoute' => 'stamp_correction_requests.list',
            'detailRouteName' => 'admin.attendance.approve',
        ]);
    }

    public function detail(AttendanceCorrection $attendanceCorrection)
    {
        // 申請詳細の閲覧権限を確認する。
        $this->authorize('view', $attendanceCorrection);
        // 承認済みかどうかを判定する。
        $isApproved = $attendanceCorrection->approval_status_code === ApprovalStatusCode::APPROVED;
        // 詳細表示データを組み立てる。
        $detail = $this->buildDetailFromCorrection($attendanceCorrection);
        // 詳細フォーム用の表示データを組み立てる。
        $detailFields = $this->buildAttendanceDetailFields(
            $detail['attendance'],
            $detail['breaks'],
            $detail['break'],
            true,
            $isApproved
        );
        // 承認画面用の詳細を表示する。
        return view('attendance_detail_screen', [
            'headerVariant' => 'admin',
            ...$detail,
            'detailFields' => $detailFields,
            'readonly' => true,
            'plainReadonly' => $isApproved,
            'formAction' => route('admin.attendance.approve.update', $attendanceCorrection),
            'formMethod' => 'PUT',
            'submitLabel' => $isApproved ? '承認済み' : '承認',
            'submitDisabled' => $isApproved,
        ]);
    }

    public function approve(AttendanceCorrection $attendanceCorrection)
    {
        // 申請承認の権限を確認する。
        $this->authorize('approve', $attendanceCorrection);

        // 承認済み申請の重複承認は行わない。
        if ($attendanceCorrection->approval_status_code === ApprovalStatusCode::APPROVED) {
            return redirect()
                ->route('admin.attendance.approve', $attendanceCorrection)
                ->with('status', 'この申請は既に承認済みです。');
        }

        // 承認結果を本体勤怠へ反映する。
        $this->attendanceWorkflow->approveCorrection(
            $attendanceCorrection,
            (int) auth()->id()
        );
        // 承認後は詳細へ戻し、承認済み状態を表示する。
        return redirect()
            ->route('admin.attendance.approve', $attendanceCorrection)
            ->with('status', '申請を承認しました。');
    }
}
