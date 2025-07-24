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

    // Retrieve or set default view type
    $session = \Drupal::request()->getSession();
    $view_type = $session->get('sem_select_view_type', 'table');
    $form_state->set('view_type', $view_type);

    // Attach necessary libraries
    $form['#attached']['library'][] = 'core/drupal.bootstrap';

    $form['#attached']['library'][] = 'core/jquery';
    $form['#attached']['library'][] = 'core/jquery.once';
    $form['#attached']['library'][] = 'core/drupal';
    $form['#attached']['library'][] = 'core/drupalSettings';
    $form['#attached']['library'][] = 'sem/sem_js_css';

    $form['#attached']['drupalSettings']['sem_select_form']['base_url'] = \Drupal::request()->getSchemeAndHttpHost() . base_path();
    $form['#attached']['drupalSettings']['sem_select_form']['elementtype'] = $elementtype;

    // Get value `pagesize` (default 9)
    if ($form_state->get('page_size')) {
      $pagesize = $form_state->get('page_size');
    } else {
      $pagesize = $session->get('sir_select_form_pagesize', 9);
      $form_state->set('page_size', $pagesize);
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
        $outputCard = SemanticVariable::generateCardOutput($this->getList());
        break;
      case "semanticdatadictionary":
        $this->single_class_name = "Semantic Data Dictionary";
        $this->plural_class_name = "Semantic Data Dictionary";
        $header = SemanticDataDictionary::generateHeader();
        $output = SemanticDataDictionary::generateOutput($this->getList());
        $outputCard = SemanticDataDictionary::generateCardOutput($this->getList());
        break;
      case "sdd":
        $this->single_class_name = "SDD";
        $this->plural_class_name = "SDDs";
        $header = SDD::generateHeader();
        $output = $outputCard = SDD::generateOutput($this->getList());
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

    // Adicionar botões de alternância de visualização
    $form['view_toggle'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['view-toggle', 'd-flex', 'justify-content-end']],
    ];

    $form['view_toggle']['table_view'] = [
      '#type' => 'submit',
      '#value' => '',
      '#name' => 'view_table',
      '#attributes' => [
        'style' => 'padding: 20px;',
        'class' => ['table-view-button', 'fa-xl', 'mx-1'],
        'title' => $this->t('Tabel View'),
      ],
      '#submit' => ['::viewTableSubmit'],
      '#limit_validation_errors' => [],
    ];

    $form['view_toggle']['card_view'] = [
      '#type' => 'submit',
      '#value' => '',
      '#name' => 'view_card',
      '#attributes' => [
        'style' => 'padding: 20px;',
        'class' => ['card-view-button', 'fa-xl'],
        'title' => $this->t('Card View'),
      ],
      '#submit' => ['::viewCardSubmit'],
      '#limit_validation_errors' => [],
    ];

    $form['add_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add New ' . $this->single_class_name),
      '#name' => 'add_element',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'add-element-button'],
      ],
    ];

    if ($view_type == 'table') {
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
    } elseif ($view_type == 'card') {
      $this->buildCardView($form, $form_state, $header, $outputCard);

      // SHOW "Load More" BUTTON
      // TOTAL ITEMS
      $total_items = $this->getListSize();

      // PAGESIZE
      $current_page_size = $form_state->get('page_size') ?? 9;

      //Prevent infinite scroll without new data
      if ($total_items > $current_page_size) {
        $form['load_more_button'] = [
          '#type' => 'submit',
          '#value' => $this->t('Load More'),
          '#name' => 'load_more_button',
          '#attributes' => [
            'id' => 'load-more-button',
            'class' => ['btn', 'btn-primary', 'load-more-button'],
            'style' => 'display: none;',
          ],
          '#submit' => ['::loadMoreSubmit'],
          '#limit_validation_errors' => [],
        ];

        $form['list_state'] = [
          '#type' => 'hidden',
          '#value' => ($total_items > $current_page_size ? 1:0),
          "#name" => 'list_state',
          '#attributes' => [
            'id' => 'list_state',
          ]
        ];
      }
    }

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
   * Submit handler for the Load More button.
   */
  public function loadMoreSubmit(array &$form, FormStateInterface $form_state) {
    // Increments page number to load more cards
    $current_page_size = $form_state->get('page_size') ?? 9;
    $new_page_size = $current_page_size + 9;
    $form_state->set('page_size', $new_page_size);

    // Forces rebuild to load more cards
    $form_state->setRebuild();
  }

  /**
   * Build Table View
   */
  protected function buildTableView(array &$form, FormStateInterface $form_state, $header, $output) {
    $form['element_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#js_select' => FALSE,
      '#empty' => $this->t('No ' . $this->plural_class_name . ' found'),
    ];
  }

  /**
   * Build Cards view with infinite scroll
   */
  protected function buildCardView(array &$form, FormStateInterface $form_state, $header, $output) {

    // Se não estiver adicionando mais, crie o wrapper principal
    $form['loading_overlay'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'loading-overlay',
        'class' => ['loading-overlay'],
        'style' => 'display: none;', // Inicialmente escondido
      ],
      '#markup' => '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>',
    ];

    $form['cards_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
            'id' => 'cards-wrapper',
            'class' => ['row'],
        ],
    ];

    // Wrapper para AJAX
    $form['element_cards_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'element-cards-wrapper'],
    ];

    $cards_output = $form_state->get('cards_output');
    if ($cards_output === NULL) {
        $cards_output = [];
        $form_state->set('cards_output', $cards_output);
    }

    $cards_output = $form_state->get('cards_output');
    $cards_output = array_merge($cards_output, $output);
    $form_state->set('cards_output', $cards_output);

    $form['element_cards_wrapper']['element_cards'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['row', 'mt-3']],
    ];

    foreach ($cards_output as $key => $item) {
      $sanitized_key = md5($key);

      $form['element_cards_wrapper']['element_cards'][$sanitized_key] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-md-4']],
      ];

      $form['element_cards_wrapper']['element_cards'][$sanitized_key]['card'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'mb-4']],
      ];

      $header_text = '';

      $image_uri = Utils::getAPIImage($item['element_uri'], $item['element_image'], UTILS::placeholderImage($item['element_hascotypeuri'],$this->element_type, '/'));

      foreach ($header as $column_key => $column_label) {
        if ($column_label == 'Name') {
          $value = isset($item[$column_key]) ? $item[$column_key] : '';
          $header_text = strip_tags($value);
          break;
        }
      }

      if (strlen($header_text) > 0) {
        $form['element_cards_wrapper']['element_cards'][$sanitized_key]['card']['header'] = [
          '#type' => 'container',
          '#attributes' => [
            'style' => 'margin-bottom:0!important;',
            'class' => ['card-header'],
          ],
          '#markup' => '<h5 class="mb-0">' . $header_text . '</h5>',
        ];
      }

      $form['element_cards_wrapper']['element_cards'][$sanitized_key]['card']['content'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['row', 'card-body', 'd-flex', 'flex-row'],
          'style' => 'margin-bottom: 0!important;',
        ],
        'left_column' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['col-md-5', 'd-flex', 'justify-content-center', 'align-items-center'],
            'style' => 'margin-bottom:0!important;',
          ],
          'image' => [
            '#type' => 'html_tag',
            '#tag' => 'img',
            '#attributes' => [
              'src' => $image_uri,
              'alt' => $header_text,
              'style' => 'max-width: 70%; height: auto;',
              'class' => ['img-fluid', 'mb-0', 'border', 'border-5', 'rounded', 'rounded-5'],
            ],
          ],
        ],
        'right_column' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['right-column', 'col-md-7'],
            'style' => 'margin-bottom:0!important;',
          ],
        ],
      ];

      // Colocando o conteúdo atual dentro da coluna direita
      foreach ($header as $column_key => $column_label) {
        $value = isset($item[$column_key]) ? $item[$column_key] : '';
        if ($column_label == 'Name') {
          continue;
        }

        if ($column_label == 'Status') {
          $value_rendered = [
            '#markup' => $value,
            '#allowed_tags' => ['b', 'font', 'span', 'div', 'strong', 'em'],
          ];
        } else {
          $value_rendered = [
            '#markup' => $value,
          ];
        }

        $form['element_cards_wrapper']['element_cards'][$sanitized_key]['card']['content']['right_column'][$column_key] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field-container'],
          ],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'strong',
            '#value' => $column_label . ': ',
          ],
          'value' => $value_rendered,
        ];
      }


      $form['element_cards_wrapper']['element_cards'][$sanitized_key]['card']['footer'] = [
        '#type' => 'container',
        '#attributes' => [
          'style' => 'margin-bottom:0!important;',
          'class' => ['d-flex', 'card-footer', 'justify-content-end'],
        ],
      ];

      $form['element_cards_wrapper']['element_cards'][$sanitized_key]['card']['footer']['actions'] = [
        '#type' => 'actions',
        '#attributes' => [
          'style' => 'margin-bottom:0!important;',
          'class' => ['mb-0'],
        ],
      ];

      // EDIT BUTTON
      $form['element_cards_wrapper']['element_cards'][$sanitized_key]['card']['footer']['actions']['edit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Edit'),
        '#name' => 'edit_element_' . $sanitized_key,
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'btn-sm', 'edit-element-button'],
        ],
        '#submit' => ['::editElementSubmit'],
        '#limit_validation_errors' => [],
        '#element_uri' => $key,
      ];

      // DELETE BUTTON
      $form['element_cards_wrapper']['element_cards'][$sanitized_key]['card']['footer']['actions']['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#name' => 'delete_element_' . $sanitized_key,
        '#attributes' => [
          'class' => ['btn', 'btn-danger', 'btn-sm', 'delete-element-button'],
          'onclick' => 'if(!confirm("Really Delete?")){return false;}',
        ],
        '#submit' => ['::deleteElementSubmit'],
        '#limit_validation_errors' => [],
        '#element_uri' => $key
      ];
    }
  }

  /**
   * Submit handler for table view toggle.
   */
  public function viewTableSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('view_type', 'table');
    // Update the view type in the session
    $session = \Drupal::request()->getSession();
    $session->set('sem_select_view_type', 'table');
    $form_state->setRebuild();
  }

  /**
   * Submit handler for card view toggle.
   */
  public function viewCardSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('view_type', 'card');
    // Update the view type in the session
    $session = \Drupal::request()->getSession();
    $session->set('sem_select_view_type', 'card');
    $form_state->setRebuild();
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

  /**
   * Submit handler para editar um elemento na visualização em cards.
   */
  public function editElementSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];

    $this->performEdit($uri, $form_state);
  }

  /**
   * Submit handler para excluir um elemento na visualização em cards.
   */
  public function deleteElementSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];

    $this->performDelete([$uri], $form_state);
  }

  /**
   * Executa a ação de editar.
   */
  protected function performEdit($uri, FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();

    if ($this->element_type == 'semanticvariable') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sem.edit_semantic_variable');
      $url = Url::fromRoute('sem.edit_semantic_variable', ['semanticvariableuri' => base64_encode($uri)]);
    }
    if ($this->element_type == 'semanticdatadictionary') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sem.edit_semantic_data_dictionary');
      $url = Url::fromRoute('sem.edit_semantic_data_dictionary', [
        'state' => 'init',
        'uri' => base64_encode($uri)
      ]);
    }

    $form_state->setRedirectUrl($url);
  }

  /**
   * Executa a ação de excluir.
   */
  protected function performDelete(array $uris, FormStateInterface $form_state) {
    $api = \Drupal::service('rep.api_connector');
    foreach($uris as $uri) {
      $mt = $api->parseObjectResponse($api->getUri($uri),'getUri');
      if ($mt != NULL && $mt->hasDataFile != NULL) {

        // DELETE FILE
        if (isset($mt->hasDataFile->id)) {
          $file = File::load($mt->hasDataFile->id);
          if ($file) {
            $file->delete();
            \Drupal::messenger()->addMessage(t("Archive with ID ".$mt->hasDataFile->id." deleted."));
          }
        }

        // DELETE DATAFILE
        if (isset($mt->hasDataFile->uri)) {
          $api->dataFileDel($mt->hasDataFile->uri);
          \Drupal::messenger()->addMessage(t("DataFile with URI ".$mt->hasDataFile->uri." deleted."));
        }
      }
    }
    \Drupal::messenger()->addMessage(t("The " . $this->plural_class_name . " selected were deletes successfully."));
    $form_state->setRebuild();
  }

}
