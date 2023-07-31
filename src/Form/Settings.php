<?php

namespace Drupal\config_features\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a setting UI for Config Ignore UUID.
 *
 * @package Drupal\config_ignore_uuid\Form
 */
class Settings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'config_features.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_features_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $description = $this->t('One configuration name per line.<br />
Examples: <ul>
<li>meida.type.audio</li>
<li>meida.type.image</li>
<li>meida.type.* (will ignore all config entities that starts with <em>webform.webform</em>)</li>
<li>*.contact_message.custom_contact_form.* (will ignore all config entities that starts with <em>.contact_message.custom_contact_form.</em> like fields attached to a custom contact form)</li>
</ul>');

    $config_ignore_uuid_settings = $this->config('config_ignore_uuid.settings');
    $form['configs_to_ignore_uuid_change'] = [
      '#type' => 'textarea',
      '#rows' => 25,
      '#title' => $this->t('Configuration entity names to ignore'),
      '#description' => $description,
      '#default_value' => implode(PHP_EOL, $config_ignore_uuid_settings->get('configs_to_ignore_uuid_change')),
      '#size' => 60,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config_ignore_settings = $this->config('config_ignore_uuid.settings');
    $config_ignore_settings_array = preg_split("/[\r\n]+/", $values['configs_to_ignore_uuid_change']);
    $config_ignore_settings_array = array_filter($config_ignore_settings_array);
    $config_ignore_settings_array = array_values($config_ignore_settings_array);
    $config_ignore_settings->set('configs_to_ignore_uuid_change', $config_ignore_settings_array);
    $config_ignore_settings->save();
    parent::submitForm($form, $form_state);
  }

}
