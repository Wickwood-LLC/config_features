<?php

namespace Drupal\config_features\Controller;

use Symfony\Component\HttpFoundation\Request;
use Drupal\system\FileDownloadController;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\config_features\ConfigFeaturesManager;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Serialization\Yaml;

class ConfigDownloadController implements ContainerInjectionInterface {

  const FILE_PREFIX = 'config-feature--';

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The feature manager.
   *
   * @var \Drupal\config_features\ConfigFeaturesManager
   */
  protected $manager;
  
  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Config\StorageInterface $activeStorage
   *   The active storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager.
   * @param \Drupal\Core\Diff\DiffFormatter $diffFormatter
   *   The diff formatter.
   * @param \Drupal\config_features\ConfigFeaturesManager $configFeaturesManager
   *   The feature manager.
   */
  public function __construct(StateInterface $state, ConfigFeaturesManager $configFeaturesManager, RouteMatchInterface $route_match) {
    $this->state = $state;
    $this->manager = $configFeaturesManager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('config_features.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * Get a feature from the route.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The feature config.
   */
  protected function getFeature() {
    $feature = $this->manager->getFeatureConfig($this->routeMatch->getRawParameter('config_feature'));
    if ($feature === NULL) {
      throw new \UnexpectedValueException("Unknown feature");
    }
    return $feature;
  }

  public function download() {
    $request = \Drupal::request();
    $file_system = \Drupal::service('file_system');
    $feature = $this->getFeature();

    $date = \DateTime::createFromFormat('U', $request->server->get('REQUEST_TIME'));
    $date_string = $date->format('Y-m-d-H-i');

    $filename = self::FILE_PREFIX . $feature->get('id') . '-' . $date_string . '.tar.gz';

    $archiver = new ArchiveTar($file_system->getTempDirectory() . '/' . $filename, 'gz');

    $target = $this->manager->singleExportTarget($feature);

    $names = $target->listAll();
    foreach ($names as $name) {
      $archiver->addString($name . '.yml', Yaml::encode($target->read($name)));
    }

    $request = new Request(['file' => $filename]);

    $file_download_controller = new FileDownloadController(\Drupal::service('stream_wrapper_manager'));

    return $file_download_controller->download($request, 'temporary');
  }
}