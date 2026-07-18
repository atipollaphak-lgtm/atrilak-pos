# First Owner Provisioning Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Provide a safe, command-only way to create the first Owner and remove public registration.

**Architecture:** `FirstOwnerProvisioningService` owns the transaction and role synchronization. The console command and Owner user-management controller orchestrate it; Form Requests validate HTTP input.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL, Spatie Laravel Permission, PHPUnit.

## Global Constraints

- Work only in Sprint 19A; do not modify backup, scheduler, timezone, production environment, migrations, or POS rules.
- Authoritative authorization remains `users.role`; Spatie role must be kept identical.
- Do not commit without explicit Owner instruction.

---

### Task 1: First Owner service and command

**Files:**
- Create: `app/Services/FirstOwnerProvisioningService.php`
- Create: `app/Console/Commands/CreateFirstOwnerCommand.php`
- Test: `tests/Feature/Console/CreateFirstOwnerCommandTest.php`

- [ ] Write failing tests for: successful interactive creation assigns `users.role=owner` and Spatie `Owner`; existing Owner returns failure with no writes; invalid name/email/password returns failure.
- [ ] Run `php artisan test tests/Feature/Console/CreateFirstOwnerCommandTest.php`; confirm RED.
- [ ] Implement `create(string $name, string $email, string $password): User` using `DB::transaction`, `User::query()->where('role', 'owner')->exists()`, validated input, `User::create`, and `syncRoles(['Owner'])`.
- [ ] Implement `atrilak:owner:create` with `ask`, `secret`, confirmation, `Validator::make`, exception handling, and `Command::FAILURE`.
- [ ] Re-run the scoped test until GREEN.

### Task 2: Role synchronization and route policy

**Files:**
- Create: `app/Services/UserRoleSynchronizationService.php`
- Modify: `app/Http/Controllers/UserController.php`
- Create: `app/Http/Requests/UpdateUserRoleRequest.php`
- Modify: `routes/auth.php`
- Modify: `config/adminlte.php`
- Test: `tests/Feature/Users/UserRoleSynchronizationTest.php`
- Test: `tests/Feature/Auth/RegistrationTest.php`

- [ ] Write failing tests: Owner role change updates both stores; GET/POST `/register` are unavailable; Technician Payment menu requires `manager` to match route policy.
- [ ] Run those tests; confirm RED.
- [ ] Add a transaction-backed `UserRoleSynchronizationService` that locks the user, updates `users.role`, and synchronizes the matching Spatie role; make `updateRole` call it. Remove registration routes and set `register_url` to `false`; change the two Technician Payment menu entries to `can => manager`.
- [ ] Re-run scoped tests until GREEN.

### Task 3: Settings validation

**Files:**
- Create: `app/Http/Requests/UpdateSettingRequest.php`
- Modify: `app/Http/Controllers/SettingController.php`
- Test: `tests/Feature/Settings/SettingUpdateTest.php`

- [ ] Write failing tests for rejected non-image uploads and invalid branch type, plus allowed image upload.
- [ ] Run the test; confirm RED.
- [ ] Implement bounded nullable text inputs, `branch_type` in `head_office,branch`, and `image`/mimes/max validation. Use `$request->validated()`.
- [ ] Re-run until GREEN.

### Task 4: Verification

- [ ] Run all new/updated Sprint 19A feature tests.
- [ ] Run `vendor/bin/pint --dirty` and `git diff --check`.
- [ ] Review the diff for scope and secrets; report test output and the uncommitted status.
