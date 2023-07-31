<?php

namespace Drupal\config_features\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * The form for de-activating a feature.
 */
class ConfigFeatureDeactivateForm extends FormBase {

  use ConfigImportFormTrait;

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

    $comparer = new StorageComparer($this->manager->singleDeactivate($feature, FALSE), $this->activeStorage);
    $options = [
      'route' => [
        'config_feature' => $feature->getName(),
        'operation' => 'deactivate',
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

    $override = FALSE;
    if ($form_state->getValue('deactivate_local_only')) {
      $override = TRUE;
    }

    $storage = $this->manager->singleDeactivate($feature, $form_state->getValue('export_before'), $override);
    $this->launchImport($storage, $override ? 'inactive' : 'none');
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
