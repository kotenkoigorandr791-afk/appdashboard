# Add OAuth Provider (Google, GitHub, Microsoft, etc.)

Enable "Login with Google/GitHub/..." for your users.

---

## Prerequisites

- Admin access to AppDashboard
- OAuth app credentials from provider (Client ID + Secret)
- Domain with HTTPS (required by all major providers)

---

## General Steps (All Providers)

1. **Create OAuth App** at provider's developer console
2. **Configure Redirect URI**: `https://your-domain.com/oauth/callback`
3. **Copy Client ID + Secret** to AppDashboard
4. **Test** with incognito browser window

---

## Provider-Specific Setup

### Google

1. [Google Cloud Console](https://console.cloud.google.com/) → **APIs & Services** → **Credentials**
2. **Create Credentials** → **OAuth client ID**
   - Application type: **Web application**
   - Name: `AppDashboard`
   - **Authorized redirect URIs**: `https://your-domain.com/oauth/callback`
3. Copy **Client ID** & **Client Secret**
4. In AppDashboard: Admin → Config → **OAuth Providers** → **Add**
   ```
   Name: google
   Client ID: [from Google]
   Client Secret: [from Google]
   Scopes: openid email profile
   Auth URL: https://accounts.google.com/o/oauth2/v2/auth
   Token URL: https://oauth2.googleapis.com/token
   Userinfo URL: https://www.googleapis.com/oauth2/v3/userinfo
   ```

### GitHub

1. [GitHub Settings](https://github.com/settings/developers) → **OAuth Apps** → **New OAuth App**
   - Application name: `AppDashboard`
   - Homepage URL: `https://your-domain.com`
   - **Authorization callback URL**: `https://your-domain.com/oauth/callback`
2. Copy **Client ID** & **Client Secret**
3. AppDashboard:
   ```
   Name: github
   Client ID: [from GitHub]
   Client Secret: [from GitHub]
   Scopes: read:user user:email
   Auth URL: https://github.com/login/oauth/authorize
   Token URL: https://github.com/login/oauth/access_token
   Userinfo URL: https://api.github.com/user
   ```

### Microsoft (Azure AD / Personal)

1. [Azure Portal](https://portal.azure.com/) → **Microsoft Entra ID** → **App registrations** → **New registration**
   - Name: `AppDashboard`
   - Supported account types: **Accounts in any organizational directory + personal Microsoft accounts**
   - **Redirect URI**: `https://your-domain.com/oauth/callback` (Web)
2. **Certificates & secrets** → **New client secret**
3. **API permissions** → **Add** → **Microsoft Graph** → **Delegated** → `openid`, `profile`, `email`, `User.Read`
4. **Overview** → Copy **Application (client) ID** & **Directory (tenant) ID**
5. AppDashboard:
   ```
   Name: microsoft
   Client ID: [Application ID]
   Client Secret: [Client Secret]
   Scopes: openid profile email User.Read
   Auth URL: https://login.microsoftonline.com/[Tenant ID]/oauth2/v2.0/authorize
   Token URL: https://login.microsoftonline.com/[Tenant ID]/oauth2/v2.0/token
   Userinfo URL: https://graph.microsoft.com/oidc/userinfo
   ```

### GitLab

1. GitLab → **Settings** → **Applications** → **New application**
   - Name: `AppDashboard`
   - **Redirect URI**: `https://your-domain.com/oauth/callback`
   - Scopes: `openid`, `profile`, `email`, `read_user`
2. AppDashboard:
   ```
   Name: gitlab
   Client ID: [Application ID]
   Client Secret: [Secret]
   Scopes: openid profile email read_user
   Auth URL: https://gitlab.com/oauth/authorize
   Token URL: https://gitlab.com/oauth/token
   Userinfo URL: https://gitlab.com/oauth/userinfo
   ```

### Generic OIDC (Keycloak, Authentik, etc.)

1. Create client in your IdP:
   - Redirect URI: `https://your-domain.com/oauth/callback`
   - Scopes: `openid profile email`
   - Access type: Confidential
2. AppDashboard:
   ```
   Name: my-oidc
   Client ID: [from IdP]
   Client Secret: [from IdP]
   Scopes: openid profile email
   Auth URL: https://idp.example.com/realms/myrealm/protocol/openid-connect/auth
   Token URL: https://idp.example.com/realms/myrealm/protocol/openid-connect/token
   Userinfo URL: https://idp.example.com/realms/myrealm/protocol/openid-connect/userinfo
   ```

---

## Configure in AppDashboard

1. Admin → **Configuration** → **OAuth Providers**
2. Click **Add Provider**
3. Fill form (see provider-specific values above)
4. **Test Configuration** button → Should show green checkmarks
5. **Save**

### Enable for Users

- Login page automatically shows buttons for all enabled providers
- Users can link multiple providers to one account (Profile → Linked Accounts)

---

## Auto-Provisioning

When user logs in via OAuth **first time**:

1. AppDashboard checks if `email` exists in DB
2. **Exists** → Links OAuth identity to existing account
3. **Not exists** → Creates new user with:
   - Email from OAuth
   - Name from OAuth
   - Role: `user`
   - App Access: **None** (admin must grant)
   - Email verified: **Yes** (trusted provider)

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| "Redirect URI mismatch" | Must match **exactly** (trailing slash, http vs https) |
| "Invalid client" | Client ID/Secret wrong; check copy-paste; regenerate secret |
| "Access blocked" (Google) | OAuth consent screen not published; add test users; or verify domain |
| "Email not verified" | Provider didn't return `email_verified: true`; check scopes include `email` |
| User created but no app access | Admin → Users → Edit → Grant app access |
| "State mismatch" / CSRF error | Clear cookies; try incognito; check `SESSION_DOMAIN` in `.env` |
| Multiple accounts for same email | User registered with email/password first → OAuth links automatically |
| Provider not showing on login | Check **Enabled** toggle in Admin → OAuth Providers |

---

## Advanced: Custom Button Text/Icon

Admin → Config → OAuth Providers → Edit provider:

| Field | Example |
|-------|---------|
| Display Name | `Sign in with Company SSO` |
| Icon URL | `https://your-domain.com/icons/company-sso.svg` |
| Button Color | `#4285F4` (Google blue) |
| Button Text Color | `#FFFFFF` |

---

## Security Notes

- **Never commit** Client Secrets to git
- Store in `.env` or Admin UI (encrypted in DB)
- Rotate secrets periodically (every 90 days)
- Limit scopes to minimum needed (`openid profile email`)
- Monitor audit logs for failed OAuth attempts