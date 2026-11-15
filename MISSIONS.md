# Leonida Progress

The repository previously contained ten general checklist rows and four fictional web-demo story missions. It had no dedicated backend mission catalog. The existing checklist remains separate and unchanged.

`2026_11_15_500000_create_missions_and_progress` adds `missions` and `mission_progress` and seeds 20 explicitly fictional demo entries. It does not modify existing checklist, account, or gameplay records. Mission names, rewards, objectives, and coordinates are sample content; they are not an official GTA VI mission list. The current Rockstar hub was reviewed on 15 November 2026 and did not publish a mission-title catalog: https://www.rockstargames.com/VI.

- `GET /api/missions`: public catalog and completion summary. An optional Sanctum bearer token adds only that user's saved progress.
- `POST /api/missions/{id}/progress`: authenticated save. Send `status` (`not_started`, `in_progress`, `completed`) and/or `progress_current` (integer from zero through the mission's total). Completion fills the target; resetting clears it. Counts alone infer the state.
- Each catalog entry has `map_x` and `map_y`, percentages on the same square basemap used by the native Maps screen, plus `is_dummy`, `district`, `category`, and `reward`.
- Progress belongs to the authenticated user, has one row per mission, and is removed by the user foreign-key cascade when an account is deleted.

Deploy the additive migration with the API before distributing the native feature. The migration has been exercised only against an isolated SQLite in-memory database during development; production deployment is a separate step. Test with `DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=` and `php artisan test --filter=MissionProgressTest`.
