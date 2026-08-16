<?php

namespace Tests\Unit\Customers;

use App\Services\Customers\CustomerImportPhoneNormalizer;
use PHPUnit\Framework\TestCase;

class CustomerImportPhoneNormalizerTest extends TestCase
{
    public function test_blank_input_is_reported_as_blank(): void
    {
        $result = (new CustomerImportPhoneNormalizer)->normalize('   ');

        $this->assertNull($result->phone);
        $this->assertSame('blank', $result->status);
    }

    public function test_valid_ten_digit_thai_mobile_is_kept_as_a_string(): void
    {
        $result = (new CustomerImportPhoneNormalizer)->normalize('0805098556');

        $this->assertSame('0805098556', $result->phone);
        $this->assertSame('valid', $result->status);
        $this->assertSame([], $result->warnings);
    }

    public function test_numeric_missing_leading_zero_is_added_only_for_a_plausible_mobile(): void
    {
        $result = (new CustomerImportPhoneNormalizer)->normalize(805098556);

        $this->assertSame('0805098556', $result->phone);
        $this->assertSame('normalized', $result->status);
        $this->assertNotEmpty($result->warnings);
    }

    public function test_spaces_hyphens_parentheses_and_thai_digits_are_normalized_safely(): void
    {
        $result = (new CustomerImportPhoneNormalizer)->normalize('๐๘๐-๕๐๙ ๘๕๕๖');

        $this->assertSame('0805098556', $result->phone);
        $this->assertSame('valid', $result->status);
    }

    public function test_landline_like_number_is_preserved_but_requires_review(): void
    {
        $result = (new CustomerImportPhoneNormalizer)->normalize('02-123-4567');

        $this->assertSame('021234567', $result->phone);
        $this->assertSame('review_required', $result->status);
    }

    public function test_malformed_number_requires_review_without_becoming_a_phone(): void
    {
        $result = (new CustomerImportPhoneNormalizer)->normalize('โทรไม่ทราบ');

        $this->assertNull($result->phone);
        $this->assertSame('review_required', $result->status);
    }

    public function test_unusual_length_is_preserved_as_a_review_value(): void
    {
        $result = (new CustomerImportPhoneNormalizer)->normalize('12345');

        $this->assertSame('12345', $result->phone);
        $this->assertSame('review_required', $result->status);
    }

    public function test_embedded_mobile_numbers_are_detected_without_changing_the_source_text(): void
    {
        $name = 'เทือง คลองดู่ 0969534388';

        $phones = (new CustomerImportPhoneNormalizer)->findEmbeddedPhones($name);

        $this->assertSame(['0969534388'], $phones);
        $this->assertSame('เทือง คลองดู่ 0969534388', $name);
    }
}
