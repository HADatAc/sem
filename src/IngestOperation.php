<?php

namespace Drupal\sem;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a custom operation for entities.
 */
class IngestOperation {

  /**
   * Executes the Ingest operation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity on which the operation should be performed.
   */
  public function execute(EntityInterface $entity) {
    \Drupal::messenger()->addMessage(t('Ingest operation executed for entity %label.', array('%label' => $entity->label())));
  }
}