# Google AI Tools

Google Calendar and Gmail integration for the Laravel AI SDK. Part of the **OpenCompany** AI tool ecosystem — an open platform where AI agents collaborate with humans to run organizations.

## Integrations

This package registers **two separate integrations**, each appearing independently on the integrations page:

### Google Calendar (3 tools)

| Tool | Type | Description |
|------|------|-------------|
| `google_calendar_list` | read | List calendars and search/list events |
| `google_calendar_event` | write | Create, update, delete, or quick-add calendar events |
| `google_calendar_freebusy` | read | Check free/busy status across calendars |

### Gmail (4 tools)

| Tool | Type | Description |
|------|------|-------------|
| `gmail_search` | read | Search and list email messages |
| `gmail_read` | read | Get full email content |
| `gmail_send` | write | Send emails or create/send drafts |
| `gmail_manage` | write | Labels, read/unread, trash, and archive |

## Installation

```bash
composer require opencompanyapp/ai-tool-google
```

The service provider is auto-discovered by Laravel.

## Configuration

Each integration requires its own Google Cloud OAuth credentials:

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `client_id` | text | Yes | OAuth 2.0 Client ID from Google Cloud Console |
| `client_secret` | secret | Yes | OAuth 2.0 Client Secret |
| `access_token` | oauth | Yes | Connected via OAuth flow |

### Setup

1. Create a project in [Google Cloud Console](https://console.cloud.google.com/)
2. Enable the **Google Calendar API** and/or **Gmail API**
3. Create OAuth 2.0 credentials (Web application type)
4. Add the redirect URI: `{your-domain}/api/integrations/google/oauth/callback`
5. Enter Client ID and Secret in Settings → Integrations
6. Click "Connect" to authorize via OAuth

## Quick Start

```php
use Laravel\Ai\Facades\Ai;

$response = Ai::tools(['google_calendar_list', 'google_calendar_event'])
    ->prompt('List my calendars, then create a meeting called "Team Standup" tomorrow at 10am.');
```

## Dependencies

| Package | Version |
|---------|---------|
| PHP | ^8.2 |
| opencompanyapp/integration-core | ^2.0 |
| laravel/ai | ^0.1 |

## License

MIT
