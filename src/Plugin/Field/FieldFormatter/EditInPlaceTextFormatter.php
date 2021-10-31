<?php

namespace Drupal\edit_in_place_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;

/**
 * Plugin implementation of the 'edit_in_place_field_text' formatter.
 *
 * @FieldFormatter(
 *   id = "edit_in_place_field_text",
 *   label = @Translation("Edit in place"),
 *   field_types = {
 *     "string",
 *     "uri",
 *   }
 * )
 */
class EditInPlaceTextFormatter extends StringFormatter implements ContainerFactoryPluginInterface{

  /**
   * Cache about list of entities.
   *
   * @var array
   */
  protected $cacheList = [];

  /**
   * The form builder service
   *
   * @var FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Machine name of field that will replace the label.
   *
   * @var mixed|null
   */
  protected $labelSubstitution;

  /**
   * Construct a EditInPlaceFieldReferenceFormatter object
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service
   * @param FormBuilderInterface $form_builder
   *   The form builder service
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings
    , EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $entity_type_manager);

    $this->formBuilder = $form_builder;
    $this->labelSubstitution = $this->getSetting('label_substitution');
  }


  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    if (!\Drupal::currentUser()->hasPermission('edit in place field editing permission')) {
      return parent::viewElements($items, $langcode);
    }
    $elements = [];
    $cardinality = 1;

    $field_values = [];
    if (!empty($items) ) {
      // Get cardinality of current field.
      $cardinality = $items->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();

      /**
       * @var string $delta
       * @var \Drupal\Core\Field\Plugin\Field\FieldType\StringItem $entity
       */
      foreach($items as $delta => $entity) {
        $field_values[] = $entity->getValue();
      }
    }

    // 'edit-in-place-replace-' + field_name + '-' + parent_entity_id + '-' + parent_entity_language_code
    $ajax_call_replace = 'edit-in-place-replace-'.$items->getFieldDefinition()->getName().'-'.$items->getEntity()->id().'-'.$items->getEntity()->language()->getId();

    $elements[0] = [
      'field_container' => [
        '#attached' => [
          'library' => ['edit_in_place_field/edit_in_place'],
        ],
        '#type' => 'fieldset',
        '#title' => t('Edit'),
        '#attributes' => [
          'class' => ['edit-in-place-clickable', 'edit-in-place-clickable-init', $ajax_call_replace]
        ],
        'base_render' => [
          '#theme' => 'edit_in_place_string_values',
          '#multiple' => ($cardinality !== 1),
          '#values' => array_column($field_values, 'value'),
        ],
        'form_container' =>  $this->formBuilder->getForm('Drupal\edit_in_place_field\Form\EditInPlaceStringForm', [
          'values' => $field_values,
          'cardinality' => $cardinality,
          'entity_type' => $items->getEntity()->getEntityTypeId(),
          'entity_id' => $items->getEntity()->id(),
          'field_name' => $items->getName(),
          'ajax_replace' => $ajax_call_replace,
        ])
      ]
    ];
    return $elements;
  }
}
