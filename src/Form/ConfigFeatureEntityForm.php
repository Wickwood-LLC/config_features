<?php

namespace Drupal\config_features\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The entity form.
 */
class ConfigFeatureEntityForm extends EntityForm {

  /**
   * The drupal state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\config_features\Entity\ConfigFeatureEntityInterface
   */
  protected $entity;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The drupal state.
   */
  public function __construct(
    StateInterface $state
  ) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\config_features\Entity\ConfigFeatureEntityInterface $config */
    $config = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $config->label(),
      '#description' => $this->t("Label for the Configuration Feature setting."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $config->id(),
      '#machine_name' => [
        'exists' => '\Drupal\config_features\Entity\ConfigFeatureEntity::load',
      ],
    ];

    $form['static_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Static Settings'),
      '#description' => $this->t("These settings can be overridden in settings.php"),
    ];
    $form['static_fieldset']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Describe this config features setting. The text will be displayed on the <em>Configuration Feature setting</em> list page.'),
      '#default_value' => $config->get('description'),
    ];
    // $form['static_fieldset']['storage'] = [
    //   '#type' => 'radios',
    //   '#title' => $this->t('Storage'),
    //   '#description' => $this->t('Select where you would like the features to be stored.<br /><em>Folder:</em> A specified directory on its own. Select this option if you want to decide the placement of your configuration directories.<br /><em>Collection:</em> A collection inside of the sync storage. Select this option if you want features to be part of the main config, including in zip archives.<br /><em>Database:</em> A dedicated table in the database. Select this option if the features should not be shared (it will be included in database dumps).'),
    //   '#default_value' => $config->get('storage') ?? 'folder',
    //   '#options' => [
    //     'folder' => $this->t('Folder'),
    //     'collection' => $this->t('Collection'),
    //     'database' => $this->t('Database'),
    //   ],
    // ];
    $form['static_fieldset']['folder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Folder'),
      '#description' => $this->t('The directory, relative to the Drupal root, in which to save the filtered config. This is typically a sibling directory of what you defined as <code>$settings["config_sync_directory"]</code> in settings.php, for more information consult the README.<br/>Configuration related to the "filtered" items below will be features from the main configuration and exported to this folder.'),
      '#default_value' => $config->get('folder'),
      // '#states' => [
      //   'visible' => [
      //     ':input[name="storage"]' => ['value' => 'folder'],
      //   ],
      //   'required' => [
      //     ':input[name="storage"]' => ['value' => 'folder'],
      //   ],
      // ],
    ];
    $form['static_fieldset']['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#description' => $this->t('The weight to order the features.'),
      '#default_value' => $config->get('weight'),
    ];

    $form['static_fieldset']['status_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Status'),
      '#description' => $this->t('Changing the status does not affect the other active config. You need to activate or deactivate the features for that.'),
    ];
    $overrideExample = '$config["config_features.config_feature.' . ($config->get('id') ?? 'example') . '"]["status"] = ' . ($config->get('status') ? 'FALSE' : 'TRUE') . ';';
    $form['static_fieldset']['status_fieldset']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active'),
      '#description' => $this->t('Active features get used to features and merge when importing and exporting config, this property is likely what you want to override in settings.php, for example: <code>@example</code>', ['@example' => $overrideExample]),
      '#default_value' => ($config->get('status') ? TRUE : FALSE),
    ];
    // $overrideDefault = 'none';
    // $form['static_fieldset']['status_fieldset']['status_override'] = [
    //   '#type' => 'select',
    //   '#title' => $this->t('Status override'),
    //   '#default_value' => $overrideDefault,
    //   '#options' => [
    //     'none' => $this->t('None'),
    //     'active' => $this->t('Active'),
    //     'inactive' => $this->t('Inactive'),
    //   ],
    //   '#description' => $this->t('This setting will override the status of the features with a config override saved in the database (state). The config override from settings.php will override this and take precedence.'),
    // ];

    $form['complete_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Complete Feature'),
      '#description' => $this->t("<em>Complete Feature:</em>
       Configuration listed here will be removed from the sync directory and
       saved in the features storage instead. Modules will be removed from
       core.extension when exporting (and added back when importing with the
       features enabled.). Config dependencies are updated and their changes are
       recorded in a config patch saved in in the features storage."),
    ];

    // $module_handler = $this->moduleHandler;
    // $modules = array_map(function ($module) use ($module_handler) {
    //   return $module_handler->getName($module->getName());
    // }, $module_handler->getModuleList());
    // // Add the existing ones with the machine name, so they do not get lost.
    // foreach (array_diff_key($config->get('module'), $modules) as $missing => $weight) {
    //   $modules[$missing] = $missing;
    // }

    // // Sorting module list by name for making selection easier.
    // asort($modules, SORT_NATURAL | SORT_FLAG_CASE);

    $multiselect_type = 'select';
    if (!$this->useSelectList()) {
      $multiselect_type = 'checkboxes';
      // Add the css library if we use checkboxes.
      $form['#attached']['library'][] = 'config_features/config-features-form';
    }

    // $form['complete_fieldset']['module'] = [
    //   '#type' => $multiselect_type,
    //   '#title' => $this->t('Modules'),
    //   '#description' => $this->t('Select modules to features. Configuration depending on the modules is changed as if the module would be uninstalled or automatically features off completely as well.'),
    //   '#options' => $modules,
    //   '#size' => 20,
    //   '#multiple' => TRUE,
    //   '#default_value' => array_keys($config->get('module')),
    // ];


    $options = array_combine($this->configFactory()->listAll(), $this->configFactory()->listAll());

    $form['complete_fieldset']['complete_picker'] = [
      '#type' => $multiselect_type,
      '#title' => $this->t('Configuration items'),
      '#description' => $this->t('Select configuration to features. Configuration depending on features modules does not need to be selected here specifically.'),
      '#options' => $options,
      '#size' => 20,
      '#multiple' => TRUE,
      '#default_value' => array_intersect($config->get('configs_shared'), array_keys($options)),
    ];
    $form['complete_fieldset']['complete_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional configuration'),
      '#description' => $this->t('Select additional configuration to features. One configuration key per line. You can use wildcards.'),
      '#size' => 5,
      '#default_value' => implode("\n", array_diff($config->get('configs_shared'), array_keys($options))),
    ];

    $form['exclude_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Exclude Feature'),
      '#description' => $this->t("<em>Exclude Feature:</em>
       Configuration listed here will be excluded from being part of this feature.
       It would be useful in case you want to specifically remove some configurations that are automatically become part as a dependency."),
    ];

    $form['exclude_fieldset']['exclude_picker'] = [
      '#type' => $multiselect_type,
      '#title' => $this->t('Configuration items to exclude'),
      '#description' => $this->t('Select configurations to exclude. Configuration depending on features will be automatically inlcuded. In case you want to remove any of them.'),
      '#options' => $options,
      '#size' => 20,
      '#multiple' => TRUE,
      '#default_value' => array_intersect($config->get('configs_excluded') ?? [], array_keys($options)),
    ];

    $form['exclude_fieldset']['exclude_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional configuration'),
      '#description' => $this->t('Select additional configuration to exclude. One configuration key per line. You can use wildcards.'),
      '#size' => 5,
      '#default_value' => implode("\n", array_diff($config->get('configs_excluded') ?? [], array_keys($options))),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $folder = $form_state->getValue('folder');
    if (static::isConflicting($folder)) {
      $form_state->setErrorByName('folder', $this->t('The features folder can not be in the sync folder.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $selection = $this->readValuesFromPicker($form_state->getValue('complete_picker'));
    $form_state->setValue('configs_shared', array_merge(
      array_keys($selection),
      $this->filterConfigNames($form_state->getValue('complete_text'))
    ));

    $selection = $this->readValuesFromPicker($form_state->getValue('exclude_picker'));
    $form_state->setValue('configs_excluded', array_merge(
      array_keys($selection),
      $this->filterConfigNames($form_state->getValue('exclude_text'))
    ));

    parent::submitForm($form, $form_state);
  }

  /**
   * If the chosen or select2 module is active, the form must use select field.
   *
   * @return bool
   *   True if the form must use a select field
   */
  protected function useSelectList() {
    // Allow the setting to be overwritten with the drupal state.
    $stateOverride = $this->state->get('config_features_use_select');
    if ($stateOverride !== NULL) {
      // Honestly this is probably only useful in tests or if another module
      // comes along and does what chosen or select2 do.
      return (bool) $stateOverride;
    }

    // Modules make the select widget useful.
    foreach (['chosen', 'select2_all'] as $module) {
      if ($this->moduleHandler->moduleExists($module)) {
        return TRUE;
      }
    }

    // Fall back to checkboxes.
    return FALSE;
  }

  /**
   * Read values selected depending on widget used: select or checkbox.
   *
   * @param array $pickerSelection
   *   The form value array.
   *
   * @return array
   *   Array of selected values
   */
  protected function readValuesFromPicker(array $pickerSelection) {
    if ($this->useSelectList()) {
      $moduleSelection = $pickerSelection;
    }
    else {
      // Checkboxes return a value for each item. We only keep the selected one.
      $moduleSelection = array_filter($pickerSelection, function ($value) {
        return $value;
      });
    }

    return $moduleSelection;
  }

  /**
   * Filter text input for valid configuration names (including wildcards).
   *
   * @param string|string[] $text
   *   The configuration names, one name per line.
   *
   * @return string[]
   *   The array of configuration names.
   */
  protected function filterConfigNames($text) {
    if (!is_array($text)) {
      $text = explode("\n", $text);
    }

    foreach ($text as &$config_entry) {
      $config_entry = strtolower($config_entry);
    }

    // Filter out illegal characters.
    return array_filter(preg_replace('/[^a-z0-9_\.\-\*]+/', '', $text));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $config_features = $this->entity;
    $status = $config_features->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label Configuration Feature setting.', [
          '%label' => $config_features->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label Configuration Feature setting.', [
          '%label' => $config_features->label(),
        ]));
    }
    $folder = $form_state->getValue('folder');
    if (!empty($folder) && !file_exists(\Drupal::getContainer()->getParameter('site.path') . '/' . $folder)) {
      $this->messenger()->addWarning(
        $this->t('The storage path "%path" for %label Configuration Feature setting does not exist. Make sure it exists and is writable.',
          [
            '%label' => $config_features->label(),
            '%path' => $folder,
          ]
        ));
    }
    $form_state->setRedirectUrl($config_features->toUrl('collection'));

    return $status;
  }

  /**
   * Check whether the folder name conflicts with the default sync directory.
   *
   * @param string $folder
   *   The features folder name to check.
   *
   * @return bool
   *   True if the folder is inside the sync directory.
   */
  protected static function isConflicting($folder) {
    return strpos(rtrim($folder, '/') . '/', rtrim(Settings::get('config_sync_directory'), '/') . '/') !== FALSE;
  }

}
