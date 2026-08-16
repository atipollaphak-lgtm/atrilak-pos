<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerExternalReference;

class CustomerImportDuplicateService
{
    public function __construct(
        private CustomerImportPhoneNormalizer $phoneNormalizer,
    ) {}

    /**
     * Annotate parsed rows without changing existing Customer records.
     *
     * @return list<array<string, mixed>>
     */
    public function annotate(string $sourceSystem, array $rows): array
    {
        $rows = $this->annotateWorkbookExternalIds($rows);
        $rows = $this->annotateWorkbookPhones($rows);

        $externalIds = array_values(array_unique(array_filter(array_map(
            fn (array $row): string => trim((string) ($row['external_id'] ?? '')),
            $rows,
        ), static fn (string $externalId): bool => $externalId !== '')));

        $existingReferences = $externalIds === []
            ? []
            : CustomerExternalReference::query()
                ->where('source_system', $sourceSystem)
                ->whereIn('external_id', $externalIds)
                ->get(['external_id', 'customer_id'])
                ->keyBy('external_id');

        $customers = Customer::query()
            ->select(['id', 'name', 'phone', 'tax_number', 'branch_type', 'branch_number'])
            ->get();

        $phoneMatches = [];
        $taxMatches = [];
        $nameMatches = [];

        foreach ($customers as $customer) {
            $phone = $this->phoneNormalizer->normalize($customer->phone)->phone;
            if ($phone !== null) {
                $phoneMatches[$phone][] = $customer;
            }

            $nameKey = $this->nameKey($customer->name);
            if ($nameKey !== '') {
                $nameMatches[$nameKey][] = $customer;
            }

            $taxNumber = trim((string) ($customer->tax_number ?? ''));
            if ($taxNumber !== '') {
                $taxMatches[$taxNumber][] = $customer;
            }
        }

        foreach ($rows as $index => $row) {
            if (($row['status'] ?? null) === 'invalid') {
                continue;
            }

            $externalId = trim((string) ($row['external_id'] ?? ''));
            if ($externalId !== '' && isset($existingReferences[$externalId])) {
                $this->markDuplicate($rows[$index], 'พบ external ID จาก '.$sourceSystem.' ที่เคยนำเข้าแล้ว');
            }

            $phone = trim((string) ($row['phone'] ?? ''));
            if ($phone !== '' && isset($phoneMatches[$phone])) {
                $this->markDuplicate($rows[$index], 'พบ Customer เดิมจากเบอร์โทรที่ตรงกัน');
            }

            $taxNumber = trim((string) ($row['tax_number'] ?? ''));
            $nameKey = $this->nameKey($row['name'] ?? null);
            if ($taxNumber !== '' && $nameKey !== '' && isset($taxMatches[$taxNumber])) {
                foreach ($taxMatches[$taxNumber] as $customer) {
                    if ($this->nameKey($customer->name) !== $nameKey) {
                        continue;
                    }

                    if ($this->branchKey($customer->branch_type, $customer->branch_number)
                        === $this->branchKey($row['branch_type'] ?? null, $row['branch_number'] ?? null)) {
                        $this->markDuplicate($rows[$index], 'พบเลขผู้เสียภาษี ชื่อ และสาขาตรงกับ Customer เดิม');
                    } else {
                        $this->markReview($rows[$index], 'พบเลขผู้เสียภาษีและชื่อเดียวกันแต่เป็นคนละสาขา ต้องตรวจสอบ');
                    }

                    break;
                }
            }

            if (($rows[$index]['status'] ?? null) === 'ready' && $nameKey !== '' && isset($nameMatches[$nameKey])) {
                $this->addWarning($rows[$index], 'พบชื่อ Customer เดิม ควรตรวจสอบเพิ่มเติม');
            }
        }

        return array_values($rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function markDuplicate(array &$row, string $reason): void
    {
        $row['status'] = $row['status'] === 'invalid' ? 'invalid' : 'duplicate';
        $this->addReason($row, $reason);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function markReview(array &$row, string $reason): void
    {
        if (($row['status'] ?? null) === 'ready') {
            $row['status'] = 'review_required';
        }
        $this->addReason($row, $reason);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function addReason(array &$row, string $reason): void
    {
        $row['reasons'] = array_values(array_unique([
            ...($row['reasons'] ?? []),
            $reason,
        ]));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function addWarning(array &$row, string $warning): void
    {
        $row['warnings'] = array_values(array_unique([
            ...($row['warnings'] ?? []),
            $warning,
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function annotateWorkbookExternalIds(array $rows): array
    {
        $groups = [];
        foreach ($rows as $index => $row) {
            $externalId = trim((string) ($row['external_id'] ?? ''));
            if ($externalId !== '') {
                $groups[$externalId][] = $index;
            }
        }

        foreach ($groups as $externalId => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            foreach ($indexes as $index) {
                $rows[$index]['status'] = 'invalid';
                $this->addReason($rows[$index], 'external ID ซ้ำในไฟล์เดียวกัน: '.$externalId);
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function annotateWorkbookPhones(array $rows): array
    {
        $groups = [];
        foreach ($rows as $index => $row) {
            if (($row['status'] ?? null) === 'invalid') {
                continue;
            }

            $phone = trim((string) ($row['phone'] ?? ''));
            if ($phone !== '') {
                $groups[$phone][] = $index;
            }
        }

        foreach ($groups as $indexes) {
            foreach (array_slice($indexes, 1) as $index) {
                $this->markDuplicate($rows[$index], 'เบอร์โทรซ้ำในไฟล์เดียวกัน จึงข้ามรายการที่ซ้ำ');
            }
        }

        return $rows;
    }

    private function nameKey(mixed $value): string
    {
        $name = trim((string) ($value ?? ''));
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return mb_strtolower($name);
    }

    private function branchKey(mixed $branchType, mixed $branchNumber): string
    {
        $type = trim((string) ($branchType ?: config('customer_import.default_branch_type')));
        $number = trim((string) ($branchNumber ?? ''));

        return $type.'|'.$number;
    }
}
