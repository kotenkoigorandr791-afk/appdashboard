# Graph Report - recipes  (2026-06-16)

## Corpus Check
- 24 files · ~49,782 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 113 nodes · 216 edges · 23 communities (22 shown, 1 thin omitted)
- Extraction: 86% EXTRACTED · 14% INFERRED · 0% AMBIGUOUS · INFERRED: 31 edges (avg confidence: 0.8)
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
- [[_COMMUNITY_Community 8|Community 8]]
- [[_COMMUNITY_Community 11|Community 11]]

## God Nodes (most connected - your core abstractions)
1. `recipes_ai_create_product()` - 14 edges
2. `recipes_ai_run_agent()` - 14 edges
3. `recipes_normalize_product_name()` - 11 edges
4. `build_alpha_mask()` - 9 edges
5. `recipes_ai_search_products_sqlite()` - 8 edges
6. `recipes_load_product_row()` - 7 edges
7. `recipes_ai_product_image_query()` - 7 edges
8. `recipes_ai_gather_image_options()` - 7 edges
9. `recipes_ai_search_image()` - 7 edges
10. `recipes_image_save_optimized_blob()` - 6 edges

## Surprising Connections (you probably didn't know these)
- `recipes_resolve_session_role()` --calls--> `getDB()`  [EXTRACTED]
  fetch_image_url.php → core/db.php
- `recipes_resolve_session_role()` --calls--> `getUserRoleByEmail()`  [EXTRACTED]
  fetch_image_url.php → core/session.php
- `recipes_ai_normalize_draft()` --calls--> `recipes_load_product_row()`  [INFERRED]
  core/ai.php → core/image.php
- `recipes_ai_match_draft_products()` --calls--> `recipes_load_product_row()`  [INFERRED]
  core/ai_agent.php → core/image.php
- `recipes_backfill_product_previews()` --calls--> `recipes_ensure_products_image_preview_column()`  [INFERRED]
  core/image.php → core/schema.php

## Communities (23 total, 1 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.16
Nodes (21): recipes_ai_apply_product_decisions(), recipes_ai_create_product(), recipes_ai_download_image(), recipes_ai_execute_tool(), recipes_ai_make_placeholder_image(), recipes_ai_product_image_query(), recipes_ai_save_draft(), recipes_ai_search_image() (+13 more)

### Community 1 - "Community 1"
Cohesion: 0.29
Nodes (11): border_connected_mask(), build_alpha_mask(), downscale_for_processing(), fill_holes(), keep_largest_center_components(), keep_main_components(), mahalanobis_map(), main() (+3 more)

### Community 2 - "Community 2"
Cohesion: 0.35
Nodes (13): recipes_ai_agent_system_prompt(), recipes_ai_agent_tools(), recipes_ai_build_steps_from_ingredients_json(), recipes_ai_find_product_exact(), recipes_ai_match_draft_products(), recipes_ai_run_agent(), recipes_ai_run_agent_stream(), recipes_ai_search_products_ranked() (+5 more)

### Community 3 - "Community 3"
Cohesion: 0.29
Nodes (7): recipes_ai_build_steps_from_json(), recipes_ai_image_reject_homonym(), recipes_ai_image_reject_non_food(), recipes_ai_image_score_candidate(), recipes_ai_json_out(), recipes_ai_json_to_draft(), recipes_ai_require_role()

### Community 4 - "Community 4"
Cohesion: 0.39
Nodes (5): getDB(), getUserRoleByEmail(), getDB(), getUserRoleByEmail(), recipes_resolve_session_role()

### Community 5 - "Community 5"
Cohesion: 0.39
Nodes (8): recipes_ai_commons_food_candidates(), recipes_ai_gather_image_options(), recipes_ai_product_en_fallback(), recipes_ai_recipe_image_searches(), recipes_ai_wiki_fetch_pages(), recipes_ai_wiki_http_get(), recipes_ai_wikimedia_candidates(), recipes_ai_wikimedia_product_candidates()

### Community 6 - "Community 6"
Cohesion: 0.33
Nodes (7): recipes_ai_chat(), recipes_ai_extract_json(), recipes_ai_generate_json(), recipes_ai_json_system_prompt(), recipes_ai_message_text(), recipes_ai_search_product_image(), recipes_ai_verify_image_choice()

### Community 7 - "Community 7"
Cohesion: 0.5
Nodes (4): recipes_ai_find_product_by_name(), recipes_ai_normalize_draft(), recipes_ai_search_recipe_image(), recipes_ai_search_wikimedia_image()

### Community 8 - "Community 8"
Cohesion: 0.83
Nodes (3): migrateJSONToSQLite(), recipes_ensure_products_image_preview_column(), recipes_ensure_steps_table()

## Knowledge Gaps
- **1 isolated node(s):** `graphify`
  These have ≤1 connection - possible missing edges or undocumented components.
- **1 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `recipes_ai_create_product()` connect `Community 0` to `Community 3`, `Community 6`, `Community 7`?**
  _High betweenness centrality (0.072) - this node is a cross-community bridge._
- **Why does `recipes_ai_run_agent()` connect `Community 2` to `Community 6`, `Community 7`?**
  _High betweenness centrality (0.051) - this node is a cross-community bridge._
- **Why does `recipes_normalize_product_name()` connect `Community 2` to `Community 0`, `Community 3`, `Community 5`?**
  _High betweenness centrality (0.051) - this node is a cross-community bridge._
- **Are the 7 inferred relationships involving `recipes_ai_create_product()` (e.g. with `translate_text()` and `recipes_product_name_exists()`) actually correct?**
  _`recipes_ai_create_product()` has 7 INFERRED edges - model-reasoned connections that need verification._
- **Are the 5 inferred relationships involving `recipes_ai_run_agent()` (e.g. with `recipes_ai_chat()` and `recipes_normalize_product_name()`) actually correct?**
  _`recipes_ai_run_agent()` has 5 INFERRED edges - model-reasoned connections that need verification._
- **Are the 9 inferred relationships involving `recipes_normalize_product_name()` (e.g. with `recipes_ai_search_products_sqlite()` and `recipes_ai_image_reject_homonym()`) actually correct?**
  _`recipes_normalize_product_name()` has 9 INFERRED edges - model-reasoned connections that need verification._
- **Are the 2 inferred relationships involving `recipes_ai_search_products_sqlite()` (e.g. with `recipes_normalize_product_name()` and `recipes_ai_search_products_ranked()`) actually correct?**
  _`recipes_ai_search_products_sqlite()` has 2 INFERRED edges - model-reasoned connections that need verification._