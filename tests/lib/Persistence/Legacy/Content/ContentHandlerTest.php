<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Tests\Core\Persistence\Legacy\Content;

use Ibexa\Contracts\Core\Persistence\Content;
use Ibexa\Contracts\Core\Persistence\Content\ContentInfo;
use Ibexa\Contracts\Core\Persistence\Content\CreateStruct;
use Ibexa\Contracts\Core\Persistence\Content\Field;
use Ibexa\Contracts\Core\Persistence\Content\FieldValue;
use Ibexa\Contracts\Core\Persistence\Content\Location\CreateStruct as LocationCreateStruct;
use Ibexa\Contracts\Core\Persistence\Content\MetadataUpdateStruct;
use Ibexa\Contracts\Core\Persistence\Content\Relation;
use Ibexa\Contracts\Core\Persistence\Content\Relation\CreateStruct as RelationCreateStruct;
use Ibexa\Contracts\Core\Persistence\Content\Type;
use Ibexa\Contracts\Core\Persistence\Content\UpdateStruct;
use Ibexa\Contracts\Core\Persistence\Content\VersionInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Relation as RelationValue;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Core\Persistence\Legacy\Content\FieldHandler;
use Ibexa\Core\Persistence\Legacy\Content\Gateway as ContentGateway;
use Ibexa\Core\Persistence\Legacy\Content\Handler;
use Ibexa\Core\Persistence\Legacy\Content\Location\Gateway as LocationGateway;
use Ibexa\Core\Persistence\Legacy\Content\Mapper;
use Ibexa\Core\Persistence\Legacy\Content\TreeHandler;
use Ibexa\Core\Persistence\Legacy\Content\Type\Gateway as ContentTypeGateway;
use Ibexa\Core\Persistence\Legacy\Content\Type\Handler as ContentTypeHandler;
use Ibexa\Core\Persistence\Legacy\Content\UrlAlias\Gateway as UrlAliasGateway;
use Ibexa\Core\Persistence\Legacy\Content\UrlAlias\SlugConverter;
use Ibexa\Tests\Core\Persistence\Legacy\TestCase;
use ReflectionException;

/**
 * @covers \Ibexa\Core\Persistence\Legacy\Content\Handler
 */
class ContentHandlerTest extends TestCase
{
    private const RELATION_ID = 1;

    /**
     * Content handler to test.
     *
     * @var \Ibexa\Core\Persistence\Legacy\Content\Handler
     */
    protected $contentHandler;

    /**
     * Gateway mock.
     *
     * @var \Ibexa\Core\Persistence\Legacy\Content\Gateway
     */
    protected $gatewayMock;

    /**
     * Location gateway mock.
     *
     * @var \Ibexa\Core\Persistence\Legacy\Content\Location\Gateway
     */
    protected $locationGatewayMock;

    /**
     * Type gateway mock.
     *
     * @var \Ibexa\Core\Persistence\Legacy\Content\Type\Gateway
     */
    protected $typeGatewayMock;

    /**
     * Mapper mock.
     *
     * @var \Ibexa\Core\Persistence\Legacy\Content\Mapper
     */
    protected $mapperMock;

    /**
     * Field handler mock.
     *
     * @var \Ibexa\Core\Persistence\Legacy\Content\FieldHandler
     */
    protected $fieldHandlerMock;

    /**
     * Location handler mock.
     *
     * @var \Ibexa\Core\Persistence\Legacy\Content\TreeHandler
     */
    protected $treeHandlerMock;

    /**
     * Slug converter mock.
     *
     * @var \Ibexa\Core\Persistence\Legacy\Content\UrlAlias\SlugConverter
     */
    protected $slugConverterMock;

    /**
     * Location handler mock.
     *
     * @var \Ibexa\Core\Persistence\Legacy\Content\UrlAlias\Gateway
     */
    protected $urlAliasGatewayMock;

    /**
     * ContentType handler mock.
     *
     * @var \Ibexa\Core\Persistence\Legacy\Content\Type\Handler
     */
    protected $contentTypeHandlerMock;

    /**
     * @todo Current method way to complex to test, refactor!
     */
    public function testCreate()
    {
        $handler = $this->getContentHandler();

        $mapperMock = $this->getMapperMock();
        $gatewayMock = $this->getGatewayMock();
        $fieldHandlerMock = $this->getFieldHandlerMock();
        $locationMock = $this->getLocationGatewayMock();
        $contentTypeHandlerMock = $this->getContentTypeHandlerMock();
        $contentTypeMock = $this->createMock(Type::class);
        $createStruct = $this->getCreateStructFixture();

        $contentTypeHandlerMock->expects($this->once())
            ->method('load')
            ->with($createStruct->typeId)
            ->will($this->returnValue($contentTypeMock));

        $mapperMock->expects($this->once())
            ->method('createVersionInfoFromCreateStruct')
            ->with(
                $this->isInstanceOf(
                    CreateStruct::class
                )
            )->will(
                $this->returnValue(
                    new VersionInfo(
                        [
                            'names' => [],
                            'contentInfo' => new ContentInfo(),
                        ]
                    )
                )
            );

        $gatewayMock->expects($this->once())
            ->method('insertContentObject')
            ->with(
                $this->isInstanceOf(CreateStruct::class)
            )->will($this->returnValue(23));

        $gatewayMock->expects($this->once())
            ->method('insertVersion')
            ->with(
                $this->isInstanceOf(VersionInfo::class),
                $this->isType('array')
            )->will($this->returnValue(1));

        $fieldHandlerMock->expects($this->once())
            ->method('createNewFields')
            ->with(
                $this->isInstanceOf(Content::class),
                $this->isInstanceOf(Type::class)
            );

        $locationMock->expects($this->once())
            ->method('createNodeAssignment')
            ->with(
                $this->isInstanceOf(
                    LocationCreateStruct::class
                ),
                $this->equalTo(42),
                $this->equalTo(3) // Location\Gateway::NODE_ASSIGNMENT_OP_CODE_CREATE
            );

        $res = $handler->create($createStruct);

        // @todo Make subsequent tests

        $this->assertInstanceOf(
            Content::class,
            $res,
            'Content not created'
        );
        $this->assertEquals(
            23,
            $res->versionInfo->contentInfo->id,
            'Content ID not set correctly'
        );
        $this->assertInstanceOf(
            VersionInfo::class,
            $res->versionInfo,
            'Version infos not created'
        );
        $this->assertEquals(
            1,
            $res->versionInfo->id,
            'Version ID not set correctly'
        );
        $this->assertCount(
            2,
            $res->fields,
            'Fields not set correctly in version'
        );
    }

    public function testPublishFirstVersion()
    {
        $handler = $this->getPartlyMockedHandler(['loadVersionInfo']);

        $gatewayMock = $this->getGatewayMock();
        $mapperMock = $this->getMapperMock();
        $locationMock = $this->getLocationGatewayMock();
        $fieldHandlerMock = $this->getFieldHandlerMock();
        $metadataUpdateStruct = new MetadataUpdateStruct();

        $handler->expects($this->at(0))
            ->method('loadVersionInfo')
            ->with(23, 1)
            ->will(
                $this->returnValue(
                    new VersionInfo([
                        'contentInfo' => new ContentInfo([
                            'currentVersionNo' => 1,
                            'mainLanguageCode' => 'eng-GB',
                        ]),
                        'names' => [
                            'eng-GB' => '',
                        ],
                    ])
                )
            );

        $contentRows = [['ezcontentobject_version_version' => 1]];

        $gatewayMock->expects($this->once())
            ->method('load')
            ->with(
                $this->equalTo(23),
                $this->equalTo(1),
                $this->equalTo(null)
            )->willReturn($contentRows);

        $gatewayMock->expects($this->once())
            ->method('loadVersionedNameData')
            ->with(
                $this->equalTo([['id' => 23, 'version' => 1]])
            )->will(
                $this->returnValue([22])
            );

        $mapperMock->expects($this->once())
            ->method('extractContentFromRows')
            ->with($this->equalTo($contentRows), $this->equalTo([22]))
            ->will($this->returnValue([$this->getContentFixtureForDraft()]));

        $fieldHandlerMock->expects($this->once())
            ->method('loadExternalFieldData')
            ->with($this->isInstanceOf(Content::class));

        $gatewayMock
            ->expects($this->once())
            ->method('updateContent')
            ->with(23, $metadataUpdateStruct);

        $locationMock
            ->expects($this->once())
            ->method('createLocationsFromNodeAssignments')
            ->with(23, 1);

        $locationMock
            ->expects($this->once())
            ->method('updateLocationsContentVersionNo')
            ->with(23, 1);

        $gatewayMock
            ->expects($this->once())
            ->method('setPublishedStatus')
            ->with(23, 1);

        $handler->publish(23, 1, $metadataUpdateStruct);
    }

    public function testPublish()
    {
        $handler = $this->getPartlyMockedHandler(['loadVersionInfo', 'setStatus']);

        $gatewayMock = $this->getGatewayMock();
        $mapperMock = $this->getMapperMock();
        $locationMock = $this->getLocationGatewayMock();
        $fieldHandlerMock = $this->getFieldHandlerMock();
        $metadataUpdateStruct = new MetadataUpdateStruct();

        $handler->expects($this->at(0))
            ->method('loadVersionInfo')
            ->with(23, 2)
            ->will(
                $this->returnValue(
                    new VersionInfo([
                        'contentInfo' => new ContentInfo([
                            'currentVersionNo' => 1,
                            'mainLanguageCode' => 'eng-GB',
                        ]),
                        'names' => [
                            'eng-GB' => '',
                        ],
                    ])
                )
            );

        $handler
            ->expects($this->at(1))
            ->method('setStatus')
            ->with(23, VersionInfo::STATUS_ARCHIVED, 1);

        $contentRows = [['ezcontentobject_version_version' => 2]];

        $gatewayMock->expects($this->once())
            ->method('load')
            ->with(
                $this->equalTo(23),
                $this->equalTo(2),
                $this->equalTo(null)
            )
            ->willReturn($contentRows);

        $gatewayMock->expects($this->once())
            ->method('loadVersionedNameData')
            ->with(
                $this->equalTo([['id' => 23, 'version' => 2]])
            )->will(
                $this->returnValue([22])
            );

        $mapperMock->expects($this->once())
            ->method('extractContentFromRows')
            ->with($this->equalTo($contentRows), $this->equalTo([22]))
            ->will($this->returnValue([$this->getContentFixtureForDraft()]));

        $fieldHandlerMock->expects($this->once())
            ->method('loadExternalFieldData')
            ->with($this->isInstanceOf(Content::class));

        $gatewayMock
            ->expects($this->once())
            ->method('updateContent')
            ->with(23, $metadataUpdateStruct, $this->isInstanceOf(VersionInfo::class));

        $locationMock
            ->expects($this->once())
            ->method('createLocationsFromNodeAssignments')
            ->with(23, 2);

        $locationMock
            ->expects($this->once())
            ->method('updateLocationsContentVersionNo')
            ->with(23, 2);

        $gatewayMock
            ->expects($this->once())
            ->method('setPublishedStatus')
            ->with(23, 2);

        $handler->publish(23, 2, $metadataUpdateStruct);
    }

    public function testCreateDraftFromVersion()
    {
        $handler = $this->getPartlyMockedHandler(['load']);

        $mapperMock = $this->getMapperMock();
        $gatewayMock = $this->getGatewayMock();
        $fieldHandlerMock = $this->getFieldHandlerMock();

        $handler->expects($this->once())
            ->method('load')
            ->with(23, 2)
            ->will($this->returnValue($this->getContentFixtureForDraft()));

        $mapperMock->expects($this->once())
            ->method('createVersionInfoForContent')
            ->with(
                $this->isInstanceOf(Content::class),
                $this->equalTo(3),
                $this->equalTo(14)
            )->will(
                $this->returnValue(
                    new VersionInfo(
                        [
                            'names' => [],
                            'versionNo' => 3,
                        ]
                    )
                )
            );

        $gatewayMock->expects($this->once())
            ->method('insertVersion')
            ->with(
                $this->isInstanceOf(VersionInfo::class),
                $this->getContentFixtureForDraft()->fields
            )->will($this->returnValue(42));

        $gatewayMock->expects($this->once())
            ->method('getLastVersionNumber')
            ->with($this->equalTo(23))
            ->will($this->returnValue(2));

        $fieldHandlerMock->expects($this->once())
            ->method('createExistingFieldsInNewVersion')
            ->with($this->isInstanceOf(Content::class));

        $relationData = [
            [
                'ezcontentobject_link_contentclassattribute_id' => 0,
                'ezcontentobject_link_to_contentobject_id' => 42,
                'ezcontentobject_link_relation_type' => 1,
            ],
        ];

        $gatewayMock->expects($this->once())
            ->method('loadRelations')
            ->with(
                $this->equalTo(23),
                $this->equalTo(2)
            )
            ->will($this->returnValue($relationData));

        $relationStruct = new RelationCreateStruct(
            [
                'sourceContentId' => 23,
                'sourceContentVersionNo' => 3,
                'sourceFieldDefinitionId' => 0,
                'destinationContentId' => 42,
                'type' => 1,
            ]
        );

        $gatewayMock->expects($this->once())
            ->method('insertRelation')
            ->with($this->equalTo($relationStruct));

        $result = $handler->createDraftFromVersion(23, 2, 14);

        $this->assertInstanceOf(
            Content::class,
            $result
        );
        $this->assertEquals(
            42,
            $result->versionInfo->id
        );
    }

    public function testLoad()
    {
        $handler = $this->getContentHandler();

        $gatewayMock = $this->getGatewayMock();
        $mapperMock = $this->getMapperMock();
        $fieldHandlerMock = $this->getFieldHandlerMock();

        $contentRows = [['ezcontentobject_version_version' => 2]];

        $gatewayMock->expects($this->once())
            ->method('load')
            ->with(
                $this->equalTo(23),
                $this->equalTo(2),
                $this->equalTo(['eng-GB'])
            )->will(
                $this->returnValue($contentRows)
            );

        $gatewayMock->expects($this->once())
            ->method('loadVersionedNameData')
            ->with(
                $this->equalTo([['id' => 23, 'version' => 2]])
            )->will(
                $this->returnValue([22])
            );

        $mapperMock->expects($this->once())
            ->method('extractContentFromRows')
            ->with($this->equalTo($contentRows), $this->equalTo([22]))
            ->will($this->returnValue([$this->getContentFixtureForDraft()]));

        $fieldHandlerMock->expects($this->once())
            ->method('loadExternalFieldData')
            ->with($this->isInstanceOf(Content::class));

        $result = $handler->load(23, 2, ['eng-GB']);

        $this->assertEquals(
            $result,
            $this->getContentFixtureForDraft()
        );
    }

    public function testLoadContentList()
    {
        $handler = $this->getContentHandler();

        $gatewayMock = $this->getGatewayMock();
        $mapperMock = $this->getMapperMock();
        $fieldHandlerMock = $this->getFieldHandlerMock();
        $contentRows = [
            ['ezcontentobject_id' => 2, 'ezcontentobject_version_version' => 2],
            ['ezcontentobject_id' => 3, 'ezcontentobject_version_version' => 1],
        ];
        $gatewayMock->expects($this->once())
            ->method('loadContentList')
            ->with([2, 3], ['eng-GB', 'eng-US'])
            ->willReturn($contentRows);

        $nameDataRows = [
            ['ezcontentobject_name_contentobject_id' => 2, 'ezcontentobject_name_content_version' => 2],
            ['ezcontentobject_name_contentobject_id' => 3, 'ezcontentobject_name_content_version' => 1],
        ];

        $gatewayMock->expects($this->once())
            ->method('loadVersionedNameData')
            ->with($this->equalTo([['id' => 2, 'version' => 2], ['id' => 3, 'version' => 1]]))
            ->willReturn($nameDataRows);

        $expected = [
            2 => $this->getContentFixtureForDraft(2, 2),
            3 => $this->getContentFixtureForDraft(3, 1),
        ];
        $mapperMock->expects($this->at(0))
            ->method('extractContentFromRows')
            ->with($this->equalTo([$contentRows[0]]), $this->equalTo([$nameDataRows[0]]))
            ->willReturn([$expected[2]]);

        $mapperMock->expects($this->at(1))
            ->method('extractContentFromRows')
            ->with($this->equalTo([$contentRows[1]]), $this->equalTo([$nameDataRows[1]]))
            ->willReturn([$expected[3]]);

        $fieldHandlerMock->expects($this->exactly(2))
            ->method('loadExternalFieldData')
            ->with($this->isInstanceOf(Content::class));

        $result = $handler->loadContentList([2, 3], ['eng-GB', 'eng-US']);

        $this->assertEquals(
            $expected,
            $result
        );
    }

    public function testLoadContentInfoByRemoteId()
    {
        $contentInfoData = [new ContentInfo()];
        $this->getGatewayMock()->expects($this->once())
            ->method('loadContentInfoByRemoteId')
            ->with(
                $this->equalTo('15b256dbea2ae72418ff5facc999e8f9')
            )->will(
                $this->returnValue([42])
            );

        $this->getMapperMock()->expects($this->once())
            ->method('extractContentInfoFromRow')
            ->with($this->equalTo([42]))
            ->will($this->returnValue($contentInfoData));

        $this->assertSame(
            $contentInfoData,
            $this->getContentHandler()->loadContentInfoByRemoteId('15b256dbea2ae72418ff5facc999e8f9')
        );
    }

    public function testLoadErrorNotFound()
    {
        $this->expectException(NotFoundException::class);

        $handler = $this->getContentHandler();

        $gatewayMock = $this->getGatewayMock();

        $gatewayMock->expects($this->once())
            ->method('load')
            ->will(
                $this->returnValue([])
            );

        $result = $handler->load(23, 2, ['eng-GB']);
    }

    /**
     * Returns a Content for {@link testCreateDraftFromVersion()}.
     *
     * @param int $id Optional id
     * @param int $versionNo Optional version number
     *
     * @return \Ibexa\Contracts\Core\Persistence\Content
     */
    protected function getContentFixtureForDraft(int $id = 23, int $versionNo = 2)
    {
        $content = new Content();
        $content->versionInfo = new VersionInfo();
        $content->versionInfo->versionNo = $versionNo;

        $content->versionInfo->contentInfo = new ContentInfo(['id' => $id]);

        $field = new Field();
        $field->versionNo = $versionNo;

        $content->fields = [$field];

        return $content;
    }

    public function testUpdateContent()
    {
        $handler = $this->getPartlyMockedHandler(['load', 'loadContentInfo']);

        $gatewayMock = $this->getGatewayMock();
        $fieldHandlerMock = $this->getFieldHandlerMock();
        $contentTypeHandlerMock = $this->getContentTypeHandlerMock();
        $contentTypeMock = $this->createMock(Type::class);
        $contentStub = new Content(
            [
                'versionInfo' => new VersionInfo(
                    [
                        'contentInfo' => new ContentInfo(
                            [
                                'contentTypeId' => 4242,
                            ]
                        ),
                    ]
                ),
            ]
        );

        $contentTypeHandlerMock->expects($this->once())
            ->method('load')
            ->with($contentStub->versionInfo->contentInfo->contentTypeId)
            ->will($this->returnValue($contentTypeMock));

        $gatewayMock->expects($this->once())
            ->method('updateContent')
            ->with(14, $this->isInstanceOf(MetadataUpdateStruct::class));
        $gatewayMock->expects($this->once())
            ->method('updateVersion')
            ->with(14, 4, $this->isInstanceOf(UpdateStruct::class));

        $fieldHandlerMock->expects($this->once())
            ->method('updateFields')
            ->with(
                $this->isInstanceOf(Content::class),
                $this->isInstanceOf(UpdateStruct::class),
                $this->isInstanceOf(Type::class)
            );

        $handler->expects($this->at(0))
            ->method('load')
            ->with(14, 4)
            ->will($this->returnValue($contentStub));

        $handler->expects($this->at(1))
            ->method('load')
            ->with(14, 4);

        $handler->expects($this->at(2))
            ->method('loadContentInfo')
            ->with(14);

        $resultContent = $handler->updateContent(
            14, // ContentId
            4, // VersionNo
            new UpdateStruct(
                [
                    'creatorId' => 14,
                    'modificationDate' => time(),
                    'initialLanguageId' => 2,
                    'fields' => [
                        new Field(
                            [
                                'id' => 23,
                                'fieldDefinitionId' => 42,
                                'type' => 'some-type',
                                'value' => new FieldValue(),
                            ]
                        ),
                        new Field(
                            [
                                'id' => 23,
                                'fieldDefinitionId' => 43,
                                'type' => 'some-type',
                                'value' => new FieldValue(),
                            ]
                        ),
                    ],
                ]
            )
        );

        $resultContentInfo = $handler->updateMetadata(
            14, // ContentId
            new MetadataUpdateStruct(
                [
                    'ownerId' => 14,
                    'name' => 'Some name',
                    'modificationDate' => time(),
                    'alwaysAvailable' => true,
                ]
            )
        );
    }

    public function testUpdateMetadata()
    {
        $handler = $this->getPartlyMockedHandler(['load', 'loadContentInfo']);

        $gatewayMock = $this->getGatewayMock();
        $fieldHandlerMock = $this->getFieldHandlerMock();
        $updateStruct = new MetadataUpdateStruct(
            [
                'ownerId' => 14,
                'name' => 'Some name',
                'modificationDate' => time(),
                'alwaysAvailable' => true,
            ]
        );

        $gatewayMock->expects($this->once())
            ->method('updateContent')
            ->with(14, $updateStruct);

        $handler->expects($this->once())
            ->method('loadContentInfo')
            ->with(14)
            ->will(
                $this->returnValue(
                    $this->createMock(ContentInfo::class)
                )
            );

        $resultContentInfo = $handler->updateMetadata(
            14, // ContentId
            $updateStruct
        );
        self::assertInstanceOf(ContentInfo::class, $resultContentInfo);
    }

    public function testUpdateMetadataUpdatesPathIdentificationString()
    {
        $handler = $this->getPartlyMockedHandler(['load', 'loadContentInfo']);
        $locationGatewayMock = $this->getLocationGatewayMock();
        $slugConverterMock = $this->getSlugConverterMock();
        $urlAliasGatewayMock = $this->getUrlAliasGatewayMock();
        $gatewayMock = $this->getGatewayMock();
        $updateStruct = new MetadataUpdateStruct(['mainLanguageId' => 2]);

        $gatewayMock->expects($this->once())
            ->method('updateContent')
            ->with(14, $updateStruct);

        $locationGatewayMock->expects($this->once())
            ->method('loadLocationDataByContent')
            ->with(14)
            ->will(
                $this->returnValue(
                    [
                        [
                            'node_id' => 100,
                            'parent_node_id' => 200,
                        ],
                    ]
                )
            );

        $urlAliasGatewayMock->expects($this->once())
            ->method('loadLocationEntries')
            ->with(100, false, 2)
            ->will(
                $this->returnValue(
                    [
                        [
                            'text' => 'slug',
                        ],
                    ]
                )
            );

        $slugConverterMock->expects($this->once())
            ->method('convert')
            ->with('slug', 'node_100', 'urlalias_compat')
            ->will($this->returnValue('transformed_slug'));

        $locationGatewayMock->expects($this->once())
            ->method('updatePathIdentificationString')
            ->with(100, 200, 'transformed_slug');

        $handler->expects($this->once())
            ->method('loadContentInfo')
            ->with(14)
            ->will(
                $this->returnValue(
                    $this->createMock(ContentInfo::class)
                )
            );

        $handler->updateMetadata(
            14, // ContentId
            $updateStruct
        );
    }

    /**
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException
     */
    public function testLoadRelation(): void
    {
        $handler = $this->getContentHandler();

        $gatewayMock = $this->getGatewayMock();
        $mapperMock = $this->getMapperMock();
        $relationFixture = $this->getRelationFixture();

        $gatewayMock
            ->expects(self::once())
            ->method('loadRelation')
            ->with(self::RELATION_ID)
            ->willReturn([self::RELATION_ID]);

        $mapperMock
            ->expects(self::once())
            ->method('extractRelationFromRow')
            ->with([self::RELATION_ID])
            ->willReturn($relationFixture);

        $result = $handler->loadRelation(self::RELATION_ID);

        $this->assertEquals(
            $result,
            $relationFixture
        );
    }

    public function testLoadRelations()
    {
        $handler = $this->getContentHandler();

        $gatewayMock = $this->getGatewayMock();
        $mapperMock = $this->getMapperMock();

        $gatewayMock->expects($this->once())
            ->method('loadRelations')
            ->with(
                $this->equalTo(23),
                $this->equalTo(null),
                $this->equalTo(null)
            )->will(
                $this->returnValue([42])
            );

        $mapperMock->expects($this->once())
            ->method('extractRelationsFromRows')
            ->with($this->equalTo([42]))
            ->will($this->returnValue($this->getRelationFixture()));

        $result = $handler->loadRelations(23);

        $this->assertEquals(
            $result,
            $this->getRelationFixture()
        );
    }

    public function testLoadReverseRelations()
    {
        $handler = $this->getContentHandler();

        $gatewayMock = $this->getGatewayMock();
        $mapperMock = $this->getMapperMock();

        $gatewayMock->expects($this->once())
            ->method('loadReverseRelations')
            ->with(
                $this->equalTo(23),
                $this->equalTo(null)
            )->will(
                $this->returnValue([42])
            );

        $mapperMock->expects($this->once())
            ->method('extractRelationsFromRows')
            ->with($this->equalTo([42]))
            ->will($this->returnValue($this->getRelationFixture()));

        $result = $handler->loadReverseRelations(23);

        $this->assertEquals(
            $result,
            $this->getRelationFixture()
        );
    }

    public function testAddRelation()
    {
        // expected relation object after creation
        $expectedRelationObject = new Relation();
        $expectedRelationObject->id = 42; // mocked value, not a real one
        $expectedRelationObject->sourceContentId = 23;
        $expectedRelationObject->sourceContentVersionNo = 1;
        $expectedRelationObject->destinationContentId = 66;
        $expectedRelationObject->type = RelationValue::COMMON;

        // relation create struct
        $relationCreateStruct = new Relation\CreateStruct();
        $relationCreateStruct->destinationContentId = 66;
        $relationCreateStruct->sourceContentId = 23;
        $relationCreateStruct->sourceContentVersionNo = 1;
        $relationCreateStruct->type = RelationValue::COMMON;

        $handler = $this->getContentHandler();

        $gatewayMock = $this->getGatewayMock();
        $mapperMock = $this->getMapperMock();

        $mapperMock->expects($this->once())
            ->method('createRelationFromCreateStruct')
            // @todo Connected with the todo above
            ->with($this->equalTo($relationCreateStruct))
            ->will($this->returnValue($expectedRelationObject));

        $gatewayMock->expects($this->once())
            ->method('insertRelation')
            ->with($this->equalTo($relationCreateStruct))
            ->will(
                // @todo Should this return a row as if it was selected from the database, the id... ? Check with other, similar create methods
                $this->returnValue(42)
            );

        $result = $handler->addRelation($relationCreateStruct);

        $this->assertEquals(
            $result,
            $expectedRelationObject
        );
    }

    public function testRemoveRelation()
    {
        $gatewayMock = $this->getGatewayMock();

        $gatewayMock->expects($this->once())
            ->method('deleteRelation')
            ->with($this->equalTo(1, RelationValue::COMMON));

        $this->getContentHandler()->removeRelation(1, RelationValue::COMMON);
    }

    protected function getRelationFixture()
    {
        $relation = new Relation();
        $relation->id = self::RELATION_ID;
        $relation->sourceContentId = 23;
        $relation->sourceContentVersionNo = 1;
        $relation->destinationContentId = 69;

        return $relation;
    }

    /**
     * Returns a CreateStruct fixture.
     *
     * @return \Ibexa\Contracts\Core\Persistence\Content\CreateStruct
     */
    public function getCreateStructFixture()
    {
        $struct = new CreateStruct();

        $struct->typeId = 4242;

        $firstField = new Field();
        $firstField->type = 'some-type';
        $firstField->value = new FieldValue();

        $secondField = clone $firstField;

        $struct->fields = [
            $firstField, $secondField,
        ];

        $struct->locations = [
            new LocationCreateStruct(
                ['parentId' => 42]
            ),
        ];

        $struct->name = [
            'eng-GB' => 'This is a test name',
        ];

        return $struct;
    }

    public function testLoadDraftsForUser()
    {
        $handler = $this->getContentHandler();
        $rows = [['ezcontentobject_version_contentobject_id' => 42, 'ezcontentobject_version_version' => 2]];

        $gatewayMock = $this->getGatewayMock();
        $mapperMock = $this->getMapperMock();

        $gatewayMock->expects($this->once())
            ->method('listVersionsForUser')
            ->with($this->equalTo(23))
            ->will($this->returnValue($rows));

        $gatewayMock->expects($this->once())
            ->method('loadVersionedNameData')
            ->with($this->equalTo([['id' => 42, 'version' => 2]]))
            ->will($this->returnValue([]));

        $mapperMock->expects($this->once())
            ->method('extractVersionInfoListFromRows')
            ->with($this->equalTo($rows), $this->equalTo([]))
            ->will($this->returnValue([new VersionInfo()]));

        $res = $handler->loadDraftsForUser(23);

        $this->assertEquals(
            [new VersionInfo()],
            $res
        );
    }

    public function testListVersions()
    {
        $handler = $this->getContentHandler();

        $treeHandlerMock = $this->getTreeHandlerMock();

        $treeHandlerMock
            ->expects($this->once())
            ->method('listVersions')
            ->with(23)
            ->will($this->returnValue([new VersionInfo()]));

        $versions = $handler->listVersions(23);

        $this->assertEquals(
            [new VersionInfo()],
            $versions
        );
    }

    public function testRemoveRawContent()
    {
        $handler = $this->getContentHandler();
        $treeHandlerMock = $this->getTreeHandlerMock();

        $treeHandlerMock
            ->expects($this->once())
            ->method('removeRawContent')
            ->with(23);

        $handler->removeRawContent(23);
    }

    /**
     * Test for the deleteContent() method.
     */
    public function testDeleteContentWithLocations()
    {
        $handlerMock = $this->getPartlyMockedHandler(['getAllLocationIds']);
        $gatewayMock = $this->getGatewayMock();
        $treeHandlerMock = $this->getTreeHandlerMock();

        $gatewayMock->expects($this->once())
            ->method('getAllLocationIds')
            ->with($this->equalTo(23))
            ->will($this->returnValue([42, 24]));
        $treeHandlerMock->expects($this->exactly(2))
            ->method('removeSubtree')
            ->with(
                $this->logicalOr(
                    $this->equalTo(42),
                    $this->equalTo(24)
                )
            );

        $handlerMock->deleteContent(23);
    }

    /**
     * Test for the deleteContent() method.
     */
    public function testDeleteContentWithoutLocations()
    {
        $handlerMock = $this->getPartlyMockedHandler(['removeRawContent']);
        $gatewayMock = $this->getGatewayMock();

        $gatewayMock->expects($this->once())
            ->method('getAllLocationIds')
            ->with($this->equalTo(23))
            ->will($this->returnValue([]));
        $handlerMock->expects($this->once())
            ->method('removeRawContent')
            ->with($this->equalTo(23));

        $handlerMock->deleteContent(23);
    }

    public function testDeleteVersion()
    {
        $handler = $this->getContentHandler();

        $gatewayMock = $this->getGatewayMock();
        $mapperMock = $this->getMapperMock();
        $locationHandlerMock = $this->getLocationGatewayMock();
        $fieldHandlerMock = $this->getFieldHandlerMock();

        $rows = [['ezcontentobject_version_version' => 2]];

        // Load VersionInfo to delete fields
        $gatewayMock->expects($this->once())
            ->method('loadVersionInfo')
            ->with($this->equalTo(225), $this->equalTo(2))
            ->willReturn($rows);

        $gatewayMock->expects($this->once())
            ->method('loadVersionedNameData')
            ->with($this->equalTo([['id' => 225, 'version' => 2]]))
            ->will($this->returnValue([22]));

        $mapperMock->expects($this->once())
            ->method('extractVersionInfoListFromRows')
            ->with($this->equalTo($rows), $this->equalTo([22]))
            ->will($this->returnValue([new VersionInfo()]));

        $locationHandlerMock->expects($this->once())
            ->method('deleteNodeAssignment')
            ->with(
                $this->equalTo(225),
                $this->equalTo(2)
            );

        $fieldHandlerMock->expects($this->once())
            ->method('deleteFields')
            ->with(
                $this->equalTo(225),
                $this->isInstanceOf(VersionInfo::class)
            );
        $gatewayMock->expects($this->once())
            ->method('deleteRelations')
            ->with(
                $this->equalTo(225),
                $this->equalTo(2)
            );
        $gatewayMock->expects($this->once())
            ->method('deleteVersions')
            ->with(
                $this->equalTo(225),
                $this->equalTo(2)
            );
        $gatewayMock->expects($this->once())
            ->method('deleteNames')
            ->with(
                $this->equalTo(225),
                $this->equalTo(2)
            );

        $handler->deleteVersion(225, 2);
    }

    public function testCopySingleVersion()
    {
        $handler = $this->getPartlyMockedHandler(['load', 'internalCreate']);
        $gatewayMock = $this->getGatewayMock();
        $mapperMock = $this->getMapperMock();

        $handler->expects(
            $this->once()
        )->method(
            'load'
        )->with(
            $this->equalTo(23),
            $this->equalTo(32)
        )->will(
            $this->returnValue(new Content())
        );

        $mapperMock->expects(
            $this->once()
        )->method(
            'createCreateStructFromContent'
        )->with(
            $this->isInstanceOf(Content::class)
        )->will(
            $this->returnValue(new CreateStruct())
        );

        $handler->expects(
            $this->once()
        )->method(
            'internalCreate'
        )->with(
            $this->isInstanceOf(CreateStruct::class),
            $this->equalTo(32)
        )->will(
            $this->returnValue(
                new Content(
                    [
                        'versionInfo' => new VersionInfo(['contentInfo' => new ContentInfo(['id' => 24])]),
                    ]
                )
            )
        );

        $gatewayMock->expects($this->once())
            ->method('copyRelations')
            ->with(
                $this->equalTo(23),
                $this->equalTo(24),
                $this->equalTo(32)
            )
            ->will($this->returnValue(null));

        $result = $handler->copy(23, 32);

        $this->assertInstanceOf(
            Content::class,
            $result
        );
    }

    public function testCopyAllVersions()
    {
        $handler = $this->getPartlyMockedHandler(
            [
                'loadContentInfo',
                'load',
                'internalCreate',
                'listVersions',
            ]
        );
        $gatewayMock = $this->getGatewayMock();
        $mapperMock = $this->getMapperMock();
        $fieldHandlerMock = $this->getFieldHandlerMock();
        $contentTypeHandlerMock = $this->getContentTypeHandlerMock();
        $contentTypeMock = $this->createMock(Type::class);
        $time = time();
        $createStructStub = new CreateStruct(
            [
                'modified' => $time,
                'typeId' => 4242,
            ]
        );

        $contentTypeHandlerMock->expects($this->once())
            ->method('load')
            ->with($createStructStub->typeId)
            ->will($this->returnValue($contentTypeMock));

        $handler->expects($this->once())
            ->method('loadContentInfo')
            ->with($this->equalTo(23))
            ->will($this->returnValue(new ContentInfo(['currentVersionNo' => 2])));

        $handler->expects($this->at(1))
            ->method('load')
            ->with($this->equalTo(23), $this->equalTo(2))
            ->will($this->returnValue(new Content()));

        $mapperMock->expects($this->once())
            ->method('createCreateStructFromContent')
            ->with($this->isInstanceOf(Content::class))
            ->will(
                $this->returnValue($createStructStub)
            );

        $handler->expects($this->once())
            ->method('internalCreate')
            ->with(
                $this->isInstanceOf(CreateStruct::class),
                $this->equalTo(2)
            )->will(
                $this->returnValue(
                    new Content(
                        [
                            'versionInfo' => new VersionInfo(
                                [
                                    'contentInfo' => new ContentInfo(['id' => 24]),
                                ]
                            ),
                        ]
                    )
                )
            );

        $handler->expects($this->once())
            ->method('listVersions')
            ->with($this->equalTo(23))
            ->will(
                $this->returnValue(
                    [
                        new VersionInfo(['versionNo' => 1]),
                        new VersionInfo(['versionNo' => 2]),
                    ]
                )
            );

        $versionInfo = new VersionInfo(
            [
                'names' => ['eng-US' => 'Test'],
                'contentInfo' => new ContentInfo(
                    [
                        'id' => 24,
                        'alwaysAvailable' => true,
                    ]
                ),
            ]
        );
        $handler->expects($this->at(4))
            ->method('load')
            ->with($this->equalTo(23), $this->equalTo(1))
            ->will(
                $this->returnValue(
                    new Content(
                        [
                            'versionInfo' => $versionInfo,
                            'fields' => [],
                        ]
                    )
                )
            );

        $versionInfo->creationDate = $time;
        $versionInfo->modificationDate = $time;
        $gatewayMock->expects($this->once())
            ->method('insertVersion')
            ->with(
                $this->equalTo($versionInfo),
                $this->isType('array')
            )->will($this->returnValue(42));

        $versionInfo = clone $versionInfo;
        $versionInfo->id = 42;
        $fieldHandlerMock->expects($this->once())
            ->method('createNewFields')
            ->with(
                $this->equalTo(
                    new Content(
                        [
                            'versionInfo' => $versionInfo,
                            'fields' => [],
                        ]
                    )
                ),
                $this->isInstanceOf(Type::class)
            );

        $gatewayMock->expects($this->once())
            ->method('setName')
            ->with(
                $this->equalTo(24),
                $this->equalTo(1),
                $this->equalTo('Test'),
                $this->equalTo('eng-US')
            );

        $gatewayMock->expects($this->once())
            ->method('copyRelations')
            ->with(
                $this->equalTo(23),
                $this->equalTo(24),
                $this->equalTo(null)
            )
            ->will($this->returnValue(null));

        $result = $handler->copy(23);

        $this->assertInstanceOf(
            Content::class,
            $result
        );
    }

    public function testCopyThrowsNotFoundExceptionContentNotFound()
    {
        $this->expectException(NotFoundException::class);

        $handler = $this->getContentHandler();

        $treeHandlerMock = $this->getTreeHandlerMock();
        $treeHandlerMock
            ->expects($this->once())
            ->method('loadContentInfo')
            ->with($this->equalTo(23))
            ->will(
                $this->throwException(new NotFoundException('ContentInfo', 23))
            );

        $handler->copy(23);
    }

    public function testCopyThrowsNotFoundExceptionVersionNotFound()
    {
        $this->expectException(NotFoundException::class);

        $handler = $this->getContentHandler();

        $gatewayMock = $this->getGatewayMock();
        $gatewayMock->expects($this->once())
            ->method('load')
            ->with($this->equalTo(23, 32))
            ->will($this->returnValue([]));

        $result = $handler->copy(23, 32);
    }

    public function testSetStatus()
    {
        $handler = $this->getContentHandler();

        $mapperMock = $this->getMapperMock();
        $gatewayMock = $this->getGatewayMock();

        $gatewayMock->expects($this->once())
            ->method('setStatus')
            ->with(23, 5, 2)
            ->will($this->returnValue(true));

        $this->assertTrue(
            $handler->setStatus(23, 2, 5)
        );
    }

    /**
     * Returns the handler to test.
     *
     * @return \Ibexa\Core\Persistence\Legacy\Content\Handler
     */
    protected function getContentHandler()
    {
        if (!isset($this->contentHandler)) {
            $this->contentHandler = new Handler(
                $this->getGatewayMock(),
                $this->getLocationGatewayMock(),
                $this->getMapperMock(),
                $this->getFieldHandlerMock(),
                $this->getSlugConverterMock(),
                $this->getUrlAliasGatewayMock(),
                $this->getContentTypeHandlerMock(),
                $this->getTreeHandlerMock()
            );
        }

        return $this->contentHandler;
    }

    /**
     * Returns the handler to test with $methods mocked.
     *
     * @param string[] $methods
     *
     * @return \Ibexa\Core\Persistence\Legacy\Content\Handler
     */
    protected function getPartlyMockedHandler(array $methods)
    {
        return $this->getMockBuilder(Handler::class)
            ->setMethods($methods)
            ->setConstructorArgs(
                [
                    $this->getGatewayMock(),
                    $this->getLocationGatewayMock(),
                    $this->getMapperMock(),
                    $this->getFieldHandlerMock(),
                    $this->getSlugConverterMock(),
                    $this->getUrlAliasGatewayMock(),
                    $this->getContentTypeHandlerMock(),
                    $this->getTreeHandlerMock(),
                ]
            )
            ->getMock();
    }

    /**
     * Returns a TreeHandler mock.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject|\Ibexa\Core\Persistence\Legacy\Content\TreeHandler
     */
    protected function getTreeHandlerMock()
    {
        if (!isset($this->treeHandlerMock)) {
            $this->treeHandlerMock = $this->createMock(TreeHandler::class);
        }

        return $this->treeHandlerMock;
    }

    /**
     * Returns a ContentTypeHandler mock.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject|\Ibexa\Core\Persistence\Legacy\Content\Type\Handler
     */
    protected function getContentTypeHandlerMock()
    {
        if (!isset($this->contentTypeHandlerMock)) {
            $this->contentTypeHandlerMock = $this->createMock(ContentTypeHandler::class);
        }

        return $this->contentTypeHandlerMock;
    }

    /**
     * Returns a FieldHandler mock.
     *
     * @return \Ibexa\Core\Persistence\Legacy\Content\FieldHandler
     */
    protected function getFieldHandlerMock()
    {
        if (!isset($this->fieldHandlerMock)) {
            $this->fieldHandlerMock = $this->createMock(FieldHandler::class);
        }

        return $this->fieldHandlerMock;
    }

    /**
     * Returns a Mapper mock.
     *
     * @return \Ibexa\Core\Persistence\Legacy\Content\Mapper
     */
    protected function getMapperMock()
    {
        if (!isset($this->mapperMock)) {
            $this->mapperMock = $this->createMock(Mapper::class);
        }

        return $this->mapperMock;
    }

    /**
     * Returns a Location Gateway mock.
     *
     * @return \Ibexa\Core\Persistence\Legacy\Content\Location\Gateway
     */
    protected function getLocationGatewayMock()
    {
        if (!isset($this->locationGatewayMock)) {
            $this->locationGatewayMock = $this->createMock(LocationGateway::class);
        }

        return $this->locationGatewayMock;
    }

    /**
     * Returns a Content Type gateway mock.
     *
     * @return \Ibexa\Core\Persistence\Legacy\Content\Type\Gateway
     */
    protected function getTypeGatewayMock()
    {
        if (!isset($this->typeGatewayMock)) {
            $this->typeGatewayMock = $this->createMock(ContentTypeGateway::class);
        }

        return $this->typeGatewayMock;
    }

    /**
     * Returns a mock object for the Content Gateway.
     *
     * @return \Ibexa\Core\Persistence\Legacy\Content\Gateway|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getGatewayMock()
    {
        if (!isset($this->gatewayMock)) {
            try {
                $this->gatewayMock = $this->getMockForAbstractClass(ContentGateway::class);
            } catch (ReflectionException $e) {
                self::fail($e);
            }
        }

        return $this->gatewayMock;
    }

    /**
     * Returns a mock object for the UrlAlias Handler.
     *
     * @return \Ibexa\Core\Persistence\Legacy\Content\UrlAlias\SlugConverter
     */
    protected function getSlugConverterMock()
    {
        if (!isset($this->slugConverterMock)) {
            $this->slugConverterMock = $this->createMock(SlugConverter::class);
        }

        return $this->slugConverterMock;
    }

    /**
     * Returns a mock object for the UrlAlias Gateway.
     *
     * @return \Ibexa\Core\Persistence\Legacy\Content\UrlAlias\Gateway
     */
    protected function getUrlAliasGatewayMock()
    {
        if (!isset($this->urlAliasGatewayMock)) {
            $this->urlAliasGatewayMock = $this->getMockForAbstractClass(UrlAliasGateway::class);
        }

        return $this->urlAliasGatewayMock;
    }
}

class_alias(ContentHandlerTest::class, 'eZ\Publish\Core\Persistence\Legacy\Tests\Content\ContentHandlerTest');
