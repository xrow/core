<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Core\FieldType\EmailAddress;

use Ibexa\Contracts\Core\FieldType\Indexable;
use Ibexa\Contracts\Core\Persistence\Content\Field;
use Ibexa\Contracts\Core\Persistence\Content\Type\FieldDefinition;
use Ibexa\Contracts\Core\Search;

/**
 * Indexable definition for EmailAddress field type.
 */
class SearchField implements Indexable
{
    public function getIndexData(Field $field, FieldDefinition $fieldDefinition)
    {
        return [
            new Search\Field(
                'value',
                $field->value->data,
                new Search\FieldType\StringField()
            ),
            new Search\Field(
                'fulltext',
                $field->value->data,
                new Search\FieldType\FullTextField([
                    'space_normalize',
                    'latin1_lowercase',
                    'ascii_lowercase',
                    'cyrillic_lowercase',
                    'greek_lowercase',
                    'latin_lowercase',
                    'latin-exta_lowercase',
                ], false)
            ),
        ];
    }

    public function getIndexDefinition()
    {
        return [
            'value' => new Search\FieldType\StringField(),
        ];
    }

    /**
     * Get name of the default field to be used for matching.
     *
     * As field types can index multiple fields (see MapLocation field type's
     * implementation of this interface), this method is used to define default
     * field for matching. Default field is typically used by Field criterion.
     *
     * @return string
     */
    public function getDefaultMatchField()
    {
        return 'value';
    }

    /**
     * Get name of the default field to be used for sorting.
     *
     * As field types can index multiple fields (see MapLocation field type's
     * implementation of this interface), this method is used to define default
     * field for sorting. Default field is typically used by Field sort clause.
     *
     * @return string
     */
    public function getDefaultSortField()
    {
        return $this->getDefaultMatchField();
    }
}

class_alias(SearchField::class, 'eZ\Publish\Core\FieldType\EmailAddress\SearchField');
