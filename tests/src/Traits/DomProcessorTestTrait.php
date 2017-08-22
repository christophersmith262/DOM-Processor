<?php

namespace Drupal\Tests\dom_processor\Traits;

use Drupal\Component\Utility\Html;
use Drupal\dom_processor\DomProcessor\SemanticData;
use Drupal\dom_processor\DomProcessor\DomProcessorResult;
use Symfony\Component\CssSelector\CssSelectorConverter;

trait DomProcessorTestTrait {

  protected $cssConverter = NULL;

  protected function createDomProcessorData($markup, $selector, array $data = [], $parent = NULL) {
    if (!$this->cssConverter) {
      $this->cssConverter = new CssSelectorConverter();
    }

    $document = Html::load($markup);
    $xpath = new \DOMXpath($document);
    $selector = $this->cssConverter->toXPath($selector);
    $node = $xpath->query($selector)->item(0);

    return SemanticData::create($node, $xpath, $data);
  }

  protected function createDomProcessorResult($data = []) {
    return DomProcessorResult::create($data);
  }

}
