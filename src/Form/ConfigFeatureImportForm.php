<?php

namespace Drupal\config_features\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * The form for importing a feature.
 */
class ConfigFeatureImportForm extends FormBase {

  use ConfigImportFormTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_feature_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $feature = $this->getFeature();
    $comparer = new StorageComparer($this->manager->singleImport($feature, !$feature->get('status')), $this->activeStorage);
    $options = [
      'route' => [
        'config_feature' => $feature->getName(),
        'operation' => 'import',
      ],
      'operation label' => $this->t('Import all'),
    ];
    $form = $this->buildFormWithStorageComparer($form, $form_state, $comparer, $options);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $feature = $this->getFeature();
    $activate = !$feature->get('status');
    $override = NULL;
    if ($activate) {
      if ($form_state->getValue('activate_local_only')) {
        $override = 'active';
        $activate = FALSE;
      }
      else {
        $override = 'none';
      }
    }

    $storage = $this->manager->singleImport($feature, $activate);
    $this->launchImport($storage, $override);
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
    return AccessResult::allowedIfHasPermission($account, 'administer configuration features')
      ->andIf(AccessResult::allowedIf($feature->get('status') || $feature->get('storage') === 'collection'))
      ->addCacheableDependency($feature);
  }

}
