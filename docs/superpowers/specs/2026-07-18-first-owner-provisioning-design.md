# First Owner Provisioning Design

## Goal

Secure a fresh installation by creating exactly one Owner through an interactive Artisan command, while removing public registration.

## Scope

- Remove the web registration routes.
- Add `atrilak:owner:create`, which asks for name, email, hidden password, and hidden password confirmation.
- Validate name, normalized email uniqueness, and Laravel default password rules.
- Refuse before any write if an Owner already exists in `users.role`.
- In one database transaction, create `users.role = owner` and synchronize the Spatie `Owner` role.
- Synchronize role stores when the Owner changes a user's role from the existing user-management route.
- Validate settings fields and image uploads.
- Align Technician Payment menu visibility with its manager route authorization.

## Non-goals

- No web first-owner page, user-creation page, migration, backup/scheduler/configuration changes, or POS business-rule changes.

## Error handling

The command returns a non-zero exit code for validation, duplicate Owner, and unexpected errors. The service wraps all writes in `DB::transaction`; a failed role assignment rolls back user creation.

## Tests

Feature tests cover command success, duplicate Owner with no new writes, validation failure, role synchronization, closed registration, settings validation, and route/menu policy alignment.
