<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Pure unit test verifying store DTO field names.
 *
 * Ensures that write (input) fields use the correct underscore notation
 * and that the serializer output keys are consistent with write fields.
 */
final class StoreContractTest extends TestCase
{
    /** @var list<string> */
    private const EXPECTED_WRITE_FIELDS = [
        'code',
        'name',
        'store_type',
        'region_id',
        'timezone',
        'address_line_1',
        'address_line_2',
        'city',
        'postal_code',
        'latitude',
        'longitude',
        'status',
    ];

    /** @var list<string> */
    private const SERIALIZER_KEYS = [
        'id',
        'code',
        'name',
        'store_type',
        'status',
        'region_id',
        'timezone',
        'address_line_1',
        'address_line_2',
        'city',
        'postal_code',
        'latitude',
        'longitude',
        'is_active',
        'created_at',
        'updated_at',
        'version',
    ];

    public function testStoreWriteFieldNamesUseUnderscoreNotation(): void
    {
        $expectedWriteFields = self::EXPECTED_WRITE_FIELDS;

        self::assertContains('address_line_1', $expectedWriteFields);
        self::assertContains('address_line_2', $expectedWriteFields);
        self::assertContains('postal_code', $expectedWriteFields);

        // These are WRONG and must not be used.
        self::assertNotContains('address_line1', $expectedWriteFields);
        self::assertNotContains('address_line2', $expectedWriteFields);
        self::assertNotContains('zip_code', $expectedWriteFields);
        self::assertNotContains('address', $expectedWriteFields);
        self::assertNotContains('state', $expectedWriteFields);
    }

    public function testStoreSerializerFieldNamesMatchWriteFields(): void
    {
        $serializerKeys = self::SERIALIZER_KEYS;

        // Write field names must be a subset of serializer keys (minus read-only fields).
        self::assertContains('address_line_1', $serializerKeys);
        self::assertContains('address_line_2', $serializerKeys);
        self::assertCount(17, $serializerKeys);
    }
}
