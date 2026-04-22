<?php

namespace App\Http\Controllers;

use App\Constants\ApprovalStatusCode;
use App\Http\Controllers\Concerns\BuildsAttendanceViewData;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Workflows\AttendanceWorkflow;
use Illuminate\Http\Request;

class CorrectionController extends Controller
{
    use BuildsAttendanceViewData;

    public function __construct(
        // 申請/承認の業務ロジック。
        private AttendanceWorkflow $attendanceWorkflow,
    )
    {
        // DI注入のみ。
    }

    public function store(AttendanceCorrectionRequest $request, Attendance $attendance)
    {
        // 対象勤怠への修正申請権限を確認する。
        $this->authorize('store', $attendance);

        // バリデーション済み入力で修正申請を作成する。
        $correction = $this->attendanceWorkflow->requestCorrection(
            $attendance,
            (int) $request->user()->id,
            $request->validated(),
        );

        // 申請詳細（承認待ち）画面へ遷移させる。
        return redirect()->route('stamp_correction_request.detail', $correction);
    }

    public function list(Request $request)
    {
        // タブは pending/approved を受け取る（既定: pending）。
        $tab = $request->query('tab', 'pending');
        // ログインユーザー自身の申請一覧を取得する。
        $applications = AttendanceCorrection::with(['attendance.user'])
            ->where('request_user_id', $request->user()->id)
            ->forTab($tab)
            ->latest('created_at')
            ->get();

        // 一般ユーザー向け申請一覧を表示する。
        return view('applications_screen', [
            'headerVariant' => $this->headerVariant(),
            'applications' => $applications,
            'tab' => $tab,
            'isAdmin' => false,
            'detailRouteName' => 'stamp_correction_request.detail',
        ]);
    }

    public function detail(AttendanceCorrection $attendanceCorrection)
    {
        // 申請詳細の閲覧権限を確認する。
        $this->authorize('view', $attendanceCorrection);
        // 詳細表示データを組み立てる。
        $detail = $this->buildDetailFromCorrection($attendanceCorrection);
        // 承認済みかどうかで編集可否を決める。
        $isApproved = $attendanceCorrection->approval_status_code === ApprovalStatusCode::APPROVED;
        // 詳細フォーム用の表示データを組み立てる。
        $detailFields = $this->buildAttendanceDetailFields(
            $detail['attendance'],
            $detail['breaks'],
            $detail['break'],
            ! $isApproved,
            ! $isApproved
        );

        // 状態に応じて編集可否を切り替えて詳細を表示する。
        return view('attendance_detail_screen', [
            'headerVariant' => $this->headerVariant(),
            ...$detail,
            'detailFields' => $detailFields,
            'readonly' => ! $isApproved,
            'plainReadonly' => ! $isApproved,
            'formAction' => $isApproved ? route('attendance.request', $detail['attendance']) : null,
            'formMethod' => 'PUT',
            'submitLabel' => $isApproved ? '修正' : null,
            'statusMessage' => $isApproved ? null : '※承認待ちのため、修正はできません。',
        ]);
    }
}
