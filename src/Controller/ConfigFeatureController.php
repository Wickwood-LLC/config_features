<?php

namespace Drupal\config_features\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * The controller for feature actions.
 */
class ConfigFeatureController extends ControllerBase {

  /**
   * Enable the feature.
   *
   * @param string $config_feature
   *   The feature name.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function enableEntity($config_feature) {
    /** @var \Drupal\config_feature\Entity\ConfigFeatureEntityInterface $entity */
    $entity = $this->entityTypeManager()->getStorage('config_feature')->load($config_feature);
    $entity->set('status', TRUE);
    $entity->save();

    return $this->redirect('entity.config_feature.collection');
  }

  /**
   * Disable the feature.
   *
   * @param string $config_feature
   *   The feature name.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function disableEntity($config_feature) {
    /** @var \Drupal\config_features\Entity\ConfigFeatureEntityInterface $entity */
    $entity = $this->entityTypeManager()->getStorage('config_feature')->load($config_feature);
    $entity->set('status', FALSE);
    $entity->save();

    return $this->redirect('entity.config_feature.collection');
  }

}
