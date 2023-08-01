<?php

namespace Drupal\config_features\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageCopyTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * The form for exporting a feature.
 */
class ConfigFeatureExportForm extends FormBase {

  use ConfigImportFormTrait;
  use StorageCopyTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_feature_deactivate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $feature = $this->getFeature();
    $comparer = new StorageComparer($this->manager->singleExportPreview($feature), $this->manager->singleExportTarget($feature));
    $options = [
      'route' => [
        'config_feature' => $feature->get('id'),
        'operation' => 'export',
      ],
      'operation label' => $this->t('Export to feature storage'),
    ];
    return $this->buildFormWithStorageComparer($form, $form_state, $comparer, $options, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $feature = $this->getFeature();
    $target = $this->manager->singleExportTarget($feature);
    self::replaceStorageContents($this->manager->singleExportPreview($feature), $target);
    $this->redirect('entity.config_feature.collection');
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    $feature = $this->getFeature();
    return AccessResult::allowedIfHasPermission($account, 'administer configuration feature')
      ->andIf(AccessResult::allowedIf($feature->get('status')))
      ->addCacheableDependency($feature);
  }

}
