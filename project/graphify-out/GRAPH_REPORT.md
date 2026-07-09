# Graph Report - project  (2026-07-09)

## Corpus Check
- 17 files · ~102,972 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 142 nodes · 264 edges · 30 communities (24 shown, 6 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 8 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `612292f0`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

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
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 13|Community 13]]
- [[_COMMUNITY_Community 14|Community 14]]
- [[_COMMUNITY_Community 15|Community 15]]

## God Nodes (most connected - your core abstractions)
1. `oauth_route_authorize()` - 10 edges
2. `oauth_route_token()` - 9 edges
3. `oauth_dispatch()` - 9 edges
4. `my_htmlspecialchars()` - 9 edges
5. `oauth_issue_tokens()` - 7 edges
6. `oauth_build_user_claims()` - 6 edges
7. `oauth_route_omniroute_bridge()` - 6 edges
8. `buildAuthEmailBody()` - 6 edges
9. `sendTelegramMessage()` - 6 edges
10. `collectUserStorageTargets()` - 6 edges

## Surprising Connections (you probably didn't know these)
- `oauth_redirect_with_error()` --calls--> `my_htmlspecialchars()`  [INFERRED]
  /mnt/server/project/oauth.php → core.php
- `ap_load_i18n_lang()` --calls--> `ap_normalize_i18n_lang()`  [INFERRED]
  core.php → /mnt/server/project/i18n_helpers.php
- `oauth_route_authorize()` --calls--> `clientAuthSessionOk()`  [INFERRED]
  /mnt/server/project/oauth.php → core.php
- `oauth_route_authorize()` --calls--> `setPostLoginRedirect()`  [INFERRED]
  /mnt/server/project/oauth.php → core.php
- `oauth_route_bridge()` --calls--> `clientAuthSessionOk()`  [INFERRED]
  /mnt/server/project/oauth.php → core.php

## Communities (30 total, 6 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.18
Nodes (33): clientAuthSessionOk(), setPostLoginRedirect(), ensureOAuthProviderSchema(), oauth_base64url_encode(), oauth_build_user_claims(), oauth_consume_auth_code(), oauth_consume_bridge_token(), oauth_consume_refresh_token() (+25 more)

### Community 2 - "Community 2"
Cohesion: 0.22
Nodes (16): apEmailLogoHtml(), apEmailPublicBaseUrl(), buildAuthEmailBody(), buildTelegramInlineKeyboard(), getTelegramDomain(), loadAuthorizedChats(), my_htmlspecialchars(), notifyTelegramEmailVerified() (+8 more)

### Community 3 - "Community 3"
Cohesion: 0.27
Nodes (7): apSecConvertImageToWebp(), apSecDetectMimeFromFile(), apSecExpectedHosts(), apSecRandomFilename(), apSecRequireSameOrigin(), apSecSameOrigin(), apSecStoreSupportChatMedia()

### Community 4 - "Community 4"
Cohesion: 0.44
Nodes (9): ap_i18n_lang_exists(), ap_i18n_lang_exists_in_dir(), ap_i18n_supported_langs(), ap_i18n_t(), ap_load_i18n_all_langs(), ap_load_i18n_file(), ap_load_i18n_from_dir(), ap_load_i18n_merged_lang() (+1 more)

### Community 5 - "Community 5"
Cohesion: 0.29
Nodes (8): collectUserStorageTargets(), countUserFiles(), deleteDirectory(), deleteUserFolders(), getUserFolderVariants(), scanFilesRecursive(), scanUserFiles(), scanUserFilesProgressive()

### Community 6 - "Community 6"
Cohesion: 0.8
Nodes (3): applyViewport(), boot(), isTouchMobile()

### Community 7 - "Community 7"
Cohesion: 0.5
Nodes (4): createClientBrowserAuthRequest(), ensureClientBrowserAuthSchema(), getClientBrowserAuthRequest(), purgeClientBrowserAuthRequests()

### Community 9 - "Community 9"
Cohesion: 0.67
Nodes (3): newsEnsureColumn(), newsEnsureDb(), newsTableColumns()

### Community 10 - "Community 10"
Cohesion: 0.67
Nodes (3): collectServerSecurityMeta(), failedAuthNotifyPayload(), parseJsonClientContext()

## Knowledge Gaps
- **6 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `clientAuthSessionOk()` connect `Community 0` to `Community 1`?**
  _High betweenness centrality (0.108) - this node is a cross-community bridge._
- **Why does `ap_i18n_lang_exists()` connect `Community 4` to `Community 1`?**
  _High betweenness centrality (0.077) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `oauth_route_authorize()` (e.g. with `clientAuthSessionOk()` and `setPostLoginRedirect()`) actually correct?**
  _`oauth_route_authorize()` has 2 INFERRED edges - model-reasoned connections that need verification._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.12 - nodes in this community are weakly interconnected._