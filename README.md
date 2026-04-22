# 勤怠管理アプリ（exam-kintai）

勤怠打刻、勤怠修正申請、管理者承認を行う Web アプリケーションです。

## 目次

- [機能](#機能)
- [セットアップ詳細](#セットアップ詳細)
- [テスト実行](#テスト実行)
- [使用技術（実行環境）](#使用技術実行環境)
- [各種 URL](#各種-url)
- [デモユーザー](#デモユーザー)
- [設計資料](#設計資料)
- [補足](#補足)

## 機能

- ユーザー登録 / ログイン / メール認証（Fortify）
- 管理者ログイン（`/admin/login`）
- 打刻（出勤・退勤・休憩入・休憩戻）
- 一般ユーザー勤怠一覧（月次）・勤怠詳細
- 勤怠修正申請（休憩行含む）
- 申請一覧（承認待ち / 承認済み）
- 管理者による申請承認
- 管理者による全体勤怠確認（日次）
- 管理者によるスタッフ別月次勤怠確認・CSV出力

## セットアップ詳細

### 1. 事前準備

- Docker Desktop
- GNU Make
- Mailtrap アカウント（SMTP 認証情報）

### 2. 初期起動（推奨）

```bash
git clone https://github.com/nekomajin-1017/exam-kintai.git
cd exam-kintai
make up
```

`make up` の実行内容:

1. `.env` が無ければ `.env.example` をコピー
2. `vendor/` が無ければ `composer install`
3. Sail コンテナ起動
4. `php artisan migrate:fresh --seed`

### 3. メール設定（認証メール送信用）

`.env` の Mailtrap 設定を更新してください。

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

設定反映:

```bash
./vendor/bin/sail artisan config:clear
```

## テスト実行

```bash
make test
```

または:

```bash
./vendor/bin/sail test
```

## 使用技術（実行環境）

- PHP: 8.3（`composer.json`）
- Laravel: 13.x
- Laravel Fortify: 1.36+
- Livewire: 4.2+
- MySQL: 8.4（Docker）
- phpMyAdmin: 5.2（Docker）
- Docker Compose / Laravel Sail

## 各種 URL

- アプリ: `http://localhost`（`/` は `/attendance` へリダイレクト）
- 一般ログイン: `http://localhost/login`
- 会員登録: `http://localhost/register`
- 管理者ログイン: `http://localhost/admin/login`
- 申請一覧: `http://localhost/stamp_correction_request/list`
- phpMyAdmin: `http://localhost:8080`
- Mailtrap Inbox: `https://mailtrap.io/inboxes`

## デモユーザー

`make up`（または `make fresh`）実行後に利用可能:

- 一般ユーザー: `user1@example.com` 〜 `user10@example.com`
- 管理者: `admin1@example.com` 〜 `admin3@example.com`
- 共通パスワード: `Coachtech777`

## 設計資料

- 画面/設計図: [`attendance.png`](attendance.png)
- テーブル： [`tables.png`](tables.png)
