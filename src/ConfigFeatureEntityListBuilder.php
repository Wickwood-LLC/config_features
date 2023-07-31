<?php

namespace Drupal\config_features;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Configuration Split setting entities.
 */
class ConfigFeatureEntityListBuilder extends ConfigEntityListBuilder {

  /**
   * The config factory that knows what is overwritten.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('config.factory')
    );
  }

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, ConfigFactoryInterface $config_factory) {
    parent::__construct($entity_type, $storage);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Configuration Split setting');
    $header['id'] = $this->t('Machine name');
    $header['description'] = $this->t('Description');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\config_features\Entity\ConfigFeatureEntityInterface $entity */
    $row['label'] = $entity->toLink();
    $row['id'] = $entity->id();
    $config = $this->configFactory->get('config_features.config_feature.' . $entity->id());
    $row['description'] = $config->get('description');
    $row['status'] = $entity->status() ? 'active' : 'inactive';

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    $operations = parent::getDefaultOperations($entity);

    // Operations changing the entity.
    if (!$entity->get('status') && $entity->hasLinkTemplate('enable')) {
      $operations['enable'] = [
        'title' => $this->t('Enable'),
        'weight' => 40,
        'url' => $entity->toUrl('enable'),
      ];
    }
    elseif ($entity->hasLinkTemplate('disable')) {
      $operations['disable'] = [
        'title' => $this->t('Disable'),
        'weight' => 50,
        'url' => $entity->toUrl('disable'),
      ];
    }

    // Operations changing the site config.
    $config = $this->configFactory->get('config_features.config_feature.' . $entity->id());
    if ($config->get('status')) {
      if ($entity->hasLinkTemplate('import')) {
        $operations['import'] = [
          'title' => $this->t('Import'),
          'weight' => 40,
          'url' => $entity->toUrl('import'),
        ];
      }
      if ($entity->hasLinkTemplate('export')) {
        $operations['export'] = [
          'title' => $this->t('Export'),
          'weight' => 40,
          'url' => $entity->toUrl('export'),
        ];
      }
      if ($entity->hasLinkTemplate('deactivate')) {
        $operations['deactivate'] = [
          'title' => $this->t('Deactivate'),
          'weight' => 40,
          'url' => $entity->toUrl('deactivate'),
        ];
      }

    }
    else {
      if ($entity->get('storage') === 'collection') {
        if ($entity->hasLinkTemplate('activate')) {
          $operations['activate'] = [
            'title' => $this->t('Activate'),
            'weight' => 40,
            'url' => $entity->toUrl('activate'),
          ];
        }
        if ($entity->hasLinkTemplate('import')) {
          $operations['import'] = [
            'title' => $this->t('Import'),
            'weight' => 40,
            'url' => $entity->toUrl('import'),
          ];
        }
      }
      else {
        if ($entity->hasLinkTemplate('import')) {
          $operations['import'] = [
            'title' => $this->t('Activate/Import'),
            'weight' => 40,
            'url' => $entity->toUrl('import'),
          ];
        }
      }
    }

    return $operations;
  }

}
