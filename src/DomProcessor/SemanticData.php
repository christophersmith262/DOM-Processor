<?php

namespace Drupal\dom_processor\DomProcessor;

use Drupal\Component\Utility\NestedArray;
use Symfony\Component\CssSelector\CssSelectorConverter;

class SemanticData implements SemanticDataInterface {

  public function __construct(\DOMNode $node, \DOMXpath $xpath, array $data, SemanticDataInterface $parent = NULL) {
    $this->converter = new CssSelectorConverter();
    $this->node = $node;
    $this->xpath = $xpath;
    $this->data = $data;
    $this->parent = $parent;
  }

  public static function create(\DOMNode $node, \DOMXpath $xpath, array $data, SemanticDataInterface $parent = NULL) {
    return new static($node, $xpath, $data, $parent);
  }

  public function get($name) {
    $parents = explode('.', $name);
    $data = $this->toArray();
    return NestedArray::getValue($data, $parents);
  }

  public function is($candidate) {
    if ($candidate instanceof SemanticDataInterface) {
      return $this->node()->isSameNode($candidate->node());
    }
    else if ($candidate instanceof \DOMNode) {
      return $this->node()->isSameNode($candidate);
    }
    else if (is_string($candidate)) {
      $selector = $this->converter->toXPath($candidate, 'self::');
      return !!$this->xpath->query($selector, $this->node())->length;
    }
    return FALSE;
  }

  public function tagged($tag_name) {
    return isset($this->toArray()[$tag_name]);
  }

  public function isRoot() {
    return !!$this->parent();
  }

  public function node() {
    return $this->node;
  }

  public function parent() {
    return $this->parent;
  }

  public function toArray() {
    return $this->data;
  }

  public function push(\DOMNode $node) {
    return static::create($node, $this->xpath, $this->data, $this);
  }

  public function tag($tag_name, array $data, $deep_merge = FALSE) {
    $data = [
      $tag_name => $data,
    ];
    if ($deep_merge) {
      $data = NestedArray::mergeDeep($this->toArray(), $data);
    }
    else {
      $data = array_merge_recursive($this->toArray(), $data);
    }
    return static::create($node, $this->xpath, $data, $this->parent());
  }

  public function clear($keys) {
    if (!is_array($keys)) {
      $keys = [$keys];
    }
    $data = $this->toArray();
    foreach ($keys as $key) {
      $parents = explode('.', $key);
      $data = NestedArray::unsetValue($data, $parents);
    }
    return static::create($this->node(), $this->xpath, $data, $this->parent());
  }
}
