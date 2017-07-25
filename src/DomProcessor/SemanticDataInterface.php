<?php

namespace Drupal\dom_processor\DomProcessor;

interface SemanticDataInterface {
  public static function create(\DOMNode $node, \DOMXpath $xpath, array $data, SemanticDataInterface $parent);
  public function get($name);
  public function is($candidate);
  public function tagged($tag_name);
  public function isRoot();
  public function node();
  public function parent();
  public function toArray();
  public function push(\DOMNode $node);
  public function tag($tag_name, $tag_data, $deep_merge);
}
