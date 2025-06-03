<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\HASCO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Component\Serialization\Json;

class ViewSemanticDataDictionaryForm extends FormBase {

  protected $semanticDataDictionary;

  public function getSemanticDataDictionary() {
    return $this->semanticDataDictionary;
  }

  public function setSemanticDataDictionary($sdd) {
    return $this->semanticDataDictionary = $sdd;
  }

  public function getFormId() {
    return 'view_semantic_data_dictionary_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $form_title = NULL, $state = NULL, $uri = NULL) {

    $form['#cache']['max-age'] = 0;

    $form['#prefix'] = '<div id="sdd-tabbed-form-wrapper">';
    $form['#suffix'] = '</div>';

    $tables = new Tables();
    $namespaces = $tables->getNamespaces();

    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    if (empty($uri)) {

      \Drupal::messenger()->addError($this->t('No Semantic Data Dictionary URI provided.'));
      return $form;
    }
    $decoded_uri = base64_decode($uri);
    $api = \Drupal::service('rep.api_connector');
    $sdd_object = $api->parseObjectResponse($api->getUri($decoded_uri), 'getUri');
    if ($sdd_object === NULL) {
      \Drupal::messenger()->addError($this->t('Failed to retrieve Semantic Data Dictionary.'));
      return $form;
    }

    $this->setSemanticDataDictionary($sdd_object);

    $basic = $this->populateBasic();
    $variables = $this->populateVariables($namespaces);
    $objects = $this->populateObjects($namespaces);
    $codes = $this->populateCodes($namespaces);

    // ================================================================
    // 3) Build vertical tabs container
    // ================================================================
    $form['tabs'] = [
      '#type' => 'vertical_tabs',
      '#weight' => 0,
    ];

    // ================================================================
    // 4) “Basic variables” tab:
    // ================================================================
    $form['basic_tab'] = [
      '#type' => 'details',
      '#title' => $this->t('Basic variables'),
      '#group' => 'tabs',
      '#open' => TRUE,
    ];

    $form['basic_tab']['semantic_data_dictionary_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $basic['name'] ?? '',
      '#disabled' => TRUE,
      '#wrapper_attributes' => [
        'class' => ['mt-3'],
      ],
    ];

    $form['basic_tab']['semantic_data_dictionary_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $basic['version'] ?? '',
      '#disabled' => TRUE,
    ];

    $form['basic_tab']['semantic_data_dictionary_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $basic['description'] ?? '',
      '#disabled' => TRUE,
    ];

    // ================================================================
    // 5) “Data Dictionary” tab (Variables + Objects):
    // ================================================================
    $form['dictionary_tab'] = [
      '#type' => 'details',
      '#title' => $this->t('Data Dictionary'),
      '#group' => 'tabs',
    ];

    $form['dictionary_tab']['variables_title'] = [
      '#type' => 'markup',
      '#markup' => '<br /><h4>' . $this->t('Variables') . '</h4>',
    ];

    $form['dictionary_tab']['variables'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'p-3', 'bg-light', 'text-dark',
          'row', 'border', 'border-secondary', 'rounded',
        ],
      ],
      // '#disabled' => TRUE,
    ];

    $form['dictionary_tab']['variables']['header'] = [
      '#type' => 'markup',
      '#markup' =>
        '<div class="p-2 col bg-secondary text-white border border-white">Column</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">Attribute</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">Is Attribute Of</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">Unit</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">Time</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">In Relation To</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">Derived From</div>',
    ];

    $form['dictionary_tab']['variables']['rows'] = $this->renderVariableRows($variables);

    $form['dictionary_tab']['objects_title'] = [
      '#type' => 'markup',
      '#markup' => '<br /><h4>' . $this->t('Objects') . '</h4>',
    ];

    $form['dictionary_tab']['objects'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'p-3', 'bg-light', 'text-dark',
          'row', 'border', 'border-secondary', 'rounded',
        ],
      ],
      // '#disabled' => TRUE,
    ];

    $form['dictionary_tab']['objects']['header'] = [
      '#type' => 'markup',
      '#markup' =>
        '<div class="p-2 col bg-secondary text-white border border-white">Column</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">Entity</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">Role</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">Relation</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">In Relation To</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">Derived From</div>',
    ];

    $form['dictionary_tab']['objects']['rows'] = $this->renderObjectRows($objects);

    // ================================================================
    // 6) “Codebook” tab (Possible Values / Codes):
    // ================================================================
    $form['codebook_tab'] = [
      '#type' => 'details',
      '#title' => $this->t('Codebook'),
      '#group' => 'tabs',
    ];

    $form['codebook_tab']['codes_title'] = [
      '#type' => 'markup',
      '#markup' => '<br ><h4>' . $this->t('Codes') . '</h4>',
    ];

    $form['codebook_tab']['codes'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'p-3', 'bg-light', 'text-dark',
          'row', 'border', 'border-secondary', 'rounded',
        ],
      ],
      '#disabled' => TRUE,
    ];

    $form['codebook_tab']['codes']['header'] = [
      '#type' => 'markup',
      '#markup' =>
        '<div class="p-2 col bg-secondary text-white border border-white">Column</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">Code</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">Label</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">Class</div>',
    ];

    $form['codebook_tab']['codes']['rows'] = $this->renderCodeRows($codes);

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 100,
    ];

    // $form['actions']['save'] = [
    //   '#type' => 'submit',
    //   '#value' => $this->t('Save'),
    //   '#button_type' => 'primary',
    // ];

    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#button_type' => 'secondary',
      '#submit' => ['::backButtonSubmit'],
      '#attributes' => [
        'class' => ['btn', 'btn-secondary', 'back-button', 'mb-5'],
      ],
    ];

    return $form;
  }

  /**
   * “Back” button submit handler: limpa caches e redireciona ao referrer.
   */
  public function backButtonSubmit(array &$form, FormStateInterface $form_state) {
    \Drupal::state()->delete('my_form_basic');
    \Drupal::state()->delete('my_form_variables');
    \Drupal::state()->delete('my_form_objects');
    \Drupal::state()->delete('my_form_codes');

    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'std.manage_study_elements');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

  /**
   * {@inheritdoc}
   *
   * Nenhuma validação extra: todos os campos estão em “#disabled => TRUE”.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Nada a validar.
  }

  /**
   * {@inheritdoc}
   *
   * No submit, recriamos via REP API:
   *   1. Carrega valores em memória (basic, variables, objects, codes).
   *   2. Deleta o SDD antigo.
   *   3. Recria o SDD (basic JSON).
   *   4. Chama saveVariables(), saveObjects(), saveCodes().
   *   5. Limpa caches e redireciona de volta.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Carrega cada array em cache (armazenado em populate...).
    $basic = \Drupal::state()->get('my_form_basic', []);
    $variables = \Drupal::state()->get('my_form_variables', []);
    $objects = \Drupal::state()->get('my_form_objects', []);
    $codes = \Drupal::state()->get('my_form_codes', []);

    // Se faltar o URI básico, exibimos erro e abortamos.
    if (empty($basic['uri'])) {
      \Drupal::messenger()->addError($this->t('Cannot save: missing SDD URI.'));
      return;
    }

    try {
      $useremail = \Drupal::currentUser()->getEmail();
      // JSON básico para o SDD:
      $sdd_json = json_encode([
        'uri' => $basic['uri'],
        'typeUri' => HASCO::SEMANTIC_DATA_DICTIONARY,
        'hascoTypeUri' => HASCO::SEMANTIC_DATA_DICTIONARY,
        'label' => $basic['name'],
        'hasVersion' => $basic['version'],
        'comment' => $basic['description'],
        'hasSIRManagerEmail' => $useremail,
      ]);

      $api = \Drupal::service('rep.api_connector');
      // Deleta o SDD antigo (isso já deleta variáveis/objetos/códigos).
      $api->elementDel('semanticdatadictionary', $basic['uri']);
      // Recria o SDD principal.
      $api->elementAdd('semanticdatadictionary', $sdd_json);

      // Se existirem variáveis, recriamos uma a uma:
      if (!empty($variables) && is_array($variables)) {
        $this->saveVariables($basic['uri'], $variables);
      }
      // Se existirem objetos, recriamos cada um:
      if (!empty($objects) && is_array($objects)) {
        $this->saveObjects($basic['uri'], $objects);
      }
      // Se existirem códigos, recriamos cada um:
      if (!empty($codes) && is_array($codes)) {
        $this->saveCodes($basic['uri'], $codes);
      }

      // Por fim, limpa caches:
      \Drupal::state()->delete('my_form_basic');
      \Drupal::state()->delete('my_form_variables');
      \Drupal::state()->delete('my_form_objects');
      \Drupal::state()->delete('my_form_codes');

      \Drupal::messenger()->addStatus($this->t('Semantic Data Dictionary has been updated successfully.'));
      // Redireciona de volta ao referer (ou root “/”).
      $referer = \Drupal::request()->headers->get('referer') ?: '/';
      $response = new \Symfony\Component\HttpFoundation\RedirectResponse($referer);
      $form_state->setResponse($response);
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('An error occurred while saving: @msg', ['@msg' => $e->getMessage()]));
      // Mesmo em caso de erro, redireciona de volta:
      $referer = \Drupal::request()->headers->get('referer') ?: '/';
      $response = new \Symfony\Component\HttpFoundation\RedirectResponse($referer);
      $form_state->setResponse($response);
    }
  }

  // ============================================================================
  // “Basic variables” helpers
  // ============================================================================

  /**
   * Monta o array “basic” ([ uri, name, version, description ]).
   *
   * @return array
   *   [
   *     'uri' => string,
   *     'name' => string,
   *     'version' => string,
   *     'description' => string,
   *   ]
   */
  protected function populateBasic() {
    $sdd = $this->getSemanticDataDictionary();
    $basic = [
      'uri' => $sdd->uri,
      'name' => $sdd->label,
      'version' => $sdd->hasVersion,
      'description' => $sdd->comment,
    ];
    \Drupal::state()->set('my_form_basic', $basic);
    return $basic;
  }

  // ============================================================================
  // “Data Dictionary” helpers (Variables)
  // ============================================================================

  /**
   * Monta o array “variables” a partir do objeto SDD.
   *
   * @param array $namespaces
   *   Mapa de prefixo → URI para namespacing (útil para tree-modal).
   * @return array
   *   [
   *     listPosition => [
   *       'column' => string,
   *       'attribute' => string,
   *       'is_attribute_of' => string,
   *       'unit' => string,
   *       'time' => string,
   *       'in_relation_to' => string,
   *       'was_derived_from' => string,
   *     ],
   *     …
   *   ]
   */
  protected function populateVariables(array $namespaces) {
    $variables = [];
    $attributes = $this->getSemanticDataDictionary()->attributes;
    if (is_array($attributes) && count($attributes) > 0) {
      foreach ($attributes as $aid => $attribute) {
        $pos = $attribute->listPosition;
        $variables[$pos] = [
          'column' => $attribute->label,
          'attribute' => Utils::namespaceUriWithNS($attribute->attribute, $namespaces),
          'is_attribute_of' => $attribute->objectUri,
          'unit' => Utils::namespaceUriWithNS($attribute->unit, $namespaces),
          'time' => Utils::namespaceUriWithNS($attribute->eventUri, $namespaces),
          'in_relation_to' => $attribute->inRelationTo,
          'was_derived_from' => $attribute->wasDerivedFrom,
        ];
      }
      // Ordena pelo listPosition (chave do array).
      ksort($variables);
    }
    \Drupal::state()->set('my_form_variables', $variables);
    return $variables;
  }

  /**
   * Renderiza cada linha da tabela “Variables” como um container read-only.
   *
   * @param array $variables
   *   [
   *     listPosition => [
   *       'column' => string,
   *       'attribute' => string,
   *       'is_attribute_of' => string,
   *       'unit' => string,
   *       'time' => string,
   *       'in_relation_to' => string,
   *       'was_derived_from' => string,
   *     ],
   *     …
   *   ]
   *
   * @return array
   *   Render array com várias linhas (containers).
   */
  protected function renderVariableRows(array $variables) {
    $rows = [];

    foreach ($variables as $delta => $variable) {
      $rows[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'mb-2']],
        'column' => [
          '#type' => 'textfield',
          '#value' => $variable['column'],
          '#disabled' => TRUE,
          '#attributes' => ['class' => ['form-control-plaintext']],
          '#prefix' => '<div class="col">',
          '#suffix' => '</div>',
        ],
        'attribute' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="col">',
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
                'silent' => true,
              ], [
                'query' => [
                  'field_id'     => 'variable_attribute_' . $delta,
                  'search_value' => $variable['attribute'],
                ],
              ])->toString(),
              'data-field-id'    => 'variable_attribute_' . $delta,
              'data-search-value'=> $variable['attribute'], // opcional, mas podemos usar como fallback
              'data-elementtype' => 'attribute',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'is_attribute_of' => [
          '#type' => 'textfield',
          '#value' => $variable['is_attribute_of'],
          '#disabled' => TRUE,
          '#attributes' => ['class' => ['form-control-plaintext']],
          '#prefix' => '<div class="col">',
          '#suffix' => '</div>',
        ],
        'unit' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="col">',
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
                'silent' => true,
              ], [
                'query' => [
                  'field_id'     => 'variable_unit_' . $delta,
                  'search_value' => $variable['unit'],
                ],
              ])->toString(),
              'data-field-id'    => 'variable_unit_' . $delta,
              'data-search-value'=> $variable['unit'], // opcional, mas podemos usar como fallback
              'data-elementtype' => 'unit',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'time' => [
          '#type' => 'textfield',
          '#value' => $variable['time'],
          '#disabled' => TRUE,
          '#attributes' => ['class' => ['form-control-plaintext']],
          '#prefix' => '<div class="col">',
          '#suffix' => '</div>',
        ],
        'in_relation_to' => [
          '#type' => 'textfield',
          '#value' => $variable['in_relation_to'],
          '#disabled' => TRUE,
          '#attributes' => ['class' => ['form-control-plaintext']],
          '#prefix' => '<div class="col">',
          '#suffix' => '</div>',
        ],
        'was_derived_from' => [
          '#type' => 'textfield',
          '#value' => $variable['was_derived_from'],
          '#disabled' => TRUE,
          '#attributes' => ['class' => ['form-control-plaintext']],
          '#prefix' => '<div class="col">',
          '#suffix' => '</div>',
        ],
      ];
    }

    return $rows;
  }

  /**
   * No momento do Save, percorre todas as variáveis em cache e envia à API.
   *
   * @param string $sdd_uri
   *   URI completa do SDD.
   * @param array $variables
   *   [
   *     listPosition => [
   *       'column' => string,
   *       'attribute' => string,
   *       'is_attribute_of' => string,
   *       'unit' => string,
   *       'time' => string,
   *       'in_relation_to' => string,
   *       'was_derived_from' => string,
   *     ],
   *     …
   *   ]
   */
  protected function saveVariables($sdd_uri, array $variables) {
    if (empty($sdd_uri)) {
      \Drupal::messenger()->addError($this->t('Cannot save variables: missing SDD URI.'));
      return;
    }
    if (empty($variables) || !is_array($variables)) {
      return;
    }

    $api = \Drupal::service('rep.api_connector');
    $useremail = \Drupal::currentUser()->getEmail();

    foreach ($variables as $vid => $variable) {
      // Monta a URI única de cada variável (prefixo + posição).
      $variable_uri = str_replace(
        Constant::PREFIX_SEMANTIC_DATA_DICTIONARY,
        Constant::PREFIX_SDD_ATTRIBUTE,
        $sdd_uri
      ) . '/' . $vid;

      // Prepara o payload em JSON.
      $payload = [
        'uri' => $variable_uri,
        'typeUri' => HASCO::SDD_ATTRIBUTE,
        'hascoTypeUri' => HASCO::SDD_ATTRIBUTE,
        'partOfSchema' => $sdd_uri,
        'listPosition' => (string) $vid,
        'label' => $variable['column'] ?: ' ',
        'attribute' => $variable['attribute'] ?: ' ',
        'objectUri' => $variable['is_attribute_of'] ?: ' ',
        'unit' => $variable['unit'] ?: ' ',
        'eventUri' => $variable['time'] ?: ' ',
        'inRelationTo' => $variable['in_relation_to'] ?: ' ',
        'wasDerivedFrom' => $variable['was_derived_from'] ?: ' ',
        'comment' => 'Column ' . $variable['column'] . ' of ' . $sdd_uri,
        'hasSIRManagerEmail' => $useremail,
      ];

      try {
        $api->elementAdd('sddattribute', json_encode($payload));
      }
      catch (\Exception $e) {
        \Drupal::messenger()->addError($this->t('Error saving variable @col: @msg', [
          '@col' => $variable['column'],
          '@msg' => $e->getMessage(),
        ]));
      }
    }
  }

  // ============================================================================
  // “Data Dictionary” helpers (Objects)
  // ============================================================================

  /**
   * Monta o array “objects” a partir do SDD.
   *
   * @param array $namespaces
   *   Mapa de prefixos → URIs para nominar entidades, se necessário.
   * @return array
   *   [
   *     listPosition => [
   *       'column' => string,
   *       'entity' => string,
   *       'role' => string,
   *       'relation' => string,
   *       'in_relation_to' => string,
   *       'was_derived_from' => string,
   *     ],
   *     …
   *   ]
   */
  protected function populateObjects(array $namespaces) {
    $objects = [];
    $objs = $this->getSemanticDataDictionary()->objects;
    if (is_array($objs) && count($objs) > 0) {
      foreach ($objs as $oid => $obj) {
        $pos = $obj->listPosition;
        $objects[$pos] = [
          'column' => $obj->label,
          'entity' => Utils::namespaceUriWithNS($obj->entity, $namespaces),
          'role' => Utils::namespaceUriWithNS($obj->role, $namespaces),
          'relation' => Utils::namespaceUriWithNS($obj->relation, $namespaces),
          'in_relation_to' => $obj->inRelationTo,
          'was_derived_from' => $obj->wasDerivedFrom,
        ];
      }
      ksort($objects);
    }
    \Drupal::state()->set('my_form_objects', $objects);
    return $objects;
  }

  /**
   * Renderiza cada linha da tabela “Objects” como container readonly.
   *
   * @param array $objects
   *   [
   *     listPosition => [
   *       'column' => string,
   *       'entity' => string,
   *       'role' => string,
   *       'relation' => string,
   *       'in_relation_to' => string,
   *       'was_derived_from' => string,
   *     ],
   *     …
   *   ]
   *
   * @return array
   *   Render array com várias linhas de objetos.
   */
  protected function renderObjectRows(array $objects) {
    $rows = [];

    foreach ($objects as $delta => $object) {
      $rows[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'mb-2']],
        'column' => [
          '#type' => 'textfield',
          '#value' => $object['column'],
          '#disabled' => TRUE,
          '#attributes' => ['class' => ['form-control-plaintext']],
          '#prefix' => '<div class="col">',
          '#suffix' => '</div>',
        ],
        'entity' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="col">',
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
                'silent' => true,
              ], [
                'query' => [
                  'field_id'     => 'object_entity_' . $delta,
                  'search_value' => $object['entity'],
                ],
              ])->toString(),
              'data-field-id'    => 'object_entity_' . $delta,
              'data-search-value'=> $object['entity'], // opcional, mas podemos usar como fallback
              'data-elementtype' => 'entity',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'role' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="col">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'object_role_' . $delta,
            '#value' => $object['role'],
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'role',
                'silent' => true,
              ], [
                'query' => [
                  'field_id'     => 'object_role_' . $delta,
                  'search_value' => $object['role'],
                ],
              ])->toString(),
              'data-field-id'    => 'object_role_' . $delta,
              'data-search-value'=> $object['role'], // opcional, mas podemos usar como fallback
              'data-elementtype' => 'role',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'relation' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="col">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'object_relation_' . $delta,
            '#value' => $object['relation'],
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'relation',
                'silent' => true,
              ], [
                'query' => [
                  'field_id'     => 'object_relation_' . $delta,
                  'search_value' => $object['relation'],
                ],
              ])->toString(),
              'data-field-id'    => 'object_relation_' . $delta,
              'data-search-value'=> $object['relation'], // opcional, mas podemos usar como fallback
              'data-elementtype' => 'relation',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'in_relation_to' => [
          '#type' => 'textfield',
          '#value' => $object['in_relation_to'],
          '#disabled' => TRUE,
          '#attributes' => ['class' => ['form-control-plaintext']],
          '#prefix' => '<div class="col">',
          '#suffix' => '</div>',
        ],
        'was_derived_from' => [
          '#type' => 'textfield',
          '#value' => $object['was_derived_from'],
          '#disabled' => TRUE,
          '#attributes' => ['class' => ['form-control-plaintext']],
          '#prefix' => '<div class="col">',
          '#suffix' => '</div>',
        ],
      ];
    }

    return $rows;
  }

  /**
   * No momento do Save, recria cada “object” via RPC.
   *
   * @param string $sdd_uri
   *   URI completa do SDD.
   * @param array $objects
   *   [
   *     listPosition => [
   *       'column' => string,
   *       'entity' => string,
   *       'role' => string,
   *       'relation' => string,
   *       'in_relation_to' => string,
   *       'was_derived_from' => string,
   *     ],
   *     …
   *   ]
   */
  protected function saveObjects($sdd_uri, array $objects) {
    if (empty($sdd_uri)) {
      \Drupal::messenger()->addError($this->t('Cannot save objects: missing SDD URI.'));
      return;
    }
    if (empty($objects) || !is_array($objects)) {
      return;
    }

    $api = \Drupal::service('rep.api_connector');
    $useremail = \Drupal::currentUser()->getEmail();

    foreach ($objects as $oid => $object) {
      // Monta URI de cada objeto.
      $object_uri = str_replace(
        Constant::PREFIX_SEMANTIC_DATA_DICTIONARY,
        Constant::PREFIX_SDD_OBJECT,
        $sdd_uri
      ) . '/' . $oid;

      // Prepara payload JSON.
      $payload = [
        'uri' => $object_uri,
        'typeUri' => HASCO::SDD_OBJECT,
        'hascoTypeUri' => HASCO::SDD_OBJECT,
        'partOfSchema' => $sdd_uri,
        'listPosition' => (string) $oid,
        'label' => $object['column'] ?: ' ',
        'entity' => $object['entity'] ?: ' ',
        'role' => $object['role'] ?: ' ',
        'relation' => $object['relation'] ?: ' ',
        'inRelationTo' => $object['in_relation_to'] ?: ' ',
        'wasDerivedFrom' => $object['was_derived_from'] ?: ' ',
        'comment' => 'Column ' . $object['column'] . ' of ' . $sdd_uri,
        'hasSIRManagerEmail' => $useremail,
      ];

      try {
        $api->elementAdd('sddobject', json_encode($payload));
      }
      catch (\Exception $e) {
        \Drupal::messenger()->addError($this->t('Error saving object @col: @msg', [
          '@col' => $object['column'],
          '@msg' => $e->getMessage(),
        ]));
      }
    }
  }

  // ============================================================================
  // “Codebook” helpers (Possible Values / Codes)
  // ============================================================================

  /**
   * Monta o array “codes” (possibleValues) do SDD.
   *
   * @param array $namespaces
   *   Mapa de prefixo → URI para namespacing.
   * @return array
   *   [
   *     listPosition => [
   *       'column' => string,
   *       'code' => string,
   *       'label' => string,
   *       'class' => string,
   *     ],
   *     …
   *   ]
   */
  protected function populateCodes(array $namespaces) {
    $codes = [];
    $possible_values = $this->getSemanticDataDictionary()->possibleValues;
    if (is_array($possible_values) && count($possible_values) > 0) {
      foreach ($possible_values as $cid => $pv) {
        $pos = $pv->listPosition;
        $codes[$pos] = [
          'column' => $pv->isPossibleValueOf,
          'code' => $pv->hasCode,
          'label' => $pv->hasCodeLabel,
          'class' => Utils::namespaceUriWithNS($pv->hasClass, $namespaces),
        ];
      }
      ksort($codes);
    }
    \Drupal::state()->set('my_form_codes', $codes);
    return $codes;
  }

  /**
   * Renderiza cada linha da tabela “Codes” como container readonly.
   *
   * @param array $codes
   *   [
   *     listPosition => [
   *       'column' => string,
   *       'code' => string,
   *       'label' => string,
   *       'class' => string,
   *     ],
   *     …
   *   ]
   *
   * @return array
   *   Render array com várias linhas de códigos.
   */
  protected function renderCodeRows(array $codes) {
    $rows = [];

    foreach ($codes as $delta => $code) {
      $rows[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'mb-2']],
        'column' => [
          '#type' => 'textfield',
          '#default_value' => $code['column'],
          '#disabled' => TRUE,
          '#attributes' => ['class' => ['form-control-plaintext']],
          '#prefix' => '<div class="col">',
          '#suffix' => '</div>',
        ],
        'code' => [
          '#type' => 'textfield',
          '#default_value' => $code['code'],
          '#disabled' => TRUE,
          '#attributes' => ['class' => ['form-control-plaintext']],
          '#prefix' => '<div class="col">',
          '#suffix' => '</div>',
        ],
        'label' => [
          '#type' => 'textfield',
          '#default_value' => $code['label'],
          '#disabled' => TRUE,
          '#attributes' => ['class' => ['form-control-plaintext']],
          '#prefix' => '<div class="col">',
          '#suffix' => '</div>',
        ],
        'class' => [
          '#type' => 'textfield',
          '#default_value' => $code['class'],
          '#disabled' => TRUE,
          '#attributes' => ['class' => ['form-control-plaintext']],
          '#prefix' => '<div class="col">',
          '#suffix' => '</div>',
        ],
      ];
    }

    return $rows;
  }

  /**
   * No momento do Save, recria cada “code” via REP API.
   *
   * @param string $sdd_uri
   *   URI completa do SDD.
   * @param array $codes
   *   [
   *     listPosition => [
   *       'column' => string,
   *       'code' => string,
   *       'label' => string,
   *       'class' => string,
   *     ],
   *     …
   *   ]
   */
  protected function saveCodes($sdd_uri, array $codes) {
    if (empty($sdd_uri)) {
      \Drupal::messenger()->addError($this->t('Cannot save codes: missing SDD URI.'));
      return;
    }
    if (empty($codes) || !is_array($codes)) {
      return;
    }

    $api = \Drupal::service('rep.api_connector');
    $useremail = \Drupal::currentUser()->getEmail();

    foreach ($codes as $cid => $code) {
      // Monta URI do possível valor:
      $code_uri = str_replace(
        Constant::PREFIX_SEMANTIC_DATA_DICTIONARY,
        Constant::PREFIX_POSSIBLE_VALUE,
        $sdd_uri
      ) . '/' . $cid;

      // Prepara payload JSON:
      $payload = [
        'uri' => $code_uri,
        'superUri' => HASCO::POSSIBLE_VALUE,
        'hascoTypeUri' => HASCO::POSSIBLE_VALUE,
        'partOfSchema' => $sdd_uri,
        'listPosition' => (string) $cid,
        'isPossibleValueOf' => $code['column'] ?: ' ',
        'label' => $code['column'] ?: ' ',
        'hasCode' => $code['code'] ?: ' ',
        'hasCodeLabel' => $code['label'] ?: ' ',
        'hasClass' => $code['class'] ?: ' ',
        'comment' => 'Possible value ' . $code['column'] . ' of ' . $sdd_uri,
        'hasSIRManagerEmail' => $useremail,
      ];

      try {
        $api->elementAdd('possiblevalue', json_encode($payload));
      }
      catch (\Exception $e) {
        \Drupal::messenger()->addError($this->t('Error saving code for column @col: @msg', [
          '@col' => $code['column'],
          '@msg' => $e->getMessage(),
        ]));
      }
    }
  }

}
