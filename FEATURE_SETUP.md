# Account safety and Bleeter activity

The native app, API and web changes are a coordinated release. Native sign-in now sends explicit legal acceptance; the API records version `2026-11-15` and rejects missing acceptance.

## Deployment

- Deploy the API with migration `2026_11_15_400000_add_account_safety_and_notifications`. It adds consent fields, reports, push registrations, unique likes and activity records. It also widens news comment article identifiers. Existing data is preserved.
- Deploy the web build. Public documents are `/gta-vi-ai-companion/eula.html` and `/gta-vi-ai-companion/privacy.html`; their stylesheet and logo are copied by Vite. Native sign-in and Settings link to these URLs.
- Build a new native binary with the `expo-notifications` config plugin. Ensure APNs credentials for iOS and FCM credentials for Android are configured in the existing EAS project. An OTA update alone cannot add missing native push capabilities.
- Support mail uses `SUPPORT_EMAIL=gta@qla.dev`, `SUPPORT_FROM_ADDRESS=gta@qla.dev`, and `SUPPORT_MAILER=sendmail` by default. On a host using SMTP instead, select `SUPPORT_MAILER=smtp` and configure the usual `MAIL_*` settings. The report endpoint deliberately bypasses the existing `MAIL_MAILER=log` setting. Delivery failures return an error and keep the ticket for a retry; they are never presented as successful delivery.

## Behavior

- Settings → Account contains the Notifications switch. Consent is requested only when enabling push. Opting out unregisters the device even when offline and retries server cleanup later. Signing out detaches the device registration.
- Bleeter's separate heart action opens a paginated, account-scoped list of likes and comments. Its in-app history works with push disabled. Self-actions do not notify; repeated likes do not inflate counters or send repeated notifications.
- Reports are available in Settings, on bleets, and in direct conversations. Authenticated reports are rate limited and use an idempotency key to avoid duplicate emails after retries.
- Account deletion requires an explicit destructive confirmation. Server deletion removes the account and related records through foreign-key cascades, revokes every app token, and removes associated chat uploads. An owned crew and its shared conversations are deleted too. Local settings and external provider accounts are separate, as described in the privacy page.
- Apple/Google provider authorization grants and provider-held records are not automatically revoked or erased by app-account deletion. The project currently signs in using identity tokens and does not store provider refresh tokens. Provider disconnection is described in the privacy page; implementing automatic Apple grant revocation requires the corresponding Apple authorization-code exchange credentials.

## Verification

- Native: `npm run typecheck`, `npm run test:feeds`, `npm run test:safety`.
- Web: `npm run build`, `npm run lint`.
- API: set `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`, then run `php vendor/phpunit/phpunit/phpunit --filter 'AccountSafetyTest|BleeterPaginationTest'`. Tests use an in-memory database, in-memory mail transport and fake external HTTP; they do not delete production accounts, send email or deliver real pushes.
- On a release device, enable notifications, like/comment from another account, open the heart history, tap a delivered push, then disable notifications and repeat. Verify the configured server mail transport using the contact form after deployment.
