<?php

namespace Drupal\sem\Entity;

use Drupal\rep\Vocabulary\REPGUI;
use Drupal\rep\Utils;

class SemanticVariable {

  public static function generateHeader() {

    return $header = [
      'element_uri' => t('URI'),
      'element_name' => t('Name'),
      'element_entity' => t('Entity'),
      'element_attribute' => t('Attribute'),
    ];
  
  }

  public static function generateOutput($list) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    $output = array();
    foreach ($list as $element) {
      $uri = ' ';
      if ($element->uri != NULL) {
        $uri = $element->uri;
      }
      $uri = Utils::namespaceUri($uri);
      $label = ' ';
      if ($element->label != NULL) {
        $label = $element->label;
      }
      $entityUri = ' ';
      if ($element->entityUri != NULL && $element->entityUri != '') {
        $entityUri = $element->entityLabel . ' (' . Utils::namespaceUri($element->entityUri) . ')';
      }
      $attributeUri = ' ';
      if ($element->attributeUri != NULL && $element->attributeUri != '') {
        $attributeUri = $element->attributeLabel . ' (' . Utils::namespaceUri($element->attributeUri) . ')';
      }
      $root_url = \Drupal::request()->getBaseUrl();
      $encodedUri = rawurlencode(rawurlencode($element->uri));
      $output[$element->uri] = [
        'element_uri' => t('<a href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),     
        'element_name' => t($label),    
        'element_entity' => $entityUri,
        'element_attribute' => $attributeUri, 
      ];
    }
    return $output;

  }

}