# 処理フロー索引（Flow Index）

このファイルは「どのルートが、どのクラスを、どの順で通るか」を最短で追うための索引。

## 読み方

1. `Route`（入口）を特定する
2. `Controller` の該当メソッドへ移動する
3. そのメソッドが呼ぶ `Workflow / Query` を順に追う
4. 最後に `View` または `Redirect` を確認する
5. `主な副作用` で更新対象テーブルを確認する

---

## 1. 認証・入口

### 1-1. ルート `/`
- Route: `GET /`
- 実行順:
1. `Route::redirect('/', '/attendance')`
2. `/attendance` へリダイレクト
- 主な副作用: なし

### 1-2. 管理者ログイン
- Route: `GET /admin/login`（`admin.login`）
- Route: `POST /admin/login`（`admin.login.store`）
- 実行順:
1. `AuthenticatedSessionController@create/store`（Fortify）
2. Fortify認証ロジック（`FortifyServiceProvider`）
3. 成功時は `LoginResponse` の遷移先へ
- 主な副作用: `sessions`

### 1-3. ログアウト
- Route: `POST /logout`（`logout`）
- 実行順:
1. `AuthenticatedSessionController@destroy`
2. セッション破棄
3. リダイレクト
- 主な副作用: `sessions`

---

## 2. 一般ユーザー勤怠（打刻）

### 2-1. 打刻トップ表示
- Route: `GET /attendance`（`attendance.index`）
- 実行順:
1. `AttendanceScreenController@index`
2. 当日 `Attendance` を取得
3. `view('attendance', [...])`
- 主な副作用: なし

### 2-2. 出勤打刻
- Route: `POST /attendance/check_in`（`attendance.check_in`）
- 実行順:
1. `AttendanceScreenController@checkIn`
2. `AttendanceWorkflow::stamp(userId, 'check_in')`
3. `redirect()->route('attendance.index')`
- 主な副作用: `attendances`（当日レコード作成/更新）

### 2-3. 退勤打刻
- Route: `POST /attendance/check_out`（`attendance.check_out`）
- 実行順:
1. `AttendanceScreenController@checkOut`
2. `AttendanceWorkflow::stamp(userId, 'check_out')`
3. `redirect()->route('attendance.index')`
- 主な副作用: `attendance_breaks`（未終了休憩の終了）, `attendances`（退勤時刻・状態更新）

### 2-4. 休憩入
- Route: `POST /attendance/break_in`（`attendance.break_in`）
- 実行順:
1. `AttendanceScreenController@breakIn`
2. `AttendanceWorkflow::stamp(userId, 'break_in')`
3. `redirect()->route('attendance.index')`
- 主な副作用: `attendance_breaks`（休憩開始作成）, `attendances`（状態更新）

### 2-5. 休憩戻
- Route: `POST /attendance/break_out`（`attendance.break_out`）
- 実行順:
1. `AttendanceScreenController@breakOut`
2. `AttendanceWorkflow::stamp(userId, 'break_out')`
3. `redirect()->route('attendance.index')`
- 主な副作用: `attendance_breaks`（未終了休憩の終了）, `attendances`（状態更新）

---

## 3. 一般ユーザー勤怠（一覧・詳細・修正申請）

### 3-1. 勤怠一覧（月次）
- Route: `GET /attendance/list`（`attendance.list`）
- 実行順:
1. `AttendanceScreenController@userList`
2. `AttendanceListQuery::forUserMonth(... includeMissingDates=true)`
3. `BuildsAttendanceViewData::buildMonthNavigation(...)`
4. `view('attendance_records_screen', ...)`
5. Blade内 `@livewire('attendance-records-screen', ...)`
- 主な副作用: なし

### 3-2. 勤怠詳細（ID指定）
- Route: `GET /attendance/detail/{attendance}`（`attendance.detail`）
- 実行順:
1. `AttendanceScreenController@userDetail`
2. Policy `AttendancePolicy@view`
3. `renderAttendanceDetail(...)`
4. `view('attendance_detail_screen', ...)`
5. Blade内 `@livewire('attendance-detail-screen', ...)`
- 主な副作用: なし

### 3-3. 勤怠詳細（日付指定）
- Route: `GET /attendance/detail/date/{date}`（`attendance.detail.date`）
- 実行順:
1. `AttendanceScreenController@showUserDetailByDate`
2. 日付を検証
3. `Attendance::firstOrCreate(...)`
4. `AttendanceScreenController@userDetail` へ委譲
- 主な副作用: `attendances`（対象日の新規作成の可能性）

### 3-4. 修正申請の作成
- Route: `PUT /attendance/request/{attendance}`（`attendance.request`）
- 実行順:
1. `CorrectionRequestController@store`
2. `AttendanceCorrectionRequest` で入力検証
   - `break_start_at.*` / `break_end_at.*` は勤務時間内チェック
   - 休憩2以降は、それ以前の休憩と重複不可
3. Policy `AttendancePolicy@store`
4. `AttendanceWorkflow::requestCorrection(...)`
5. 申請詳細へリダイレクト
- 主な副作用: `attendance_corrections`, `break_corrections`

### 3-5. 修正申請の詳細表示
- Route: `GET /stamp_correction_request/{attendanceCorrection}`（`stamp_correction_request.detail`）
- 実行順:
1. `CorrectionRequestController@userDetail`
2. Policy `AttendanceCorrectionPolicy@view`
3. `BuildsAttendanceViewData::buildDetailFromCorrection(...)`
4. `view('attendance_detail_screen', ...)`
5. Blade内 `@livewire('attendance-detail-screen', ...)`
- 主な副作用: なし

### 3-6. 申請一覧（共通入口）
- Route: `GET /stamp_correction_request/list`（`stamp_correction_requests.list`）
- 実行順:
1. `CorrectionRequestController@list`
2. `ActorContext::fromUser(...)` で user/admin 判定
3. 一般ユーザーかつ未認証メールなら `verification.notice` へ
4. コンテキスト別に申請一覧を取得
5. `view('applications_screen', ...)`
6. Blade内 `@livewire('applications-screen', ...)`
- 主な副作用: なし

---

## 4. 管理者勤怠

### 4-1. 管理者ダッシュボード（日次一覧）
- Route: `GET /admin/attendance/list`（`admin.dashboard`）
- 実行順:
1. `AttendanceScreenController@adminDashboard`
2. `AttendanceListQuery::forDay(...)`
3. `BuildsAttendanceViewData::buildDayNavigation(...)`
4. `view('attendance_records_screen', ...)`
- 主な副作用: なし

### 4-2. 管理者の勤怠詳細
- Route: `GET /admin/attendance/{attendance}`（`admin.attendance.detail`）
- 実行順:
1. `AttendanceScreenController@adminDetail`
2. Policy `AttendancePolicy@view`
3. `renderAttendanceDetail(...)`
4. `view('attendance_detail_screen', ...)`
- 主な副作用: なし

### 4-3. 管理者の勤怠更新
- Route: `PUT /admin/attendance/{attendance}`（`admin.attendance.update`）
- 実行順:
1. `AttendanceScreenController@adminUpdate`
2. `AttendanceCorrectionRequest` で入力検証
   - `break_start_at.*` / `break_end_at.*` は勤務時間内チェック
   - 休憩2以降は、それ以前の休憩と重複不可
3. Policy `AttendancePolicy@update`
4. `AttendanceWorkflow::updateAttendance(...)`
5. 詳細へリダイレクト
- 主な副作用: `attendances`, `attendance_breaks`

### 4-4. スタッフ一覧
- Route: `GET /admin/staff/list`（`admin.staff_list`）
- 実行順:
1. `AttendanceScreenController@adminStaff`
2. 一般ユーザーのみ取得
3. `view('admin_attendance_staff', ...)`
- 主な副作用: なし

### 4-5. スタッフ月次勤怠一覧
- Route: `GET /admin/attendance/staff/{user}`（`admin.attendance.list`）
- 実行順:
1. `AttendanceScreenController@adminStaffList`
2. `AttendanceListQuery::forUserMonth(... withUser=true)`
3. `BuildsAttendanceViewData::buildMonthNavigation(...)`
4. `view('attendance_records_screen', ...)`
- 主な副作用: なし

### 4-6. スタッフ月次勤怠CSV
- Route: `GET /admin/attendance/staff/{user}/csv`（`admin.attendance.list.csv`）
- 実行順:
1. `AttendanceScreenController@adminStaffCsv`
2. `AttendanceListQuery::forUserMonth(...)`
3. `response()->streamDownload(...)`
- 主な副作用: なし（レスポンス出力のみ）

---

## 5. 管理者申請承認

### 5-1. 申請一覧（管理者）
- Route: `GET /stamp_correction_request/list`（管理者でアクセス時）
- 実行順:
1. `CorrectionRequestController@list`
2. `ActorContext::ADMIN` として全申請を取得
3. `view('applications_screen', ...)`
- 主な副作用: なし

### 5-2. 申請承認画面
- Route: `GET /stamp_correction_request/approve/{attendanceCorrection}`（`admin.attendance.approve`）
- 実行順:
1. `CorrectionRequestController@adminDetail`
2. Policy `AttendanceCorrectionPolicy@view`
3. `buildDetailFromCorrection(...)`
4. `view('attendance_detail_screen', 承認ボタン表示)`
- 主な副作用: なし

### 5-3. 申請承認実行
- Route: `PUT /stamp_correction_request/approve/{attendanceCorrection}`（`admin.attendance.approve.update`）
- 実行順:
1. `CorrectionRequestController@approve`
2. Policy `AttendanceCorrectionPolicy@approve`
3. `AttendanceWorkflow::approveCorrection(...)`
4. 同詳細へリダイレクト
- 主な副作用: `attendances`, `attendance_breaks`, `attendance_corrections`

---

## 6. 主要クラスの役割（現行）

- `AttendanceScreenController`
  - 一般/管理者の勤怠画面制御を統合
- `CorrectionRequestController`
  - 一般/管理者の申請画面制御を統合
- `ActorContext`
  - user/admin 文脈判定
- `AttendanceWorkflow`
  - 打刻 (`stamp`)
  - 修正申請作成 (`requestCorrection`)
  - 承認反映 (`approveCorrection`)
  - 管理者更新 (`updateAttendance`)
- `AttendanceListQuery`
  - 日次/ユーザー月次勤怠取得
  - 計算済み時間を付与
- `Livewire`
  - `AttendanceRecordsScreen`
  - `AttendanceDetailScreen`
  - `ApplicationsScreen`

---

## 7. 追跡時の最短コマンド

```bash
# ルート定義確認
nl -ba routes/web.php

# 主要統合Controllerの呼び出し追跡
rg -n "AttendanceScreenController|CorrectionRequestController|AttendanceWorkflow|AttendanceListQuery" app routes

# Livewire画面コンポーネント確認
rg -n "attendance-records-screen|attendance-detail-screen|applications-screen" resources/views app/Livewire
```
