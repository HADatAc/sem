<?php

namespace Drupal\sem;

use Drupal\sem\Vocabulary\SEMGUI;

class ListKeywordPage {

  public static function exec($elementtype, $keyword, $page, $pagesize) {
    if ($elementtype == NULL || $page == NULL || $pagesize == NULL) {
        $resp = array();
        return $resp;
    }

    $offset = -1;
    if ($page <= 1) {
      $offset = 0;
    } else {
      $offset = ($page - 1) * $pagesize;
    }

    if ($keyword == NULL) {
      $keyword = "_";
    }
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $element_list = $fusekiAPIservice->listByKeyword($elementtype,$keyword,$pagesize,$offset);
    $elements = [];
    if ($element_list != null) {
      $obj = json_decode($element_list);
      if ($obj->isSuccessful) {
        $elements = $obj->body;
      }
    }
    return $elements;

  }

  public static function total($elementtype, $keyword) {
    if ($elementtype == NULL) {
      return -1;
    }
    if ($keyword == NULL) {
      $keyword = "_";
    }
    
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    
    $response = $fusekiAPIservice->listSizeByKeyword($elementtype,$keyword);
    $listSize = -1;
    if ($response != null) {
      $obj = json_decode($response);
      if ($obj->isSuccessful) {
        $listSizeStr = $obj->body;
        $obj2 = json_decode($listSizeStr);
        $listSize = $obj2->total;
      }
    }
    return $listSize;

  }

  public static function link($elementtype, $keyword, $page, $pagesize) {
    $root_url = \Drupal::request()->getBaseUrl();
    if ($elementtype != NULL && $page > 0 && $pagesize > 0) {
      return $root_url . SEMGUI::LIST_PAGE . 
          $elementtype . '/' .
          $keyword . '/' .
          strval($page) . '/' . 
          strval($pagesize);
    }
    return ''; 
  }

}

?>