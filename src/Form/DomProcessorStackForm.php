<?php

namespace Drupal\dom_processor\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the DomProcessorStack add and edit forms.
 */
class DomProcessorStackForm extends EntityForm {

  /**
   * Constructs an DomProcessorStackForm object.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query.
   */
  public function __construct(PluginManagerInterface $analyzer_plugin_manager, PluginManagerInterface $processor_plugin_manager, QueryFactory $entity_query) {
    $this->analyzerPluginManager = $analyzer_plugin_manager;
    $this->processorPluginManager = $processor_plugin_manager;
    $this->entityQuery = $entity_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.dom_processor.semantic_analyzer'),
      $container->get('plugin.manager.dom_processor.data_processor'),
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $entity = $this->entity;

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#description' => $this->t("Label for the DOM Processor Stack."),
      '#required' => TRUE,
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => array(
        'exists' => array($this, 'exist'),
      ),
      '#disabled' => !$entity->isNew(),
    );

    $form['semantic_analyzers'] = $this->createPluginSelect($this->t('Semantic Analyzers'), 'semantic-analyzers', $this->analyzerPluginManager, $entity->getAnalyzers());

    $variants_wrapper = Html::getUniqueId('variants');
    $form['variants'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => $this->t('Variants'),
      '#prefix' => '<div id="' . $variants_wrapper . '">',
      '#suffix' => '</div>',
    ];

    $variants = $form_state->get('variants');
    if (!$variants) {
      $variants = $entity->getVariants();
      $form_state->set('variants', $variants);
    }
    $default_variant = $variants['default'];
    unset($variants['default']);
    $form['variants']['default'] = [
      '#type' => 'details',
      '#title' => $this->t('Default'),
    ];

    $form['variants']['default']['processors'] = $this->createPluginSelect($this->t('Data Processors'), 'processors', $this->processorPluginManager, $default_variant['processors']);

    foreach($variants as $id => $variant) {

      $form['variants'][$id] = [
        '#type' => 'details',
        '#title' => $this->t('@label', ['@label' => $variant['label']]),
        '#attributes' => [
          'class' => ['dom-processor-variant-group']
        ],
        'label' => [
          '#type' => 'textfield',
          '#title' => 'Variant Name',
          '#attributes' => [
            'class' => ['dom-processor-variant-group__label'],
          ],
          '#default_value' => $variant['label'],
        ],
        'id' => [
          '#type' => 'machine_name',
          '#machine_name' => array(
            'source' => ['variants', $id, 'label'],
          ),
          '#disabled' => FALSE,
          '#default_value' => empty($variant['is_new']) ? $id : '',
        ],
        'remove' => [
          '#type' => 'submit',
          '#name' => 'remove-' . $id,
          '#value' => $this->t('Remove Variant'),
          '#submit' => [[$this, 'removeVariantSubmit']],
          '#limit_validation_errors' => [['variants']],
          '#ajax' => [
            'callback' => [$this, 'deliverVariants'],
            'wrapper' => $variants_wrapper,
            'effect' => 'fade',
          ],
          '#weight' => 999,
          '#variant_id' => $id,
        ],
      ];

      $form['variants'][$id]['processors'] = $this->createPluginSelect($this->t('Data Processors'), 'processors', $this->processorPluginManager, $variant['processors']);
    }

    $form['variants']['_add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Variant'),
      '#submit' => [[$this, 'addVariantSubmit']],
      '#limit_validation_errors' => [['variants']],
      '#ajax' => [
        'callback' => [$this, 'deliverVariants'],
        'wrapper' => $variants_wrapper,
        'effect' => 'fade',
      ],
    ];

    // You will need additional form elements for your custom properties.
    return $form;
  }

  public function addVariantSubmit(array $form, FormStateInterface $form_state) {
    $variants = [];
    $submitted_values = $this->getVariantValues($form_state->getValue('variants'));
    unset($submitted_values['_add']);
    foreach ($form_state->get('variants') as $id => $variant) {
      if (!empty($submitted_values[$id])) {
        $variant = $submitted_values[$id];
        if (!empty($submitted_values[$id]['id'])) {
          $id = $submitted_values[$id]['id'];
        }
        $variants[$id] = $variant;
      }
      else {
        $variants[$id] = $variant;
      }
    }
    $count = count($variants);
    $new_id = 'variant' . $count;
    $variants[$new_id] = [
      'label' => 'Variant ' . $count,
      'processors' => [],
      'is_new' => TRUE,
    ];
    $form_state->set('variants', $variants);
    $form_state->setRebuild(TRUE);
  }

  public function removeVariantSubmit(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $id = $button['#variant_id'];
    $variants = $form_state->get('variants');
    unset($variants[$id]);
    $form_state->set('variants', $variants);
    $form_state->setRebuild(TRUE);
  }

  public function deliverVariants(array &$form, FormStateInterface $form_state) {
    return $form['variants'];
  }

  protected function getPluginSelectValues(array $form_state) {
    $result = [];

    uasort($form_state['order'], ['\Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
    foreach ($form_state['order'] as $plugin_id => $order) {
      if (!empty($form_state['options'][$plugin_id])) {
        $result[$plugin_id] = [];
      }
    }

    return $result;
  }

  protected function getVariantValues(array $form_state) {
    $result = [];
    unset($form_state['_add']);

    foreach ($form_state as $variant_id => $variant) {
      if (empty($variant['processors'])) {
        $variant['processors'] = [];
      }
      if (!empty($variant['id'])) {
        $variant_id = $variant['id'];
        unset($variant['id']);
      }
      $variant['processors'] = $this->getPluginSelectValues($variant['processors']);
      $result[$variant_id] = $variant;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $entity->setAnalyzers($this->getPluginSelectValues($form_state->getValue('semantic_analyzers')));
    $entity->setVariants($this->getVariantValues($form_state->getValue('variants')));
    $status = $entity->save();

    if ($status) {
      drupal_set_message($this->t('Saved the %label DomProcessorStack.', array(
        '%label' => $entity->label(),
      )));
    }
    else {
      drupal_set_message($this->t('The %label DomProcessorStack was not saved.', array(
        '%label' => $entity->label(),
      )));
    }

    $form_state->setRedirect('entity.dom_processor_stack.collection');
  }

  /**
   * Helper function to check whether an DomProcessorStack configuration entity exists.
   */
  public function exist($id) {
    $entity = $this->entityQuery->get('dom_processor_stack')
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

  protected function createPluginSelect($title, $plugin_type, PluginManagerInterface $plugin_manager, array $enabled_defaults = []) {

    $defaults = [];
    $order = [];
    foreach ($enabled_defaults as $plugin_id => $config) {
      $defaults[$plugin_id] = $plugin_id;
      $order[$plugin_id] = TRUE;
    }

    $element = [
      '#type' => 'fieldset',
      '#attributes' => [
        'class' => [
          'dom-processor-plugin-container',
        ],
      ],
      '#title' => $title,
      '#tree' => TRUE,
    ];

    $options = [];
    foreach ($plugin_manager->getDefinitions() as $plugin_definition) {
      $options[$plugin_definition['id']] = $plugin_definition['label'];
      $order[$plugin_definition['id']] = TRUE;
    }

    $type_class_prefix = Html::cleanCssIdentifier($plugin_type);
    $element['options'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $defaults,
      '#attributes' => [
        'class' => ['dom-processor-plugin-selection'],
        'data-plugin-type' => $type_class_prefix,
      ],
    ];

    $group_class = Html::cleanCssIdentifier($plugin_type . '-order-weight');
    $element['order'] = [
      '#type' => 'table',
      '#header' => [$this->t('Order'), $this->t('Weight')],
      '#empty' => $this->t('There are no plugins enabled.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => $group_class,
        ],
      ],
      '#attached' => [
        'library' => [
          'dom_processor/admin',
        ]
      ],
    ];

    foreach ($order as $id => $noop) {
      if (!empty($options[$id])) {
        $element['order'][$id] = [
          '#attributes' => [
            'class' => [
              'draggable',
              $type_class_prefix . '-order-' . $id,
            ]
          ],
          '#weight' => 0,
          'label' => [
            '#plain_text' => $options[$id],
          ],
          'weight' => [
            '#type' => 'weight',
            '#title' => $this->t('Weight'),
            '#title_display' => 'invisible',
            '#default_value' => 0,
            '#attributes' => [
              'class' => [
                $group_class,
              ]
            ],
          ],
        ];
      }
    }

    return $element;
  }
}
