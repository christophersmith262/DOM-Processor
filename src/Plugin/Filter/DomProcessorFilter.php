<?php

namespace Drupal\dom_processor\Plugin\Filter;


use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\dom_processor\DomProcessor\DomProcessorInterface;
use Drupal\dom_processor\DomProcessor\DomProcessorResult;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A filter to transform paragraph embed codes into rendered entities.
 *
 * @Filter(
 *   id = "dom_processor",
 *   title = @Translation("Apply DOM Processor"),
 *   description = @Translation("Applies DOM Processing stacks to user input."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   settings = {
 *   }
 * )
 */
class DomProcessorFilter extends FilterBase implements ContainerFactoryPluginInterface {

  protected $stackStorage = [];

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, DomProcessorInterface $dom_processor) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stackStorage = $entity_type_manager->getStorage('dom_processor_stack');
    $this->domProcessor = $dom_processor;
  }

  /**
   * {@inheritdoc}
   */
  static public function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('dom_processor.dom_processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = DomProcessorResult::create([
      'markup' => $text,
    ]);
    foreach ($this->getConfiguration()['settings'] as $id => $variant) {
      $stack = $this->stackStorage->load($id);
      $result = $result->merge($this->domProcessor->process($result->get('markup'), $stack, $variant, [
        'langcode' => $langcode,
      ]));
    }
    $filter_result = new FilterProcessResult($result->get('markup'));
    if ($result->get('attachments')) {
      $filter_result->setAttachments($result->get('attachments'));
    }
    return $filter_result;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $options = [];
    foreach ($this->stackStorage->loadMultiple() as $id => $stack) {
      $options[$id] = $stack->label();
    }

    $defaults = [];
    $settings = $this->getConfiguration()['settings'];
    foreach ($settings as $id => $variant) {
      $defaults[$id] = $id;
    }

    $form['stacks'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => [
        'class' => [
          'dom-processor-plugin-container',
        ],
      ],
    ];

    $form['stacks']['enabled'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Stacks'),
      '#options' => $options,
      '#default_value' => $defaults,
      '#attributes' => [
        'class' => ['dom-processor-plugin-selection'],
        'data-plugin-type' => 'stacks',
      ],
    ];

    $group_class = 'stacks-order-weight';
    $form['stacks']['order'] = [
      '#type' => 'table',
      '#header' => [$this->t('Order'), $this->t('Variant'), $this->t('Weight')],
      '#empty' => $this->t('There are no stacks available.'),
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

    $ordered = [];
    foreach ($settings as $id => $variant_name) {
      $ordered[$id] = [];
    }
    foreach ($this->stackStorage->loadMultiple() as $id => $stack) {
      $ordered[$id] = $stack;
    }

    foreach ($ordered as $id => $stack) {
      $variant_options = [];
      foreach ($stack->getVariants() as $variant_id => $variant) {
        $variant_options[$variant_id] = $variant['label'];
      }
      $form['stacks']['order'][$id] = [
        '#attributes' => [
          'class' => [
            'draggable',
            'stacks-order-' . $id,
          ]
        ],
        '#weight' => 0,
        'label' => [
          '#plain_text' => $stack->label(),
        ],
        'variant' => [
          '#type' => 'select',
          '#options' => $variant_options,
          '#default_value' => !empty($settings[$id]) ? $settings[$id] : 'default',
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

    $form['#element_validate'][] = [$this, 'validateSettingsForm'];

    return $form;
  }

  public function validateSettingsForm(array $element, FormStateInterface $form_state) {
    $parents = $element['#parents'];
    $submitted_values = $form_state->getValue($parents)['stacks'];
    $value = [];
    foreach ($submitted_values['order'] as $id => $info) {
      if (!empty($submitted_values['enabled'][$id])) {
        $value[$id] = $info['variant'];
      }
    }
    $form_state->setValue($parents, $value);
  }
}
