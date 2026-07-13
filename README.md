# Casa Paraiso

Casa Paraiso is a Laravel application for spa booking and operations, including immediate customer appointment confirmation, staff scheduling, transactions, rule-based RFM promotions, feedback sentiment, and management reports.

## Documentation

Use these documents as the source of truth instead of repeating project rules here:

- [MVP scope](docs/MVP_SCOPE.md)
- [Technology stack](docs/TECH_STACK.md)
- [Database design](docs/DATABASE_DESIGN.md)
- [Screen flow](docs/SCREEN_FLOW.md)
- [Implementation roadmap](docs/IMPLEMENTATION_ROADMAP.md)
- [Brand and UI guide](docs/BRAND_UI_GUIDE.md)
- [Docker and deployment workflow](docs/DOCKER_WORKFLOW.md)
- [Google authentication setup](docs/GOOGLE_AUTH_SETUP.md)
- [Local demo credentials](docs/LOCAL_DEMO_CREDENTIALS.md)
- [Completed CRUD remediation record](docs/CRUD_REMEDIATION_RECORD.md)

## Development

The primary local environment is Laravel Sail through direct `docker compose` commands on Windows. Follow the [Docker workflow](docs/DOCKER_WORKFLOW.md) for setup, daily commands, verification, XAMPP fallback, and the Hostinger production boundary.

## Database Safety

Do not migrate, seed, import, truncate, repair, or otherwise change database schema or data without explicit approval for the specific operation. Read-only inspection is allowed. The Docker workflow documents the required backup, verification, and recovery sequence.
