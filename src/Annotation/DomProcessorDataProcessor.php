<?php

namespace Drupal\dom_processor\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a dom_processor semantic data processor plugin.
 *
 * Plugin Namespace: Plugin\dom_processor\semantic_analyzer
 *
 * @Annotation
 */
class DomProcessorDataProcessor extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $title;

  /**
   * The description of the plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;
}

