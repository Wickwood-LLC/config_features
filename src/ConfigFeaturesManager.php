<?php

namespace Drupal\config_features;

use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\config_features\Entity\ConfigFeatureEntity;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\DatabaseStorage;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\StorageCopyTrait;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\Database\Connection;
use Drupal\config_features\Config\EphemeralConfigFactory;

/**
 * The manager to feature and merge.
 *
 * @internal This is not an API, it is code for config features internal code, it
 *   may change without notice. You have been warned!
 */
final class ConfigFeaturesManager {

  use StorageCopyTrait;

  /**
   * The config factory to load config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $factory;

  /**
   * The database connection to set up database storages.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $connection;

  /**
   * The active config store to do single import.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  private $active;

  /**
   * The sync storage for checking conditional feature.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  private $sync;

  /**
   * The export storage to do single export.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  private $export;

  /**
   * The config manager to calculate dependencies.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  private $manager;


  /**
   * ConfigFeaturesManager constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $factory
   *   The config factory.
   * @param \Drupal\Core\Config\ConfigManagerInterface $manager
   *   The config manager.
   * @param \Drupal\Core\Config\StorageInterface $active
   *   The active config store.
   * @param \Drupal\Core\Config\StorageInterface $sync
   *   The sync config store.
   * @param \Drupal\Core\Config\StorageInterface $export
   *   The export config store.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(
    ConfigFactoryInterface $factory,
    ConfigManagerInterface $manager,
    StorageInterface $active,
    StorageInterface $sync,
    StorageInterface $export,
    Connection $connection
  ) {
    $this->factory = $factory;
    $this->sync = $sync;
    $this->active = $active;
    $this->export = $export;
    $this->connection = $connection;
    $this->manager = $manager;
  }

  /**
   * Get a feature from a name.
   *
   * @param string $name
   *   The name of the feature.
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The storage to get a feature from if not the active one.
   *
   * @return \Drupal\Core\Config\ImmutableConfig|null
   *   The feature config.
   */
  public function getFeatureConfig(string $name, StorageInterface $storage = NULL): ?ImmutableConfig {
    if (strpos($name, 'config_features.config_feature.') !== 0) {
      $name = 'config_features.config_feature.' . $name;
    }
    // Get the feature from the storage passed as an argument.
    if ($storage instanceof StorageInterface && $this->factory instanceof ConfigFactory) {
      $factory = EphemeralConfigFactory::fromService($this->factory, $storage);
      if (in_array($name, $factory->listAll('config_features.config_feature.'), TRUE)) {
        return $factory->get($name);
      }
    }
    // Use the config factory service as a fallback.
    if (in_array($name, $this->factory->listAll('config_features.config_feature.'), TRUE)) {
      return $this->factory->get($name);
    }

    return NULL;
  }

  /**
   * Get a feature entity.
   *
   * @param string $name
   *   The feature name.
   *
   * @return \Drupal\config_features\Entity\ConfigFeatureEntity|null
   *   The config entity.
   */
  public function getFeatureEntity(string $name): ?ConfigFeatureEntity {
    $config = $this->getFeatureConfig($name);
    if ($config === NULL) {
      return NULL;
    }
    $entity = $this->manager->loadConfigEntityByName($config->getName());
    if ($entity instanceof ConfigFeatureEntity) {
      return $entity;
    }
    // Do we throw an exception? Do we return null?
    // @todo find out in what legitimate case this could possibly happen.
    throw new \RuntimeException('A feature config does not load a feature entity? something is very wrong.');
  }

  /**
   * Get all features from the active storage plus the given storage.
   *
   * @param \Drupal\Core\Config\StorageInterface|null $storage
   *   The storage to consider when listing features.
   *
   * @return string[]
   *   The feature names from the active storage and the given stoage.
   */
  public function listAll(StorageInterface $storage = NULL): array {
    $names = [];
    if ($storage instanceof StorageInterface && $this->factory instanceof ConfigFactory) {
      $factory = EphemeralConfigFactory::fromService($this->factory, $storage);
      $names = $factory->listAll('config_features.config_feature.');
    }

    return array_unique(array_merge($names, $this->factory->listAll('config_features.config_feature.')));
  }

  /**
   * Load multiple features and prefer loading it from the given storage.
   *
   * @param array $names
   *   The names to load.
   * @param \Drupal\Core\Config\StorageInterface|null $storage
   *   The storage to check.
   *
   * @return \Drupal\Core\Config\ImmutableConfig[]
   *   Loaded features (with config overrides).
   */
  public function loadMultiple(array $names, StorageInterface $storage = NULL): array {
    $configs = [];
    if ($storage instanceof StorageInterface && $this->factory instanceof ConfigFactory) {
      $factory = EphemeralConfigFactory::fromService($this->factory, $storage);
      $configs = $factory->loadMultiple($names);
    }

    return $configs + $this->factory->loadMultiple($names);
  }

  /**
   * Process the export of a feature.
   *
   * @param string $name
   *   The name of the feature.
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The transformation event.
   */
  public function exportTransform(string $name, StorageTransformEvent $event): void {
    $feature = $this->getFeatureConfig($name);
    if ($feature === NULL) {
      return;
    }
    if (!$feature->get('status')) {
      return;
    }
    $storage = $event->getStorage();
    $preview = $this->getPreviewStorage($feature, $storage);
    if ($preview !== NULL) {
      // Without a storage there is no featureting.
      $this->featurePreview($feature, $storage, $preview);
    }
  }

  /**
   * Process the import of a feature.
   *
   * @param string $name
   *   The name of the feature.
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The transformation event.
   */
  public function importTransform(string $name, StorageTransformEvent $event): void {
    $feature = $this->getFeatureConfig($name, $event->getStorage());
    if ($feature === NULL) {
      return;
    }
    if (!$feature->get('status')) {
      return;
    }
    $storage = $event->getStorage();
    $secondary = $this->getSplitStorage($feature, $storage);
    if ($secondary !== NULL) {
      $this->mergeFeature($feature, $storage, $secondary);
    }
  }

  /**
   * Make the feature permanent by copying the preview to the feature storage.
   */
  public function commitAll(): void {
    $features = $this->factory->loadMultiple($this->factory->listAll('config_feature'));

    $features = array_filter($features, function (ImmutableConfig $config) {
      return $config->get('status');
    });

    // Copy the preview to the permanent place.
    foreach ($features as $feature) {
      $preview = $this->getPreviewStorage($feature);
      $permanent = $this->getSplitStorage($feature);
      if ($preview !== NULL && $permanent !== NULL) {
        self::replaceStorageContents($preview, $permanent);
      }
    }
  }

  /**
   * Split the config of a feature to the preview storage.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The feature config.
   * @param \Drupal\Core\Config\StorageInterface $transforming
   *   The transforming storage.
   * @param \Drupal\Core\Config\StorageInterface $featureStorage
   *   The features preview storage.
   */
  public function featurePreview(ImmutableConfig $config, StorageInterface $transforming, StorageInterface $featureStorage): void {
    // Empty the feature storage.
    foreach (array_merge([StorageInterface::DEFAULT_COLLECTION], $featureStorage->getAllCollectionNames()) as $collection) {
      $featureStorage->createCollection($collection)->deleteAll();
    }
    $transforming = $transforming->createCollection(StorageInterface::DEFAULT_COLLECTION);
    $featureStorage = $featureStorage->createCollection(StorageInterface::DEFAULT_COLLECTION);
    $source = $this->active->createCollection(StorageInterface::DEFAULT_COLLECTION);

    $configsExcluded = $config->get('configs_excluded');

    // $modules = array_keys($config->get('module'));
    // $changes = $this->manager->getConfigEntitiesToChangeOnDependencyRemoval('module', $modules, FALSE);
    $changes = ['update' => [], 'delete' => [], 'unchanged' => []];

    $this->processEntitiesToChangeOnDependencyRemoval($changes, $source, $transforming, $featureStorage, $configsExcluded);

    $completelySplit = array_map(function (ConfigEntityInterface $entity) {
      return $entity->getConfigDependencyName();
    }, $changes['delete']);


    // Get explicitly featured config.
    $completeSplitList = $config->get('configs_shared');

    if (!empty($completeSplitList)) {
      // For the complete feature we use the active storage config. This way two
      // features can feature the same config and both will have them. But also
      // because we use the config manager service to get entities to change
      // based on the modules which are configured to be feature.
      $completeList = array_filter($source->listAll(), function ($name) use ($completeSplitList) {
        // Check for wildcards.
        return self::inFilterList($name, $completeSplitList);
      });
      // Check what is not processed already.
      $completeList = array_diff($completeList, $completelySplit);

      // Process also the config being removed.
      $changes = $this->manager->getConfigEntitiesToChangeOnDependencyRemoval('config', $completeList, FALSE);
      $this->processEntitiesToChangeOnDependencyRemoval($changes, $source, $transforming, $featureStorage, $configsExcluded);

      // Split all the config which was specified but not processed yet.
      $processed = array_map(function (ConfigEntityInterface $entity) {
        return $entity->getConfigDependencyName();
      }, $changes['delete']);
      $unprocessed = array_diff($completeList, $processed);
      foreach ($unprocessed as $name) {
        if (in_array($name, $configsExcluded)) {
          continue;
        }
        self::moveConfigToSplit($name, $source, $featureStorage, $transforming);
        $completelySplit[] = $name;
      }
    }

    // Split from collections what was feature from the default collection.
    if (!empty($completelySplit) || !empty($completeSplitList)) {
      foreach ($source->getAllCollectionNames() as $collection) {
        $storageCollection = $transforming->createCollection($collection);
        $featureCollection = $featureStorage->createCollection($collection);
        $sourceCollection = $source->createCollection($collection);

        $removeList = array_filter($sourceCollection->listAll(), function ($name) use ($completeSplitList, $completelySplit) {
          // Check for wildcards.
          return in_array($name, $completelySplit) || self::inFilterList($name, $completeSplitList);
        });
        foreach ($removeList as $name) {
          if (in_array($name, $configsExcluded)) {
          continue;
        }
          // Split collections.
          self::moveConfigToSplit($name, $sourceCollection, $featureCollection, $storageCollection);
        }
      }
    }

  }

  /**
   * Merge the config of a feature to the transformation storage.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The feature config.
   * @param \Drupal\Core\Config\StorageInterface $transforming
   *   The transforming storage.
   * @param \Drupal\Core\Config\StorageInterface $featureStorage
   *   The feature storage.
   */
  public function mergeFeature(ImmutableConfig $config, StorageInterface $transforming, StorageInterface $featureStorage): void {
    $transforming = $transforming->createCollection(StorageInterface::DEFAULT_COLLECTION);
    $featureStorage = $featureStorage->createCollection(StorageInterface::DEFAULT_COLLECTION);

    // Merge all the configuration from all collections.
    foreach (array_merge([StorageInterface::DEFAULT_COLLECTION], $featureStorage->getAllCollectionNames()) as $collection) {
      $feature = $featureStorage->createCollection($collection);
      $storage = $transforming->createCollection($collection);
      foreach ($feature->listAll() as $name) {
        $data = $feature->read($name);
        if ($data !== FALSE) {
          $dataExisting = $storage->read($name);
          if ($dataExisting && isset($data['uuid']) && isset($dataExisting['uuid']) && $data['uuid'] != $dataExisting['uuid']) {
            $data['uuid'] = $dataExisting['uuid'];
          }
          else {
            $activeData = $this->active->read($name);
            if ($activeData && isset($data['uuid']) && isset($activeData['uuid']) && $data['uuid'] != $activeData['uuid']) {
              $data['uuid'] = $activeData['uuid'];
            }
          }
          $storage->write($name, $data);
        }
      }
    }


    $updated = $transforming->read($config->getName());
    if ($updated === FALSE) {
      return;
    }

  }

  /**
   * Get the feature storage.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The feature config.
   * @param \Drupal\Core\Config\StorageInterface|null $transforming
   *   The transforming storage.
   *
   * @return \Drupal\Core\Config\StorageInterface|null
   *   The feature storage.
   */
  protected function getSplitStorage(ImmutableConfig $config, StorageInterface $transforming = NULL): ?StorageInterface {
    // Here we could determine to use relative paths etc.
    $directory = \Drupal::getContainer()->getParameter('site.path') . '/' . $config->get('folder');
    if (!is_dir($directory)) {
      // If the directory doesn't exist, attempt to create it.
      // This might have some negative consequences, but we trust the user to
      // have properly configured their site.
      /* @noinspection MkdirRaceConditionInspection */
      @mkdir($directory, 0777, TRUE);
    }
    // The following is roughly: file_save_htaccess($directory, TRUE, TRUE);
    // But we can't use global drupal functions, and we want to write the
    // .htaccess file to ensure the configuration is protected and the
    // directory not empty.
    if (file_exists($directory) && is_writable($directory)) {
      $htaccess_path = rtrim($directory, '/\\') . '/.htaccess';
      if (!file_exists($htaccess_path)) {
        file_put_contents($htaccess_path, FileSecurity::htaccessLines(TRUE));
        @chmod($htaccess_path, 0444);
      }
    }

    if (file_exists($directory) || strpos($directory, 'vfs://') === 0) {
      // Allow virtual file systems even if file_exists is false.
      return new FileStorage($directory);
    }

    return NULL;
  }

  /**
   * Get the preview storage.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The feature config.
   * @param \Drupal\Core\Config\StorageInterface|null $transforming
   *   The transforming storage.
   *
   * @return \Drupal\Core\Config\StorageInterface|null
   *   The preview storage.
   */
  public function getPreviewStorage(ImmutableConfig $config, StorageInterface $transforming = NULL): ?StorageInterface {
    $name = substr($config->getName(), strlen('config_features.config_feature.'));
    $name = 'config_feature_preview_' . strtr($name, ['.' => '_']);
    // Use the database for everything.
    return new DatabaseStorage($this->connection, $this->connection->escapeTable($name));
  }

  /**
   * Get the single export preview.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $feature
   *   The feature config.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The single export preview.
   */
  public function singleExportPreview(ImmutableConfig $feature): StorageInterface {

    // Force the transformation.
    $this->export->listAll();
    $preview = $this->getPreviewStorage($feature, $this->export);
    if (!$feature->get('status') && $preview !== NULL) {
      // @todo decide if featureting an inactive feature is wise.
      $transforming = new MemoryStorage();
      self::replaceStorageContents($this->export, $transforming);
      $this->featurePreview($feature, $transforming, $preview);
    }

    if ($preview === NULL) {
      throw new \RuntimeException();
    }

    // Change conflicting UUIDs on preview storage to match with configs on feature directory.
    // So, it won't show UUID differences.
    $featureStorage = $this->getSplitStorage($feature, $this->sync);
    foreach ($featureStorage->listAll() as $name) {
      if ($preview->exists($name)) {
        $featureData = $featureStorage->read($name);
        $previewData = $preview->read($name);
        if (isset($featureData['uuid']) && isset($previewData['uuid']) && $featureData['uuid'] != $previewData['uuid']) {
          $previewData['uuid'] = $featureData['uuid'];
          $preview->write($name, $previewData);
        }
      }
    }
    return $preview;
  }

  /**
   * Get the single export target.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $feature
   *   The feature config.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The single export target.
   */
  public function singleExportTarget(ImmutableConfig $feature): StorageInterface {
    $permanent = $this->getSplitStorage($feature, $this->sync);
    if ($permanent === NULL) {
      throw new \RuntimeException();
    }
    return $permanent;
  }

  /**
   * Import the config of a single feature.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $feature
   *   The feature config.
   * @param bool $activate
   *   Whether to activate the feature as well.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The storage to pass to a ConfigImporter to do the actual importing.
   */
  public function singleImport(ImmutableConfig $feature, bool $activate): StorageInterface {
    $storage = $this->getSplitStorage($feature, $this->sync);
    return $this->singleImportOrActivate($feature, $storage, $activate);
  }

  /**
   * Import the config of a single feature.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $feature
   *   The feature config.
   * @param bool $activate
   *   Whether to activate the feature as well.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The storage to pass to a ConfigImporter to do the actual importing.
   */
  public function singleActivate(ImmutableConfig $feature, bool $activate): StorageInterface {
    $storage = $this->getSplitStorage($feature, $this->active);
    return $this->singleImportOrActivate($feature, $storage, $activate);
  }

  /**
   * Deactivate a feature.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $feature
   *   The feature config.
   * @param bool $exportSplit
   *   Whether to export the feature config first.
   * @param bool $override
   *   Allows the deactivation via override.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The storage to pass to a ConfigImporter to do the config changes.
   */
  public function singleDeactivate(ImmutableConfig $feature, bool $exportSplit = FALSE, $override = FALSE): StorageInterface {
    if (!$feature->get('status') && !$override) {
      throw new \InvalidArgumentException('Split is already not active.');
    }

    $transformation = new MemoryStorage();
    static::replaceStorageContents($this->active, $transformation);

    $preview = $this->getPreviewStorage($feature, $transformation);
    if ($preview === NULL) {
      throw new \RuntimeException();
    }
    $this->featurePreview($feature, $transformation, $preview);

    if ($exportSplit) {
      $permanent = $this->getSplitStorage($feature, $this->sync);
      if ($permanent === NULL) {
        throw new \RuntimeException();
      }
      static::replaceStorageContents($preview, $permanent);
    }

    // Deactivate the feature in the transformation so that the importer does it.
    $config = $transformation->read($feature->getName());
    if ($config !== FALSE && !$override) {
      $config['status'] = FALSE;
      $transformation->write($feature->getName(), $config);
    }

    return $transformation;
  }

  /**
   * Importing and activating are almost the same.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $feature
   *   The feature.
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The storage.
   * @param bool $activate
   *   Whether to activate the feature in the transformation.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The storage to pass to a ConfigImporter to do the config changes.
   */
  protected function singleImportOrActivate(ImmutableConfig $feature, StorageInterface $storage, bool $activate): StorageInterface {
    $transformation = new MemoryStorage();
    static::replaceStorageContents($this->active, $transformation);

    $this->mergeFeature($feature, $transformation, $storage);

    // Activate the feature in the transformation so that the importer does it.
    $config = $transformation->read($feature->getName());
    if ($activate && $config !== FALSE) {
      $config['status'] = TRUE;
      $transformation->write($feature->getName(), $config);
    }

    return $transformation;
  }

  /**
   * Process changes the config manager calculated into the storages.
   *
   * @param array $changes
   *   The changes from getConfigEntitiesToChangeOnDependencyRemoval().
   * @param \Drupal\Core\Config\StorageInterface $source
   *   The storage to take the config from.
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The primary config transformation storage.
   * @param \Drupal\Core\Config\StorageInterface $feature
   *   The feature storage.
   */
  protected function processEntitiesToChangeOnDependencyRemoval(array $changes, StorageInterface $source, StorageInterface $storage, StorageInterface $feature, $configsExcluded = []) {
    // Process entities that need to be updated.
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    foreach ($changes['update'] as $entity) {
      $name = $entity->getConfigDependencyName();
      if ($feature->exists($name)) {
        // The config is already completely feature.
        continue;
      }

      // We use the active store because we also load the entity from it.
      $original = $this->active->read($name);
      $updated = $entity->toArray();

      // if (!$patch->isEmpty() && $storage->exists($name)) {
      //   // We update the data in the transformation storage to apply the
      //   // combined patch.
      //   $data = $storage->read($name);

      //   $storage->write($name, $data);
      // }
    }

    // Process entities that need to be deleted.
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    foreach ($changes['delete'] as $entity) {
      $name = $entity->getConfigDependencyName();
      if (in_array($name, $configsExcluded)) {
        continue;
      }
      self::moveConfigToSplit($name, $source, $feature, $storage);
    }
  }

  /**
   * Move a config from the source to the feature storage.
   *
   * @param string $name
   *   The name of the config.
   * @param \Drupal\Core\Config\StorageInterface $source
   *   The source storage.
   * @param \Drupal\Core\Config\StorageInterface $feature
   *   The target storage.
   * @param \Drupal\Core\Config\StorageInterface $transforming
   *   The transforming storage from which to remove the config.
   */
  protected static function moveConfigToSplit(string $name, StorageInterface $source, StorageInterface $feature, StorageInterface $transforming) {
    if ($source->exists($name)) {
      // Write the data to the feature.
      $feature->write($name, $source->read($name));
    }
    $transforming->delete($name);
  }

  /**
   * Prepare a storage to compare partial config with.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The config which we are comparing.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The storage to use for comparing the partial feature config.
   */
  protected function prepareSyncForPartialComparison(ImmutableConfig $config): StorageInterface {
    if (!$config->get('stackable')) {
      return $this->sync;
    }

    // Create a new storage and fill it with the sync storage contents.
    $composed = new MemoryStorage();
    self::replaceStorageContents($this->sync, $composed);
    // Load all features and sort them.
    $features = $this->loadMultiple($this->listAll());
    unset($features[$config->getName()]);
    uasort($features, function (ImmutableConfig $a, ImmutableConfig $b) {
      // Sort in reverse order, we need the import order.
      return $b->get('weight') <=> $a->get('weight');
    });

    // Merge all active features export storage except the one to compare.
    foreach ($features as $feature) {
      if (!$feature->get('status') || $feature->get('weight') <= $config->get('weight') || !$feature->get('stackable')) {
        // Exclude inactive features and features that come before on export.
        continue;
      }
      $this->mergeFeature($feature, $composed, $this->singleExportTarget($feature));
    }

    return $composed;
  }

  /**
   * Check whether the needle is in the haystack.
   *
   * @param string $name
   *   The needle which is checked.
   * @param string[] $list
   *   The haystack, a list of identifiers to determine whether $name is in it.
   *
   * @return bool
   *   True if the name is considered to be in the list.
   */
  protected static function inFilterList($name, array $list) {
    // Prepare the list for regex matching by quoting all regex symbols and
    // replacing back the original '*' with '.*' to allow it to catch all.
    $list = array_map(function ($line) {
      return str_replace('\*', '.*', preg_quote($line, '/'));
    }, $list);
    foreach ($list as $line) {
      if (preg_match('/^' . $line . '$/', $name)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
