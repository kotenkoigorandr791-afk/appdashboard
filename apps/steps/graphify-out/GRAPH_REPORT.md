# Graph Report - steps  (2026-06-25)

## Corpus Check
- 27 files · ~180,351 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 167 nodes · 329 edges · 23 communities (22 shown, 1 thin omitted)
- Extraction: 90% EXTRACTED · 10% INFERRED · 0% AMBIGUOUS · INFERRED: 32 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 9|Community 9]]

## God Nodes (most connected - your core abstractions)
1. `run_job()` - 37 edges
2. `main()` - 19 edges
3. `run_job()` - 13 edges
4. `main()` - 12 edges
5. `run_job()` - 12 edges
6. `_write_state()` - 10 edges
7. `clean_ocr()` - 9 edges
8. `find_steps_data()` - 9 edges
9. `find_date_data()` - 9 edges
10. `_frame_mega_and_pairs()` - 8 edges

## Surprising Connections (you probably didn't know these)
- `getUserPhoto()` --calls--> `getDB()`  [INFERRED]
  core/user_helpers.php → auth_common.php
- `getUserRoleByEmail()` --calls--> `getDB()`  [INFERRED]
  core/user_helpers.php → auth_common.php
- `isUserRoot()` --calls--> `getDB()`  [INFERRED]
  core/user_helpers.php → auth_common.php
- `getUserDB()` --calls--> `get_current_user_email()`  [INFERRED]
  core/db_helpers.php → auth_common.php
- `getUserDB()` --calls--> `fit_takeout_ensure_schema()`  [INFERRED]
  core/db_helpers.php → utils/google_fit_takeout/fit_takeout_state.php

## Communities (23 total, 1 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.16
Nodes (30): main(), main(), _build_merged_list(), _build_ocr_preview(), _clamp_crop(), clean_ocr(), _cy_month_token(), extract_chart_pairs_from_ocr() (+22 more)

### Community 1 - "Community 1"
Cohesion: 0.19
Nodes (26): _atomic_write(), _cleanup_job_dir(), _is_cancelled(), main(), run_job(), _write_pending_video_confirm(), _write_state(), _write_state_job() (+18 more)

### Community 2 - "Community 2"
Cohesion: 0.12
Nodes (12): app_steps_migrate_sqlite_file(), getUserDB(), fit_takeout_ensure_schema(), fit_takeout_reset(), fit_takeout_save(), video_job_db_connect(), video_job_ensure_schema(), video_job_reset() (+4 more)

### Community 3 - "Community 3"
Cohesion: 0.22
Nodes (19): _cleanup_job_dir(), _count_conflict_days_sqlite(), _import_steps_sqlite(), _is_cancelled(), _load_day_steps_snapshot(), main(), _nanos_to_day_utc(), _norm_key() (+11 more)

### Community 4 - "Community 4"
Cohesion: 0.18
Nodes (8): csvDateToISO(), isoToCSVDate(), buildStepsWhere(), exportStepsToCSV(), importStepsFromCSVContent(), loadStepsPage(), normalizeStepsDatesTable(), normalizeStoredDate()

### Community 5 - "Community 5"
Cohesion: 0.19
Nodes (7): analize_chunk_promote_to_job(), analize_chunk_session_paths(), analize_extract_sample_frame(), analize_find_ffmpeg(), analize_find_python(), analize_python_cv2_ok(), analize_spawn_worker()

### Community 6 - "Community 6"
Cohesion: 0.29
Nodes (9): checkDirectory(), getUserPhoto(), getUserRoleByEmail(), isUserRoot(), logError(), emailToFolderName(), get_current_user_email(), get_current_user_folder() (+1 more)

### Community 7 - "Community 7"
Cohesion: 0.32
Nodes (4): fit_chunk_move_to_path(), fit_chunk_session_paths(), fit_find_python(), fit_spawn_worker()

## Knowledge Gaps
- **1 isolated node(s):** `graphify`
  These have ≤1 connection - possible missing edges or undocumented components.
- **1 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `run_job()` connect `Community 1` to `Community 0`, `Community 3`?**
  _High betweenness centrality (0.079) - this node is a cross-community bridge._
- **Why does `main()` connect `Community 0` to `Community 1`, `Community 3`?**
  _High betweenness centrality (0.031) - this node is a cross-community bridge._
- **Why does `getUserDB()` connect `Community 2` to `Community 6`?**
  _High betweenness centrality (0.026) - this node is a cross-community bridge._
- **Are the 11 inferred relationships involving `main()` (e.g. with `clean_ocr()` and `find_steps_data()`) actually correct?**
  _`main()` has 11 INFERRED edges - model-reasoned connections that need verification._
- **Are the 11 inferred relationships involving `main()` (e.g. with `_ffprobe_video_size()` and `_ffprobe_duration()`) actually correct?**
  _`main()` has 11 INFERRED edges - model-reasoned connections that need verification._
- **What connects `graphify` to the rest of the system?**
  _1 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.12 - nodes in this community are weakly interconnected._