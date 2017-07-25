<?php

namespace Drupal\dom_processor\DomProcessor;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;

class DomProcessorResult implements DomProcessorResultInterface {

  protected $data;
  protected $reprocess = FALSE;

  public function __construct(array $data) {
    $this->data = $data;
  }

  public static function create(array $data = []) {
    return new static($data);
  }

  public function get($name = NULL) {
    $parents = explode('.', $name);
    $data = $this->toArray();
    return NestedArray::getValue($data, $parents);
  }

  public function toArray() {
    return $this->data;
  }

  public function merge($merge_data, $deep_merge = TRUE) {
    if ($merge_data instanceof DomProcessorResultInterface) {
      $merge_data = $merge_data->toArray();
    }
    $data = $this->toArray();
    if ($deep_merge) {
      $data = NestedArray::mergeDeep($data, $merge_data);
    }
    else {
      foreach ($merge_data as $key => $value) {
        $data[$key] = $value;
      }
    }
    return self::create($data);
  }

  public function needsReprocess() {
    return $this->reprocess;
  }

  public function reprocess($status = TRUE) {
    $this->reprocess = $status;
    return $this;
  }

  public function replaceWithHtml(SemanticDataInterface $data, $markup) {
    // Insert new children.
    $document = Html::load($markup);
    $xpath = new \DOMXPath($document);
    $body_nodes = $xpath->query('//body');
    $body_node = $body_nodes->item(0);
    if ($body_node->hasChildNodes()) {
      foreach ($body_node->childNodes as $child_node) {
        $child_node = $data->node()->ownerDocument->importNode($child_node, true);
        $data->node()->parentNode->insertBefore($child_node, $data->node());
      }
    }

    // Remove existing node.
    $data->node()->parentNode->removeChild($data->node());

    return $this->reprocess();
  }

  public function setInnerHtml(SemanticDataInterface $data, $markup) {
    // Remove existing children.
    if ($data->node()->hasChildren()) {
      foreach ($data->node()->childNodes as $child_node) {
        $data->node()->removeChild($child_node);
      }
    }

    // Append new children.
    $document = Html::load($markup);
    $xpath = new \DOMXPath($document);
    $body_nodes = $xpath->query('//body');
    $body_node = $body_nodes->item(0);
    if ($body_node->hasChildNodes()) {
      foreach ($body_node->childNodes as $child_node) {
        $child_node = $data->node()->ownerDocument->importNode($child_node);
        $data->node()->appendChild($child_node);
      }
    }

    return $this;
  }
}
