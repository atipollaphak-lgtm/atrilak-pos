<?php

namespace App\Console\Commands;

use App\Services\Reconciliation\DataReconciliationService;
use Illuminate\Console\Command;
use Throwable;

class DataReconciliationCommand extends Command
{
    protected $signature = 'atrilak:reconcile-data
        {--json : Output machine-readable JSON}
        {--sale-id= : Limit sale and commission checks to one sale}
        {--product-id= : Limit product checks and related sale checks to one product}';

    protected $description = 'Read-only reconciliation report for sales, stock, and commissions';

    public function handle(DataReconciliationService $service): int
    {
        try {
            $saleId = $this->positiveIntegerOption('sale-id');
            $productId = $this->positiveIntegerOption('product-id');
            $report = $service->reconcile($saleId, $productId);

            if ($this->option('json')) {
                $this->line(json_encode(
                    $report,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ));
            } else {
                $this->renderReport($report);
            }

            return $report['summary']['confirmed_anomalies'] > 0
                ? self::FAILURE
                : self::SUCCESS;
        } catch (Throwable $exception) {
            if ($this->option('json')) {
                $this->line(json_encode([
                    'error' => 'Data reconciliation failed.',
                    'type' => $exception::class,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->error('Data reconciliation failed: '.$exception->getMessage());
            }

            return 2;
        }
    }

    private function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
            throw new \InvalidArgumentException("--{$name} must be a positive integer.");
        }

        return (int) $value;
    }

    private function renderReport(array $report): void
    {
        $this->renderSection('Confirmed anomalies', $report['confirmed_anomalies']);
        $this->renderSection('Warnings', $report['warnings']);
        $this->renderSection('Informational findings', $report['informational_findings']);

        $summary = $report['summary'];
        $checked = $summary['checked'];

        $this->newLine();
        $this->info('Summary');
        $this->table(
            ['Confirmed', 'Warnings', 'Informational', 'Sales', 'Sale items', 'Products', 'Movements', 'Commissions'],
            [[
                $summary['confirmed_anomalies'],
                $summary['warnings'],
                $summary['informational_findings'],
                $checked['sales'],
                $checked['sale_items'],
                $checked['products'],
                $checked['stock_movements'],
                $checked['commissions'],
            ]]
        );
    }

    private function renderSection(string $title, array $findings): void
    {
        $this->newLine();
        $this->info($title.' ('.count($findings).')');

        if ($findings === []) {
            $this->line('None');

            return;
        }

        $this->table(
            ['Code', 'Entity', 'ID', 'Document', 'Actual', 'Expected', 'Difference', 'Details'],
            array_map(fn (array $finding): array => [
                $finding['code'],
                $finding['entity_type'],
                $finding['entity_id'],
                $finding['document_no'] ?? '-',
                $this->stringify($finding['actual']),
                $this->stringify($finding['expected']),
                $this->stringify($finding['difference']),
                $this->stringify($finding['details']),
            ], $findings)
        );
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        return (string) $value;
    }
}
