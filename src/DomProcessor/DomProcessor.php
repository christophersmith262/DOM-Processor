<?php

namespace Drupal\dom_processor\DomProcessor;

use Drupal\Component\Utility\Html;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dom_processor\DomProcessorStackInterface;

class DomProcessor implements DomProcessorInterface {

  protected $storage;
  protected $pluginManagers = [];

  public function __construct(EntityTypeManagerInterface $entity_type_manager, PluginManagerInterface $analyzer_plugin_manager, PluginManagerInterface $processor_plugin_manager) {
    $this->storage = $entity_type_manager->getStorage('dom_processor_stack');
    $this->pluginManagers['analyzer'] = $analyzer_plugin_manager;
    $this->pluginManagers['processor'] = $processor_plugin_manager;
  }

  public function process($markup, $processor_stack, $variant_name = 'default', array $data = []) {
    if (is_string($processor_stack)) {
      $processor_stack = $this->storage->load($processor_stack);
    }

    $plugins = $this->getPlugins($processor_stack, $variant_name);
    $document = Html::load($markup);
    $xpath = new \DOMXPath($document);

    // Get the body element which contains the actual content.
    $body_nodes = $xpath->query('//body');
    $body_node = $body_nodes->item(0);

    $data = $this->createData($body_node, $xpath, $data);
    $result = $this->applyPlugins($data, $plugins);
    $result = $result->merge([
      'markup' => Html::serialize($document)
    ]);
    return $result;
  }

  protected function applyPlugins(SemanticDataInterface $data, array $plugins) {
    $original_data = $data;
    $result = $this->createResult();

    try {
      $data = $this->applyAnalyzers($data, $plugins);

      if ($data->node()->hasChildNodes()) {
        foreach ($data->node()->childNodes as $child_node) {
          $result->merge($this->applyPlugins($data->push($child_node), $plugins));
        }
      }
    }
    catch (DomProcessorError $e) {
      if (!$data->isRoot()) {
        $data->node()->parentNode->removeChild($data->node());
      }
      $data = $data->tag('error', [
        'exception' => $e,
      ]);
    }

    $result = $this->applyProcessors($data, $result, $plugins);
    if ($result->needsReprocess()) {
      $result = $this->applyPlugins($original_data, $plugins);
    }
    return $result;
  }

  protected function createData(\DOMNode $node, \DOMXpath $xpath, array $data) {
    return SemanticData::create($node, $xpath, $data);
  }

  protected function createResult() {
    return DomProcessorResult::create([]);
  }

  protected function applyAnalyzers(SemanticDataInterface $data, array $plugins) {
    $data = $data->clear(['error', 'warning']);
    foreach ($plugins['analyzer'] as $plugin) {
      try {
        $data = $plugin->analyze($data);
      }
      catch (DomProcessorWarning $e) {
        $data = $data->tag('warning', [
          'exception' => $e,
        ]);
      }
    }
    return $data;
  }

  protected function applyProcessors(SemanticDataInterface $data, DomProcessorResultInterface $result, array $plugins) {
    foreach ($plugins['processor'] as $plugin) {
      $result = $result->merge($plugin->process($data, $result));
    }
    return $result;
  }

  protected function getPlugins(DomProcessorStackInterface $processor_stack, $variant_name = 'default') {
    $plugins = [
      'analyzer' => [],
      'processor' => [],
    ];

    $analyzers = $processor_stack->getAnalyzers();
    foreach ($analyzers as $plugin_id => $config) {
      $plugins['analyzer'][$plugin_id] = $this->pluginManagers['analyzer']->createInstance($plugin_id, $config);
    }

    $variant = $processor_stack->getVariant($variant_name);
    if ($variant) {
      foreach ($variant['processors'] as $plugin_id => $config) {
        $plugins['processor'][$plugin_id] = $this->pluginManagers['processor']->createInstance($plugin_id, $config);
      }
    }

    return $plugins;
  }
}
