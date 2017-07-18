<?php

namespace Drupal\dom_processor\DomProcessor;

use Drupal\Component\Utility\NestedArray;

class DomProcessorResult implements DomProcessorResultInterface {

  protected $data;

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

  public function merge($data, $deep_merge = TRUE) {
    if ($data instanceof DomProcessorResultInterface) {
      $data = $data->toArray();
    }
    if ($deep_merge) {
      $data = NestedArray::mergeDeep($this->toArray(), $data);
    }
    else {
      $data = array_merge_recursive($this->toArray(), $data);
    }
    return self::create($data);
  }
}
