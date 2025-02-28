<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Tests\Core\FieldType;

use DateTimeImmutable;
use Ibexa\Contracts\Core\Persistence\Content\FieldValue;
use Ibexa\Contracts\Core\Persistence\User;
use Ibexa\Contracts\Core\Repository\PasswordHashService;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\Base\Exceptions\InvalidArgumentException;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Core\FieldType\User\Type;
use Ibexa\Core\FieldType\User\Type as UserType;
use Ibexa\Core\FieldType\User\Value as UserValue;
use Ibexa\Core\FieldType\ValidationError;
use Ibexa\Core\Persistence\Cache\UserHandler;
use Ibexa\Core\Repository\User\PasswordValidatorInterface;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinition as CoreFieldDefinition;
use Ibexa\Core\Repository\Values\User\User as RepositoryUser;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;

/**
 * @group fieldType
 * @group ezurl
 */
class UserTest extends FieldTypeTest
{
    private const UNSUPPORTED_HASH_TYPE = 0xDEADBEEF;

    /**
     * Returns the field type under test.
     *
     * This method is used by all test cases to retrieve the field type under
     * test. Just create the FieldType instance using mocks from the provided
     * get*Mock() methods and/or custom get*Mock() implementations. You MUST
     * NOT take care for test case wide caching of the field type, just return
     * a new instance from this method!
     *
     * @return \Ibexa\Core\FieldType\User\Type
     */
    protected function createFieldTypeUnderTest(): UserType
    {
        $fieldType = new UserType(
            $this->createMock(UserHandler::class),
            $this->createMock(PasswordHashService::class),
            $this->createMock(PasswordValidatorInterface::class)
        );
        $fieldType->setTransformationProcessor($this->getTransformationProcessorMock());

        return $fieldType;
    }

    /**
     * Returns the validator configuration schema expected from the field type.
     *
     * @return array
     */
    protected function getValidatorConfigurationSchemaExpectation()
    {
        return [
            'PasswordValueValidator' => [
                'requireAtLeastOneUpperCaseCharacter' => [
                    'type' => 'int',
                    'default' => 1,
                ],
                'requireAtLeastOneLowerCaseCharacter' => [
                    'type' => 'int',
                    'default' => 1,
                ],
                'requireAtLeastOneNumericCharacter' => [
                    'type' => 'int',
                    'default' => 1,
                ],
                'requireAtLeastOneNonAlphanumericCharacter' => [
                    'type' => 'int',
                    'default' => null,
                ],
                'requireNewPassword' => [
                    'type' => 'int',
                    'default' => null,
                ],
                'minLength' => [
                    'type' => 'int',
                    'default' => 10,
                ],
            ],
        ];
    }

    /**
     * Returns the settings schema expected from the field type.
     *
     * @return array
     */
    protected function getSettingsSchemaExpectation()
    {
        return [
            UserType::PASSWORD_TTL_SETTING => [
                'type' => 'int',
                'default' => null,
            ],
            UserType::PASSWORD_TTL_WARNING_SETTING => [
                'type' => 'int',
                'default' => null,
            ],
            UserType::REQUIRE_UNIQUE_EMAIL => [
                'type' => 'bool',
                'default' => true,
            ],
            UserType::USERNAME_PATTERN => [
                'type' => 'string',
                'default' => '^[^@]+$',
            ],
        ];
    }

    /**
     * Returns the empty value expected from the field type.
     */
    protected function getEmptyValueExpectation()
    {
        return new UserValue();
    }

    public function provideInvalidInputForAcceptValue()
    {
        return [
            [
                23,
                InvalidArgumentException::class,
            ],
        ];
    }

    /**
     * Data provider for valid input to acceptValue().
     *
     * Returns an array of data provider sets with 2 arguments: 1. The valid
     * input to acceptValue(), 2. The expected return value from acceptValue().
     * For example:
     *
     * <code>
     *  return array(
     *      array(
     *          null,
     *          null
     *      ),
     *      array(
     *          __FILE__,
     *          new BinaryFileValue( array(
     *              'path' => __FILE__,
     *              'fileName' => basename( __FILE__ ),
     *              'fileSize' => filesize( __FILE__ ),
     *              'downloadCount' => 0,
     *              'mimeType' => 'text/plain',
     *          ) )
     *      ),
     *      // ...
     *  );
     * </code>
     *
     * @return array
     */
    public function provideValidInputForAcceptValue()
    {
        return [
            [
                null,
                new UserValue(),
            ],
            [
                [],
                new UserValue([]),
            ],
            [
                new UserValue(['login' => 'sindelfingen']),
                new UserValue(['login' => 'sindelfingen']),
            ],
            [
                $userData = [
                    'hasStoredLogin' => true,
                    'contentId' => 23,
                    'login' => 'sindelfingen',
                    'email' => 'sindelfingen@example.com',
                    'passwordHash' => '1234567890abcdef',
                    'passwordHashType' => 'md5',
                    'enabled' => true,
                    'maxLogin' => 1000,
                ],
                new UserValue($userData),
            ],
            [
                new UserValue(
                    $userData = [
                        'hasStoredLogin' => true,
                        'contentId' => 23,
                        'login' => 'sindelfingen',
                        'email' => 'sindelfingen@example.com',
                        'passwordHash' => '1234567890abcdef',
                        'passwordHashType' => 'md5',
                        'enabled' => true,
                        'maxLogin' => 1000,
                    ]
                ),
                new UserValue($userData),
            ],
        ];
    }

    /**
     * Provide input for the toHash() method.
     *
     * Returns an array of data provider sets with 2 arguments: 1. The valid
     * input to toHash(), 2. The expected return value from toHash().
     * For example:
     *
     * <code>
     *  return array(
     *      array(
     *          null,
     *          null
     *      ),
     *      array(
     *          new BinaryFileValue(
     *              array(
     *                  'path' => 'some/file/here',
     *                  'fileName' => 'sindelfingen.jpg',
     *                  'fileSize' => 2342,
     *                  'downloadCount' => 0,
     *                  'mimeType' => 'image/jpeg',
     *              )
     *          ),
     *          array(
     *              'path' => 'some/file/here',
     *              'fileName' => 'sindelfingen.jpg',
     *              'fileSize' => 2342,
     *              'downloadCount' => 0,
     *              'mimeType' => 'image/jpeg',
     *          )
     *      ),
     *      // ...
     *  );
     * </code>
     *
     * @return array
     */
    public function provideInputForToHash()
    {
        $passwordUpdatedAt = new DateTimeImmutable();

        return [
            [
                new UserValue(),
                null,
            ],
            [
                new UserValue(
                    $userData = [
                        'hasStoredLogin' => true,
                        'contentId' => 23,
                        'login' => 'sindelfingen',
                        'email' => 'sindelfingen@example.com',
                        'passwordHash' => '1234567890abcdef',
                        'passwordHashType' => 'md5',
                        'passwordUpdatedAt' => $passwordUpdatedAt,
                        'enabled' => true,
                        'maxLogin' => 1000,
                        'plainPassword' => null,
                    ]
                ),
                [
                    'passwordUpdatedAt' => $passwordUpdatedAt->getTimestamp(),
                ] + $userData,
            ],
        ];
    }

    /**
     * Provide input to fromHash() method.
     *
     * Returns an array of data provider sets with 2 arguments: 1. The valid
     * input to fromHash(), 2. The expected return value from fromHash().
     * For example:
     *
     * <code>
     *  return array(
     *      array(
     *          null,
     *          null
     *      ),
     *      array(
     *          array(
     *              'path' => 'some/file/here',
     *              'fileName' => 'sindelfingen.jpg',
     *              'fileSize' => 2342,
     *              'downloadCount' => 0,
     *              'mimeType' => 'image/jpeg',
     *          ),
     *          new BinaryFileValue(
     *              array(
     *                  'path' => 'some/file/here',
     *                  'fileName' => 'sindelfingen.jpg',
     *                  'fileSize' => 2342,
     *                  'downloadCount' => 0,
     *                  'mimeType' => 'image/jpeg',
     *              )
     *          )
     *      ),
     *      // ...
     *  );
     * </code>
     *
     * @return array
     */
    public function provideInputForFromHash()
    {
        return [
            [
                null,
                new UserValue(),
            ],
            [
                $userData = [
                    'hasStoredLogin' => true,
                    'contentId' => 23,
                    'login' => 'sindelfingen',
                    'email' => 'sindelfingen@example.com',
                    'passwordHash' => '1234567890abcdef',
                    'passwordHashType' => 'md5',
                    'passwordUpdatedAt' => 1567071092,
                    'enabled' => true,
                    'maxLogin' => 1000,
                ],
                new UserValue([
                    'passwordUpdatedAt' => new DateTimeImmutable('@1567071092'),
                ] + $userData),
            ],
        ];
    }

    /**
     * Returns empty data set. Validation tests were moved to testValidate method.
     *
     * @return array
     */
    public function provideValidDataForValidate(): array
    {
        return [];
    }

    /**
     * Returns empty data set. Validation tests were moved to testValidate method.
     *
     * @see testValidate
     * @see providerForTestValidate
     *
     * @return array
     */
    public function provideInvalidDataForValidate(): array
    {
        return [];
    }

    /**
     * @covers \Ibexa\Core\FieldType\User\Type::validate
     *
     * @dataProvider providerForTestValidate
     *
     * @param \Ibexa\Core\FieldType\User\Value $userValue
     * @param array $expectedValidationErrors
     * @param callable|null $loadByLoginBehaviorCallback
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException
     */
    public function testValidate(
        UserValue $userValue,
        array $expectedValidationErrors,
        ?callable $loadByLoginBehaviorCallback
    ): void {
        $userHandlerMock = $this->createMock(UserHandler::class);

        if (null !== $loadByLoginBehaviorCallback) {
            $loadByLoginBehaviorCallback(
                $userHandlerMock
                    ->expects($this->once())
                    ->method('loadByLogin')
                    ->with($userValue->login)
            );
        }

        $userType = new UserType(
            $userHandlerMock,
            $this->createMock(PasswordHashService::class),
            $this->createMock(PasswordValidatorInterface::class)
        );

        $fieldSettings = [
            Type::USERNAME_PATTERN => '.*',
            Type::REQUIRE_UNIQUE_EMAIL => false,
        ];

        $fieldDefinitionMock = $this->createMock(FieldDefinition::class);
        $fieldDefinitionMock->method('__get')->with('fieldSettings')->willReturn($fieldSettings);
        $fieldDefinitionMock->method('getFieldSettings')->willReturn($fieldSettings);

        $validationErrors = $userType->validate($fieldDefinitionMock, $userValue);

        self::assertEquals($expectedValidationErrors, $validationErrors);
    }

    public function testInvalidLoginFormat(): void
    {
        $validateUserValue = new UserValue([
            'hasStoredLogin' => false,
            'contentId' => 46,
            'login' => 'validate@user',
            'email' => 'example@test.ibexa.co',
            'passwordHash' => '1234567890abcdef',
            'passwordHashType' => 'md5',
            'enabled' => true,
            'maxLogin' => 1000,
            'plainPassword' => 'testPassword',
        ]);

        $userHandlerMock = $this->createMock(UserHandler::class);

        $userHandlerMock
            ->expects($this->once())
            ->method('loadByLogin')
            ->with($validateUserValue->login)
            ->willThrowException(new NotFoundException('', ''));

        $userType = new UserType(
            $userHandlerMock,
            $this->createMock(PasswordHashService::class),
            $this->createMock(PasswordValidatorInterface::class)
        );

        $fieldSettings = [
            UserType::REQUIRE_UNIQUE_EMAIL => false,
            UserType::USERNAME_PATTERN => '^[^@]+$',
        ];

        $fieldDefinition = new CoreFieldDefinition(['fieldSettings' => $fieldSettings]);

        $validationErrors = $userType->validate($fieldDefinition, $validateUserValue);

        self::assertEquals([
            new ValidationError(
                'Invalid login format',
                null,
                [],
                'username'
            ),
        ], $validationErrors);
    }

    public function testValidLoginFormat(): void
    {
        $validateUserValue = new UserValue([
            'hasStoredLogin' => false,
            'contentId' => 46,
            'login' => 'validate_user',
            'email' => 'example@test.ibexa.co',
            'passwordHash' => '1234567890abcdef',
            'passwordHashType' => 'md5',
            'enabled' => true,
            'maxLogin' => 1000,
            'plainPassword' => 'testPassword',
        ]);

        $userHandlerMock = $this->createMock(UserHandler::class);

        $userHandlerMock
            ->expects($this->once())
            ->method('loadByLogin')
            ->with($validateUserValue->login)
            ->willThrowException(new NotFoundException('', ''));

        $userType = new UserType(
            $userHandlerMock,
            $this->createMock(PasswordHashService::class),
            $this->createMock(PasswordValidatorInterface::class)
        );

        $fieldSettings = [
            UserType::REQUIRE_UNIQUE_EMAIL => false,
            UserType::USERNAME_PATTERN => '^[^@]+$',
        ];

        $fieldDefinition = new CoreFieldDefinition(['fieldSettings' => $fieldSettings]);

        $validationErrors = $userType->validate($fieldDefinition, $validateUserValue);

        self::assertEquals([], $validationErrors);
    }

    public function testEmailAlreadyTaken(): void
    {
        $existingUser = new User([
            'id' => 23,
            'login' => 'existing_user',
            'email' => 'test@test.ibexa.co',
        ]);

        $validateUserValue = new UserValue([
            'hasStoredLogin' => false,
            'contentId' => 46,
            'login' => 'validate_user',
            'email' => 'test@test.ibexa.co',
            'passwordHash' => '1234567890abcdef',
            'passwordHashType' => 'md5',
            'enabled' => true,
            'maxLogin' => 1000,
            'plainPassword' => 'testPassword',
        ]);

        $userHandlerMock = $this->createMock(UserHandler::class);

        $userHandlerMock
            ->expects($this->once())
            ->method('loadByLogin')
            ->with($validateUserValue->login)
            ->willThrowException(new NotFoundException('', ''));

        $userHandlerMock
            ->expects($this->once())
            ->method('loadByEmail')
            ->with($validateUserValue->email)
            ->willReturn($existingUser);

        $userType = new UserType(
            $userHandlerMock,
            $this->createMock(PasswordHashService::class),
            $this->createMock(PasswordValidatorInterface::class)
        );

        $fieldSettings = [
            UserType::REQUIRE_UNIQUE_EMAIL => true,
            UserType::USERNAME_PATTERN => '^[^@]+$',
        ];

        $fieldDefinition = new CoreFieldDefinition(['fieldSettings' => $fieldSettings]);

        $validationErrors = $userType->validate($fieldDefinition, $validateUserValue);

        self::assertEquals([
            new ValidationError(
                "Email '%email%' is used by another user. You must enter a unique email.",
                null,
                [
                    '%email%' => $validateUserValue->email,
                ],
                'email'
            ),
        ], $validationErrors);
    }

    /**
     * @covers \Ibexa\Core\FieldType\User\Type::toPersistenceValue
     *
     * @dataProvider providerForTestCreatePersistenceValue
     */
    public function testCreatePersistenceValue(array $userValueDate, array $expectedFieldValueExternalData): void
    {
        $passwordHashServiceMock = $this->createMock(PasswordHashService::class);
        $passwordHashServiceMock->method('getDefaultHashType')->willReturn(RepositoryUser::DEFAULT_PASSWORD_HASH);
        $userType = new UserType(
            $this->createMock(UserHandler::class),
            $passwordHashServiceMock,
            $this->createMock(PasswordValidatorInterface::class)
        );

        $value = new UserValue($userValueDate);
        $fieldValue = $userType->toPersistenceValue($value);

        $expected = new FieldValue(
            [
                'data' => null,
                'externalData' => $expectedFieldValueExternalData,
                'sortKey' => null,
            ]
        );
        self::assertEquals($expected, $fieldValue);
    }

    public function providerForTestCreatePersistenceValue(): iterable
    {
        $passwordUpdatedAt = new DateTimeImmutable();
        $userData = [
            'hasStoredLogin' => false,
            'contentId' => 46,
            'login' => 'validate_user',
            'email' => 'test@test.ibexa.co',
            'passwordHash' => '1234567890abcdef',
            'enabled' => true,
            'maxLogin' => 1000,
            'plainPassword' => '',
            'passwordUpdatedAt' => $passwordUpdatedAt,
        ];

        yield 'when password hash type is given' => [
            $userValueData = [
                'passwordHashType' => RepositoryUser::PASSWORD_HASH_PHP_DEFAULT,
            ] + $userData,
            $expectedFieldValueExternalData = [
                'passwordHashType' => RepositoryUser::PASSWORD_HASH_PHP_DEFAULT,
                'passwordUpdatedAt' => $passwordUpdatedAt->getTimestamp(),
            ] + $userData,
        ];
        yield 'when password hash type is null' => [
            $userValueData = [
                    'passwordHashType' => null,
                ] + $userData,
            $expectedFieldValueExternalData = [
                    'passwordHashType' => RepositoryUser::DEFAULT_PASSWORD_HASH,
                    'passwordUpdatedAt' => $passwordUpdatedAt->getTimestamp(),
                ] + $userData,
        ];
        yield 'when password hash type is unsupported' => [
            $userValueData = [
                    'passwordHashType' => self::UNSUPPORTED_HASH_TYPE,
                ] + $userData,
            $expectedFieldValueExternalData = [
                    'passwordHashType' => RepositoryUser::DEFAULT_PASSWORD_HASH,
                    'passwordUpdatedAt' => $passwordUpdatedAt->getTimestamp(),
                ] + $userData,
        ];
    }

    public function testEmailFreeToUse(): void
    {
        $validateUserValue = new UserValue([
            'hasStoredLogin' => false,
            'contentId' => 46,
            'login' => 'validate_user',
            'email' => 'test@test.ibexa.co',
            'passwordHash' => '1234567890abcdef',
            'passwordHashType' => 'md5',
            'enabled' => true,
            'maxLogin' => 1000,
            'plainPassword' => 'testPassword',
        ]);

        $userHandlerMock = $this->createMock(UserHandler::class);

        $userHandlerMock
            ->expects($this->once())
            ->method('loadByLogin')
            ->with($validateUserValue->login)
            ->willThrowException(new NotFoundException('', ''));

        $userHandlerMock
            ->expects($this->once())
            ->method('loadByEmail')
            ->with($validateUserValue->email)
            ->willThrowException(new NotFoundException('', ''));

        $userType = new UserType(
            $userHandlerMock,
            $this->createMock(PasswordHashService::class),
            $this->createMock(PasswordValidatorInterface::class)
        );

        $fieldSettings = [
            UserType::REQUIRE_UNIQUE_EMAIL => true,
            UserType::USERNAME_PATTERN => '^[^@]+$',
        ];

        $fieldDefinition = new CoreFieldDefinition(['fieldSettings' => $fieldSettings]);

        $validationErrors = $userType->validate($fieldDefinition, $validateUserValue);

        self::assertEquals([], $validationErrors);
    }

    /**
     * Data provider for testValidate test.
     *
     * @see testValidate
     *
     * @return array data sets for testValidate method (<code>$userValue, $expectedValidationErrors, $loadByLoginBehaviorCallback</code>)
     */
    public function providerForTestValidate(): array
    {
        return [
            [
                new UserValue(
                    [
                        'hasStoredLogin' => false,
                        'contentId' => 23,
                        'login' => 'user',
                        'email' => 'invalid',
                        'passwordHash' => '1234567890abcdef',
                        'passwordHashType' => 'md5',
                        'enabled' => true,
                        'maxLogin' => 1000,
                        'plainPassword' => 'testPassword',
                    ]
                ),
                [
                    new ValidationError(
                        "The given e-mail '%email%' is invalid",
                        null,
                        [
                            '%email%' => 'invalid',
                        ],
                        'email'
                    ),
                ],
                static function (InvocationMocker $loadByLoginInvocationMocker) {
                    $loadByLoginInvocationMocker->willThrowException(
                        new NotFoundException('user', 'user')
                    );
                },
            ],
            [
                new UserValue([
                    'hasStoredLogin' => false,
                    'contentId' => 23,
                    'login' => 'sindelfingen',
                    'email' => 'sindelfingen@example.com',
                    'passwordHash' => '1234567890abcdef',
                    'passwordHashType' => 'md5',
                    'enabled' => true,
                    'maxLogin' => 1000,
                    'plainPassword' => 'testPassword',
                ]),
                [
                    new ValidationError(
                        "The user login '%login%' is used by another user. You must enter a unique login.",
                        null,
                        [
                            '%login%' => 'sindelfingen',
                        ],
                        'username'
                    ),
                ],
                function (InvocationMocker $loadByLoginInvocationMocker) {
                    $loadByLoginInvocationMocker->willReturn(
                        $this->createMock(UserValue::class)
                    );
                },
            ],
            [
                new UserValue([
                    'hasStoredLogin' => true,
                    'contentId' => 23,
                    'login' => 'sindelfingen',
                    'email' => 'sindelfingen@example.com',
                    'passwordHash' => '1234567890abcdef',
                    'passwordHashType' => 'md5',
                    'enabled' => true,
                    'maxLogin' => 1000,
                ]),
                [],
                null,
            ],
        ];
    }

    /**
     * Provide data sets with field settings which are considered valid by the
     * {@link validateFieldSettings()} method.
     *
     * Returns an array of data provider sets with a single argument: A valid
     * set of field settings.
     * For example:
     *
     * <code>
     *  return array(
     *      array(
     *          array(),
     *      ),
     *      array(
     *          array( 'rows' => 2 )
     *      ),
     *      // ...
     *  );
     * </code>
     *
     * @return array
     */
    public function provideValidFieldSettings(): array
    {
        return [
            [
                [],
            ],
            [
                [
                    UserType::PASSWORD_TTL_SETTING => 30,
                ],
            ],
            [
                [
                    UserType::PASSWORD_TTL_SETTING => 30,
                    UserType::PASSWORD_TTL_WARNING_SETTING => null,
                ],
            ],
            [
                [
                    UserType::PASSWORD_TTL_SETTING => 30,
                    UserType::PASSWORD_TTL_WARNING_SETTING => 14,
                    UserType::REQUIRE_UNIQUE_EMAIL => true,
                    UserType::USERNAME_PATTERN => '^[^!]+$',
                ],
            ],
        ];
    }

    /**
     * Provide data sets with field settings which are considered invalid by the
     * {@link validateFieldSettings()} method. The method must return a
     * non-empty array of validation error when receiving such field settings.
     *
     * Returns an array of data provider sets with a single argument: A valid
     * set of field settings.
     * For example:
     *
     * <code>
     *  return array(
     *      array(
     *          true,
     *      ),
     *      array(
     *          array( 'nonExistentKey' => 2 )
     *      ),
     *      // ...
     *  );
     * </code>
     *
     * @return array
     */
    public function provideInValidFieldSettings(): array
    {
        return [
            [
                [
                    UserType::PASSWORD_TTL_WARNING_SETTING => 30,
                ],
            ],
            [
                [
                    UserType::PASSWORD_TTL_SETTING => null,
                    UserType::PASSWORD_TTL_WARNING_SETTING => 60,
                ],
            ],
            [
                [
                    UserType::PASSWORD_TTL_SETTING => 30,
                    UserType::PASSWORD_TTL_WARNING_SETTING => 60,
                ],
            ],
        ];
    }

    protected function provideFieldTypeIdentifier()
    {
        return 'ezuser';
    }

    public function provideDataForGetName(): array
    {
        return [
            [$this->getEmptyValueExpectation(), '', [], 'en_GB'],
            [new UserValue(['login' => 'johndoe']), 'johndoe', [], 'en_GB'],
        ];
    }
}

class_alias(UserTest::class, 'eZ\Publish\Core\FieldType\Tests\UserTest');
