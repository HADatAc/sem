<?php

namespace Drupal\sem\Entity;

use Drupal\rep\Vocabulary\REPGUI;
use Drupal\rep\Utils;

class SemanticDataDictionary {

  public static function generateHeader() {

    return $header = [
      'element_uri' => t('URI'),
      'element_name' => t('Name'),
      'element_version' => t('Version'),
      'element_tot_variables' => t('#Variables'),
      'element_tot_objects' => t('#Objects'),
      'element_tot_codes' => t('#Codes'),
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
      $version = ' ';
      if ($element->hasVersion != NULL) {
        $version = $element->hasVersion;
      }
      $root_url = \Drupal::request()->getBaseUrl();
      $encodedUri = rawurlencode(rawurlencode($element->uri));
      $output[$element->uri] = [
        'element_uri' => t('<a href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),
        'element_name' => t($label),
        'element_version' => t($version),
        'element_tot_variables' => $element->totalVariables,
        'element_tot_objects' => $element->totalObjects,
        'element_tot_codes' => $element->totalCodes,
        ];
    }
    return $output;

  }

  public static function generateCardOutput($list) {

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
      $version = ' ';
      if ($element->hasVersion != NULL) {
        $version = $element->hasVersion;
      }
      $root_url = \Drupal::request()->getBaseUrl();
      $encodedUri = rawurlencode(rawurlencode($element->uri));
      $output[$element->uri] = [
        'element_uri' => t('<a href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),
        'element_name' => t($label),
        'element_version' => t($version),
        'element_tot_variables' => $element->totalVariables,
        'element_tot_objects' => $element->totalObjects,
        'element_tot_codes' => $element->totalCodes,
        'element_image' => $element->hasImageUri,
        'element_hascotypeuri' => $element->hascoTypeUri
        ];
    }
    return $output;

  }

}
