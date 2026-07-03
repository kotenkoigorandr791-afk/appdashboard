# Graph Report - smart_home  (2026-05-25)

## Corpus Check
- 8 files · ~74,095 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 137 nodes · 321 edges · 17 communities (13 shown, 4 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 8|Community 8]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]

## God Nodes (most connected - your core abstractions)
1. `SmartHomeWsHandler` - 31 edges
2. `sh_db()` - 11 edges
3. `sh_ws_dispatch_control()` - 8 edges
4. `sh_json_field()` - 7 edges
5. `sh_push_local_control()` - 7 edges
6. `sh_push_local_gp()` - 7 edges
7. `sh_deliver_control()` - 7 edges
8. `smart_home_load_token_index()` - 7 edges
9. `sh_group_access()` - 6 edges
10. `sh_enqueue_control_request()` - 6 edges

## Surprising Connections (you probably didn't know these)
- `sh_store_dashboard_ws_token()` --calls--> `sh_db()`  [EXTRACTED]
  index.php → index.php  _Bridges community 1 → community 3_
- `sh_record_device_events()` --calls--> `sh_device_row_for_user()`  [EXTRACTED]
  index.php → index.php  _Bridges community 7 → community 1_
- `sh_record_device_events()` --calls--> `sh_json_field()`  [EXTRACTED]
  index.php → index.php  _Bridges community 5 → community 1_
- `sh_apply_device_definition()` --calls--> `sh_templates()`  [EXTRACTED]
  index.php → index.php  _Bridges community 9 → community 1_
- `sh_deliver_control()` --calls--> `sh_push_local_control()`  [EXTRACTED]
  index.php → index.php  _Bridges community 5 → community 3_

## Communities (17 total, 4 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.1
Nodes (11): sh_event_label(), sh_load_user_settings(), sh_settings_defaults(), sh_source_label(), sh_ws_decode_frame(), sh_ws_read_exact(), sh_ws_wait_for_data(), smart_home_create_device() (+3 more)

### Community 1 - "Community 1"
Cohesion: 0.14
Nodes (18): sh_auto_mqtt(), sh_can_edit_endpoints(), sh_control_queue_file(), sh_control_results_file(), sh_db(), sh_enqueue_control_request(), sh_ensure_column(), sh_init_tables() (+10 more)

### Community 2 - "Community 2"
Cohesion: 0.22
Nodes (13): smart_home_data_paths(), smart_home_ensure_dir(), smart_home_find_device(), smart_home_issue_dashboard_ws_token(), smart_home_load_json(), smart_home_load_token_index(), smart_home_materialize_files(), smart_home_remove_token_index() (+5 more)

### Community 3 - "Community 3"
Cohesion: 0.18
Nodes (12): sh_clear_control_queue_for_device(), sh_deliver_control(), sh_local_ws_host(), sh_local_ws_port(), sh_log_error(), sh_store_dashboard_ws_token(), sh_token(), sh_trigger_ws_dispatch() (+4 more)

### Community 5 - "Community 5"
Cohesion: 0.33
Nodes (10): sh_control_supports_local_gp(), sh_device_row_decode(), sh_fetch_my_devices(), sh_fetch_scoped_devices(), sh_gp_encode_value(), sh_gp_hardware_name(), sh_json_field(), sh_push_local_control() (+2 more)

### Community 7 - "Community 7"
Cohesion: 0.22
Nodes (9): sh_device_can_configure(), sh_device_row_for_user(), sh_group_access(), sh_group_member_role(), sh_group_row(), sh_resolve_home_context(), sh_user_can_assign_group(), sh_user_in_group() (+1 more)

## Knowledge Gaps
- **1 isolated node(s):** `graphify`
  These have ≤1 connection - possible missing edges or undocumented components.
- **4 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `sh_apply_device_definition()` connect `Community 9` to `Community 0`, `Community 1`, `Community 4`, `Community 8`?**
  _High betweenness centrality (0.341) - this node is a cross-community bridge._
- **Why does `SmartHomeWsHandler` connect `Community 8` to `Community 9`, `Community 4`, `Community 6`?**
  _High betweenness centrality (0.049) - this node is a cross-community bridge._
- **Why does `sh_db()` connect `Community 1` to `Community 0`, `Community 3`?**
  _High betweenness centrality (0.002) - this node is a cross-community bridge._
- **What connects `graphify` to the rest of the system?**
  _1 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.1 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.14 - nodes in this community are weakly interconnected._