<?php

namespace Drupal\config_features\Form;

use Drupal\config_features\Config\ConfigImporterTrait;
use Drupal\config_features\ConfigFeaturesManager;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigImporterException;
use Drupal\Core\Config\Importer\ConfigImporterBatch;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Trait for config import forms. Extracted from the core form.
 */
trait ConfigImportFormTrait {

  use ConfigImporterTrait;

  /**
   * The active config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $activeStorage;

  /**
   * The feature manager.
   *
   * @var \Drupal\config_features\ConfigFeaturesManager
   */
  protected $manager;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Config\StorageInterface $activeStorage
   *   The active config storage.
   * @param \Drupal\config_features\ConfigFeaturesManager $configSplitManager
   *   The feature manager.
   */
  public function __construct(
    StorageInterface $activeStorage,
    ConfigFeaturesManager $configSplitManager,
  ) {
    $this->activeStorage = $activeStorage;
    $this->manager = $configSplitManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.storage'),
      $container->get('config_features.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function buildFormWithStorageComparer(
    array $form,
    FormStateInterface $form_state,
    StorageComparer $storage_comparer,
    array $options,
    $validate = TRUE
  ) {
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $options['operation label'],
    ];

    if (!$storage_comparer->createChangelist()->hasChanges()) {
      $form['no_changes'] = [
        '#type' => 'table',
        '#header' => [$this->t('Name'), $this->t('Operations')],
        '#rows' => [],
        '#empty' => $this->t('There are no configuration changes to make.'),
      ];
      $form['actions']['#access'] = FALSE;
      $form['download'] = [
        '#title' => $this->t('Download'),
        '#type' => 'link',
        '#url' => Url::fromRoute('config_features.entity_export_download', ['config_feature' => $options['route']['config_feature']]),
      ];
      return $form;
    }
    elseif ($validate && !$storage_comparer->validateSiteUuid()) {
      $this->messenger()->addError($this->t('The staged configuration cannot be imported, because it originates from a different site than this site. You can only synchronize configuration between cloned instances of this site.'));
      $form['actions']['#access'] = FALSE;
      return $form;
    }

    // Store the comparer for use in the submit.
    $form_state->set('storage_comparer', $storage_comparer);

    // Add the AJAX library to the form for dialog support.
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    foreach ($storage_comparer->getAllCollectionNames() as $collection) {
      if ($collection != StorageInterface::DEFAULT_COLLECTION) {
        $form[$collection]['collection_heading'] = [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('@collection configuration collection', ['@collection' => $collection]),
        ];
      }
      foreach ($storage_comparer->getChangelist(NULL, $collection) as $config_change_type => $config_names) {
        if (empty($config_names)) {
          continue;
        }

        // @todo A table caption would be more appropriate, but does not have the
        //   visual importance of a heading.
        $form[$collection][$config_change_type]['heading'] = [
          '#type' => 'html_tag',
          '#tag' => 'h3',
        ];
        switch ($config_change_type) {
          case 'create':
            $form[$collection][$config_change_type]['heading']['#value'] = $this->formatPlural(count($config_names), '@count new', '@count new');
            break;

          case 'update':
            $form[$collection][$config_change_type]['heading']['#value'] = $this->formatPlural(count($config_names), '@count changed', '@count changed');
            break;

          case 'delete':
            $form[$collection][$config_change_type]['heading']['#value'] = $this->formatPlural(count($config_names), '@count removed', '@count removed');
            break;

          case 'rename':
            $form[$collection][$config_change_type]['heading']['#value'] = $this->formatPlural(count($config_names), '@count renamed', '@count renamed');
            break;
        }
        $form[$collection][$config_change_type]['list'] = [
          '#type' => 'table',
          '#header' => [$this->t('Name'), $this->t('Operations')],
        ];

        foreach ($config_names as $config_name) {
          $route_options = $options['route'];
          if ($config_change_type == 'rename') {
            $names = $storage_comparer->extractRenameNames($config_name);
            $route_options['source_name'] = $names['old_name'];
            $route_options['target_name'] = $names['new_name'];
            $config_name = $this->t('@source_name to @target_name', ['@source_name' => $names['old_name'], '@target_name' => $names['new_name']]);
          }
          else {
            $route_options['source_name'] = $config_name;
          }
          if ($collection != StorageInterface::DEFAULT_COLLECTION) {
            $route_name = 'config_features.diff_collection';
            $route_options['collection'] = $collection;
          }
          else {
            $route_name = 'config_features.diff';
          }
          $links['view_diff'] = [
            'title' => $this->t('View differences'),
            'url' => Url::fromRoute($route_name, $route_options),
            'attributes' => [
              'class' => ['use-ajax'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode([
                'width' => 700,
              ]),
            ],
          ];
          $form[$collection][$config_change_type]['list']['#rows'][] = [
            'name' => $config_name,
            'operations' => [
              'data' => [
                '#type' => 'operations',
                '#links' => $links,
              ],
            ],
          ];
        }
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function launchImport(StorageInterface $storage, string $override = NULL) {
    $comparer = new StorageComparer($storage, $this->activeStorage);
    $config_importer = $this->getConfigImporterFromComparer($comparer);
    if ($config_importer->alreadyImporting()) {
      $this->messenger()->addStatus($this->t('Another request may be synchronizing configuration already.'));
    }
    else {
      try {
        $sync_steps = $config_importer->initialize();
        $batch_builder = (new BatchBuilder())
          ->setTitle($this->t('Synchronizing configuration'))
          ->setFinishCallback([self::class, 'finishImportBatch'])
          ->setInitMessage($this->t('Starting configuration synchronization.'))
          ->setProgressMessage($this->t('Completed step @current of @total.'))
          ->setErrorMessage($this->t('Configuration synchronization has encountered an error.'));
        foreach ($sync_steps as $sync_step) {
          $batch_builder->addOperation([ConfigImporterBatch::class, 'process'], [$config_importer, $sync_step]);
        }

        if (!$override !== NULL) {
          $batch_builder->addOperation([$this, 'updateStatusOverride'], [$this->getFeature()->getName(), $override]);
        }

        batch_set($batch_builder->toArray());
      }
      catch (ConfigImporterException $e) {
        // There are validation errors.
        $this->messenger()->addError($this->t('The configuration cannot be imported because it failed validation for the following reasons:'));
        foreach ($config_importer->getErrors() as $message) {
          $this->messenger()->addError($message);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function finishImportBatch($success, $results, $operations) {
    ConfigImporterBatch::finish($success, $results, $operations);
    if ($success) {
      return new RedirectResponse(Url::fromRoute('entity.config_feature.collection', [], ['absolute' => TRUE])->toString(), 302);
    }

    return NULL;
  }

  /**
   * Update the feature override as part of the batch process.
   *
   * @param string $featureName
   *   The feature name.
   * @param string $status
   *   The override status to set as a string.
   */
  public function updateStatusOverride(string $featureName, string $status) {
    $map = [
      'none' => NULL,
      'active' => TRUE,
      'inactive' => FALSE,
    ];
    assert(array_key_exists($status, $map));
    $this->statusOverride->setSplitOverride($featureName, $map[$status]);
  }

  /**
   * Get a feature from the route.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The feature config.
   */
  protected function getFeature() {
    $feature = $this->manager->getFeatureConfig($this->getRouteMatch()->getRawParameter('config_feature'));
    if ($feature === NULL) {
      throw new \UnexpectedValueException("Unknown feature");
    }
    return $feature;
  }

}
