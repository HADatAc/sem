<?php

namespace Drupal\sem\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'SEMSearchBlock' block.
 *
 * @Block(
 *  id = "sem_search_block",
 *  admin_label = @Translation("Search Semantics Criteria"),
 *  category = @Translation("Search Semantics Criteria")
 * )
 */
class SEMSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\sem\Form\SEMSearchForm');

    return $form;
  }

}
