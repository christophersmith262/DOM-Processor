<?php

namespace Drupal\dom_processor;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

interface DomProcessorStackInterface extends ConfigEntityInterface {
  public function getVariant($name);
  public function getVariants();
  public function setVariant($name, array $variant);
  public function setVariants(array $variants);
  public function getAnalyzers();
  public function setAnalyzers(array $analyzers);
}
