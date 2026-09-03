<?php

declare(strict_types=1);

namespace Tests\Validation;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ract\Validation\ValidationException;
use Ract\Validation\Validator;

final class ValidatorTest extends TestCase
{
    public function testItReturnsOnlyValidatedNestedData(): void
    {
        $validated = Validator::make([
            'user' => [
                'name' => 'Ada',
                'email' => 'ada@example.test',
                'admin' => true,
            ],
            'age' => '15',
            'nickname' => null,
        ], [
            'user.name' => 'required|string|min:2',
            'user.email' => ['required', 'email'],
            'age' => 'required|integer|between:10,20',
            'nickname' => 'nullable|string',
        ])->validate();

        self::assertSame([
            'user' => [
                'name' => 'Ada',
                'email' => 'ada@example.test',
            ],
            'age' => '15',
            'nickname' => null,
        ], $validated);
    }

    public function testItReportsRuleErrorsWithCustomMessages(): void
    {
        $validator = Validator::make([
            'email' => 'not-an-email',
            'password' => 'secret',
            'password_confirmation' => 'different',
        ], [
            'email' => 'required|email',
            'password' => 'required|confirmed',
        ], [
            'email.email' => 'Use a real email for :attribute.',
        ]);

        self::assertTrue($validator->fails());
        self::assertSame('Use a real email for email.', $validator->errors()['email'][0]);
        self::assertSame('The password confirmation does not match.', $validator->errors()['password'][0]);
    }

    public function testNullableDoesNotBypassRequiredOrPresentRules(): void
    {
        $validator = Validator::make([], [
            'name' => 'required|nullable|string',
            'token' => 'present|nullable|string',
        ]);

        self::assertSame(['name', 'token'], array_keys($validator->errors()));
    }

    public function testNumericRulesUseNumericBoundsForHttpStringInput(): void
    {
        self::assertTrue(Validator::make(
            ['quantity' => '15'],
            ['quantity' => 'numeric|min:10|max:20'],
        )->passes());
        self::assertTrue(Validator::make(
            ['quantity' => '100'],
            ['quantity' => 'numeric|max:20'],
        )->fails());
    }

    public function testInRulesRejectNonScalarInputWithoutRaisingRuntimeErrors(): void
    {
        $validator = Validator::make(
            ['role' => ['admin']],
            ['role' => 'in:admin,editor'],
        );

        self::assertTrue($validator->fails());
    }

    public function testInvalidRulesAreRejectedEvenWhenAnOptionalFieldIsAbsent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported validation rule');

        Validator::make([], ['optional' => 'string|typo'])->passes();
    }

    public function testValidateThrowsAnExceptionContainingAllErrors(): void
    {
        try {
            Validator::make([], ['title' => 'required'])->validate();
            self::fail('Invalid data should throw a validation exception.');
        } catch (ValidationException $exception) {
            self::assertSame(422, $exception->statusCode());
            self::assertArrayHasKey('title', $exception->errors());
        }
    }
}
