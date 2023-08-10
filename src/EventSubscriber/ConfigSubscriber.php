<?php

namespace Drupal\config_features\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\StorageTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\config_features\ConfigFeaturesManager;
use Drupal\Core\Config\ImmutableConfig;

class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * The manager class which does the heavy lifting.
   *
   * @var \Drupal\config_features\ConfigFeaturesManager
   */
  protected $manager;

  /**
   * The config factory to load config from.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * SplitImportExportSubscriber constructor.
   *
   * @param \Drupal\config_split\ConfigSplitManager $manager
   *   The manager class which does the heavy lifting.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory to load config from.
   */
  public function __construct(ConfigFeaturesManager $manager, ConfigFactoryInterface $configFactory) {
    $this->manager = $manager;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::STORAGE_TRANSFORM_IMPORT][] = ['importDefaultPriority'];
    $events[ConfigEvents::STORAGE_TRANSFORM_EXPORT][] = ['exportDefaultPriority'];
    return $events;
  }

  /**
   * React to the import transformation.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The transformation event.
   */
  public function importDefaultPriority(StorageTransformEvent $event) {
    $splits = array_reverse($this->getDefaultPrioritySplitConfigs($event->getStorage()));
    foreach ($splits as $split) {
      $this->manager->importTransform($split->get('id'), $event);
    }
  }

  /**
   * React to the export transformation.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The transformation event.
   */
  public function exportDefaultPriority(StorageTransformEvent $event) {
    foreach ($this->getDefaultPrioritySplitConfigs() as $split) {
      $this->manager->exportTransform($split->get('id'), $event);
    }
  }

  /**
   * Get the split config that was not explicitly set with a priority.
   *
   * @return \Drupal\Core\Config\ImmutableConfig[]
   *   The default priority configs.
   */
  protected function getDefaultPrioritySplitConfigs(StorageInterface $storage = NULL): array {
    $names = $this->manager->listAll($storage);

    $splits = $this->manager->loadMultiple($names, $storage);
    uasort($splits, function (ImmutableConfig $a, ImmutableConfig $b) {
      return $a->get('weight') <=> $b->get('weight');
    });

    return $splits;
  }

  /**
   * Match a config entity name against the list of ignored config entities.
   *
   * @param string $config_name
   *   The name of the config entity to match against all ignored entities.
   *
   * @return bool
   *   True, if the config entity is to be ignored, false otherwise.
   */
  public static function matchConfigName($config_name) {
    static $configs_to_ignore_uuid_change = NULL;
    if (!$configs_to_ignore_uuid_change) {
      $configs_to_ignore_uuid_change = \Drupal::service('config.factory')->get('config_ignore_uuid.settings')->get('configs_to_ignore_uuid_change');
    }

    foreach ($configs_to_ignore_uuid_change as $config_ignore_uuid_setting) {
      // Split the ignore settings so that we can ignore individual keys.
      $ignore = explode(':', $config_ignore_uuid_setting, 2);
      if (self::wildcardMatch($ignore[0], $config_name)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Checks if a string matches a given wildcard pattern.
   *
   * @param string $pattern
   *   The wildcard pattern to me matched.
   * @param string $string
   *   The string to be checked.
   *
   * @return bool
   *   TRUE if $string string matches the $pattern pattern.
   */
  protected static function wildcardMatch($pattern, $string) {
    $pattern = '/^' . preg_quote($pattern, '/') . '$/';
    $pattern = str_replace('\*', '.*', $pattern);
    return (bool) preg_match($pattern, $string);
  }
}
