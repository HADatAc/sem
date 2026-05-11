<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\file\Entity\File;
use Drupal\rep\ListManagerEmailPage;
use Drupal\rep\ListKeywordLanguagePage;
use Drupal\rep\ManageOwnerFilter;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
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


    $this->element_type = $elementtype;
    $page = $page ?? 1;
    $pagesize = $pagesize ?? 9;

    // Retrieve or set default view type + filters
    $session = \Drupal::request()->getSession();
    $view_type = $session->get('sem_select_view_type', 'table');
    $form_state->set('view_type', $view_type);
    $table_active_class = ($view_type === 'table') ? ['selected-button'] : [];
    $card_active_class = ($view_type === 'card') ? ['selected-button'] : [];

    if ($view_type === 'card') {
      $form['#attached']['library'][] = 'rep/infinitescroll';
    }

    $status_filter_key = 'sem_select_status_filter.' . (string) $this->element_type;
    $status_filter = $form_state->getValue('status_filter');
    if ($status_filter === NULL) {
      $status_filter = $session->get($status_filter_key, '_');
    }
    else {
      $session->set($status_filter_key, $status_filter);
    }

    $supports_keywordlanguage = in_array($this->element_type, ['semanticvariable'], TRUE);
    $text_filter_key = 'sem_select_text_filter.' . (string) $this->element_type;
    $language_filter_key = 'sem_select_language_filter.' . (string) $this->element_type;

    $text_filter = $form_state->getValue('text_filter');
    if ($text_filter === NULL) {
      $text_filter = $session->get($text_filter_key, '');
    }
    else {
      $session->set($text_filter_key, $text_filter);
    }

    $language_filter = $form_state->getValue('language_filter');
    if ($language_filter === NULL) {
      $language_filter = $session->get($language_filter_key, '_');
    }
    else {
      $session->set($language_filter_key, $language_filter);
    }

    $is_admin = ManageOwnerFilter::isAdmin();
    $manager_filter_key = 'sem_select_manager_filter.' . (string) $this->element_type;
    $manager_filter = $form_state->getValue('manager_filter');
    if ($manager_filter === NULL) {
      $manager_filter = $session->get($manager_filter_key, '');
    }
    else {
      $manager_filter = ManageOwnerFilter::normalizeSelectedEmail($manager_filter);
      $session->set($manager_filter_key, $manager_filter);
    }

    $effective_manager_email = ManageOwnerFilter::resolveEffectiveOwner($this->manager_email, $manager_filter, $status_filter);

    // Attach necessary libraries
    $form['#attached']['library'][] = 'core/drupal.bootstrap';

    $form['#attached']['library'][] = 'core/jquery';
    $form['#attached']['library'][] = 'core/jquery.once';
    $form['#attached']['library'][] = 'core/drupal';
    $form['#attached']['library'][] = 'core/drupalSettings';
    $form['#attached']['library'][] = 'sem/sem_js_css';

    $form['#attached']['drupalSettings']['sem_select_form']['base_url'] = (\Drupal::request()->headers->get('x-forwarded-proto') === 'https' ? 'https://':'http://'). \Drupal::request()->getHost() . \Drupal::request()->getBaseUrl();
    $form['#attached']['drupalSettings']['sem_select_form']['elementtype'] = $elementtype;

    // Get value `pagesize` (default 9) - only override for CARD view.
    if ($view_type == 'card') {
      if ($form_state->get('page_size')) {
        $pagesize = $form_state->get('page_size');
      }
      else {
        $pagesize = $session->get('sem_select_form_pagesize', 9);
        $form_state->set('page_size', $pagesize);
      }
    }

    $has_status_filter = !($status_filter === '_' || $status_filter === NULL || $status_filter === '');
    $has_text_filter = $supports_keywordlanguage && (trim((string) $text_filter) !== '');
    $has_language_filter = $supports_keywordlanguage && !($language_filter === '_' || $language_filter === NULL || $language_filter === '');
    $use_keywordlanguage = $supports_keywordlanguage && ($has_text_filter || $has_language_filter || $has_status_filter);

    // GET TOTAL NUMBER OF ELEMENTS AND TOTAL NUMBER OF PAGES
    $this->setListSize(-1);
    if ($this->element_type != NULL) {
      if ($use_keywordlanguage) {
        $keyword_param = $has_text_filter ? trim((string) $text_filter) : '_';
        $lang_param = $has_language_filter ? (string) $language_filter : '_';
        $status_param = $has_status_filter ? (string) $status_filter : '_';
        $this->setListSize(ListKeywordLanguagePage::total($this->element_type, $keyword_param, $lang_param, '_', $effective_manager_email, $status_param));
      }
      elseif ($has_status_filter) {
        $this->setListSize(ListManagerEmailPage::totalByStatusManagerEmail($this->element_type, $status_filter, $effective_manager_email, FALSE));
      }
      else {
        $this->setListSize(ListManagerEmailPage::total($this->element_type, $effective_manager_email));
      }
    }

    $total_pages = 1;
    if (is_numeric($this->list_size) && $pagesize > 0) {
      $size = (int) $this->list_size;
      if ($size > 0) {
        $total_pages = (int) ceil($size / $pagesize);
      }
    }

    // Clamp current page
    $page = max(1, min((int) $page, (int) $total_pages));

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
    if ($use_keywordlanguage) {
      $keyword_param = $has_text_filter ? trim((string) $text_filter) : '_';
      $lang_param = $has_language_filter ? (string) $language_filter : '_';
      $status_param = $has_status_filter ? (string) $status_filter : '_';
      $this->setList(ListKeywordLanguagePage::exec($this->element_type, $keyword_param, $lang_param, '_', $effective_manager_email, $status_param, $page, $pagesize));
    }
    elseif ($has_status_filter) {
      $this->setList(ListManagerEmailPage::execByStatusManagerEmail($this->element_type, $status_filter, $effective_manager_email, FALSE, $page, $pagesize));
    }
    else {
      $this->setList(ListManagerEmailPage::exec($this->element_type, $effective_manager_email, $page, $pagesize));
    }

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

    $show_owner_indicator = $is_admin && $manager_filter !== '' && strcasecmp($effective_manager_email, $manager_filter) === 0;
    if ($show_owner_indicator) {
      $form['owner_indicator'] = [
        '#type' => 'item',
        '#markup' => $this->t('<div class="alert alert-info py-2 mb-3"><strong>A visualizar owner:</strong> @owner</div>', [
          '@owner' => $effective_manager_email,
        ]),
      ];
    }

    // Controls row: action buttons (left) + view toggle and filters (right).
    $form['controls_row'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['d-flex', 'justify-content-between', 'align-items-start', 'flex-wrap', 'gap-2', 'mb-0'],
        'style' => 'margin-bottom:0!important;',
      ],
    ];

    $form['controls_row']['buttons_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['d-flex', 'flex-nowrap', 'gap-2'],
        'style' => 'flex-wrap:nowrap;overflow-x:auto;'
      ],
    ];

    $form['controls_row']['buttons_container']['add_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add New ' . $this->single_class_name),
      '#name' => 'add_element',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'add-element-button'],
      ],
    ];

    $form['controls_row']['right_controls'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['d-flex', 'flex-column', 'align-items-end', 'gap-2'],
      ],
    ];

    $form['controls_row']['right_controls']['view_toggle'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['view-toggle', 'd-flex', 'justify-content-end']],
    ];

    $form['controls_row']['right_controls']['view_toggle']['table_view'] = [
      '#type' => 'submit',
      '#value' => '',
      '#name' => 'view_table',
      '#attributes' => [
        'style' => 'padding: 20px;',
        'class' => array_merge(['table-view-button', 'fa-xl', 'mx-1'], $table_active_class),
        'title' => $this->t('Tabel View'),
      ],
      '#submit' => ['::viewTableSubmit'],
      '#limit_validation_errors' => [],
    ];

    $form['controls_row']['right_controls']['view_toggle']['card_view'] = [
      '#type' => 'submit',
      '#value' => '',
      '#name' => 'view_card',
      '#attributes' => [
        'style' => 'padding: 20px;',
        'class' => array_merge(['card-view-button', 'fa-xl'], $card_active_class),
        'title' => $this->t('Card View'),
      ],
      '#submit' => ['::viewCardSubmit'],
      '#limit_validation_errors' => [],
    ];

    if ($view_type == 'table') {
      $form['controls_row']['buttons_container']['edit_selected_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Edit Selected ' . $this->single_class_name),
        '#name' => 'edit_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'edit-element-button'],
        ],
      ];
      $form['controls_row']['buttons_container']['delete_selected_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete Selected ' . $this->plural_class_name),
        '#name' => 'delete_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'delete-element-button'],
        ],
      ];

      // Filters (API-backed)
      $status_options = [
        '_' => $this->t('All Status'),
        VSTOI::DRAFT => $this->t('Draft'),
        VSTOI::UNDER_REVIEW => $this->t('Under Review'),
        VSTOI::CURRENT => $this->t('Current'),
        VSTOI::DEPRECATED => $this->t('Deprecated'),
      ];

      $form['controls_row']['right_controls']['filter_container'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'ms-auto', 'mb-0'],
          'style' => 'margin-bottom:0!important;'
        ],
      ];

      $form['controls_row']['right_controls']['filter_container']['filter_label'] = [
        '#type' => 'label',
        '#title' => $this->t('Filter(s): '),
        '#attributes' => [
          'class' => ['pt-3', 'me-2', 'fw-bold'],
        ]
      ];

      if ($supports_keywordlanguage) {
        $form['controls_row']['right_controls']['filter_container']['text_filter'] = [
          '#type' => 'textfield',
          '#default_value' => $text_filter,
          '#ajax' => [
            'callback' => '::ajaxReloadTable',
            'wrapper' => 'element-table-wrapper',
            'event' => 'change',
          ],
          '#attributes' => [
            'class' => ['form-select', 'w-auto', 'mt-2', 'me-1'],
            'style' => 'max-width:230px;margin-bottom:0!important;float:right;',
            'placeholder' => 'Type in your search criteria',
            'onkeydown' => 'if (event.keyCode == 13) { event.preventDefault(); this.blur(); }',
          ],
        ];

        $tables = new Tables;
        $languages = $tables->getLanguages();
        if ($languages) {
          $languages = ['_' => $this->t('All Languages')] + $languages;
        }
        $form['controls_row']['right_controls']['filter_container']['language_filter'] = [
          '#type' => 'select',
          '#options' => $languages,
          '#default_value' => $language_filter,
          '#ajax' => [
            'callback' => '::ajaxReloadTable',
            'wrapper' => 'element-table-wrapper',
            'event' => 'change',
          ],
          '#attributes' => [
            'class' => ['form-select', 'w-auto', 'mt-2', 'me-1'],
            'style' => 'margin-bottom:0!important;float:right;'
          ],
        ];
      }

      if ($is_admin) {
        $form['controls_row']['right_controls']['filter_container']['manager_filter'] = [
          '#type' => 'textfield',
          '#title' => $this->t('User'),
          '#title_display' => 'invisible',
          '#default_value' => $manager_filter,
          '#ajax' => [
            'callback' => '::ajaxReloadTable',
            'wrapper' => 'element-table-wrapper',
            'event' => 'change',
          ],
          '#attributes' => [
            'class' => ['form-control', 'w-auto', 'mt-2', 'me-1'],
            'style' => 'min-width:240px;margin-bottom:0!important;float:right;',
            'placeholder' => $this->t('User email (Draft/Under Review)'),
          ],
        ];
      }

      $form['controls_row']['right_controls']['filter_container']['status_filter'] = [
        '#type' => 'select',
        '#options' => $status_options,
        '#default_value' => $status_filter,
        '#ajax' => [
          'callback' => '::ajaxReloadTable',
          'wrapper' => 'element-table-wrapper',
          'event' => 'change',
        ],
        '#attributes' => [
          'class' => ['form-select', 'w-auto', 'mt-2'],
          'style' => 'margin-bottom:0!important;float:right;'
        ],
      ];
      if ($this->element_type == "sdd") {
        $form['controls_row']['buttons_container']['download_sdd'] = [
          '#type' => 'submit',
          '#value' => $this->t('Download Selected ' . $this->single_class_name),
          '#name' => 'download_sdd',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'download-button'],
          ],
        ];
        $form['controls_row']['buttons_container']['ingest_sdd'] = [
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
        $form['controls_row']['buttons_container']['uningest_sdd'] = [
          '#type' => 'submit',
          '#value' => $this->t('Uningest Selected ' . $this->plural_class_name),
          '#name' => 'uningest_sdd',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'uningest_mt-element-button'],
          ],
        ];
      }
      $form['element_table_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'element-table-wrapper'],
      ];

      $form['element_table_wrapper']['element_table'] = [
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $output,
        '#js_select' => FALSE,
        '#empty' => t('No ' . $this->plural_class_name . ' found'),
      ];
      $form['element_table_wrapper']['pager'] = [
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
      $form['cards_lazy_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'cards-lazy-wrapper'],
      ];

      $this->buildCardView($form['cards_lazy_wrapper'], $form_state, $header, $outputCard);

      // SHOW "Load More" BUTTON
      // TOTAL ITEMS
      $total_items = $this->getListSize();

      // PAGESIZE
      $current_page_size = $form_state->get('page_size') ?? 9;

      //Prevent infinite scroll without new data
      if ($total_items > $current_page_size) {
        $form['cards_lazy_wrapper']['load_more_button'] = [
          '#type' => 'submit',
          '#value' => $this->t('Load More'),
          '#name' => 'load_more_button',
          '#attributes' => [
            'id' => 'load-more-button',
            'class' => ['btn', 'btn-primary', 'load-more-button'],
            'style' => 'display: none;',
          ],
          '#submit' => ['::loadMoreSubmit'],
          '#ajax' => [
            'callback' => '::ajaxReloadCards',
            'wrapper' => 'cards-lazy-wrapper',
            'event' => 'click',
          ],
          '#limit_validation_errors' => [],
        ];

        $form['cards_lazy_wrapper']['list_state'] = [
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
   * AJAX callback to reload list when filters change.
   */
  public function ajaxReloadTable(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['element_table_wrapper'];
  }

  /**
   * AJAX callback to reload card view when loading more.
   */
  public function ajaxReloadCards(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['cards_lazy_wrapper'];
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

    $form['element_cards_wrapper']['element_cards'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['row', 'mt-3']],
    ];

    foreach ($output as $key => $item) {
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
