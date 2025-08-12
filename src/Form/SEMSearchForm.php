<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;

class SEMSearchForm extends FormBase {

  public function getFormId() {
    return 'sem_search_form';
  }

  protected $elementtype;
  protected $keyword;
  protected $page;
  protected $pagesize;

  public function getElementType() {
    return $this->elementtype;
  }
  public function setElementType($type) {
    return $this->elementtype = $type;
  }
  public function getKeyword() {
    return $this->keyword;
  }
  public function setKeyword($kw) {
    return $this->keyword = $kw;
  }
  public function getPage() {
    return $this->page;
  }
  public function setPage($pg) {
    return $this->page = $pg;
  }
  public function getPageSize() {
    return $this->pagesize;
  }
  public function setPageSize($pgsize) {
    return $this->pagesize = $pgsize;
  }

  public function iconSubmitForm(array &$form, FormStateInterface $form_state) {
    $clicked_button = $form_state->getTriggeringElement()['#name'];
    $form_state->setValue('search_element_type', $clicked_button);
    $form_state->setValue('search_keyword', '');
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'sem/sem_search_icons';

    $request = \Drupal::request();
    $pathInfo = $request->getPathInfo();
    $pathElements = explode('/', $pathInfo);

    $this->setElementType('entity');
    $this->setKeyword('');
    $this->setPage(1);
    $this->setPageSize(12);

    if (sizeof($pathElements) >= 7) {
      $this->setElementType($pathElements[3]);
      $this->setKeyword($pathElements[4] == '_' ? '' : $pathElements[4]);
      $this->setPage((int)$pathElements[5]);
      $this->setPageSize((int)$pathElements[6]);
    }

    $form['element_icons'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['element-icons-grid-wrapper']],
    ];

    $form['element_icons']['grid'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['element-icons-grid']],
    ];

    $element_types = [
      'datadictionary' => ['label' => 'DD', 'image' => 'datadictionary_placeholder.png'],
      'semanticdatadictionary' => ['label' => 'SDD', 'image' => 'semanticdatadictionary_placeholder.png'],
      'semanticvariable' => ['label' => 'SV', 'image' => 'semanticvariable_placeholder.png'],
      'entity' => ['label' => 'Entity', 'image' => 'entity_placeholder.png'],
      'attribute' => ['label' => 'Attribute', 'image' => 'attribute_placeholder.png'],
      'unit' => ['label' => 'Unit', 'image' => 'unit_placeholder.png'],
    ];

    $module_path = \Drupal::request()->getBaseUrl() . '/' . \Drupal::service('extension.list.module')->getPath('rep');

    foreach ($element_types as $type => $info) {
      $placeholder_image = $module_path . '/images/placeholders/' . $info['image'];

      $button_classes = ['element-icon-button'];
      if ($type === $this->getElementType()) {
        $button_classes[] = 'selected';
      }

      $form['element_icons']['grid'][$type] = [
        '#type' => 'submit',
        '#value' => '',
        '#attributes' => [
          'class' => $button_classes,
          'style' => "background-image: url('$placeholder_image');",
          'title' => $this->t($info['label']),
          'aria-label' => $this->t($info['label']),
        ],
        '#name' => $type,
        '#submit' => ['::iconSubmitForm'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::ajaxSubmitForm',
          'progress' => ['type' => 'none'],
        ],
      ];
    }

    $form['search_keyword'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Keyword'),
      '#default_value' => $this->getKeyword(),
    ];

    $form['search_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#attributes' => ['class' => ['btn', 'btn-primary', 'search-button']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (strlen($form_state->getValue('search_element_type')) < 1) {
      $form_state->setErrorByName('search_element_type', $this->t('Please select an element type'));
    }
  }

  private function redirectUrl(FormStateInterface $form_state) {
    $this->setKeyword($form_state->getValue('search_keyword'));
    if ($this->getKeyword() == NULL || $this->getKeyword() == '') {
      $this->setKeyword('_');
    }

    $url = Url::fromRoute('sem.list_element');
    $url->setRouteParameter('elementtype', $form_state->getValue('search_element_type'));
    $url->setRouteParameter('keyword', $this->getKeyword());
    $url->setRouteParameter('page', $this->getPage());
    $url->setRouteParameter('pagesize', $this->getPageSize());
    return $url;
  }

  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $this->setPage(1);
    $this->setPageSize(12);
    $url = $this->redirectUrl($form_state);
    $response->addCommand(new RedirectCommand($url->toString()));
    return $response;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url = $this->redirectUrl($form_state);
    $form_state->setRedirectUrl($url);
  }
}
