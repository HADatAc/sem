<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\HASCO;
use Drupal\rep\Vocabulary\REPGUI;

class ViewSemanticDataDictionaryForm extends FormBase {

  protected $state;

  protected $semanticDataDictionary;

  public function getState() {
    return $this->state;
  }
  public function setState($state) {
    return $this->state = $state;
  }

  public function getSemanticDataDictionary() {
    return $this->semanticDataDictionary;
  }
  public function setSemanticDataDictionary($semanticDataDictionary) {
    return $this->semanticDataDictionary = $semanticDataDictionary;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_semantic_data_dictionary_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $state=NULL, $uri=NULL) {

    // INITIALIZE NS TABLE
    $tables = new Tables;
    $namespaces = $tables->getNamespaces();

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal'; // Biblioteca personalizada do módulo
    $form['#attached']['library'][] = 'core/drupal.dialog'; // Biblioteca do modal do Drupal

    if ($state === 'init') {
      // READ SEMANTIC_DATA_DICTIONARY
      $api = \Drupal::service('rep.api_connector');
      $uri_decode=base64_decode($uri);
      $semanticDataDictionary = $api->parseObjectResponse($api->getUri($uri_decode),'getUri');
      if ($semanticDataDictionary == NULL) {
        \Drupal::messenger()->addMessage(t("Failed to retrieve Semantic Data Dictionary."));
        self::backUrl();
        return;
      } else {
        $this->setSemanticDataDictionary($semanticDataDictionary);
        //dpm($this->getSemanticDataDictionary());
      }

      // RESET STATE TO BASIC
      $state = 'basic';

      // POPULATE DATA STRUCTURES
      $basic = $this->populateBasic();
      $variables = $this->populateVariables($namespaces);
      $objects = $this->populateObjects($namespaces);
      $codes = $this->populateCodes($namespaces);

    } else {

      $basic = \Drupal::state()->get('my_form_basic');
      $variables = \Drupal::state()->get('my_form_variables') ?? [];
      $objects = \Drupal::state()->get('my_form_objects') ?? [];
      $codes = \Drupal::state()->get('my_form_codes') ?? [];

    }

    // SAVE STATE
    $this->setState($state);

    // SET SEPARATOR
    $separator = '<div class="w-100"></div>';

    $form['semantic_data_dictionary_title'] = [
      '#type' => 'markup',
      '#markup' => '<h3 class="mt-5">Edit Semantic Data Dictionary</h3><br>',
    ];

    $form['current_state'] = [
      '#type' => 'hidden',
      '#value' => $state,
    ];

    // Container for pills and content.
    $form['pills_card'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nav', 'nav-pills', 'nav-justified', 'mb-3'],
        'id' => 'pills-card-container',
        'role' => 'tablist',
      ],
    ];

    // Define pills as links with AJAX callback.
    $states = [
      'basic' => 'Basic variables',
      'dictionary' => 'Data Dictionary',
      'codebook' => 'Codebook'
    ];

    foreach ($states as $key => $label) {
      $form['pills_card'][$key] = [
        '#type' => 'button',
        '#value' => $label,
        '#name' => 'button_' . $key,
        '#attributes' => [
          'class' => ['nav-link', $state === $key ? 'active' : ''],
          'data-state' => $key,
          'role' => 'presentation',
        ],
        '#ajax' => [
          'callback' => '::pills_card_callback',
          'event' => 'click',
          'wrapper' => 'pills-card-container',
          'progress' => ['type' => 'none'],
        ],
      ];
    }

    // Add a hidden field to capture the current state.
    $form['state'] = [
      '#type' => 'hidden',
      '#value' => $state,
    ];

    /* ========================== BASIC ========================= */

    if ($this->getState() == 'basic') {

      $name = '';
      if (isset($basic['name'])) {
        $name = $basic['name'];
      }
      $version = '';
      if (isset($basic['version'])) {
        $version = $basic['version'];
      }
      $description = '';
      if (isset($basic['description'])) {
        $description = $basic['description'];
      }

      $form['semantic_data_dictionary_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#default_value' => $name,
      ];
      $form['semantic_data_dictionary_version'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Version'),
        '#default_value' => $version,
      ];
      $form['semantic_data_dictionary_description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => $description,
      ];

    }

    /* ======================= DICTIONARY ======================= */

    if ($this->getState() == 'dictionary') {

      /*
      *      VARIABLES
      */

      $form['variables_title'] = [
        '#type' => 'markup',
        '#markup' => 'Variables',
      ];

      $form['variables'] = array(
        '#type' => 'container',
        '#title' => $this->t('variables'),
        '#attributes' => array(
          'class' => array('p-3', 'bg-light', 'text-dark', 'row', 'border', 'border-secondary', 'rounded'),
          'id' => 'custom-table-wrapper',
        ),
      );

      $form['variables']['header'] = array(
        '#type' => 'markup',
        '#markup' =>
          '<div class="p-2 col bg-secondary text-white border border-white">Column</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Attribute</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Is Attribute Of</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Unit</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Time</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">In Relation To</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Was Derived From</div>' .
          '<div class="p-2 col-md-1 bg-secondary text-white border border-white">Operations</div>' . $separator,
      );

      $form['variables']['rows'] = $this->renderVariableRows($variables);

      $form['variables']['space_3'] = [
        '#type' => 'markup',
        '#markup' => $separator,
      ];

      $form['variables']['actions']['top'] = array(
        '#type' => 'markup',
        '#markup' => '<div class="p-3 col">',
      );

      $form['variables']['actions']['add_row'] = [
        '#type' => 'submit',
        '#value' => $this->t('New Variable'),
        '#name' => 'new_variable',
        '#attributes' => array('class' => array('btn', 'btn-sm', 'add-element-button')),
      ];

      $form['variables']['actions']['bottom'] = array(
        '#type' => 'markup',
        '#markup' => '</div>' . $separator,
      );

      /*
      *      OBJECTS
      */

      $form['objects_title'] = [
        '#type' => 'markup',
        '#markup' => 'Objects',
      ];

      $form['objects'] = array(
        '#type' => 'container',
        '#title' => $this->t('Objects'),
        '#attributes' => array(
          'class' => array('p-3', 'bg-light', 'text-dark', 'row', 'border', 'border-secondary', 'rounded'),
          'id' => 'custom-table-wrapper',
        ),
      );

      $form['objects']['header'] = array(
        '#type' => 'markup',
        '#markup' =>
          '<div class="p-2 col bg-secondary text-white border border-white">Column</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Entity</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Role</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Relation</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">In Relation To</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Was Derived From</div>' .
          '<div class="p-2 col-md-1 bg-secondary text-white border border-white">Operations</div>' . $separator,
      );

      $form['objects']['rows'] = $this->renderObjectRows($objects);

      $form['objects']['space_3'] = [
        '#type' => 'markup',
        '#markup' => $separator,
      ];

      $form['objects']['actions']['top'] = array(
        '#type' => 'markup',
        '#markup' => '<div class="p-3 col">',
      );

      $form['objects']['actions']['add_row'] = [
        '#type' => 'submit',
        '#value' => $this->t('New Object'),
        '#name' => 'new_object',
        '#attributes' => array('class' => array('btn', 'btn-sm', 'add-element-button')),
      ];

      $form['objects']['actions']['bottom'] = array(
        '#type' => 'markup',
        '#markup' => '</div>' . $separator,
      );

    }

    /* ======================= CODEBOOK ======================= */

    if ($this->getState() == 'codebook') {

      /*
      *      CODES
      */

      $form['codes_title'] = [
        '#type' => 'markup',
        '#markup' => 'Codes',
      ];

      $form['codes'] = array(
        '#type' => 'container',
        '#title' => $this->t('codes'),
        '#attributes' => array(
          'class' => array('p-3', 'bg-light', 'text-dark', 'row', 'border', 'border-secondary', 'rounded'),
          'id' => 'custom-table-wrapper',
        ),
      );

      $form['codes']['header'] = array(
        '#type' => 'markup',
        '#markup' =>
          '<div class="p-2 col bg-secondary text-white border border-white">Column</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Code</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Label</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Class</div>' .
          '<div class="p-2 col-md-1 bg-secondary text-white border border-white">Operations</div>' . $separator,
      );

      $form['codes']['rows'] = $this->renderCodeRows($codes);

      $form['codes']['space_3'] = [
        '#type' => 'markup',
        '#markup' => $separator,
      ];

      $form['codes']['actions']['top'] = array(
        '#type' => 'markup',
        '#markup' => '<div class="p-3 col">',
      );

      $form['codes']['actions']['add_row'] = [
        '#type' => 'submit',
        '#value' => $this->t('New Code'),
        '#name' => 'new_code',
        '#attributes' => array('class' => array('btn', 'btn-sm', 'add-element-button')),
      ];

      $form['codes']['actions']['bottom'] = array(
        '#type' => 'markup',
        '#markup' => '</div>' . $separator,
      );

    }

    /* ======================= COMMON BOTTOM ======================= */

    $form['space'] = [
      '#type' => 'markup',
      '#markup' => '<br><br>',
    ];

    // $form['save_submit'] = [
    //   '#type' => 'submit',
    //   '#value' => $this->t('Save'),
    //   '#name' => 'save',
    //   '#attributes' => [
    //     'class' => ['btn', 'btn-primary', 'save-button'],
    //   ],
    // ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];

    //$form['#attached']['library'][] = 'sem/sem_list';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'save') {
      // TODO
      /*
      $basic = \Drupal::state()->get('my_form_basic');
      if(strlen($basic['name']) < 1) {
        $form_state->setErrorByName(
          'semantic_data_dictionary_name',
          $this->t('Please enter a valid name for the Semantic Data Dictionary')
        );
      }
      */
    }
  }

  public function pills_card_callback(array &$form, FormStateInterface $form_state) {

    // RETRIEVE CURRENT STATE AND SAVE IT ACCORDINGLY
    $currentState = $form_state->getValue('state');
    if ($currentState == 'basic') {
      $this->updateBasic($form_state);
    }
    if ($currentState == 'dictionary') {
      $this->updateVariables($form_state);
      $this->updateObjects($form_state);
    }
    if ($currentState == 'codebook') {
      $this->updateCodes($form_state);
    }

    // Need to retrieve $basic because it contains the semantic data dictionary's URI
    $basic = \Drupal::state()->get('my_form_basic');

    // RETRIEVE FUTURE STATE
    $triggering_element = $form_state->getTriggeringElement();
    $parts = explode('_', $triggering_element['#name']);
    $state = (isset($parts) && is_array($parts)) ? end($parts) : null;

    // BUILD NEW URL
    $root_url = \Drupal::request()->getBaseUrl();
    $newUrl = $root_url . REPGUI::EDIT_SEMANTIC_DATA_DICTIONARY . $state . '/' . base64_encode($basic['uri']);

    // REDIRECT TO NEW URL
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand($newUrl));

    return $response;
  }

  /******************************
   *
   *    BASIC'S FUNCTIONS
   *
   ******************************/

  /**
   * {@inheritdoc}
   */
  public function updateBasic(FormStateInterface $form_state) {
    $basic = \Drupal::state()->get('my_form_basic');
    $input = $form_state->getUserInput();

    if (isset($input) && is_array($input) &&
        isset($basic) && is_array($basic)) {
      $basic['name']        = $input['semantic_data_dictionary_name'] ?? '';
      $basic['version']     = $input['semantic_data_dictionary_version'] ?? '';
      $basic['description'] = $input['semantic_data_dictionary_description'] ?? '';
      \Drupal::state()->set('my_form_basic', $basic);
    }
    $response = new AjaxResponse();
    return $response;
  }

  public function populateBasic() {
    $basic = [
      'uri' => $this->getSemanticDataDictionary()->uri,
      'name' => $this->getSemanticDataDictionary()->label,
      'version' => $this->getSemanticDataDictionary()->hasVersion,
      'description' => $this->getSemanticDataDictionary()->comment,
    ];
    \Drupal::state()->set('my_form_basic', $basic);
    return $basic;
  }

  /******************************
   *
   *    variables' FUNCTIONS
   *
   ******************************/

  protected function renderVariableRows(array $variables) {
    $form_rows = [];
    $separator = '<div class="w-100"></div>';
    foreach ($variables as $delta => $variable) {

      $form_row = array(
        'column' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'variable_column_' . $delta,
            '#value' => $variable['column'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'attribute' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'variable_attribute_' . $delta,
            '#value' => $variable['attribute'],
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'attribute',
              ], ['query' => ['field_id' => 'variable_attribute_' . $delta]])->toString(),
              'data-field-id' => 'variable_attribute_' . $delta,
              'data-search-value' => $variable['attribute'],
              'data-elementtype' => 'attribute',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'is_attribute_of' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'variable_is_attribute_of_' . $delta,
            '#value' => $variable['is_attribute_of'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'unit' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'variable_unit_' . $delta,
            '#value' => $variable['unit'],
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'unit',
              ], ['query' => ['field_id' => 'variable_unit_' . $delta]])->toString(),
              'data-field-id' => 'variable_unit_' . $delta,
              'data-search-value' => $variable['unit'],
              'data-elementtype' => 'unit',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'time' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'variable_time_' . $delta,
            '#value' => $variable['time'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'in_relation_to' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'variable_in_relation_to_' . $delta,
            '#value' => $variable['in_relation_to'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'was_derived_from' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'variable_was_derived_from_' . $delta,
            '#value' => $variable['was_derived_from'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'operations' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col-md-1 border border-white">',
          ),
          'main' => array(
            '#type' => 'submit',
            '#name' => 'variable_remove_' . $delta,
            '#value' => $this->t('Remove'),
            '#attributes' => array(
              'class' => array('remove-row', 'btn', 'btn-sm', 'delete-element-button'),
              'id' => 'variable-' . $delta,
            ),
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>' . $separator,
          ),
        ),
      );

      $rowId = 'row' . $delta;
      $form_rows[] = [
        $rowId => $form_row,
      ];

    }
    return $form_rows;
  }

  protected function updateVariables(FormStateInterface $form_state) {
    $variables = \Drupal::state()->get('my_form_variables');
    $input = $form_state->getUserInput();
    if (isset($input) && is_array($input) &&
        isset($variables) && is_array($variables)) {

      foreach ($variables as $variable_id => $variable) {
        if (isset($variable_id) && isset($variable)) {
          $variables[$variable_id]['column']            = $input['variable_column_' . $variable_id] ?? '';
          $variables[$variable_id]['attribute']         = $input['variable_attribute_' . $variable_id] ?? '';
          $variables[$variable_id]['is_attribute_of']   = $input['variable_is_attribute_of_' . $variable_id] ?? '';
          $variables[$variable_id]['unit']              = $input['variable_unit_' . $variable_id] ?? '';
          $variables[$variable_id]['time']              = $input['variable_time_' . $variable_id] ?? '';
          $variables[$variable_id]['in_relation_to']    = $input['variable_in_relation_to_' . $variable_id] ?? '';
          $variables[$variable_id]['was_derived_from']  = $input['variable_was_derived_from_' . $variable_id] ?? '';
        }
      }
      \Drupal::state()->set('my_form_variables', $variables);
    }
    return;
  }

  protected function populateVariables($namespaces) {
    $variables = [];
    $attributes = $this->getSemanticDataDictionary()->attributes;
    if (count($attributes) > 0) {
      foreach ($attributes as $attribute_id => $attribute) {
        if (isset($attribute_id) && isset($attribute)) {
          $listPosition = $attribute->listPosition;
          $variables[$listPosition]['column']            = $attribute->label;
          $variables[$listPosition]['attribute']         = Utils::namespaceUriWithNS($attribute->attribute,$namespaces);
          $variables[$listPosition]['is_attribute_of']   = $attribute->objectUri;
          $variables[$listPosition]['unit']              = Utils::namespaceUriWithNS($attribute->unit,$namespaces);
          $variables[$listPosition]['time']              = Utils::namespaceUriWithNS($attribute->eventUri,$namespaces);
          $variables[$listPosition]['in_relation_to']    = $attribute->inRelationTo;
          $variables[$listPosition]['was_derived_from']  = $attribute->wasDerivedFrom;
        }
      }
      ksort($variables);
    }
    \Drupal::state()->set('my_form_variables', $variables);

    return $variables;
  }

  protected function saveVariables($semanticDataDictionaryUri, array $variables) {
    if (!isset($semanticDataDictionaryUri)) {
      \Drupal::messenger()->addError(t("No semantic data dictionary's URI have been provided to save variables."));
      return;
    }
    if (!isset($variables) || !is_array($variables)) {
      \Drupal::messenger()->addWarning(t("Semantic data dictionary has no variable to be saved."));
      return;
    }

    foreach ($variables as $variable_id => $variable) {
      if (isset($variable_id) && isset($variable)) {
        try {
          $useremail = \Drupal::currentUser()->getEmail();

          $column = ' ';
          if ($variables[$variable_id]['column'] != NULL && $variables[$variable_id]['column'] != '') {
            $column = $variables[$variable_id]['column'];
          }

          $attributeUri = ' ';
          if ($variables[$variable_id]['attribute'] != NULL && $variables[$variable_id]['attribute'] != '') {
            $attributeUri = $variables[$variable_id]['attribute'];
          }

          $isAttributeOf = ' ';
          if ($variables[$variable_id]['is_attribute_of'] != NULL && $variables[$variable_id]['is_attribute_of'] != '') {
            $isAttributeOf = $variables[$variable_id]['is_attribute_of'];
          }

          $unitUri = ' ';
          if ($variables[$variable_id]['unit'] != NULL && $variables[$variable_id]['unit'] != '') {
            $unitUri = $variables[$variable_id]['unit'];
          }

          $timeUri = ' ';
          if ($variables[$variable_id]['time'] != NULL && $variables[$variable_id]['time'] != '') {
            $timeUri = $variables[$variable_id]['time'];
          }

          $inRelationToUri = ' ';
          if ($variables[$variable_id]['in_relation_to'] != NULL && $variables[$variable_id]['in_relation_to'] != '') {
            $inRelationToUri = $variables[$variable_id]['in_relation_to'];
          }

          $wasDerivedFromUri = ' ';
          if ($variables[$variable_id]['was_derived_from'] != NULL && $variables[$variable_id]['was_derived_from'] != '') {
            $wasDerivedFromUri = $variables[$variable_id]['was_derived_from'];
          }

          $variableUri = str_replace(
            Constant::PREFIX_SEMANTIC_DATA_DICTIONARY,
            Constant::PREFIX_SDD_ATTRIBUTE,
            $semanticDataDictionaryUri) . '/' . $variable_id;
          $variableJSON = '{"uri":"'. $variableUri .'",'.
              '"typeUri":"'.HASCO::SDD_ATTRIBUTE.'",'.
              '"hascoTypeUri":"'.HASCO::SDD_ATTRIBUTE.'",'.
              '"partOfSchema":"'.$semanticDataDictionaryUri.'",'.
              '"listPosition":"'.$variable_id.'",'.
              '"label":"'.$column.'",'.
              '"attribute":"' . $attributeUri . '",' .
              '"objectUri":"' . $isAttributeOf . '",' .
              '"unit":"' . $unitUri . '",' .
              '"eventUri":"' . $timeUri . '",' .
              '"inRelationTo":"' . $inRelationToUri . '",' .
              '"wasDerivedFrom":"' . $wasDerivedFromUri . '",' .
              '"comment":"Column ' . $column . ' of ' . $semanticDataDictionaryUri . '",'.
              '"hasSIRManagerEmail":"'.$useremail.'"}';
          $api = \Drupal::service('rep.api_connector');
          $api->elementAdd('sddattribute',$variableJSON);

          //dpm($variableJSON);

        } catch(\Exception $e){
          \Drupal::messenger()->addError(t("An error occurred while saving the semantic data dictionary: ".$e->getMessage()));
        }
      }
    }
    return;
  }

  public function addVariableRow() {
    $variables = \Drupal::state()->get('my_form_variables') ?? [];

    // Add a new row to the table.
    $variables[] = [
      'column' => '',
      'attribute' => '',
      'is_attribute_of' => '',
      'unit' => '',
      'time' => '',
      'in_relation_to' => '',
      'was_derived_from' => '',
    ];
    \Drupal::state()->set('my_form_variables', $variables);

    // Rebuild the table rows.
    $form['variables']['rows'] = $this->renderVariableRows($variables);
    return;
  }

  public function removeVariableRow($button_name) {
    $variables = \Drupal::state()->get('my_form_variables') ?? [];

    // from button name's value, determine which row to remove.
    $parts = explode('_', $button_name);
    $variable_to_remove = (isset($parts) && is_array($parts)) ? (int) (end($parts)) : null;

    if (isset($variable_to_remove) && $variable_to_remove > -1) {
      unset($variables[$variable_to_remove]);
      $variables = array_values($variables);
      \Drupal::state()->set('my_form_variables', $variables);
    }
    return;
  }

  /******************************
   *
   *    OBJECTS' FUNCTIONS
   *
   ******************************/

  protected function renderObjectRows(array $objects) {
    $form_rows = [];
    $separator = '<div class="w-100"></div>';
    foreach ($objects as $delta => $object) {

      $form_row = array(
        'column' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'object_column_' . $delta,
            '#value' => $object['column'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'entity' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'object_entity_' . $delta,
            '#value' => $object['entity'],
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'entity',
              ], ['query' => ['field_id' => 'object_entity_' . $delta]])->toString(),
              'data-field-id' => 'object_entity_' . $delta,
              'data-search-value' => $object['entity'],
              'data-elementtype' => 'entity',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'role' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'object_role_' . $delta,
            '#value' => $object['role'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'relation' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'object_relation_' . $delta,
            '#value' => $object['relation'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'in_relation_to' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'object_in_relation_to_' . $delta,
            '#value' => $object['in_relation_to'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'was_derived_from' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'object_was_derived_from_' . $delta,
            '#value' => $object['was_derived_from'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'operations' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col-md-1 border border-white">',
          ),
          'main' => array(
            '#type' => 'submit',
            '#name' => 'object_remove_' . $delta,
            '#value' => $this->t('Remove'),
            '#attributes' => array(
              'class' => array('remove-row', 'btn', 'btn-sm', 'delete-element-button'),
              'id' => 'object-' . $delta,
            ),
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>' . $separator,
          ),
        ),
      );

      $rowId = 'row' . $delta;
      $form_rows[] = [
        $rowId => $form_row,
      ];

    }
    return $form_rows;
  }

  protected function updateObjects(FormStateInterface $form_state) {
    $objects = \Drupal::state()->get('my_form_objects');
    $input = $form_state->getUserInput();
    if (isset($input) && is_array($input) &&
        isset($objects) && is_array($objects)) {

      foreach ($objects as $object_id => $object) {
        if (isset($object_id) && isset($object)) {
          $objects[$object_id]['column']            = $input['object_column_' . $object_id] ?? '';
          $objects[$object_id]['entity']            = $input['object_entity_' . $object_id] ?? '';
          $objects[$object_id]['role']              = $input['object_role_' . $object_id] ?? '';
          $objects[$object_id]['relation']          = $input['object_relation_' . $object_id] ?? '';
          $objects[$object_id]['in_relation_to']    = $input['object_in_relation_to_' . $object_id] ?? '';
          $objects[$object_id]['was_derived_from']  = $input['object_was_derived_from_' . $object_id] ?? '';
        }
      }
      \Drupal::state()->set('my_form_objects', $objects);
    }
    return;
  }

  protected function populateObjects($namespaces) {
    $objects = [];
    $objs = $this->getSemanticDataDictionary()->objects;
    if (count($objs) > 0) {
      foreach ($objs as $obj_id => $obj) {
        if (isset($obj_id) && isset($obj)) {
          $listPosition = $obj->listPosition;
          $objects[$listPosition]['column']            = $obj->label;
          $objects[$listPosition]['entity']            = Utils::namespaceUriWithNS($obj->entity,$namespaces);
          $objects[$listPosition]['role']              = Utils::namespaceUriWithNS($obj->role,$namespaces);
          $objects[$listPosition]['relation']          = Utils::namespaceUriWithNS($obj->relation,$namespaces);
          $objects[$listPosition]['in_relation_to']    = $obj->inRelationTo;
          $objects[$listPosition]['was_derived_from']  = $obj->wasDerivedFrom;
        }
      }
      ksort($objects);
    }
    \Drupal::state()->set('my_form_objects', $objects);

    return $objects;
  }

  protected function saveObjects($semanticDataDictionaryUri, array $objects) {
    if (!isset($semanticDataDictionaryUri)) {
      \Drupal::messenger()->addError(t("No semantic data dictionary's URI have been provided to save objects."));
      return;
    }
    if (!isset($objects) || !is_array($objects)) {
      \Drupal::messenger()->addWarning(t("Semantic data dictionary has no objects to be saved."));
      return;
    }

    foreach ($objects as $object_id => $object) {
      if (isset($object_id) && isset($object)) {
        try {
          $useremail = \Drupal::currentUser()->getEmail();

          $column = ' ';
          if ($objects[$object_id]['column'] != NULL && $objects[$object_id]['column'] != '') {
            $column = $objects[$object_id]['column'];
          }

          $entity = ' ';
          if ($objects[$object_id]['entity'] != NULL && $objects[$object_id]['entity'] != '') {
            $entity = $objects[$object_id]['entity'];
          }

          $role = ' ';
          if ($objects[$object_id]['role'] != NULL && $objects[$object_id]['role'] != '') {
            $role = $objects[$object_id]['role'];
          }

          $relation = ' ';
          if ($objects[$object_id]['relation'] != NULL && $objects[$object_id]['relation'] != '') {
            $relation = $objects[$object_id]['relation'];
          }

          $inRelationTo = ' ';
          if ($objects[$object_id]['in_relation_to'] != NULL && $objects[$object_id]['in_relation_to'] != '') {
            $inRelationTo = $objects[$object_id]['in_relation_to'];
          }

          $wasDerivedFrom = ' ';
          if ($objects[$object_id]['was_derived_from'] != NULL && $objects[$object_id]['was_derived_from'] != '') {
            $wasDerivedFrom = $objects[$object_id]['was_derived_from'];
          }

          $objectUri = str_replace(
            Constant::PREFIX_SEMANTIC_DATA_DICTIONARY,
            Constant::PREFIX_SDD_OBJECT,
            $semanticDataDictionaryUri) . '/' . $object_id;
          $objectJSON = '{"uri":"'. $objectUri .'",'.
              '"typeUri":"'.HASCO::SDD_OBJECT.'",'.
              '"hascoTypeUri":"'.HASCO::SDD_OBJECT.'",'.
              '"partOfSchema":"'.$semanticDataDictionaryUri.'",'.
              '"listPosition":"'.$object_id.'",'.
              '"label":"'.$column.'",'.
              '"entity":"' . $entity . '",' .
              '"role":"' . $role . '",' .
              '"relation":"' . $relation . '",' .
              '"inRelationTo":"' . $inRelationTo . '",' .
              '"wasDerivedFrom":"' . $wasDerivedFrom . '",' .
              '"comment":"Column ' . $column . ' of ' . $semanticDataDictionaryUri . '",'.
              '"hasSIRManagerEmail":"'.$useremail.'"}';
          $api = \Drupal::service('rep.api_connector');
          $api->elementAdd('sddobject',$objectJSON);

          //dpm($objectJSON);

        } catch(\Exception $e){
          \Drupal::messenger()->addError(t("An error occurred while saving an SDDObject: ".$e->getMessage()));
        }
      }
    }
    return;
  }

  public function addObjectRow() {

    // Retrieve existing rows from form state or initialize as empty.
    $objects = \Drupal::state()->get('my_form_objects') ?? [];

    // Add a new row to the table.
    $objects[] = [
      'column' => '',
      'entity' => '',
      'role' => '',
      'relation' => '',
      'in_relation_to' => '',
      'was_derived_from' => '',
    ];
    // Update the form state with the new rows.
    \Drupal::state()->set('my_form_objects', $objects);

    // Rebuild the table rows.
    $form['objects']['rows'] = $this->renderObjectRows($objects);

  }

  public function removeObjectRow($button_name) {
    $objects = \Drupal::state()->get('my_form_objects') ?? [];

    // from button name's value, determine which row to remove.
    $parts = explode('_', $button_name);
    $object_to_remove = (isset($parts) && is_array($parts)) ? (int) (end($parts)) : null;

    if (isset($object_to_remove) && $object_to_remove > -1) {
      unset($objects[$object_to_remove]);
      $objects = array_values($objects);
      \Drupal::state()->set('my_form_objects', $objects);
    }
    return;
  }

  /******************************
   *
   *    CODE'S FUNCTIONS
   *
   ******************************/

   protected function renderCodeRows(array $codes) {
    $form_rows = [];
    $separator = '<div class="w-100"></div>';
    foreach ($codes as $delta => $code) {

      $form_row = array(
        'column' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'code_column_' . $delta,
            '#default_value' => $code['column'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'code' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'code_code_' . $delta,
            '#default_value' => $code['code'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'label' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'code_label_' . $delta,
            '#default_value' => $code['label'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'class' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'code_class_' . $delta,
            '#default_value' => $code['class'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'operations' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col-md-1 border border-white">',
          ),
          'main' => array(
            '#type' => 'submit',
            '#name' => 'code_remove_' . $delta,
            '#value' => $this->t('Remove'),
            '#attributes' => array(
              'class' => array('remove-row', 'btn', 'btn-sm', 'delete-element-button'),
              'id' => 'code-' . $delta,
            ),
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>' . $separator,
          ),
        ),
      );

      $rowId = 'row' . $delta;
      $form_rows[] = [
        $rowId => $form_row,
      ];

    }
    return $form_rows;
  }

  protected function updateCodes(FormStateInterface $form_state) {
    $codes = \Drupal::state()->get('my_form_codes');
    $input = $form_state->getUserInput();
    if (isset($input) && is_array($input) &&
        isset($codes) && is_array($codes)) {

      foreach ($codes as $code_id => $code) {
        if (isset($code_id) && isset($code)) {
          $codes[$code_id]['column']  = $input['code_column_' . $code_id] ?? '';
          $codes[$code_id]['code']    = $input['code_code_' . $code_id] ?? '';
          $codes[$code_id]['label']   = $input['code_label_' . $code_id] ?? '';
          $codes[$code_id]['class']   = $input['code_class_' . $code_id] ?? '';
        }
      }
      \Drupal::state()->set('my_form_codes', $codes);
    }
    return;
  }

  protected function populateCodes($namespaces) {
    $codes = [];
    $possibleValues = $this->getSemanticDataDictionary()->possibleValues;
    if (count($possibleValues) > 0) {
      foreach ($possibleValues as $possibleValue_id => $possibleValue) {
        if (isset($possibleValue_id) && isset($possibleValue)) {
          $listPosition = $possibleValue->listPosition;
          $codes[$listPosition]['column']  = $possibleValue->isPossibleValueOf;
          $codes[$listPosition]['code']    = $possibleValue->hasCode;
          $codes[$listPosition]['label']   = $possibleValue->hasCodeLabel;
          $codes[$listPosition]['class']   = Utils::namespaceUriWithNS($possibleValue->hasClass,$namespaces);
        }
      }
      ksort($codes);
    }
    \Drupal::state()->set('my_form_codes', $codes);
    return $codes;
  }

  protected function saveCodes($semanticDataDictionaryUri, array $codes) {
    if (!isset($semanticDataDictionaryUri)) {
      \Drupal::messenger()->addError(t("No semantic data dictionary's URI have been provided to save possible values."));
      return;
    }
    if (!isset($codes) || !is_array($codes)) {
      \Drupal::messenger()->addWarning(t("Semantic data dictionary has no possible values to be saved."));
      return;
    }

    foreach ($codes as $code_id => $code) {
      if (isset($code_id) && isset($code)) {
        try {
          $useremail = \Drupal::currentUser()->getEmail();

          $column = ' ';
          if ($codes[$code_id]['column'] != NULL && $codes[$code_id]['column'] != '') {
            $column = $codes[$code_id]['column'];
          }

          $codeStr = ' ';
          if ($codes[$code_id]['code'] != NULL && $codes[$code_id]['code'] != '') {
            $codeStr = $codes[$code_id]['code'];
          }

          $codeLabel = ' ';
          if ($codes[$code_id]['label'] != NULL && $codes[$code_id]['label'] != '') {
            $codeLabel = $codes[$code_id]['label'];
          }

          $class = ' ';
          if ($codes[$code_id]['class'] != NULL && $codes[$code_id]['class'] != '') {
            $class = $codes[$code_id]['class'];
          }

          $codeUri = str_replace(
            Constant::PREFIX_SEMANTIC_DATA_DICTIONARY,
            Constant::PREFIX_POSSIBLE_VALUE,
            $semanticDataDictionaryUri) . '/' . $code_id;
          $codeJSON = '{"uri":"'. $codeUri .'",'.
              '"superUri":"'.HASCO::POSSIBLE_VALUE.'",'.
              '"hascoTypeUri":"'.HASCO::POSSIBLE_VALUE.'",'.
              '"partOfSchema":"'.$semanticDataDictionaryUri.'",'.
              '"listPosition":"'.$code_id.'",'.
              '"isPossibleValueOf":"'.$column.'",'.
              '"label":"'.$column.'",'.
              '"hasCode":"' . $codeStr . '",' .
              '"hasCodeLabel":"' . $codeLabel . '",' .
              '"hasClass":"' . $class . '",' .
              '"comment":"Possible value ' . $column . ' of ' . $column . ' of SDD ' . $semanticDataDictionaryUri . '",'.
              '"hasSIRManagerEmail":"'.$useremail.'"}';
          $api = \Drupal::service('rep.api_connector');
          $api->elementAdd('possiblevalue',$codeJSON);

          //dpm($codeJSON);

        } catch(\Exception $e){
          \Drupal::messenger()->addError(t("An error occurred while saving possible value(s): ".$e->getMessage()));
        }
      }
    }
    return;
  }

  public function addCodeRow() {
    $codes = \Drupal::state()->get('my_form_codes') ?? [];

    // Add a new row to the table.
    $codes[] = [
      'column' => '',
      'code' => '',
      'label' => '',
      'class' => '',
    ];
    \Drupal::state()->set('my_form_codes', $codes);

    // Rebuild the table rows.
    $form['codes']['rows'] = $this->renderCodeRows($codes);
    return;
  }

  public function removeCodeRow($button_name) {
    $codes = \Drupal::state()->get('my_form_codes') ?? [];

    // from button name's value, determine which row to remove.
    $parts = explode('_', $button_name);
    $code_to_remove = (isset($parts) && is_array($parts)) ? (int) (end($parts)) : null;

    if (isset($code_to_remove) && $code_to_remove > -1) {
      unset($codes[$code_to_remove]);
      $codes = array_values($codes);
      \Drupal::state()->set('my_form_codes', $codes);
    }
    return;
  }

  /* ================================================================================ *
   *
   *                                 SUBMIT FORM
   *
   * ================================================================================ */

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // IDENTIFY NAME OF BUTTON triggering submitForm()
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      // Release values cached in the editor before leaving it
      \Drupal::state()->delete('my_form_basic');
      \Drupal::state()->delete('my_form_variables');
      \Drupal::state()->delete('my_form_objects');
      \Drupal::state()->delete('my_form_codes');
      self::backUrl();
      return;
    }

    // If not leaving then UPDATE STATE OF VARIABLES, OBJECTS AND CODES
    // according to the current state of the editor
    if ($this->getState() === 'basic') {
      $this->updateBasic($form_state);
    }

    if ($this->getState() === 'dictionary') {
      $this->updateVariables($form_state);
      $this->updateObjects($form_state);
    }

    if ($this->getState() === 'codebook') {
      $this->updateCodes($form_state);
    }

    // Get the latest cached versions of values in the editor
    $basic = \Drupal::state()->get('my_form_basic');
    $variables = \Drupal::state()->get('my_form_variables');
    $objects = \Drupal::state()->get('my_form_objects');
    $codes = \Drupal::state()->get('my_form_codes');

    if ($button_name === 'new_variable') {
      $this->addVariableRow();
      return;
    }

    if (str_starts_with($button_name,'variable_remove_')) {
      $this->removeVariableRow($button_name);
      return;
    }

    if ($button_name === 'new_object') {
      $this->addObjectRow();
      return;
    }

    if (str_starts_with($button_name,'object_remove_')) {
      $this->removeObjectRow($button_name);
      return;
    }

    if ($button_name === 'new_code') {
      $this->addCodeRow();
      return;
    }

    if (str_starts_with($button_name,'code_remove_')) {
      $this->removeCodeRow($button_name);
      return;
    }

    if ($button_name === 'save') {
      try {
        $useremail = \Drupal::currentUser()->getEmail();
        $semanticDataDictionaryJSON = '{"uri":"'. $basic['uri'] .'",'.
            '"typeUri":"'.HASCO::SEMANTIC_DATA_DICTIONARY.'",'.
            '"hascoTypeUri":"'.HASCO::SEMANTIC_DATA_DICTIONARY.'",'.
            '"label":"'.$basic['name'].'",'.
            '"hasVersion":"'.$basic['version'].'",'.
            '"comment":"'.$basic['description'].'",'.
            '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api = \Drupal::service('rep.api_connector');

        // The DELETE of the semantic data dictionary will also delete the
        // variables, objects and codes of the dictionary
        $api->elementDel('semanticdatadictionary',$basic['uri']);

        // In order to update the semantic dictionary it is necessary to
        // add the following to the dictionary: the dictionary itself, its
        // variables, its objects and its codes
        $api->elementAdd('semanticdatadictionary',$semanticDataDictionaryJSON);
        if (isset($variables)) {
          $this->saveVariables($basic['uri'],$variables);
        }
        if (isset($objects)) {
          $this->saveObjects($basic['uri'],$objects);
        }
        if (isset($codes)) {
          $this->saveCodes($basic['uri'],$codes);
        }

        // Release values cached in the editor
        \Drupal::state()->delete('my_form_basic');
        \Drupal::state()->delete('my_form_variables');
        \Drupal::state()->delete('my_form_objects');
        \Drupal::state()->delete('my_form_codes');

        \Drupal::messenger()->addMessage(t("Semantic Data Dictionary has been updated successfully."));
        self::backUrl();
        return;

      } catch(\Exception $e){
        \Drupal::messenger()->addMessage(t("An error occurred while updating a semantic data dictionary: ".$e->getMessage()));
        self::backUrl();
        return;
      }
    }

  }

  /**
   * Callback para abrir o modal com o formulário.
   */
  public function openTreeModalCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Obtenha a URL para carregar o modal (usando data-url do campo).
    $triggering_element = $form_state->getTriggeringElement();
    $url = $triggering_element['#attributes']['data-url'];

    // Adicione o comando para abrir o modal com o formulário.
    $response->addCommand(new OpenModalDialogCommand(
      $this->t('Tree Form'),
      '<iframe src="' . $url . '" style="width: 100%; height: 400px; border: none;"></iframe>',
      ['width' => '800']
    ));

    return $response;
  }


  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sem.edit_semantic_data_dictionary');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
