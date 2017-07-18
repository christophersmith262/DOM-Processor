<?php

namespace Drupal\Tests\dom_processor\Unit;

use Drupal\Component\Utility\Html;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\dom_processor\DomProcessorStackInterface;
use Drupal\dom_processor\DomProcessor\SemanticDataInterface;
use Drupal\dom_processor\DomProcessor\DomProcessor;
use Drupal\dom_processor\DomProcessor\DomProcessorResultInterface;
use Drupal\dom_processor\Plugin\dom_processor\DataProcessorInterface;
use Drupal\dom_processor\Plugin\dom_processor\SemanticAnalyzerInterface;
use Drupal\Tests\UnitTestCase;

class TestAnalyzer implements SemanticAnalyzerInterface {

  public $expected = [];
  public $actual = [];

  public function __construct($input) {
    $document = Html::load($input);
    $xpath = new \DOMXPath($document);
    $body_nodes = $xpath->query('//body');
    $this->recordNode($body_nodes->item(0));
  }

  protected function recordNode(\DomNode $node) {
    $this->expected[] = $node;
    if ($node->hasChildNodes()) {
      foreach($node->childNodes as $child_node) {
        $this->recordNode($child_node);
      }
    }
  }

  public function analyze(SemanticDataInterface $data) {
    $this->actual[] = $data->node();
    return $data;
  }
}

class TestProcessor implements DataProcessorInterface {

  public $expected = [];
  public $actual = [];

  public function __construct($input) {
    $document = Html::load($input);
    $xpath = new \DOMXPath($document);
    $body_nodes = $xpath->query('//body');
    $this->recordNode($body_nodes->item(0));
  }

  protected function recordNode(\DomNode $node) {
    if ($node->hasChildNodes()) {
      foreach($node->childNodes as $child_node) {
        $this->recordNode($child_node);
      }
    }
    $this->expected[] = $node;
  }

  public function process(SemanticDataInterface $data, DomProcessorResultInterface $result) {
    $this->actual[] = $data->node();
    return $result;
  }
}

/**
 * @coversDefaultClass Drupal\dom_processor\DomProcessor\DomProcessor
 *
 * @group dom_processor
 */
class DomProcessorUnitTest extends UnitTestCase {

  protected function createPluginManager(array $plugins) {
    $prophecy = $this->prophesize(PluginManagerInterface::CLASS);
    foreach ($plugins as $id => $info) {
      $prophecy->createInstance($id, $info['config'])->willReturn($info['instance']);
    }
    return $prophecy->reveal();
  }

  protected function createProcessorStack(array $plugins) {
    $prophecy = $this->prophesize(DomProcessorStackInterface::CLASS);

    $analyzers = [];
    foreach ($plugins['analyzer'] as $id => $info) {
      $analyzers[$id] = $info['config'];
    }
    $prophecy->getAnalyzers()->willReturn($analyzers);

    $variant = [
      'processor' => [],
    ];
    foreach ($plugins['processor'] as $id => $info) {
      $variant['processor'][$id] = $info['config'];
    }
    $prophecy->getVariant('default')->willReturn($variant);

    return $prophecy->reveal();
  }

  protected function createProcessor(array $plugins) {
    $analyzer_plugin_manager = $this->createPluginManager($plugins['analyzer']);
    $processor_plugin_manager = $this->createPluginManager($plugins['processor']);
    $processor = new DomProcessor($analyzer_plugin_manager, $processor_plugin_manager);
    return $processor;
  }

  protected function createTestCase($input, $output = NULL, array $data = []) {
    if (!isset($output)) {
      $output = $input;
    }
    return [[
      'input' => $input,
      'output' => $output,
      'plugins' => [
        'analyzer' => [
          'test_analyzer' => [
            'config' => [
            ],
            'instance' => new TestAnalyzer($input),
          ],
        ],
        'processor' => [
          'test_processor' => [
            'config' => [
            ],
            'instance' => new TestProcessor($input),
          ],
        ],
      ],
      'data' => $data,
    ]];
  }

  public function processDataProvider() {
    return [
      $this->createTestCase('<html></html>', ''),
      $this->createTestCase('<div><div><span>test</span></div></div>'),
    ];
  }

  /**
   * @dataProvider processDataProvider
   */
  public function testProcess(array $data) {
    $processor = $this->createProcessor($data['plugins']);
    $processor_stack = $this->createProcessorStack($data['plugins']);
    $result = $processor->process($data['input'], $processor_stack, 'default', $data['data']);
    foreach ($data['plugins'] as $type => $plugins) {
      foreach ($plugins as $plugin) {
        $this->assertTrue($this->isSameNodeList($plugin['instance']->expected, $plugin['instance']->actual));
      }
    }
    $this->assertEquals($data['output'], $result->get('markup'));
  }

  protected function isSameNodeList(array $list1, array $list2) {
    if (count($list1) != count($list2)) {
      return FALSE;
    }

    foreach ($list1 as $i => $node) {
      if ($node->ownerDocument->saveXML($node) != $list2[$i]->ownerDocument->saveXML($list2[$i])) {
        return FALSE;
      }
    }

    return TRUE;
  }
}
