<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Tests\Core\Repository\Values\Content;

use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo
 */
class ContentInfoTest extends TestCase
{
    public function testObjectProperties()
    {
        $object = new ContentInfo();
        $properties = $object->attributes();
        self::assertNotContains('internalFields', $properties, 'Internal property found ');
        self::assertContains('contentTypeId', $properties, 'Property not found');
        self::assertContains('id', $properties, 'Property not found');
        self::assertContains('name', $properties, 'Property not found');
        self::assertContains('sectionId', $properties, 'Property not found');
        self::assertContains('currentVersionNo', $properties, 'Property not found');
        self::assertContains('published', $properties, 'Property not found');
        self::assertContains('ownerId', $properties, 'Property not found');
        self::assertContains('modificationDate', $properties, 'Property not found');
        self::assertContains('publishedDate', $properties, 'Property not found');
        self::assertContains('alwaysAvailable', $properties, 'Property not found');
        self::assertContains('remoteId', $properties, 'Property not found');
        self::assertContains('mainLanguageCode', $properties, 'Property not found');
        self::assertContains('mainLocationId', $properties, 'Property not found');

        // check for duplicates and double check existence of property
        $propertiesHash = [];
        foreach ($properties as $property) {
            if (isset($propertiesHash[$property])) {
                self::fail("Property '{$property}' exists several times in properties list");
            } elseif (!isset($object->$property)) {
                self::fail("Property '{$property}' does not exist on object, even though it was hinted to be there");
            }
            $propertiesHash[$property] = 1;
        }
    }
}

class_alias(ContentInfoTest::class, 'eZ\Publish\Core\Repository\Tests\Values\Content\ContentInfoTest');
