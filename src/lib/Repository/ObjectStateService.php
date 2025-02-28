<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Core\Repository;

use Exception;
use Ibexa\Contracts\Core\Persistence\Content\ObjectState as SPIObjectState;
use Ibexa\Contracts\Core\Persistence\Content\ObjectState\Group as SPIObjectStateGroup;
use Ibexa\Contracts\Core\Persistence\Content\ObjectState\Handler;
use Ibexa\Contracts\Core\Persistence\Content\ObjectState\InputStruct;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException as APINotFoundException;
use Ibexa\Contracts\Core\Repository\ObjectStateService as ObjectStateServiceInterface;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Repository as RepositoryInterface;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectState as APIObjectState;
use Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateCreateStruct;
use Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroup as APIObjectStateGroup;
use Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroupCreateStruct;
use Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroupUpdateStruct;
use Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateUpdateStruct;
use Ibexa\Core\Base\Exceptions\InvalidArgumentException;
use Ibexa\Core\Base\Exceptions\InvalidArgumentValue;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Core\Base\Exceptions\UnauthorizedException;
use Ibexa\Core\Repository\Values\ObjectState\ObjectState;
use Ibexa\Core\Repository\Values\ObjectState\ObjectStateGroup;

/**
 * ObjectStateService service.
 *
 * @example Examples/objectstates.php tbd.
 */
class ObjectStateService implements ObjectStateServiceInterface
{
    /** @var \Ibexa\Contracts\Core\Repository\Repository */
    protected $repository;

    /** @var \Ibexa\Contracts\Core\Persistence\Content\ObjectState\Handler */
    protected $objectStateHandler;

    /** @var array */
    protected $settings;

    /** @var \Ibexa\Contracts\Core\Repository\PermissionResolver */
    private $permissionResolver;

    /**
     * Setups service with reference to repository object that created it & corresponding handler.
     *
     * @param \Ibexa\Contracts\Core\Repository\Repository $repository
     * @param \Ibexa\Contracts\Core\Persistence\Content\ObjectState\Handler $objectStateHandler
     * @param array $settings
     */
    public function __construct(
        RepositoryInterface $repository,
        Handler $objectStateHandler,
        PermissionResolver $permissionResolver,
        array $settings = []
    ) {
        $this->repository = $repository;
        $this->objectStateHandler = $objectStateHandler;
        $this->permissionResolver = $permissionResolver;
        // Union makes sure default settings are ignored if provided in argument
        $this->settings = $settings + [
            //'defaultSetting' => array(),
        ];
    }

    /**
     * Creates a new object state group.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException if the user is not allowed to create an object state group
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException if the object state group with provided identifier already exists
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroupCreateStruct $objectStateGroupCreateStruct
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroup
     */
    public function createObjectStateGroup(ObjectStateGroupCreateStruct $objectStateGroupCreateStruct): APIObjectStateGroup
    {
        if (!$this->permissionResolver->canUser('state', 'administrate', $objectStateGroupCreateStruct)) {
            throw new UnauthorizedException('state', 'administrate');
        }

        $inputStruct = $this->buildCreateInputStruct(
            $objectStateGroupCreateStruct->identifier,
            $objectStateGroupCreateStruct->defaultLanguageCode,
            $objectStateGroupCreateStruct->names,
            $objectStateGroupCreateStruct->descriptions
        );

        try {
            $this->objectStateHandler->loadGroupByIdentifier($inputStruct->identifier);
            throw new InvalidArgumentException(
                'objectStateGroupCreateStruct',
                'An Object state group with the provided identifier already exists'
            );
        } catch (APINotFoundException $e) {
            // Do nothing
        }

        $this->repository->beginTransaction();
        try {
            $spiObjectStateGroup = $this->objectStateHandler->createGroup($inputStruct);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }

        return $this->buildDomainObjectStateGroupObject($spiObjectStateGroup);
    }

    /**
     * {@inheritdoc}
     */
    public function loadObjectStateGroup(int $objectStateGroupId, array $prioritizedLanguages = []): APIObjectStateGroup
    {
        $spiObjectStateGroup = $this->objectStateHandler->loadGroup($objectStateGroupId);

        return $this->buildDomainObjectStateGroupObject($spiObjectStateGroup, $prioritizedLanguages);
    }

    public function loadObjectStateGroupByIdentifier(
        string $objectStateGroupIdentifier,
        array $prioritizedLanguages = []
    ): APIObjectStateGroup {
        $spiObjectStateGroup = $this->objectStateHandler->loadGroupByIdentifier($objectStateGroupIdentifier);

        return $this->buildDomainObjectStateGroupObject($spiObjectStateGroup, $prioritizedLanguages);
    }

    /**
     * {@inheritdoc}
     */
    public function loadObjectStateGroups(int $offset = 0, int $limit = -1, array $prioritizedLanguages = []): iterable
    {
        $spiObjectStateGroups = $this->objectStateHandler->loadAllGroups($offset, $limit);

        $objectStateGroups = [];
        foreach ($spiObjectStateGroups as $spiObjectStateGroup) {
            $objectStateGroups[] = $this->buildDomainObjectStateGroupObject(
                $spiObjectStateGroup,
                $prioritizedLanguages
            );
        }

        return $objectStateGroups;
    }

    /**
     * This method returns the ordered list of object states of a group.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroup $objectStateGroup
     * @param string[] $prioritizedLanguages
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectState[]
     */
    public function loadObjectStates(
        APIObjectStateGroup $objectStateGroup,
        array $prioritizedLanguages = []
    ): iterable {
        $spiObjectStates = $this->objectStateHandler->loadObjectStates($objectStateGroup->id);

        $objectStates = [];
        foreach ($spiObjectStates as $spiObjectState) {
            $objectStates[] = $this->buildDomainObjectStateObject(
                $spiObjectState,
                $objectStateGroup,
                $prioritizedLanguages
            );
        }

        return $objectStates;
    }

    /**
     * Updates an object state group.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException if the user is not allowed to update an object state group
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException if the object state group with provided identifier already exists
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroup $objectStateGroup
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroupUpdateStruct $objectStateGroupUpdateStruct
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroup
     */
    public function updateObjectStateGroup(APIObjectStateGroup $objectStateGroup, ObjectStateGroupUpdateStruct $objectStateGroupUpdateStruct): APIObjectStateGroup
    {
        if (!$this->permissionResolver->canUser('state', 'administrate', $objectStateGroup)) {
            throw new UnauthorizedException('state', 'administrate');
        }

        $loadedObjectStateGroup = $this->loadObjectStateGroup($objectStateGroup->id);

        $inputStruct = $this->buildObjectStateGroupUpdateInputStruct(
            $loadedObjectStateGroup,
            $objectStateGroupUpdateStruct->identifier,
            $objectStateGroupUpdateStruct->defaultLanguageCode,
            $objectStateGroupUpdateStruct->names,
            $objectStateGroupUpdateStruct->descriptions
        );

        if ($objectStateGroupUpdateStruct->identifier !== null) {
            try {
                $existingObjectStateGroup = $this->objectStateHandler->loadGroupByIdentifier($inputStruct->identifier);
                if ($existingObjectStateGroup->id != $loadedObjectStateGroup->id) {
                    throw new InvalidArgumentException(
                        'objectStateGroupUpdateStruct',
                        'An Object state group with the provided identifier already exists'
                    );
                }
            } catch (APINotFoundException $e) {
                // Do nothing
            }
        }

        $this->repository->beginTransaction();
        try {
            $spiObjectStateGroup = $this->objectStateHandler->updateGroup(
                $loadedObjectStateGroup->id,
                $inputStruct
            );
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }

        return $this->buildDomainObjectStateGroupObject($spiObjectStateGroup, $objectStateGroup->prioritizedLanguages);
    }

    /**
     * Deletes a object state group including all states and links to content.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException if the user is not allowed to delete an object state group
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroup $objectStateGroup
     */
    public function deleteObjectStateGroup(APIObjectStateGroup $objectStateGroup): void
    {
        if (!$this->permissionResolver->canUser('state', 'administrate', $objectStateGroup)) {
            throw new UnauthorizedException('state', 'administrate');
        }

        $loadedObjectStateGroup = $this->loadObjectStateGroup($objectStateGroup->id);

        $this->repository->beginTransaction();
        try {
            $this->objectStateHandler->deleteGroup($loadedObjectStateGroup->id);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Creates a new object state in the given group.
     *
     * Note: in current kernel: If it is the first state all content objects will
     * set to this state.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException if the user is not allowed to create an object state
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException if the object state with provided identifier already exists in the same group
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroup $objectStateGroup
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateCreateStruct $objectStateCreateStruct
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectState
     */
    public function createObjectState(APIObjectStateGroup $objectStateGroup, ObjectStateCreateStruct $objectStateCreateStruct): APIObjectState
    {
        if (!$this->permissionResolver->canUser('state', 'administrate', $objectStateCreateStruct, [$objectStateGroup])) {
            throw new UnauthorizedException('state', 'administrate');
        }

        $inputStruct = $this->buildCreateInputStruct(
            $objectStateCreateStruct->identifier,
            $objectStateCreateStruct->defaultLanguageCode,
            $objectStateCreateStruct->names,
            $objectStateCreateStruct->descriptions
        );

        try {
            $this->objectStateHandler->loadByIdentifier($inputStruct->identifier, $objectStateGroup->id);
            throw new InvalidArgumentException(
                'objectStateCreateStruct',
                'An Object state with the provided identifier already exists in the provided Object state group'
            );
        } catch (APINotFoundException $e) {
            // Do nothing
        }

        $this->repository->beginTransaction();
        try {
            $spiObjectState = $this->objectStateHandler->create($objectStateGroup->id, $inputStruct);

            if (is_int($objectStateCreateStruct->priority)) {
                $this->objectStateHandler->setPriority(
                    $spiObjectState->id,
                    $objectStateCreateStruct->priority
                );

                // Reload the object state to have the updated priority,
                // considering that priorities are always incremental within a group
                $spiObjectState = $this->objectStateHandler->load($spiObjectState->id);
            }

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }

        return $this->buildDomainObjectStateObject($spiObjectState);
    }

    /**
     * {@inheritdoc}
     */
    public function loadObjectState(int $stateId, array $prioritizedLanguages = []): APIObjectState
    {
        $spiObjectState = $this->objectStateHandler->load($stateId);

        return $this->buildDomainObjectStateObject($spiObjectState, null, $prioritizedLanguages);
    }

    public function loadObjectStateByIdentifier(
        APIObjectStateGroup $objectStateGroup,
        string $objectStateIdentifier,
        array $prioritizedLanguages = []
    ): APIObjectState {
        $spiObjectState = $this->objectStateHandler->loadByIdentifier(
            $objectStateIdentifier,
            $objectStateGroup->id
        );

        return $this->buildDomainObjectStateObject($spiObjectState, null, $prioritizedLanguages);
    }

    /**
     * Updates an object state.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException if the user is not allowed to update an object state
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException if the object state with provided identifier already exists in the same group
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectState $objectState
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateUpdateStruct $objectStateUpdateStruct
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectState
     */
    public function updateObjectState(APIObjectState $objectState, ObjectStateUpdateStruct $objectStateUpdateStruct): APIObjectState
    {
        if (!$this->permissionResolver->canUser('state', 'administrate', $objectState)) {
            throw new UnauthorizedException('state', 'administrate');
        }

        $loadedObjectState = $this->loadObjectState($objectState->id);

        $inputStruct = $this->buildObjectStateUpdateInputStruct(
            $loadedObjectState,
            $objectStateUpdateStruct->identifier,
            $objectStateUpdateStruct->defaultLanguageCode,
            $objectStateUpdateStruct->names,
            $objectStateUpdateStruct->descriptions
        );

        if ($objectStateUpdateStruct->identifier !== null) {
            try {
                $existingObjectState = $this->objectStateHandler->loadByIdentifier(
                    $inputStruct->identifier,
                    $loadedObjectState->getObjectStateGroup()->id
                );

                if ($existingObjectState->id != $loadedObjectState->id) {
                    throw new InvalidArgumentException(
                        'objectStateUpdateStruct',
                        'An Object state with the provided identifier already exists in provided Object state group'
                    );
                }
            } catch (APINotFoundException $e) {
                // Do nothing
            }
        }

        $this->repository->beginTransaction();
        try {
            $spiObjectState = $this->objectStateHandler->update(
                $loadedObjectState->id,
                $inputStruct
            );
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }

        return $this->buildDomainObjectStateObject($spiObjectState, null, $objectState->prioritizedLanguages);
    }

    /**
     * Changes the priority of the state.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException if the user is not allowed to change priority on an object state
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectState $objectState
     * @param int $priority
     */
    public function setPriorityOfObjectState(APIObjectState $objectState, int $priority): void
    {
        if (!$this->permissionResolver->canUser('state', 'administrate', $objectState)) {
            throw new UnauthorizedException('state', 'administrate');
        }

        $loadedObjectState = $this->loadObjectState($objectState->id);

        $this->repository->beginTransaction();
        try {
            $this->objectStateHandler->setPriority(
                $loadedObjectState->id,
                $priority
            );
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Deletes a object state. The state of the content objects is reset to the
     * first object state in the group.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException if the user is not allowed to delete an object state
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectState $objectState
     */
    public function deleteObjectState(APIObjectState $objectState): void
    {
        if (!$this->permissionResolver->canUser('state', 'administrate', $objectState)) {
            throw new UnauthorizedException('state', 'administrate');
        }

        $loadedObjectState = $this->loadObjectState($objectState->id);

        $this->repository->beginTransaction();
        try {
            $this->objectStateHandler->delete($loadedObjectState->id);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Sets the object-state of a state group to $state for the given content.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException if the object state does not belong to the given group
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException if the user is not allowed to change the object state
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo $contentInfo
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroup $objectStateGroup
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectState $objectState
     */
    public function setContentState(ContentInfo $contentInfo, APIObjectStateGroup $objectStateGroup, APIObjectState $objectState): void
    {
        if (!$this->permissionResolver->canUser('state', 'assign', $contentInfo, [$objectState])) {
            throw new UnauthorizedException('state', 'assign', ['contentId' => $contentInfo->id]);
        }

        $loadedObjectState = $this->loadObjectState($objectState->id);

        if ($loadedObjectState->getObjectStateGroup()->id != $objectStateGroup->id) {
            throw new InvalidArgumentException('objectState', 'Object state does not belong to the given group');
        }

        $this->repository->beginTransaction();
        try {
            $this->objectStateHandler->setContentState(
                $contentInfo->id,
                $objectStateGroup->id,
                $loadedObjectState->id
            );
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Gets the object-state of object identified by $contentId.
     *
     * The $state is the id of the state within one group.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo $contentInfo
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroup $objectStateGroup
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectState
     */
    public function getContentState(ContentInfo $contentInfo, APIObjectStateGroup $objectStateGroup): APIObjectState
    {
        $spiObjectState = $this->objectStateHandler->getContentState(
            $contentInfo->id,
            $objectStateGroup->id
        );

        return $this->buildDomainObjectStateObject($spiObjectState, $objectStateGroup);
    }

    /**
     * Returns the number of objects which are in this state.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectState $objectState
     *
     * @return int
     */
    public function getContentCount(APIObjectState $objectState): int
    {
        return $this->objectStateHandler->getContentCount(
            $objectState->id
        );
    }

    /**
     * Instantiates a new Object State Group Create Struct and sets $identified in it.
     *
     * @param string $identifier
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroupCreateStruct
     */
    public function newObjectStateGroupCreateStruct(string $identifier): ObjectStateGroupCreateStruct
    {
        $objectStateGroupCreateStruct = new ObjectStateGroupCreateStruct();
        $objectStateGroupCreateStruct->identifier = $identifier;

        return $objectStateGroupCreateStruct;
    }

    /**
     * Instantiates a new Object State Group Update Struct.
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroupUpdateStruct
     */
    public function newObjectStateGroupUpdateStruct(): ObjectStateGroupUpdateStruct
    {
        return new ObjectStateGroupUpdateStruct();
    }

    /**
     * Instantiates a new Object State Create Struct and sets $identifier in it.
     *
     * @param string $identifier
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateCreateStruct
     */
    public function newObjectStateCreateStruct(string $identifier): ObjectStateCreateStruct
    {
        $objectStateCreateStruct = new ObjectStateCreateStruct();
        $objectStateCreateStruct->identifier = $identifier;

        return $objectStateCreateStruct;
    }

    /**
     * Instantiates a new Object State Update Struct.
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateUpdateStruct
     */
    public function newObjectStateUpdateStruct(): ObjectStateUpdateStruct
    {
        return new ObjectStateUpdateStruct();
    }

    /**
     * Converts the object state SPI value object to API value object.
     *
     * @param \Ibexa\Contracts\Core\Persistence\Content\ObjectState $spiObjectState
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroup|null $objectStateGroup
     * @param string[] $prioritizedLanguages
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectState
     */
    protected function buildDomainObjectStateObject(
        SPIObjectState $spiObjectState,
        APIObjectStateGroup $objectStateGroup = null,
        array $prioritizedLanguages = []
    ): APIObjectState {
        $objectStateGroup = $objectStateGroup ?: $this->loadObjectStateGroup($spiObjectState->groupId, $prioritizedLanguages);

        return new ObjectState(
            [
                'id' => $spiObjectState->id,
                'identifier' => $spiObjectState->identifier,
                'priority' => $spiObjectState->priority,
                'mainLanguageCode' => $spiObjectState->defaultLanguage,
                'languageCodes' => $spiObjectState->languageCodes,
                'names' => $spiObjectState->name,
                'descriptions' => $spiObjectState->description,
                'objectStateGroup' => $objectStateGroup,
                'prioritizedLanguages' => $prioritizedLanguages,
            ]
        );
    }

    /**
     * Converts the object state group SPI value object to API value object.
     *
     * @param \Ibexa\Contracts\Core\Persistence\Content\ObjectState\Group $spiObjectStateGroup
     * @param array $prioritizedLanguages
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroup
     */
    protected function buildDomainObjectStateGroupObject(
        SPIObjectStateGroup $spiObjectStateGroup,
        array $prioritizedLanguages = []
    ): APIObjectStateGroup {
        return new ObjectStateGroup(
            [
                'id' => $spiObjectStateGroup->id,
                'identifier' => $spiObjectStateGroup->identifier,
                'mainLanguageCode' => $spiObjectStateGroup->defaultLanguage,
                'languageCodes' => $spiObjectStateGroup->languageCodes,
                'names' => $spiObjectStateGroup->name,
                'descriptions' => $spiObjectStateGroup->description,
                'prioritizedLanguages' => $prioritizedLanguages,
            ]
        );
    }

    /**
     * Validates input for creating object states/groups and builds the InputStruct object.
     *
     * @param string $identifier
     * @param string $defaultLanguageCode
     * @param string[] $names
     * @param string[]|null $descriptions
     *
     * @return \Ibexa\Contracts\Core\Persistence\Content\ObjectState\InputStruct
     */
    protected function buildCreateInputStruct(
        string $identifier,
        string $defaultLanguageCode,
        array $names,
        ?array $descriptions
    ): InputStruct {
        if (!is_string($identifier) || empty($identifier)) {
            throw new InvalidArgumentValue('identifier', $identifier);
        }

        if (!is_string($defaultLanguageCode) || empty($defaultLanguageCode)) {
            throw new InvalidArgumentValue('defaultLanguageCode', $defaultLanguageCode);
        }

        if (!is_array($names) || empty($names)) {
            throw new InvalidArgumentValue('names', $names);
        }

        if (!isset($names[$defaultLanguageCode])) {
            throw new InvalidArgumentValue('names', $names);
        }

        foreach ($names as $languageCode => $name) {
            try {
                $this->repository->getContentLanguageService()->loadLanguage($languageCode);
            } catch (NotFoundException $e) {
                throw new InvalidArgumentValue('names', $names);
            }

            if (!is_string($name) || empty($name)) {
                throw new InvalidArgumentValue('names', $names);
            }
        }

        $descriptions = $descriptions !== null ? $descriptions : [];

        $inputStruct = new InputStruct();
        $inputStruct->identifier = $identifier;
        $inputStruct->defaultLanguage = $defaultLanguageCode;
        $inputStruct->name = $names;

        $inputStruct->description = [];
        foreach ($names as $languageCode => $name) {
            if (isset($descriptions[$languageCode]) && !empty($descriptions[$languageCode])) {
                $inputStruct->description[$languageCode] = $descriptions[$languageCode];
            } else {
                $inputStruct->description[$languageCode] = '';
            }
        }

        return $inputStruct;
    }

    /**
     * Validates input for updating object states and builds the InputStruct object.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectState $objectState
     * @param string|null $identifier
     * @param string|null $defaultLanguageCode
     * @param string[]|null $names
     * @param string[]|null $descriptions
     *
     * @return \Ibexa\Contracts\Core\Persistence\Content\ObjectState\InputStruct
     */
    protected function buildObjectStateUpdateInputStruct(
        APIObjectState $objectState,
        ?string $identifier,
        ?string $defaultLanguageCode,
        ?array $names,
        ?array $descriptions
    ): InputStruct {
        $inputStruct = new InputStruct();

        if ($identifier !== null && (!is_string($identifier) || empty($identifier))) {
            throw new InvalidArgumentValue('identifier', $identifier);
        }

        $inputStruct->identifier = $identifier !== null ? $identifier : $objectState->identifier;

        if ($defaultLanguageCode !== null && (!is_string($defaultLanguageCode) || empty($defaultLanguageCode))) {
            throw new InvalidArgumentValue('defaultLanguageCode', $defaultLanguageCode);
        }

        $inputStruct->defaultLanguage = $defaultLanguageCode !== null ? $defaultLanguageCode : $objectState->defaultLanguageCode;

        if ($names !== null && (!is_array($names) || empty($names))) {
            throw new InvalidArgumentValue('names', $names);
        }

        $inputStruct->name = $names !== null ? $names : $objectState->getNames();

        if (!isset($inputStruct->name[$inputStruct->defaultLanguage])) {
            throw new InvalidArgumentValue('names', $inputStruct->name);
        }

        foreach ($inputStruct->name as $languageCode => $name) {
            try {
                $this->repository->getContentLanguageService()->loadLanguage($languageCode);
            } catch (NotFoundException $e) {
                throw new InvalidArgumentValue('names', $inputStruct->name);
            }

            if (!is_string($name) || empty($name)) {
                throw new InvalidArgumentValue('names', $inputStruct->name);
            }
        }

        $descriptions = $descriptions !== null ? $descriptions : $objectState->getDescriptions();
        $descriptions = $descriptions !== null ? $descriptions : [];

        $inputStruct->description = [];
        foreach ($inputStruct->name as $languageCode => $name) {
            if (isset($descriptions[$languageCode]) && !empty($descriptions[$languageCode])) {
                $inputStruct->description[$languageCode] = $descriptions[$languageCode];
            } else {
                $inputStruct->description[$languageCode] = '';
            }
        }

        return $inputStruct;
    }

    /**
     * Validates input for updating object state groups and builds the InputStruct object.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroup $objectStateGroup
     * @param string|null $identifier
     * @param string|null $defaultLanguageCode
     * @param string[]|null $names
     * @param string[]|null $descriptions
     *
     * @return \Ibexa\Contracts\Core\Persistence\Content\ObjectState\InputStruct
     */
    protected function buildObjectStateGroupUpdateInputStruct(
        APIObjectStateGroup $objectStateGroup,
        ?string $identifier,
        ?string $defaultLanguageCode,
        ?array $names,
        ?array $descriptions
    ): InputStruct {
        $inputStruct = new InputStruct();

        if ($identifier !== null && empty($identifier)) {
            throw new InvalidArgumentValue('identifier', $identifier);
        }

        $inputStruct->identifier = $identifier !== null ? $identifier : $objectStateGroup->identifier;

        if ($defaultLanguageCode !== null && empty($defaultLanguageCode)) {
            throw new InvalidArgumentValue('defaultLanguageCode', $defaultLanguageCode);
        }

        $inputStruct->defaultLanguage = $defaultLanguageCode !== null ? $defaultLanguageCode : $objectStateGroup->defaultLanguageCode;

        if ($names !== null && empty($names)) {
            throw new InvalidArgumentValue('names', $names);
        }

        $inputStruct->name = $names !== null ? $names : $objectStateGroup->getNames();

        if (!isset($inputStruct->name[$inputStruct->defaultLanguage])) {
            throw new InvalidArgumentValue('names', $inputStruct->name);
        }

        foreach ($inputStruct->name as $languageCode => $name) {
            try {
                $this->repository->getContentLanguageService()->loadLanguage($languageCode);
            } catch (NotFoundException $e) {
                throw new InvalidArgumentValue('names', $inputStruct->name);
            }

            if (!is_string($name) || empty($name)) {
                throw new InvalidArgumentValue('names', $inputStruct->name);
            }
        }

        $descriptions = $descriptions !== null ? $descriptions : $objectStateGroup->getDescriptions();
        $descriptions = $descriptions !== null ? $descriptions : [];

        $inputStruct->description = [];
        foreach ($inputStruct->name as $languageCode => $name) {
            if (isset($descriptions[$languageCode]) && !empty($descriptions[$languageCode])) {
                $inputStruct->description[$languageCode] = $descriptions[$languageCode];
            } else {
                $inputStruct->description[$languageCode] = '';
            }
        }

        return $inputStruct;
    }
}

class_alias(ObjectStateService::class, 'eZ\Publish\Core\Repository\ObjectStateService');
