<?php

namespace Drupal\edit_in_place_field\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Class EditInPlaceStringForm.
 *
 * @package Drupal\edit_in_place_field\Form
 */
class EditInPlaceStringForm extends EditInPlaceFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_in_place_string_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getInPlaceField (array $data): array {
    $text_fields = [];
    if ($data['cardinality'] !== -1) {
      for($delta = 0; $delta < $data['cardinality']; $delta++) {
        $text_fields[] = [
          '#type' => 'textfield',
          '#default_value' => isset($data['values'][$delta]) ? $data['values'][$delta]['value'] : '',
          '#name' => $data[self::VAR_FIELD_NAME].'[]',
          '#multiple' => TRUE
        ];
      }
    }
    else {
      $delta = 0;
      while (isset($data['values'][$delta])) {
        $text_fields[] = [
          '#type' => 'textfield',
          '#default_value' => $data['values'][$delta]['value'],
          '#name' => $data[self::VAR_FIELD_NAME].'[]',
          '#multiple' => TRUE
        ];
        $delta++;
      }
      $text_fields[] = [
        '#type' => 'textfield',
        '#default_value' => '',
        '#name' => $data[self::VAR_FIELD_NAME].'[]',
        '#multiple' => TRUE
      ];
    }
    return $text_fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function processResponse(array $data): AjaxResponse {
    $field_values = $data[self::VAR_FIELD_VALUES];
    $field_name = $data[self::VAR_FIELD_NAME];

    // Try to load the entity to update.
    $error_response = new AjaxResponse();
    $entity = $this->loadEntity($data, $error_response);
    if (empty($entity)) {
      return $error_response;
    }

    // try to update the entity.
    try {
      $saved_values = [];
      foreach($field_values as $value) {
        if (trim($value) !== '') {
          $saved_values[] = $value;
        }
      }
      $entity->{$field_name} = $saved_values;
      $entity->save();
    }
    catch(EntityStorageException $e) {
      return $this->getResponse(self::ERROR_DATA_CANNOT_BE_SAVED, ['error' => $e->getMessage()]);
    }


    // Get cardinality of current field.
    $definition = $entity->{$field_name}->getFieldDefinition();
    $fieldStorageDefinition = $definition->getFieldStorageDefinition();
    $cardinality = $fieldStorageDefinition->getCardinality();

    // Render entities labels.
    $values_html = $this->theme->render('edit_in_place_string_values', [
      'values' => $saved_values,
      'multiple' => ($cardinality === 1) ? FALSE : TRUE,
    ]);

    // Return ajax response.
    return $this->reloadAndRebind($data[self::VAR_AJAX_REPLACE_ID], $values_html);
  }

}
