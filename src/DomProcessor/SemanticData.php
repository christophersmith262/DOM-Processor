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

  public function has($name) {
    $parents = explode('.', $name);
    $data = $this->toArray();
    NestedArray::getValue($data, $parents, $key_exists);
    return $key_exists;
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
    return !$this->parent();
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

  public function tag($tag_name, $tag_data, $deep_merge = FALSE) {
    if (is_array($tag_data)) {
      $data = $this->toArray();
      if ($deep_merge) {
        $tag_data = [
          $tag_name => $tag_data,
        ];
        $data = NestedArray::mergeDeep($data, $tag_data);
      }
      else {
        $data[$tag_name] = $tag_data;
      }
    }
    else {
      $data = $this->toArray();
      $data[$tag_name] = $tag_data;
    }
    return static::create($this->node(), $this->xpath, $data, $this->parent());
  }

  public function clear($keys) {
    if (!is_array($keys)) {
      $keys = [$keys];
    }
    $data = $this->toArray();
    foreach ($keys as $key) {
      $parents = explode('.', $key);
      NestedArray::unsetValue($data, $parents);
    }
    return static::create($this->node(), $this->xpath, $data, $this->parent());
  }

  public function getInnerHTML() {
    $inner_html = '';
    if ($this->node()->hasChildNodes()) {
      foreach ($this->node()->childNodes as $child_node) {
        $inner_html .= $this->node()->ownerDocument->saveHTML($child_node);
      }
    }
    return $inner_html;
  }
}
