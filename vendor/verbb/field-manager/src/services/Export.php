<?php
namespace verbb\fieldmanager\services;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;

use yii\base\Component;

use verbb\supertable\SuperTable;
use benf\neo\Plugin as Neo;

class Export extends Component
{
    // Public Methods
    // =========================================================================

    public function export(array $fieldIds)
    {
        $fields = [];

        foreach ($fieldIds as $fieldId) {
            $field = Craft::$app->fields->getFieldById($fieldId);

            if ($field) {
                $newField = array(
                    'name' => $field->name,
                    'handle' => $field->handle,
                    'instructions' => $field->instructions,
                    'required' => $field->required,
                    'searchable' => $field->searchable,
                    'translationMethod' => $field->translationMethod,
                    'translationKeyFormat' => $field->translationKeyFormat,
                    'type' => \get_class($field),
                    'settings' => $field->settings,
                );

                if (get_class($field) == 'benf\neo\Field') {
                    $newField['settings'] = $this->processNeo($field);
                }

                if (get_class($field) == 'craft\fields\Matrix') {
                    $newField['settings'] = $this->processMatrix($field);
                }

                if (get_class($field) == 'verbb\supertable\fields\SuperTableField') {
                    $newField['settings'] = $this->processSuperTable($field);
                }

                $fields[] = $newField;
            }
        }

        return $fields;
    }

    public function processMatrix($field)
    {
        $fieldSettings = $field->settings;

        $blockTypes = Craft::$app->matrix->getBlockTypesByFieldId($field->id);

        $blockCount = 1;
        foreach ($blockTypes as $blockType) {
            $fieldSettings['blockTypes']['new' . $blockCount] = [
                'name' => $blockType->name,
                'handle' => $blockType->handle,
                'fields' => array(),
            ];

            $fieldCount = 1;
            foreach ($blockType->fields as $blockField) {
                // Case for nested Super Table
                if (get_class($blockField) == 'verbb\supertable\fields\SuperTableField') {
                    $settings = $this->processSuperTable($blockField);
                } else {
                    $settings = $blockField->settings;
                }

                $width = 100;
                $fieldLayout = $blockType->getFieldLayout();
                $fieldLayoutElements = $fieldLayout->getTabs()[0]->elements ?? [];

                if ($fieldLayoutElements) {
                    $fieldLayoutElement = ArrayHelper::firstWhere($fieldLayoutElements, 'field.uid', $blockField->uid);
                    $width = (int)($fieldLayoutElement->width ?? 0) ?: 100;
                }

                $fieldSettings['blockTypes']['new' . $blockCount]['fields']['new' . $fieldCount] = [
                    'name' => $blockField->name,
                    'handle' => $blockField->handle,
                    'required' => $blockField->required,
                    'instructions' => $blockField->instructions,
                    'searchable' => $blockField->searchable,
                    'translationMethod' => $blockField->translationMethod,
                    'translationKeyFormat' => $blockField->translationKeyFormat,
                    'type' => \get_class($blockField),
                    'typesettings' => $settings,
                    'width' => $width,
                ];

                $fieldCount++;
            }

            $blockCount++;
        }

        return $fieldSettings;
    }

    public function processNeo($field)
    {
        $fieldSettings = $field->settings;

        $blockTypes = Neo::$plugin->blockTypes->getByFieldId($field->id);
        $groups = Neo::$plugin->blockTypes->getGroupsByFieldId($field->id);

        foreach ($groups as $i => $group) {
            $fieldSettings['groups'][] = [
                'name' => $group->name,
                'sortOrder' => $group->sortOrder,
            ];
        }

        foreach ($blockTypes as $i => $blockType) {
            $childBlocks = $blockType->childBlocks;

            if (!is_array($childBlocks)) {
                $childBlocks = Json::decodeIfJson((string)$childBlocks);
            }

            $fieldSettings['blockTypes']['new' . ($i + 1)] = [
                'name' => $blockType->name,
                'handle' => $blockType->handle,
                'sortOrder' => (int)$blockType->sortOrder,
                'maxBlocks' => (int)$blockType->maxBlocks,
                'maxSiblingBlocks' => (int)$blockType->maxSiblingBlocks,
                'maxChildBlocks' => (int)$blockType->maxChildBlocks,
                'childBlocks' => $childBlocks,
                'topLevel' => (bool)$blockType->topLevel,
                'fieldLayout' => $blockType->fieldLayout->getConfig(),
            ];
        }

        return $fieldSettings;
    }

    public function processSuperTable($field)
    {
        $fieldSettings = $field->settings;

        $blockTypes = SuperTable::$plugin->getService()->getBlockTypesByFieldId($field->id);

        $blockCount = 1;
        foreach ($blockTypes as $blockType) {
            $fieldSettings['blockTypes']['new' . $blockCount] = array(
                'fields' => array(),
            );

            $fieldCount = 1;
            foreach ($blockType->fields as $blockField) {
                // Case for nested Matrix
                if (get_class($blockField) == 'craft\fields\Matrix') {
                    $settings = $this->processMatrix($blockField);
                } else {
                    $settings = $blockField->settings;
                }

                $fieldSettings['blockTypes']['new' . $blockCount]['fields']['new' . $fieldCount] = array(
                    'name' => $blockField->name,
                    'handle' => $blockField->handle,
                    'required' => $blockField->required,
                    'instructions' => $blockField->instructions,
                    'searchable' => $blockField->searchable,
                    'translationMethod' => $blockField->translationMethod,
                    'translationKeyFormat' => $blockField->translationKeyFormat,
                    'type' => \get_class($blockField),
                    'typesettings' => $settings,
                );

                $fieldCount++;
            }

            $blockCount++;
        }

        return $fieldSettings;
    }
}
