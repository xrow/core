<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Tests\Integration\Core\Repository;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;

/**
 * Test case for create and update Content operations in the ContentService with regard to
 * non-redundant set of fields being passed to the storage.
 *
 * These tests depends on TextLine field type being functional.
 *
 * @covers \Ibexa\Contracts\Core\Repository\ContentService
 * @group content
 */
class NonRedundantFieldSetTest extends BaseNonRedundantFieldSetTest
{
    /**
     * Test for the createContent() method.
     *
     * Default values are stored.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentServiceTest::testCreateContent
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentTypeServiceTest::testCreateContentType
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Content
     */
    public function testCreateContentDefaultValues()
    {
        $mainLanguageCode = 'eng-US';
        $fieldValues = [
            'field1' => ['eng-US' => 'new value 1'],
            'field3' => ['eng-US' => 'new value 3'],
        ];

        $content = $this->createTestContent($mainLanguageCode, $fieldValues);

        $this->assertInstanceOf(Content::class, $content);

        return $content;
    }

    /**
     * Test for the createContent() method.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends testCreateContentDefaultValues
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content $content
     */
    public function testCreateContentDefaultValuesFields(Content $content)
    {
        $this->assertCount(1, $content->versionInfo->languageCodes);
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertCount(4, $content->getFields());

        // eng-US
        $this->assertEquals('new value 1', $content->getFieldValue('field1', 'eng-US'));
        $this->assertEquals('default value 2', $content->getFieldValue('field2', 'eng-US'));
        $this->assertEquals('new value 3', $content->getFieldValue('field3', 'eng-US'));
        $this->assertEquals('default value 4', $content->getFieldValue('field4', 'eng-US'));
    }

    /**
     * Test for the createContent() method.
     *
     * Creating fields with empty values, no values being passed to storage.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentServiceTest::testCreateContent
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentTypeServiceTest::testCreateContentType
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Content
     */
    public function testCreateContentEmptyValues()
    {
        $mainLanguageCode = 'eng-US';
        $fieldValues = [
            'field2' => ['eng-US' => null],
            'field4' => ['eng-US' => null],
        ];

        $content = $this->createTestContent($mainLanguageCode, $fieldValues);

        $this->assertInstanceOf(Content::class, $content);

        return $content;
    }

    /**
     * Test for the createContent() method.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends testCreateContentEmptyValues
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content $content
     */
    public function testCreateContentEmptyValuesFields(Content $content)
    {
        $emptyValue = $this->getRepository()->getFieldTypeService()->getFieldType('ezstring')->getEmptyValue();

        $this->assertCount(1, $content->versionInfo->languageCodes);
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertCount(4, $content->getFields());

        // eng-US
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field2', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field3', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field4', 'eng-US'));
    }

    /**
     * Test for the createContent() method.
     *
     * Creating fields with empty values, no values being passed to storage.
     * Case where additional language is not stored.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentServiceTest::testCreateContent
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentTypeServiceTest::testCreateContentType
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Content
     */
    public function testCreateContentEmptyValuesTranslationNotStored()
    {
        $mainLanguageCode = 'eng-US';
        $fieldValues = [
            'field2' => ['eng-US' => null],
            'field4' => ['eng-US' => null, 'ger-DE' => null],
        ];

        $content = $this->createTestContent($mainLanguageCode, $fieldValues);

        $this->assertInstanceOf(Content::class, $content);

        return $content;
    }

    /**
     * Test for the createContent() method.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends testCreateContentEmptyValuesTranslationNotStored
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content $content
     */
    public function testCreateContentEmptyValuesTranslationNotStoredFields(Content $content)
    {
        $emptyValue = $this->getRepository()->getFieldTypeService()->getFieldType('ezstring')->getEmptyValue();

        $this->assertCount(1, $content->versionInfo->languageCodes);
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertCount(4, $content->getFields());

        // eng-US
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field2', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field3', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field4', 'eng-US'));

        // ger-DE is not stored!
        $this->assertNotContains('ger-DE', $content->versionInfo->languageCodes);
    }

    /**
     * Test for the createContent() method.
     *
     * Creating with two languages, main language is always stored (even with all values being empty).
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentServiceTest::testCreateContent
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentTypeServiceTest::testCreateContentType
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Content
     */
    public function testCreateContentTwoLanguagesMainTranslationStored()
    {
        $mainLanguageCode = 'eng-US';
        $fieldValues = [
            'field2' => ['eng-US' => null],
            'field4' => ['eng-US' => null, 'ger-DE' => 'new ger-DE value 4'],
        ];

        $content = $this->createTestContent($mainLanguageCode, $fieldValues);

        $this->assertInstanceOf(Content::class, $content);

        return $content;
    }

    /**
     * Test for the createContent() method.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends testCreateContentTwoLanguagesMainTranslationStored
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content $content
     */
    public function testCreateContentTwoLanguagesMainTranslationStoredFields(Content $content)
    {
        $emptyValue = $this->getRepository()->getFieldTypeService()->getFieldType('ezstring')->getEmptyValue();

        $this->assertCount(2, $content->versionInfo->languageCodes);
        $this->assertContains('ger-DE', $content->versionInfo->languageCodes);
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertCount(8, $content->getFields());

        // eng-US
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field2', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field3', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field4', 'eng-US'));

        // ger-DE
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'ger-DE'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field2', 'ger-DE'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field3', 'ger-DE'));
        $this->assertEquals('new ger-DE value 4', $content->getFieldValue('field4', 'ger-DE'));
    }

    /**
     * Test for the createContent() method.
     *
     * Creating with two languages, second (not main one) language with empty values, causing no fields
     * for it being passed to the storage. Second language will not be stored.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentServiceTest::testCreateContent
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentTypeServiceTest::testCreateContentType
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Content
     */
    public function testCreateContentTwoLanguagesSecondTranslationNotStored()
    {
        $mainLanguageCode = 'eng-US';
        $fieldValues = [
            'field4' => ['ger-DE' => null],
        ];

        $content = $this->createTestContent($mainLanguageCode, $fieldValues);

        $this->assertInstanceOf(Content::class, $content);

        return $content;
    }

    /**
     * Test for the createContent() method.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends testCreateContentTwoLanguagesSecondTranslationNotStored
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content $content
     */
    public function testCreateContentTwoLanguagesSecondTranslationNotStoredFields(Content $content)
    {
        $emptyValue = $this->getRepository()->getFieldTypeService()->getFieldType('ezstring')->getEmptyValue();

        $this->assertCount(1, $content->versionInfo->languageCodes);
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertCount(4, $content->getFields());

        // eng-US
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'eng-US'));
        $this->assertEquals('default value 2', $content->getFieldValue('field2', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field3', 'eng-US'));
        $this->assertEquals('default value 4', $content->getFieldValue('field4', 'eng-US'));

        // ger-DE is not stored!
        $this->assertNotContains('ger-DE', $content->versionInfo->languageCodes);
    }

    /**
     * Test for the createContent() method.
     *
     * Creating with no fields in struct, using only default values.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentServiceTest::testCreateContent
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentTypeServiceTest::testCreateContentType
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Content
     */
    public function testCreateContentDefaultValuesNoStructFields()
    {
        $mainLanguageCode = 'eng-US';
        $fieldValues = [];

        $content = $this->createTestContent($mainLanguageCode, $fieldValues);

        $this->assertInstanceOf(Content::class, $content);

        return $content;
    }

    /**
     * Test for the createContent() method.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends testCreateContentDefaultValuesNoStructFields
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content $content
     */
    public function testCreateContentDefaultValuesNoStructFieldsFields(Content $content)
    {
        $emptyValue = $this->getRepository()->getFieldTypeService()->getFieldType('ezstring')->getEmptyValue();

        $this->assertCount(1, $content->versionInfo->languageCodes);
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertCount(4, $content->getFields());

        // eng-US
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'eng-US'));
        $this->assertEquals('default value 2', $content->getFieldValue('field2', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field3', 'eng-US'));
        $this->assertEquals('default value 4', $content->getFieldValue('field4', 'eng-US'));
    }

    /**
     * Test for the createContent() method.
     *
     * Creating in two languages with no given field values for main language.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentServiceTest::testCreateContent
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentTypeServiceTest::testCreateContentType
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Content
     */
    public function testCreateContentTwoLanguagesNoValuesForMainLanguage()
    {
        $mainLanguageCode = 'eng-US';
        $fieldValues = [
            'field4' => ['ger-DE' => 'new value 4'],
        ];

        $content = $this->createTestContent($mainLanguageCode, $fieldValues);

        $this->assertInstanceOf(Content::class, $content);

        return $content;
    }

    /**
     * Test for the createContent() method.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends testCreateContentTwoLanguagesNoValuesForMainLanguage
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content $content
     */
    public function testCreateContentTwoLanguagesNoValuesForMainLanguageFields(Content $content)
    {
        $emptyValue = $this->getRepository()->getFieldTypeService()->getFieldType('ezstring')->getEmptyValue();

        $this->assertCount(2, $content->versionInfo->languageCodes);
        $this->assertContains('ger-DE', $content->versionInfo->languageCodes);
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertCount(8, $content->getFields());

        // eng-US
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'eng-US'));
        $this->assertEquals('default value 2', $content->getFieldValue('field2', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field3', 'eng-US'));
        $this->assertEquals('default value 4', $content->getFieldValue('field4', 'eng-US'));

        // ger-DE
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'ger-DE'));
        $this->assertEquals('default value 2', $content->getFieldValue('field2', 'ger-DE'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field3', 'ger-DE'));
        $this->assertEquals('new value 4', $content->getFieldValue('field4', 'ger-DE'));
    }

    /**
     * Test for the createContentDraft() method.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContentDraft()
     * @depends testCreateContentTwoLanguagesMainTranslationStoredFields
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Content[]
     */
    public function testCreateContentDraft()
    {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();

        $draft = $this->createMultilingualTestContent();
        $published = $contentService->publishVersion($draft->versionInfo);
        $newDraft = $contentService->createContentDraft($published->contentInfo);

        $newDraft = $contentService->loadContent($newDraft->id, null, $newDraft->versionInfo->versionNo);

        return [$published, $newDraft];
    }

    /**
     * Test for the createContentDraft() method.
     *
     * @depends testCreateContentDraft
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content[] $data
     */
    public function testCreateContentDraftFields(array $data)
    {
        $content = $data[1];

        $this->assertEquals(VersionInfo::STATUS_DRAFT, $content->versionInfo->status);
        $this->assertEquals(2, $content->versionInfo->versionNo);
        $this->assertCount(2, $content->versionInfo->languageCodes);
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertContains('eng-GB', $content->versionInfo->languageCodes);
        $this->assertCount(8, $content->getFields());

        // eng-US
        $this->assertEquals('value 1', $content->getFieldValue('field1', 'eng-US'));
        $this->assertEquals('value 2', $content->getFieldValue('field2', 'eng-US'));
        $this->assertEquals('value 3', $content->getFieldValue('field3', 'eng-US'));
        $this->assertEquals('value 4', $content->getFieldValue('field4', 'eng-US'));

        // eng-GB
        $this->assertEquals('value 1', $content->getFieldValue('field1', 'eng-GB'));
        $this->assertEquals('value 2', $content->getFieldValue('field2', 'eng-GB'));
        $this->assertEquals('value 3 eng-GB', $content->getFieldValue('field3', 'eng-GB'));
        $this->assertEquals('value 4 eng-GB', $content->getFieldValue('field4', 'eng-GB'));
    }

    /**
     * Test for the createContentDraft() method.
     *
     * @depends testCreateContentDraft
     * @depends testCreateContentDraftFields
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content[] $data
     */
    public function testCreateContentDraftFieldsRetainsIds(array $data)
    {
        $this->assertFieldIds($data[0], $data[1]);
    }

    /**
     * Test for the updateContent() method.
     *
     * Testing update with new language:
     *  - value for new language is copied from value in main language
     *  - value for new language is empty
     *  - value for new language is not empty
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentServiceTest::testCreateContent
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentTypeServiceTest::testCreateContentType
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Content
     */
    public function testUpdateContentWithNewLanguage()
    {
        $initialLanguageCode = 'ger-DE';
        $fieldValues = [
            'field4' => ['ger-DE' => 'new value 4'],
        ];

        $content = $this->updateTestContent($initialLanguageCode, $fieldValues);
        $this->assertInstanceOf(Content::class, $content);

        return $content;
    }

    /**
     * Test for the updateContent() method.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::updateContent()
     * @depends testUpdateContentWithNewLanguage
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content $content
     */
    public function testUpdateContentWithNewLanguageFields(Content $content)
    {
        $emptyValue = $this->getRepository()->getFieldTypeService()->getFieldType('ezstring')->getEmptyValue();

        $this->assertCount(3, $content->versionInfo->languageCodes);
        $this->assertContains('ger-DE', $content->versionInfo->languageCodes);
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertContains('eng-GB', $content->versionInfo->languageCodes);
        $this->assertCount(12, $content->getFields());

        // eng-US
        $this->assertEquals('value 1', $content->getFieldValue('field1', 'eng-US'));
        $this->assertEquals('value 2', $content->getFieldValue('field2', 'eng-US'));
        $this->assertEquals('value 3', $content->getFieldValue('field3', 'eng-US'));
        $this->assertEquals('value 4', $content->getFieldValue('field4', 'eng-US'));

        // eng-GB
        $this->assertEquals('value 1', $content->getFieldValue('field1', 'eng-GB'));
        $this->assertEquals('value 2', $content->getFieldValue('field2', 'eng-GB'));
        $this->assertEquals('value 3 eng-GB', $content->getFieldValue('field3', 'eng-GB'));
        $this->assertEquals('value 4 eng-GB', $content->getFieldValue('field4', 'eng-GB'));

        // ger-DE
        $this->assertEquals('value 1', $content->getFieldValue('field1', 'ger-DE'));
        $this->assertEquals('value 2', $content->getFieldValue('field2', 'ger-DE'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field3', 'ger-DE'));
        $this->assertEquals('new value 4', $content->getFieldValue('field4', 'ger-DE'));
    }

    /**
     * Test for the updateContent() method.
     *
     * Testing update of existing language and adding a new language:
     *  - value for new language is copied from value in main language
     *  - value for new language is empty
     *  - value for new language is not empty
     *  - existing language value updated with empty value
     *  - existing language value not changed
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentServiceTest::testCreateContent
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentTypeServiceTest::testCreateContentType
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Content
     */
    public function testUpdateContentWithNewLanguageVariant()
    {
        $initialLanguageCode = 'ger-DE';
        $fieldValues = [
            'field1' => ['eng-US' => null],
            'field4' => ['ger-DE' => 'new value 4'],
        ];

        $content = $this->updateTestContent($initialLanguageCode, $fieldValues);
        $this->assertInstanceOf(Content::class, $content);

        return $content;
    }

    /**
     * Test for the updateContent() method.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::updateContent()
     * @depends testUpdateContentWithNewLanguageVariant
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content $content
     */
    public function testUpdateContentWithNewLanguageVariantFields(Content $content)
    {
        $emptyValue = $this->getRepository()->getFieldTypeService()->getFieldType('ezstring')->getEmptyValue();

        $this->assertCount(3, $content->versionInfo->languageCodes);
        $this->assertContains('ger-DE', $content->versionInfo->languageCodes);
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertContains('eng-GB', $content->versionInfo->languageCodes);
        $this->assertCount(12, $content->getFields());

        // eng-US
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'eng-US'));
        $this->assertEquals('value 2', $content->getFieldValue('field2', 'eng-US'));
        $this->assertEquals('value 3', $content->getFieldValue('field3', 'eng-US'));
        $this->assertEquals('value 4', $content->getFieldValue('field4', 'eng-US'));

        // eng-GB
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'eng-GB'));
        $this->assertEquals('value 2', $content->getFieldValue('field2', 'eng-GB'));
        $this->assertEquals('value 3 eng-GB', $content->getFieldValue('field3', 'eng-GB'));
        $this->assertEquals('value 4 eng-GB', $content->getFieldValue('field4', 'eng-GB'));

        // ger-DE
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'ger-DE'));
        $this->assertEquals('value 2', $content->getFieldValue('field2', 'ger-DE'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field3', 'ger-DE'));
        $this->assertEquals('new value 4', $content->getFieldValue('field4', 'ger-DE'));
    }

    /**
     * Test for the updateContent() method.
     *
     * Updating with with new language and no field values given in the update struct.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentServiceTest::testCreateContent
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentTypeServiceTest::testCreateContentType
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Content
     */
    public function testUpdateContentWithNewLanguageNoValues()
    {
        $initialLanguageCode = 'ger-DE';
        $fieldValues = [];

        $content = $this->updateTestContent($initialLanguageCode, $fieldValues);
        $this->assertInstanceOf(Content::class, $content);

        return $content;
    }

    /**
     * Test for the updateContent() method.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::updateContent()
     * @depends testUpdateContentWithNewLanguageNoValues
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content $content
     */
    public function testUpdateContentWithNewLanguageNoValuesFields(Content $content)
    {
        $emptyValue = $this->getRepository()->getFieldTypeService()->getFieldType('ezstring')->getEmptyValue();

        $this->assertCount(3, $content->versionInfo->languageCodes);
        $this->assertContains('ger-DE', $content->versionInfo->languageCodes);
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertContains('eng-GB', $content->versionInfo->languageCodes);
        $this->assertCount(12, $content->getFields());

        // eng-US
        $this->assertEquals('value 1', $content->getFieldValue('field1', 'eng-US'));
        $this->assertEquals('value 2', $content->getFieldValue('field2', 'eng-US'));
        $this->assertEquals('value 3', $content->getFieldValue('field3', 'eng-US'));
        $this->assertEquals('value 4', $content->getFieldValue('field4', 'eng-US'));

        // eng-GB
        $this->assertEquals('value 1', $content->getFieldValue('field1', 'eng-GB'));
        $this->assertEquals('value 2', $content->getFieldValue('field2', 'eng-GB'));
        $this->assertEquals('value 3 eng-GB', $content->getFieldValue('field3', 'eng-GB'));
        $this->assertEquals('value 4 eng-GB', $content->getFieldValue('field4', 'eng-GB'));

        // ger-DE
        $this->assertEquals('value 1', $content->getFieldValue('field1', 'ger-DE'));
        $this->assertEquals('value 2', $content->getFieldValue('field2', 'ger-DE'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field3', 'ger-DE'));
        $this->assertEquals('default value 4', $content->getFieldValue('field4', 'ger-DE'));
    }

    /**
     * Test for the updateContent() method.
     *
     * When updating Content with two languages, updating non-translatable field will also update it's value
     * for non-main language.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentServiceTest::testCreateContent
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentTypeServiceTest::testCreateContentType
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Content
     */
    public function testUpdateContentUpdatingNonTranslatableFieldUpdatesFieldCopy()
    {
        $initialLanguageCode = 'eng-US';
        $fieldValues = [
            'field1' => ['eng-US' => 'new value 1'],
            'field2' => ['eng-US' => null],
        ];

        $content = $this->updateTestContent($initialLanguageCode, $fieldValues);
        $this->assertInstanceOf(Content::class, $content);

        return $content;
    }

    /**
     * Test for the updateContent() method.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::updateContent()
     * @depends testUpdateContentUpdatingNonTranslatableFieldUpdatesFieldCopy
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content $content
     */
    public function testUpdateContentUpdatingNonTranslatableFieldUpdatesFieldCopyFields(Content $content)
    {
        $emptyValue = $this->getRepository()->getFieldTypeService()->getFieldType('ezstring')->getEmptyValue();

        $this->assertCount(2, $content->versionInfo->languageCodes);
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertContains('eng-GB', $content->versionInfo->languageCodes);
        $this->assertCount(8, $content->getFields());

        // eng-US
        $this->assertEquals('new value 1', $content->getFieldValue('field1', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field2', 'eng-US'));
        $this->assertEquals('value 3', $content->getFieldValue('field3', 'eng-US'));
        $this->assertEquals('value 4', $content->getFieldValue('field4', 'eng-US'));

        // eng-GB
        $this->assertEquals('new value 1', $content->getFieldValue('field1', 'eng-GB'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field2', 'eng-GB'));
        $this->assertEquals('value 3 eng-GB', $content->getFieldValue('field3', 'eng-GB'));
        $this->assertEquals('value 4 eng-GB', $content->getFieldValue('field4', 'eng-GB'));
    }

    /**
     * Test for the updateContent() method.
     *
     * Updating with two languages, initial language is always stored (even with all values being empty).
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::createContent()
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentServiceTest::testCreateContent
     * @depends Ibexa\Tests\Integration\Core\Repository\ContentTypeServiceTest::testCreateContentType
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Content
     */
    public function testUpdateContentWithTwoLanguagesInitialLanguageTranslationNotCreated()
    {
        $initialLanguageCode = 'ger-DE';
        $fieldValues = [
            'field1' => ['eng-US' => null],
            'field2' => ['eng-US' => null],
            'field4' => ['ger-DE' => null],
        ];

        $content = $this->updateTestContent($initialLanguageCode, $fieldValues);
        $this->assertInstanceOf(Content::class, $content);

        return $content;
    }

    /**
     * Test for the updateContent() method.
     *
     * @covers \Ibexa\Contracts\Core\Repository\ContentService::updateContent()
     * @depends testUpdateContentWithTwoLanguagesInitialLanguageTranslationNotCreated
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content $content
     */
    public function testUpdateContentWithTwoLanguagesInitialLanguageTranslationNotCreatedFields(Content $content)
    {
        $emptyValue = $this->getRepository()->getFieldTypeService()->getFieldType('ezstring')->getEmptyValue();

        $this->assertCount(3, $content->versionInfo->languageCodes);
        $this->assertContains('ger-DE', $content->versionInfo->languageCodes);
        $this->assertContains('eng-US', $content->versionInfo->languageCodes);
        $this->assertContains('eng-GB', $content->versionInfo->languageCodes);
        $this->assertCount(12, $content->getFields());

        // eng-US
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'eng-US'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field2', 'eng-US'));
        $this->assertEquals('value 3', $content->getFieldValue('field3', 'eng-US'));
        $this->assertEquals('value 4', $content->getFieldValue('field4', 'eng-US'));

        // eng-GB
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'eng-GB'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field2', 'eng-GB'));
        $this->assertEquals('value 3 eng-GB', $content->getFieldValue('field3', 'eng-GB'));
        $this->assertEquals('value 4 eng-GB', $content->getFieldValue('field4', 'eng-GB'));

        // ger-DE
        $this->assertEquals($emptyValue, $content->getFieldValue('field1', 'ger-DE'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field2', 'ger-DE'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field3', 'ger-DE'));
        $this->assertEquals($emptyValue, $content->getFieldValue('field4', 'ger-DE'));
    }

    protected function assertFieldIds(Content $content1, Content $content2)
    {
        $fields1 = $this->mapFields($content1->getFields());
        $fields2 = $this->mapFields($content2->getFields());

        foreach ($fields1 as $fieldDefinitionIdentifier => $languageFieldIds) {
            foreach ($languageFieldIds as $languageCode => $fieldId) {
                $this->assertEquals(
                    $fields2[$fieldDefinitionIdentifier][$languageCode],
                    $fieldId
                );
            }
        }
    }

    /**
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Field[] $fields
     *
     * @return array
     */
    protected function mapFields(array $fields)
    {
        $mappedFields = [];

        foreach ($fields as $field) {
            $mappedFields[$field->fieldDefIdentifier][$field->languageCode] = $field->id;
        }

        return $mappedFields;
    }
}

class_alias(NonRedundantFieldSetTest::class, 'eZ\Publish\API\Repository\Tests\NonRedundantFieldSetTest');
