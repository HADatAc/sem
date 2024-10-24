<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\file\Entity\File;
use Drupal\rep\ListManagerEmailPage;
use Drupal\rep\Utils;
use Drupal\sem\Entity\SDD;
use Drupal\sem\Entity\SemanticDataDictionary;
use Drupal\sem\Entity\SemanticVariable;

class SEMSelectForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sem_select_form';
  }

  public $element_type;

  public $manager_email;

  public $manager_name;

  public $single_class_name;

  public $plural_class_name;

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
  public function buildForm(array $form, FormStateInterface $form_state, $elementtype=NULL, $page=NULL, $pagesize=NULL) {

    // GET MANAGER EMAIL
    $this->manager_email = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $this->manager_name = $user->name->value;


    // GET TOTAL NUMBER OF ELEMENTS AND TOTAL NUMBER OF PAGES
    $this->element_type = $elementtype;
    $this->setListSize(-1);
    if ($this->element_type != NULL) {
      $this->setListSize(ListManagerEmailPage::total($this->element_type, $this->manager_email));
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
      $next_page_link = ListManagerEmailPage::link($this->element_type, $next_page, $pagesize);
    } else {
      $next_page_link = '';
    }
    if ($page > 1) {
      $previous_page = $page - 1;
      $previous_page_link = ListManagerEmailPage::link($this->element_type, $previous_page, $pagesize);
    } else {
      $previous_page_link = '';
    }

    // RETRIEVE ELEMENTS
    $this->setList(ListManagerEmailPage::exec($this->element_type, $this->manager_email, $page, $pagesize));

    //dpm($this->getList()[0]->dataFile);

    $this->single_class_name = "";
    $this->plural_class_name = "";
    switch ($this->element_type) {

      // ELEMENTS
      case "semanticvariable":
        $this->single_class_name = "Semantic Variable";
        $this->plural_class_name = "Semantic Variables";
        $header = SemanticVariable::generateHeader();
        $output = SemanticVariable::generateOutput($this->getList());
        break;
      case "semanticdatadictionary":
        $this->single_class_name = "Semantic Data Dictionary";
        $this->plural_class_name = "Semantic Data Dictionary";
        $header = SemanticDataDictionary::generateHeader();
        $output = SemanticDataDictionary::generateOutput($this->getList());
        break;
      case "sdd":
        $this->single_class_name = "SDD";
        $this->plural_class_name = "SDDs";
        $header = SDD::generateHeader();
        $output = SDD::generateOutput($this->getList());
        break;
      default:
        $this->single_class_name = "Object of Unknown Type";
        $this->plural_class_name = "Objects of Unknown Types";
    }

    // PUT FORM TOGETHER
    $form['page_title'] = [
      '#type' => 'item',
      '#title' => $this->t('<h3 class="mt-5">Manage ' . $this->plural_class_name . '</h3>'),
    ];
    $form['page_subtitle'] = [
      '#type' => 'item',
      '#title' => $this->t('<h4>' . $this->plural_class_name . ' maintained by <font color="DarkGreen">' . $this->manager_name . ' (' . $this->manager_email . ')</font></h4>'),
    ];
    $form['add_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add New ' . $this->single_class_name),
      '#name' => 'add_element',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'add-element-button'],
      ],
    ];
    $form['edit_selected_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit Selected ' . $this->single_class_name),
      '#name' => 'edit_element',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'edit-element-button'],
      ],
    ];
    $form['delete_selected_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected ' . $this->plural_class_name),
      '#name' => 'delete_element',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'delete-element-button'],
      ],
    ];
    if ($this->element_type == "sdd") {
      $form['download_sdd'] = [
        '#type' => 'submit',
        '#value' => $this->t('Download Selected ' . $this->single_class_name),
        '#name' => 'download_sdd',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'download-button'],
        ],
      ];
      $form['ingest_sdd'] = [
        '#type' => 'submit',
        '#value' => $this->t('Ingest Selected ' . $this->single_class_name),
        '#name' => 'ingest_sdd',
        '#attributes' => [
          'class' => ['use-ajax', 'btn', 'btn-primary', 'ingest_mt-button'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['width' => 700, 'height' => 400]),
        ],
        '#attributes' => [
        'class' => [],
      ],
      ];
      $form['uningest_sdd'] = [
        '#type' => 'submit',
        '#value' => $this->t('Uningest Selected ' . $this->plural_class_name),
        '#name' => 'uningest_sdd',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'uningest_mt-element-button'],
        ],
      ];
    }
    $form['element_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#js_select' => FALSE,
      '#empty' => t('No ' . $this->plural_class_name . ' found'),
    ];
    $form['pager'] = [
      '#theme' => 'list-page',
      '#items' => [
        'page' => strval($page),
        'first' => ListManagerEmailPage::link($this->element_type, 1, $pagesize),
        'last' => ListManagerEmailPage::link($this->element_type, $total_pages, $pagesize),
        'previous' => $previous_page_link,
        'next' => $next_page_link,
        'last_page' => strval($total_pages),
        'links' => null,
        'title' => ' ',
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
      '#attributes' => [
          'class' => ['btn', 'btn-primary', 'back-button'],
        ],
    ];
    $form['space'] = [
      '#type' => 'item',
      '#value' => $this->t('<br><br><br>'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // RETRIEVE TRIGGERING BUTTON
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    // SET USER ID AND PREVIOUS URL FOR TRACKING STORE URLS
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();

    // RETRIEVE SELECTED ROWS, IF ANY
    $selected_rows = $form_state->getValue('element_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }

    // ADD ELEMENT
    if ($button_name === 'add_element') {
      if ($this->element_type == 'semanticvariable') {
        Utils::trackingStoreUrls($uid, $previousUrl, 'sem.add_semantic_variable');
        $url = Url::fromRoute('sem.add_semantic_variable');
      }
      if ($this->element_type == 'sdd') {
        Utils::trackingStoreUrls($uid, $previousUrl, 'sem.add_sdd');
        $url = Url::fromRoute('sem.add_sdd');
      }
      if ($this->element_type == 'semanticdatadictionary') {
        Utils::trackingStoreUrls($uid, $previousUrl, 'sem.add_semantic_data_dictionary');
        $url = Url::fromRoute('sem.add_semantic_data_dictionary');
        $url->setRouteParameter('state', 'init');
      }
      $form_state->setRedirectUrl($url);
    }

    // EDIT ELEMENT
    if ($button_name === 'edit_element') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact " . $this->single_class_name . " to be edited."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("No more than one " . $this->single_class_name . " can be edited at once."));
      } else {
        $first = array_shift($rows);
        if ($this->element_type == 'semanticvariable') {
          Utils::trackingStoreUrls($uid, $previousUrl, 'sem.edit_semantic_variable');
          $url = Url::fromRoute('sem.edit_semantic_variable', ['semanticvariableuri' => base64_encode($first)]);
        }
        if ($this->element_type == 'semanticdatadictionary') {
          Utils::trackingStoreUrls($uid, $previousUrl, 'sem.edit_semantic_data_dictionary');
          $url = Url::fromRoute('sem.edit_semantic_data_dictionary', [
            'state' => 'init',
            'uri' => base64_encode($first)
          ]);
        }
        $form_state->setRedirectUrl($url);
      }
    }

    // DELETE ELEMENT
    if ($button_name === 'delete_element') {
      if (sizeof($rows) <= 0) {
        \Drupal::messenger()->addMessage(t("At least one " . $this->single_class_name . " needs to be selected to be deleted."));
      } else {
        $api = \Drupal::service('rep.api_connector');
        foreach($rows as $uri) {
          if ($this->element_type == 'semanticvariable') {
            $api->semanticVariableDel($uri);
          }
          if ($this->element_type == 'semanticdatadictionary') {
            $api->elementDel($this->element_type, $uri);
          }
          if ($this->element_type == 'sdd') {
            $sdd = $api->parseObjectResponse($api->getUri($uri),'getUri');
            if ($sdd != NULL && $sdd->dataFile != NULL) {

              // DELETE FILE
              if (isset($sdd->dataFile->id)) {
                $file = File::load($sdd->dataFile->id);
                if ($file) {
                  $file->delete();
                  \Drupal::messenger()->addMessage(t("Deleted file with following ID: ".$sdd->dataFile->id));
                }
              }

              // DELETE DATAFILE
              if (isset($sdd->dataFile->uri)) {
                $api->dataFileDel($sdd->dataFile->uri);
                \Drupal::messenger()->addMessage(t("Deleted DataFile with following URI: ".$sdd->dataFile->uri));
              }
            }
            // DELETE SDD
            $api->sddDel($uri);
            \Drupal::messenger()->addMessage(t("Deleted SDD with following URI: ".$sdd->uri));
          }
        }
        \Drupal::messenger()->addMessage(t("Selected " . $this->plural_class_name . " has/have been deleted successfully."));
      }
    }

    // INGEST SDD
    if ($button_name === 'ingest_sdd') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addWarning(t("Select the exact " . $this->single_class_name . " to be ingested."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("No more than one " . $this->single_class_name . " can be ingested at once."));
      } else {
        $api = \Drupal::service('rep.api_connector');
        if ($this->element_type == 'sdd') {
          $first = array_shift($rows);
          $sdd = $api->parseObjectResponse($api->getUri($first),'getUri');
          if ($sdd == NULL) {
            \Drupal::messenger()->addMessage(t("Failed to retrieve datafile to be ingested."));
            $form_state->setRedirectUrl(Utils::selectBackUrl('sdd'));
            return;
          }
          //dpm($sdd->dataFile->id);
          $msg = $api->parseObjectResponse($api->uploadTemplate("sdd",$sdd),'uploadTemplate');
          if ($msg == NULL) {
            \Drupal::messenger()->addError(t("Selected " . $this->single_class_name . " FAILED to be submitted for ingestion."));
            $form_state->setRedirectUrl(Utils::selectBackUrl('sdd'));
            return;
          }
          \Drupal::messenger()->addMessage(t("Selected " . $this->single_class_name . " has been submitted for ingestion."));
          $form_state->setRedirectUrl(Utils::selectBackUrl('sdd'));
          return;
        }
      }
    }

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $url = Url::fromRoute('sem.search');
      $form_state->setRedirectUrl($url);
    }

  }

}
