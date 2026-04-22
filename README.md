# Laravel 開発テンプレート

`git clone` 後に `make up` だけで開発開始できることを目的にしたテンプレートです。

## 前提

- Docker Desktop
- GNU Make
- Mailtrap アカウント（SMTP 認証情報）

## クイックスタート

```bash
git clone <this-repo>
cd new-template
make doctor
make init
```

初回 `make init` の実行内容:

1. `.env` が無ければ `.env.example` をコピー
2. `vendor/` が無ければ `composer install` を実行
3. `sail up -d --build` でコンテナ起動
4. `APP_KEY` 生成
5. MySQL の起動完了待ち
6. DB migrate
7. `.env` の `MAIL_USERNAME` / `MAIL_PASSWORD` に Mailtrap のSMTP認証情報を設定

## 開発用コマンド

```bash
make doctor    # 前提チェック（Docker/ポート）
make init      # up + APP_KEY生成 + migrate
make reset     # コンテナ/volume完全削除
make down      # 停止
make restart   # 再起動
make logs      # ログ追従
make ps        # コンテナ状態
make shell     # appコンテナにシェル接続
make test      # テスト実行
make pint      # Lint/Format
```

## CI

GitHub Actions で `make init` のスモークテストを実行します。  
設定ファイル: `.github/workflows/template-smoke.yml`

## アクセス先

- Laravel: `http://localhost`
- phpMyAdmin: `http://localhost:${FORWARD_PHPMYADMIN_PORT:-8080}`

## DB 接続情報（初期値）

- Host: `mysql`
- Port: `3306`
- Database: `laravel`
- Username: `sail`
- Password: `password`

## メール送信（Mailtrap 前提）

- Mailer: `smtp`
- Host: `sandbox.smtp.mailtrap.io`
- Port: `2525`
- Scheme: `null`（STARTTLS 自動交渉）
# exam-kintai
