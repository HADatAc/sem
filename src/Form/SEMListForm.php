<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rep\ListKeywordPage;
use Drupal\sem\Entity\Attribute;
use Drupal\sem\Entity\Entity;
use Drupal\sem\Entity\SDD;
use Drupal\sem\Entity\SemanticDataDictionary;
use Drupal\sem\Entity\SemanticVariable;
use Drupal\sem\Entity\Unit;

class SEMListForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sem_list_form';
  }

  protected $list;

  protected $list_size;

  public function getList() {
    return $this->list;
  }

  public function setList($list) {
    return $this->list = $list;
  }

  public function getListSize() {
    return $this->list_size;
  }

  public function setListSize($list_size) {
    return $this->list_size = $list_size;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $elementtype=NULL, $keyword=NULL, $page=NULL, $pagesize=NULL) {

    // GET TOTAL NUMBER OF ELEMENTS AND TOTAL NUMBER OF PAGES
    $this->setListSize(-1);
    if ($elementtype != NULL) {
      $this->setListSize(ListKeywordPage::total($elementtype, $keyword));
    }
    if (gettype($this->list_size) == 'string') {
      $total_pages = "0";
    } else {
      if ($this->list_size % $pagesize == 0) {
        $total_pages = $this->list_size / $pagesize;
      } else {
        $total_pages = floor($this->list_size / $pagesize) + 1;
      }
    }

    // CREATE LINK FOR NEXT PAGE AND PREVIOUS PAGE
    if ($page < $total_pages) {
      $next_page = $page + 1;
      $next_page_link = ListKeywordPage::link($elementtype, $keyword, $next_page, $pagesize);
    } else {
      $next_page_link = '';
    }
    if ($page > 1) {
      $previous_page = $page - 1;
      $previous_page_link = ListKeywordPage::link($elementtype, $keyword, $previous_page, $pagesize);
    } else {
      $previous_page_link = '';
    }

    // RETRIEVE ELEMENTS
    $this->setList(ListKeywordPage::exec($elementtype, $keyword, $page, $pagesize));

    $class_name = "";
    $header = array();
    $output = array();
    switch ($elementtype) {

      // DD
      case "datadictionary":
        $class_name = "Data Dictionaries";
        $header = SemanticDataDictionary::generateHeader();
        $output = SemanticDataDictionary::generateOutput($this->getList());
        break;

      // SDD
      case "semanticdatadictionary":
        $class_name = "Semantic Data Dictionaries";
        $header = SDD::generateHeader();
        $output = SDD::generateOutput($this->getList());
        break;

      // ATTRIBUTE
      case "attribute":
        $class_name = "Attributes";
        $header = Attribute::generateHeader();
        $output = Attribute::generateOutput($this->getList());
        break;

      // ENTITY
      case "entity":
        $class_name = "Entities";
        $header = Entity::generateHeader();
        $output = Entity::generateOutput($this->getList());
        break;

      // SEMANTIC VARIABLE
      case "semanticvariable":
        $class_name = "Semantic Variables";
        $header = SemanticVariable::generateHeader();
        $output = SemanticVariable::generateOutput($this->getList());
        break;

      // UNIT
      case "unit":
        $class_name = "Units";
        $header = Unit::generateHeader();
        $output = Unit::generateOutput($this->getList());
        break;

      default:
        $class_name = "Objects of Unknown Types";
    }

    $form['header'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['header-container'],
        'style' => 'display: flex; justify-content: space-between; align-items: center;',
      ],
    ];
    $form['header']['title'] = [
      '#type' => 'item',
      '#markup' => t('<h3>Available <font style="color:DarkGreen;">' . $class_name . '</font></h3>'),
    ];

    // PUT FORM TOGETHER
    $form['element_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $output,
      '#empty' => t('No results found'),
    ];

    $form['pager'] = [
      '#theme' => 'list-page',
      '#items' => [
        'page' => strval($page),
        'first' => ListKeywordPage::link($elementtype, $keyword, 1, $pagesize),
        'last' => ListKeywordPage::link($elementtype, $keyword, $total_pages, $pagesize),
        'previous' => $previous_page_link,
        'next' => $next_page_link,
        'last_page' => strval($total_pages),
        'links' => null,
        'title' => ' ',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
