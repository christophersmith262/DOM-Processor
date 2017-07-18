<?php

namespace Drupal\dom_processor\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\dom_processor\DomProcessorStackInterface;

/**
 * Defines a DOM processor stack entity.
 *
 * @ConfigEntityType(
 *   id = "dom_processor_stack",
 *   label = @Translation("DOM Processor Stack"),
 *   handlers = {
 *     "list_builder" = "Drupal\dom_processor\Controller\DomProcessorStackListBuilder",
 *     "form" = {
 *       "add": "Drupal\dom_processor\Form\DomProcessorStackForm",
 *       "edit": "Drupal\dom_processor\Form\DomProcessorStackForm",
 *       "delete": "Drupal\dom_processor\Form\DomProcessorStackDeleteForm"
 *     }
 *   },
 *   config_prefix = "dom_processor_stack",
 *   config_export = {
 *     "id",
 *     "label",
 *     "analyzers",
 *     "variants",
 *   },
 *   admin_permission = "administer dom processors",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/content/dom-processors/{dom_processor_stack}",
 *     "delete-form" = "/admin/config/content/dom-processors/{dom_processor_stack}/delete"
 *   }
 * )
 */
class DomProcessorStack extends ConfigEntityBase implements DomProcessorStackInterface {

  protected $analyzers = [];
  protected $variants = [];

  public function getVariant($name = 'default') {
    $variants = $this->getVariants();
    return !empty($variants[$name]) ? $variants[$name] : NULL;
  }

  public function getVariants() {
    $variants = $this->variants + [
      'default' => [
        'processors' => [],
      ],
    ];
    $variants['default']['label'] = 'Default';
    return $variants;
  }

  public function setVariant($name, array $variant) {
    $this->variants[$name] = $variant;
  }

  public function setVariants(array $variants) {
    $this->variants = $variants;
  }

  public function getAnalyzers() {
    return $this->analyzers;
  }

  public function setAnalyzers(array $analyzers) {
    $this->analyzers = $analyzers;
  }
}
