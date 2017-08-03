<?php

namespace Drupal\dom_processor\DomProcessor;

use Drupal\Component\Utility\Html;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dom_processor\DomProcessorStackInterface;

class DomProcessor implements DomProcessorInterface {

  protected $storage;
  protected $pluginManagers = [];
  protected $data = [];

  public function __construct(EntityTypeManagerInterface $entity_type_manager, PluginManagerInterface $analyzer_plugin_manager, PluginManagerInterface $processor_plugin_manager) {
    $this->storage = $entity_type_manager->getStorage('dom_processor_stack');
    $this->pluginManagers['analyzer'] = $analyzer_plugin_manager;
    $this->pluginManagers['processor'] = $processor_plugin_manager;
  }

  public function prepare(array $data) {
    array_push($this->data, $data);
  }

  public function prepared() {
    return !!$this->data;
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

    // Fill the initial data for the processor.
    $data = $this->createData($body_node, $xpath, $data);
    if ($this->prepared()) {
      $merge_data = end($this->data);
      foreach ($merge_data as $key => $value) {
        $data = $data->tag($key, $value);
      }
    }

    $result = $this->applyPlugins($data, $plugins);
    $result = $result->merge([
      'markup' => Html::serialize($document)
    ]);

    array_pop($this->data);
    return $result;
  }

  protected function applyPlugins(SemanticDataInterface $data, array $plugins) {
    $node_stack = [];
    $data_stack = [];
    array_push($node_stack, $data);
    $result = $this->createResult();
    $counter = 0;

    while ($node_stack || $data_stack) {

      if ($node_stack) {
        while($data = array_pop($node_stack)) {
          try {
            $data = $this->applyAnalyzers($data, $plugins);
            array_push($data_stack, $data);
          }
          catch (DomProcessorError $e) {
            if (!$data->isRoot()) {
              $data->node()->parentNode->removeChild($data->node());
            }
            $data = $data->tag('error', [
              'exception' => $e,
            ]);
          }

          if ($data->node()->hasChildNodes()) {
            $child_nodes = $data->node()->childNodes;
            for ($i = $child_nodes->length - 1; $i >= 0; $i--) {
              array_push($node_stack, $data->push($child_nodes->item($i)));
            }
          }
        }
      }

      if ($data_stack) {
        while($data = array_pop($data_stack)) {
          $next_result = $this->applyProcessors($data, $result, $plugins);

          if ($next_result->needsReprocess()) {
            if ($next_result->reprocessData()) {
              $data = $next_result->reprocessData();
            }
            array_push($node_stack, $data);
            break;
          }
          else {
            $result = $next_result;
          }
        }
      }
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
        if (!$data instanceof SemanticDataInterface) {
          throw new \Exception('Data analyzer returned an invalid value');
        }
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
    foreach ($plugins['processor'] as $plugin_name => $plugin) {
      $result = $plugin->process($data, $result);
      if (!$result instanceof DomProcessorResultInterface) {
        throw new \Exception('Data processor "' . $plugin_name . '" returned an invalid value');
      }
      if ($result->needsReprocess()) {
        return $result;
      }
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
