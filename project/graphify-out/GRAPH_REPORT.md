# Graph Report - project  (2026-07-04)

## Corpus Check
- 17 files · ~102,575 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 127 nodes · 212 edges · 28 communities (24 shown, 4 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 8 edges (avg confidence: 0.8)
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
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 12|Community 12]]
- [[_COMMUNITY_Community 13|Community 13]]
- [[_COMMUNITY_Community 14|Community 14]]
- [[_COMMUNITY_Community 15|Community 15]]
- [[_COMMUNITY_Community 18|Community 18]]

## God Nodes (most connected - your core abstractions)
1. `my_htmlspecialchars()` - 9 edges
2. `oauth_route_authorize()` - 9 edges
3. `oauth_route_token()` - 8 edges
4. `oauth_dispatch()` - 8 edges
5. `buildAuthEmailBody()` - 6 edges
6. `sendTelegramMessage()` - 6 edges
7. `collectUserStorageTargets()` - 6 edges
8. `oauth_issue_tokens()` - 6 edges
9. `ap_normalize_i18n_lang()` - 5 edges
10. `sendVerificationEmail()` - 5 edges

## Surprising Connections (you probably didn't know these)
- `ap_load_i18n_lang()` --calls--> `ap_normalize_i18n_lang()`  [INFERRED]
  core.php → i18n_helpers.php
- `oauth_redirect_with_error()` --calls--> `my_htmlspecialchars()`  [INFERRED]
  oauth.php → core.php
- `oauth_route_authorize()` --calls--> `clientAuthSessionOk()`  [INFERRED]
  oauth.php → core.php
- `oauth_route_omniroute_bridge()` --calls--> `clientAuthSessionOk()`  [INFERRED]
  oauth.php → core.php
- `oauth_route_authorize()` --calls--> `setPostLoginRedirect()`  [INFERRED]
  oauth.php → core.php

## Communities (28 total, 4 thin omitted)

### Community 1 - "Community 1"
Cohesion: 0.22
Nodes (16): apEmailLogoHtml(), apEmailPublicBaseUrl(), buildAuthEmailBody(), buildTelegramInlineKeyboard(), getTelegramDomain(), loadAuthorizedChats(), my_htmlspecialchars(), notifyTelegramEmailVerified() (+8 more)

### Community 2 - "Community 2"
Cohesion: 0.27
Nodes (7): apSecConvertImageToWebp(), apSecDetectMimeFromFile(), apSecExpectedHosts(), apSecRandomFilename(), apSecRequireSameOrigin(), apSecSameOrigin(), apSecStoreSupportChatMedia()

### Community 3 - "Community 3"
Cohesion: 0.36
Nodes (8): ap_i18n_lang_exists(), ap_i18n_lang_exists_in_dir(), ap_i18n_supported_langs(), ap_load_i18n_all_langs(), ap_load_i18n_file(), ap_load_i18n_from_dir(), ap_load_i18n_merged_lang(), ap_normalize_i18n_lang()

### Community 4 - "Community 4"
Cohesion: 0.29
Nodes (8): collectUserStorageTargets(), countUserFiles(), deleteDirectory(), deleteUserFolders(), getUserFolderVariants(), scanFilesRecursive(), scanUserFiles(), scanUserFilesProgressive()

### Community 5 - "Community 5"
Cohesion: 0.32
Nodes (8): oauth_build_user_claims(), oauth_create_refresh_token(), oauth_dispatch(), oauth_issue_tokens(), oauth_issuer(), oauth_route_discovery(), oauth_route_userinfo(), oauth_sanitize_username()

### Community 6 - "Community 6"
Cohesion: 0.52
Nodes (6): oauth_base64url_encode(), oauth_consume_auth_code(), oauth_jwk(), oauth_route_jwks(), oauth_signing_keys(), oauth_verify_access_token()

### Community 7 - "Community 7"
Cohesion: 0.33
Nodes (6): ensureOAuthProviderSchema(), oauth_create_auth_code(), oauth_redirect_uri_allowed(), oauth_redirect_with_error(), oauth_route_authorize(), oauth_upsert_client()

### Community 8 - "Community 8"
Cohesion: 0.4
Nodes (5): clientAuthSessionOk(), oauth_consume_bridge_token(), oauth_create_bridge_token(), oauth_ensure_bridge_schema(), oauth_route_bridge()

### Community 9 - "Community 9"
Cohesion: 0.5
Nodes (4): createClientBrowserAuthRequest(), ensureClientBrowserAuthSchema(), getClientBrowserAuthRequest(), purgeClientBrowserAuthRequests()

### Community 10 - "Community 10"
Cohesion: 0.5
Nodes (4): oauth_consume_refresh_token(), oauth_extract_client_credentials(), oauth_find_client(), oauth_route_token()

### Community 11 - "Community 11"
Cohesion: 0.5
Nodes (4): setPostLoginRedirect(), oauth_route_omniroute_bridge(), omniroute_issue_auth_token(), omniroute_jwt_secret()

### Community 12 - "Community 12"
Cohesion: 0.83
Nodes (3): applyViewport(), boot(), isTouchMobile()

### Community 13 - "Community 13"
Cohesion: 0.67
Nodes (3): collectServerSecurityMeta(), failedAuthNotifyPayload(), parseJsonClientContext()

## Knowledge Gaps
- **1 isolated node(s):** `graphify`
  These have ≤1 connection - possible missing edges or undocumented components.
- **4 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `clientAuthSessionOk()` connect `Community 8` to `Community 0`, `Community 11`, `Community 7`?**
  _High betweenness centrality (0.120) - this node is a cross-community bridge._
- **Why does `oauth_route_authorize()` connect `Community 7` to `Community 5`, `Community 6`, `Community 8`, `Community 10`, `Community 11`?**
  _High betweenness centrality (0.089) - this node is a cross-community bridge._
- **Why does `ap_i18n_lang_exists()` connect `Community 3` to `Community 0`?**
  _High betweenness centrality (0.085) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `oauth_route_authorize()` (e.g. with `clientAuthSessionOk()` and `setPostLoginRedirect()`) actually correct?**
  _`oauth_route_authorize()` has 2 INFERRED edges - model-reasoned connections that need verification._
- **What connects `graphify` to the rest of the system?**
  _1 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.12 - nodes in this community are weakly interconnected._