<?php
/**
 * @file Project.php
 */

namespace clever_systems\mmm_builder;

/**
 * Class Project
 * @package clever_systems\mmm_builder
 *
 * @todo Add docroot relative to gitroot setting.
 */
class Project {
  /** @var int */
  protected $drupal_major_version;
  /** @var Installation[] */
  protected $installations = [];

  /**
   * Project constructor.
   * @param int $drupal_major_version
   */
  public function __construct($drupal_major_version) {
    if (!in_array($drupal_major_version, [7, 8])) {
      throw new \UnexpectedValueException(sprintf('Drupal major version not supported: %s', $drupal_major_version));
    }
    $this->drupal_major_version = $drupal_major_version;
  }


  /**
   * @param string $name
   * @param ServerInterface $server
   * @return Installation
   */
  public function addInstallation($name, ServerInterface $server) {
    if (isset($this->installations[$name])) {
      throw new \UnexpectedValueException(sprintf('Duplicate installation: %s', $name));
    }
    $installation = new Installation($name, $server, $this);
    $this->installations[$name] = $installation;
    return $installation;
  }

  /**
   * @return int
   */
  public function getDrupalMajorVersion() {
    return $this->drupal_major_version;
  }

  public function getSettingsVariable() {
    return ($this->drupal_major_version == 7) ? '$conf' : '$settings';
  }

  /**
   * @return Installation[]
   */
  public function getInstallations() {
    return $this->installations;
  }

}
