<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

declare(strict_types=1);

namespace Treo\Listeners;

use Treo\Core\EventManager\Event;
use Treo\Core\Utils\Util;

/**
 * Class Metadata
 */
class Metadata extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function modify(Event $event)
    {
        // get data
        $data = $event->getArgument('data');

        // add owner
        $data = $this->addOwner($data);

        // add onlyActive bool filter
        $data = $this->addOnlyActiveFilter($data);

        // set thumbs sizes to options of asset field type
        $data = $this->setAssetThumbSize($data);

        // prepare multi-lang
        $data = $this->prepareMultiLang($data);

        // set data
        $event->setArgument('data', $data);
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function prepareMultiLang(array $data): array
    {
        // is multi-lang activated
        if (empty($this->getConfig()->get('isMultilangActive'))) {
            return $data;
        }

        // get locales
        if (empty($locales = $this->getConfig()->get('inputLanguageList', []))) {
            return $data;
        }

        /**
         * Set multi-lang params to few fields
         */
        $fields = ['bool', 'enum', 'multiEnum', 'text', 'varchar', 'wysiwyg'];
        foreach ($fields as $field) {
            $data['fields'][$field]['params'][] = [
                'name'    => 'isMultilang',
                'type'    => 'bool',
                'tooltip' => true
            ];
        }

        /**
         * Set multi-lang fields to entity defs
         */
        foreach ($data['entityDefs'] as $scope => $rows) {
            if (!isset($rows['fields']) || !is_array($rows['fields'])) {
                continue 1;
            }
            foreach ($rows['fields'] as $field => $params) {
                if (!empty($params['isMultilang'])) {
                    foreach ($locales as $locale) {
                        // prepare locale
                        $preparedLocale = ucfirst(Util::toCamelCase(strtolower($locale)));

                        // prepare multi-lang field
                        $mField = $field . $preparedLocale;

                        // prepare params
                        $mParams = $params;
                        $mParams['isMultilang'] = false;
                        $mParams['hideParams'] = ['isMultilang'];
                        $mParams['multilangField'] = $field;
                        $mParams['multilangLocale'] = $locale;
                        $mParams['isCustom'] = false;
                        if (isset($params['requiredForMultilang'])) {
                            $mParams['required'] = $params['requiredForMultilang'];
                        }
                        if (in_array($mParams['type'], ['enum', 'multiEnum'])) {
                            $mParams['options'] = $mParams['options' . $preparedLocale];
                            $mParams['default'] = null;
                            $mParams['readOnly'] = true;
                            $mParams['required'] = false;
                            $mParams['hideParams'] = array_merge($mParams['hideParams'], ['options', 'default', 'required', 'isSorted', 'audited', 'readOnly']);
                            $mParams['layoutMassUpdateDisabled'] = true;
                        }

                        $data['entityDefs'][$scope]['fields'][$mField] = $mParams;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function setAssetThumbSize(array $data): array
    {
        foreach ($data['fields']['asset']['params'] as $k => $row) {
            if ($row['name'] === 'previewSize') {
                $data['fields']['asset']['params'][$k]['options'] = empty($data['app']['imageSizes']) ? [] : array_keys($data['app']['imageSizes']);
                break;
            }
        }

        return $data;
    }

    /**
     * Add owner, assigned user, team if it needs
     *
     * @param array $data
     *
     * @return array
     */
    protected function addOwner(array $data): array
    {
        foreach ($data['scopes'] as $scope => $row) {
            // for owner user
            if (!empty($row['hasOwner'])) {
                if (empty($data['entityDefs'][$scope]['fields']['ownerUser'])) {
                    $data['entityDefs'][$scope]['fields']['ownerUser'] = [
                        "type"     => "link",
                        "required" => true,
                        "view"     => "views/fields/owner-user"
                    ];
                }
                if (empty($data['entityDefs'][$scope]['links']['ownerUser'])) {
                    $data['entityDefs'][$scope]['links']['ownerUser'] = [
                        "type"   => "belongsTo",
                        "entity" => "User"
                    ];
                }
                if (empty($data['entityDefs'][$scope]['indexes']['ownerUser'])) {
                    $data['entityDefs'][$scope]['indexes']['ownerUser'] = [
                        "columns" => [
                            "ownerUserId",
                            "deleted"
                        ]
                    ];
                }
            }

            // for assigned user
            if (!empty($row['hasAssignedUser'])) {
                if (empty($data['entityDefs'][$scope]['fields']['assignedUser'])) {
                    $data['entityDefs'][$scope]['fields']['assignedUser'] = [
                        "type"     => "link",
                        "required" => true,
                        "view"     => "views/fields/owner-user"
                    ];
                }
                if (empty($data['entityDefs'][$scope]['links']['assignedUser'])) {
                    $data['entityDefs'][$scope]['links']['assignedUser'] = [
                        "type"   => "belongsTo",
                        "entity" => "User"
                    ];
                }
                if (empty($data['entityDefs'][$scope]['indexes']['assignedUser'])) {
                    $data['entityDefs'][$scope]['indexes']['assignedUser'] = [
                        "columns" => [
                            "assignedUserId",
                            "deleted"
                        ]
                    ];
                }
            }

            // for teams
            if (!empty($row['hasTeam'])) {
                if (empty($data['entityDefs'][$scope]['fields']['teams'])) {
                    $data['entityDefs'][$scope]['fields']['teams'] = [
                        "type" => "linkMultiple",
                        "view" => "views/fields/teams"
                    ];
                }
                if (empty($data['entityDefs'][$scope]['links']['teams'])) {
                    $data['entityDefs'][$scope]['links']['teams'] = [
                        "type"                        => "hasMany",
                        "entity"                      => "Team",
                        "relationName"                => "EntityTeam",
                        "layoutRelationshipsDisabled" => true
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Remove field from index
     *
     * @param array  $indexes
     * @param string $fieldName
     *
     * @return array
     */
    protected function removeFieldFromIndex(array $indexes, string $fieldName): array
    {
        foreach ($indexes as $indexName => $fields) {
            // search field in index
            $key = array_search($fieldName, $fields['columns']);
            // remove field if exists
            if ($key !== false) {
                unset($indexes[$indexName]['columns'][$key]);
            }
        }

        return $indexes;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function addOnlyActiveFilter(array $data): array
    {
        foreach ($data['entityDefs'] as $entity => $row) {
            if (isset($row['fields']['isActive']['type']) && $row['fields']['isActive']['type'] == 'bool') {
                // push
                $data['clientDefs'][$entity]['boolFilterList'][] = 'onlyActive';
            }
        }

        return $data;
    }
}
