<?php

namespace Drupal\dom_processor\Plugin\dom_processor;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides a common plugin manager for all dom_processor plugins.
 */
class PluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct($type, \Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    list ($plugin_interface, $annotation) = $this->getPluginTypeInfo($type);
    parent::__construct("Plugin/dom_processor/$type", $namespaces, $module_handler, $plugin_interface, $annotation);
    $this->alterInfo("dom_processor_{$type}_info");
    $this->setCacheBackend($cache_backend, "dom_processor_{$type}_info_plugins");
    $this->factory = new PluginFactory($this->getDiscovery());
  }

  /**
   * Helper method to map plugin types to interfaces / annotations.
   *
   * @param string type
   *   The dom_processor plugin type.
   *
   * @return array
   *   A tuple where the first element is the fully qualified interface name and
   *   the second element is the fully qualified annotation name.
   */
  protected function getPluginTypeInfo($type) {
    switch ($type) {
      case 'semantic_analyzer':
        return array(
          'Drupal\dom_processor\Plugin\dom_processor\SemanticAnalyzerInterface',
          'Drupal\dom_processor\Annotation\DomProcessorSemanticAnalyzer'
        );
      case 'data_processor':
        return array(
          'Drupal\dom_processor\Plugin\dom_processor\DataProcessorInterface',
          'Drupal\dom_processor\Annotation\DomProcessorDataProcessor'
        );
      default:
        throw new \Exception("Invalid plugin type '$type'");
    }
  }

}
