# ATRILAK POS — Controlled File Deployment Plan

สถานะเอกสารนี้คือ **เตรียมพร้อมเท่านั้น ยังไม่ Deploy**. การสร้าง manifest อ่านไฟล์ Source/Production เพื่อบันทึกขนาดและ SHA-256 แต่ไม่คัดลอก ไม่แก้ `.env` และไม่สั่ง migration หรือ cache command ใน Production.

## Release boundary

- Source: `C:\laragon\www\atrilak-pos`
- Production: `C:\laragon\www\atrilak-pos-production`
- Baseline used for runtime review: `df073d08ee708521670a9619a5679307d34d6429`
- The manifest release boundary is the reviewed Source `HEAD` at generation time; it includes the
  original feature release `8c5accdd713ce06241a66395943336a0f1326443` and this round's
  test-environment guard change.
- Production has no `.git`; deployment must therefore use the reviewed allowlist and manifests, not a repository merge.

## Files eligible for deployment

The allowlist must be produced from the reviewed runtime diff and then inspected before manifest generation:

```powershell
$sourceRoot = 'C:\laragon\www\atrilak-pos'
$productionRoot = 'C:\laragon\www\atrilak-pos-production'
$outputRoot = 'C:\laragon\www\atrilak-pos\docs\deploy\manifests\20260804'
$backupRoot = 'C:\laragon\www\atrilak-pos-production-recovery\controlled-deployment-20260804'
$baseCommit = 'df073d08ee708521670a9619a5679307d34d6429'
$releaseCommit = git -C $sourceRoot rev-parse HEAD
$approvedFiles = @(
    git -C $sourceRoot diff --name-only --diff-filter=ACMRT $baseCommit $releaseCommit |
        Where-Object { $_ -match '^(app/|database/migrations/|public/css/|public/js/modules/|resources/views/|routes/)' } |
        Where-Object { $_ -notmatch '(^|/)(tests|storage/app/public/products|vendor|node_modules|\.git|\.env)(/|$)' } |
        Where-Object { $_ -notmatch '(^|/)(design-qa\.md|docs/superpowers/plans/2026-07-29-final-pos-hold-bill\.md)$' }
)

& "$sourceRoot\scripts\prepare-controlled-deployment.ps1" `
    -SourceRoot $sourceRoot `
    -ProductionRoot $productionRoot `
    -OutputRoot $outputRoot `
    -BackupRoot $backupRoot `
    -ApprovedFiles $approvedFiles
```

The generated Source manifest records, for each relative runtime path, the Source absolute path, size, SHA-256, file type, and `deploy=yes`. The Production predeploy manifest records the target path, existence, existing size, existing SHA-256, file type, and the proposed backup destination.

Review rules:

- Include only application runtime files required by the already reviewed feature work: Reset, POS V3 image/fulfillment, delivery-note print, and cost adjustment.
- Exclude `.env` and every credential/config secret.
- Exclude `.git`, `node_modules`, `vendor`, tests, test fixtures, local uploads, backup data, `design-qa.md`, and the old Hold Bill plan.
- Do not include the Source-only Test DB seed data or `storage/app/public/products/browser-test-real-image.svg`.
- If a manifest contains an unexpected file, stop and correct the allowlist before any later approval step.

## Reversible backup boundary

The proposed backup directory is outside the Production application root:

`C:\laragon\www\atrilak-pos-production-recovery\controlled-deployment-20260804`

The manifest records a per-file backup destination. Creating that directory, copying files, changing Production `.env`, running migrations, clearing Production cache, or restarting services are separate actions and are intentionally not performed in this task.

## Future execution gates

1. Owner reviews the two manifests and confirms every target file and backup destination.
2. Confirm a current Production database backup and a tested recovery path; do not use Source/test data as a Production backup.
3. Create the backup outside the application root and hash the backed-up files.
4. Copy only the approved files, verify target SHA-256 against the Source manifest, and stop on the first mismatch.
5. Run any required Production migration only after a separate Owner approval and an explicitly verified Production connection.
6. Perform the controlled Browser/Print smoke checks and record the result.
7. If verification fails, restore only the backed-up approved files and record the rollback result.

No step after the manifest generation is authorized by this task.
