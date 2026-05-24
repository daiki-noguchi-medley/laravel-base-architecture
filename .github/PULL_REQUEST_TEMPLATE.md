# 目的
**必須**

# 実装の概要
**必須**

# チェック状況
**必須**

# レビューしてほしいところ

# 保留していること

# 関連

---

# DB マイグレーション
**マイグレーション (`src/database/migrations/` 以下) を追加 / 変更した PR では必須。それ以外は丸ごと削除して構わない。**

詳しい記載要件は [CLAUDE.md §9](../CLAUDE.md) と
[`.claude/agents/laravel-migration-pr-checker.md`](../.claude/agents/laravel-migration-pr-checker.md) を参照。

### 追加マイグレーションファイル
- `src/database/migrations/YYYY_MM_DD_HHMMSS_xxx.php`

### 実行される SQL
```sql
-- CREATE TABLE / ALTER TABLE / CREATE INDEX 等の実 SQL を貼る
-- (migration ファイルから読み解いて手書き、または `\d <table>` の出力を整形)
```

### EXPLAIN (主要クエリ)
影響を受ける SELECT (新規追加カラムを WHERE / ORDER BY に使うもの) について、
`docker compose exec -T db psql -U laravel -d laravel -c "EXPLAIN ANALYZE ..."` の出力を貼る:

```sql
EXPLAIN ANALYZE SELECT * FROM <table> WHERE ... ;
```
```
Index Scan using <index_name> on <table>  (cost=... rows=...) (actual time=... rows=...)
  ...
Planning Time: ... ms
Execution Time: ... ms
```

→ Index Scan を使えているか、Seq Scan に落ちていないか、想定通りのインデックスが効いているか、簡潔にコメント。

### down() の reversibility
- どの down() がどう書かれているか
- 安全に巻き戻せるか (外部キー / 参照整合性の心配がないか)

### 既存データへの影響
- 既存行へどう影響するか (NULL 埋め / バックフィル要否)
- ロック時間 / 本番運用時の注意 (大規模テーブルなら別途段階的 migration 設計が必要)
