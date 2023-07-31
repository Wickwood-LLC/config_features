<?php

namespace Drupal\config_features;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * EntityViewBuilder for Config Split entities.
 */
class ConfigFeatureEntityViewBuilder extends EntityViewBuilder {

  /**
   * The features manager.
   *
   * @var \Drupal\config_features\ConfigFeaturesManager
   */
  protected $featuresManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $handler = parent::createInstance($container, $entity_type);
    $handler->featuresManager = $container->get('config_features.manager');
    return $handler;
  }

  /**
   * {@inheritdoc}
   */
  public function viewMultiple(array $entities = [], $view_mode = 'full', $langcode = NULL) {
    /** @var \Drupal\config_features\Entity\ConfigFeatureEntityInterface[] $entities */
    $build = [];

    /**
     * @var string $entity_id
     * @var \Drupal\config_features\Entity\ConfigFeatureEntity $entity
     */
    foreach ($entities as $entity_id => $entity) {
      $config = $this->featuresManager->getFeatureConfig($entity->getConfigDependencyName());

      // @todo make this prettier.
      $build[$entity_id] = [
        '#cache' => [
          'tags' => $entity->getCacheTags(),
        ],
      ];

      try {
        $storage = $this->featuresManager->singleExportPreview($config);
        $build[$entity_id]['preview'] = [
          '#type' => 'container',
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('Preview'),
          ],
          'items' => [
            '#theme' => 'item_list',
            '#items' => $this->listStorageContents($storage),
            '#list_type' => 'ul',
          ],
        ];
      }
      catch (\Exception $exception) {
        $build[$entity_id]['preview'] = [
          '#markup' => $this->t('Can not display preview of %features', ['%features' => $entity->label()]),
        ];
      }

      try {
        $storage = $this->featuresManager->singleExportTarget($config);
        $build[$entity_id]['exported'] = [
          '#type' => 'container',
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('Exported'),
          ],
          'items' => [
            '#theme' => 'item_list',
            '#items' => $this->listStorageContents($storage),
            '#list_type' => 'ul',
          ],
        ];
      }
      catch (\Exception $exception) {
        $build[$entity_id]['exported'] = [
          '#markup' => $this->t('Can not display export storage of %features', ['%features' => $entity->label()]),
        ];
      }

    }

    return $build;
  }

  /**
   * List the contents of a storage.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The storage.
   *
   * @return array
   *   the contents.
   */
  protected function listStorageContents(StorageInterface $storage): array {
    $list = $storage->createCollection(StorageInterface::DEFAULT_COLLECTION)->listAll();
    foreach ($storage->getAllCollectionNames() as $collection) {
      foreach ($storage->createCollection($collection)->listAll() as $name) {
        $list[] = $collection . ':' . $name;
      }
    }
    return $list;
  }

}
