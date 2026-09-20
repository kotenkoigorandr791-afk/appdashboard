# Public API Documentation

**Path:** `public-api/`  
**Type:** REST API  
**Description:** Public REST API endpoints for AppDashboard integrations.

---

## Overview

The Public API provides a standardized REST interface for interacting with AppDashboard services. All endpoints are prefixed with `/public-api/` and support JSON request/response format.

---

## File Structure

```
public-api/
├── index.php                    # API router
├── public_api.php               # API helper functions
├── public_api_auth.php          # API authentication
└── routes/                      # API route handlers
    ├── integrations.php         # Third-party integrations
    ├── oauth/                   # OAuth endpoints
    └── steps/                   # Steps counter API
```

## Authentication

### API Keys
- API keys are stored in PostgreSQL `client_auth_tokens` table
- 180-day expiry for generated tokens
- Header-based authentication: `Authorization: Bearer <token>`

### OAuth
- Standard OAuth 2.0 flow
- Client credentials and authorization code grants
- Token refresh supported

## API Endpoints

### System
- `GET /public-api/system/info` - System information
- `GET /public-api/system/status` - System status check

### User Management
- `GET /public-api/user` - Get current user
- `POST /public-api/user/login` - User login
- `POST /public-api/user/logout` - User logout
- `GET /public-api/user/profile` - Get user profile
- `PUT /public-api/user/profile` - Update user profile

### Applications
- `GET /public-api/apps` - List available applications
- `GET /public-api/apps/access_levels` - Get access levels (admin)
- `POST /public-api/apps/access_levels` - Set access level (admin)

### Steps Counter
- `GET /api/steps` - Get step history (alias: `/public-api/steps`)
- `POST /api/steps` - Submit steps
- `GET /api/steps/stats` - Get statistics
- `POST /api/steps/webhook` - Mobile webhook endpoint

### OAuth
- `GET /oauth` - OAuth authorization endpoint
- `POST /oauth/token` - Access token endpoint
- `GET /oauth/authorize` - Authorization code endpoint

### Integrations
- `GET /public-api/integrations` - List integrations
- `POST /public-api/integrations/{name}/sync` - Sync integration

## Response Format

All endpoints return JSON responses:

```json
{
    "status": "success",
    "data": { ... },
    "message": "Optional message"
}
```

Error responses:

```json
{
    "status": "error",
    "error": {
        "code": "ERROR_CODE",
        "message": "Error message"
    }
}
```

## Security Features

- Rate limiting on all endpoints
- CSRF protection
- Input validation and sanitization
- IP-based brute-force protection
- Session timeout enforcement (12 hours)

## Rate Limits

- Unauthenticated: 10 requests/minute per IP
- Authenticated: 60 requests/minute per user
- Admin endpoints: 30 requests/minute

## Versioning

Current API version: v1

## Internationalization

All API messages support the 6 interface languages based on user settings.
