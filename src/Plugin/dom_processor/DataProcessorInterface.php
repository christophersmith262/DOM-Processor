<?php

namespace Drupal\dom_processor\Plugin\dom_processor;

use Drupal\dom_processor\DomProcessor\DomProcessorResultInterface;
use Drupal\dom_processor\DomProcessor\SemanticDataInterface;

interface DataProcessorInterface {
  public function process(SemanticDataInterface $data, DomProcessorResultInterface $result);
}
