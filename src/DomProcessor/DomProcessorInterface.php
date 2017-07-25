<?php

namespace Drupal\dom_processor\DomProcessor;

use Drupal\dom_processor\DomProcessorStackInterface;

interface DomProcessorInterface {
  public function process($markup, $processor_stack, $variant_name, array $data);
}
