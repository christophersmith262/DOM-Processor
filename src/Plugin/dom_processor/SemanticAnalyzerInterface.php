<?php

namespace Drupal\dom_processor\Plugin\dom_processor;

use Drupal\dom_processor\DomProcessor\SemanticDataInterface;

interface SemanticAnalyzerInterface {
  public function analyze(SemanticDataInterface $data);
}
