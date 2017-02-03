<?php
/**
 * @file Installation.php
 */

namespace clever_systems\mmm_builder;


use clever_systems\mmm_builder\Tools\DbCredentialTools;

class Installation {
  /** @var string */
  protected $name;
  /** @var ServerInterface */
  protected $server;
  /** @var string[][] */
  protected $site_uris;
  /** @var string */
  protected $docroot;
  /** @var array[] */
  protected $db_credentials = [];

  /**
   * Installation constructor.
   *
   * @param string $name
   * @param ServerInterface $server
   */
  public function __construct($name, ServerInterface $server) {
    $this->name = $name;
    $this->server = $server;
    $this->docroot = $this->server->getDefaultDocroot();
  }

  /**
   * @return $this
   */
  public function validate() {
    if (!$this->site_uris) {
      throw new \UnexpectedValueException(sprintf('Installation %s needs site uris.', $this->getName()));
    }
    if (!$this->docroot) {
      throw new \UnexpectedValueException(sprintf('Installation %s needs docroot.', $this->getName()));
    }
    return $this;
  }

  /**
   * @param string $site
   * @param string $uri
   * @return $this
   */
  public function addSite($site, $uri) {
    // @todo Validate uri.
    if (isset($this->site_uris[$site])) {
      throw new \UnexpectedValueException(sprintf('Site %s double-defined in installation %s.', $site, $this->getName()));
    }
    $this->site_uris[$site] = [$uri];
    return $this;
  }

  /**
   * @param string $site
   * @param string $uri
   * @return $this
   */
  public function addUri($site, $uri) {
    // @todo Validate uri.
    if (empty($this->site_uris[$site])) {
      throw new \UnexpectedValueException(sprintf('Additional uri %s defined for missing site %s in installation %s.', $uri, $site, $this->getName()));
    }
    if (in_array($uri, $this->site_uris[$site])) {
      throw new \UnexpectedValueException(sprintf('Additional uri %s duplicates already defined one in installation %s.', $uri, $this->getName()));
    }
    $this->site_uris[$site][] = $uri;
    return $this;
  }

  /**
   * @param string $docroot
   * @return $this
   */
  public function setDocroot($docroot) {
    $this->docroot = $this->server->normalizeDocroot($docroot);
    return $this;
  }

  /**
   * @param array|string $credentials
   * @param string $site
   * @return $this
   */
  public function setDbCredentials($credentials, $site = 'default') {
    if (is_string($credentials)) {
      $credentials = DbCredentialTools::getDbCredentialsFromDbUrl($credentials);
    }
    $this->db_credentials[$site] = $credentials;
    return $this;
  }

  /**
   * @param array|string $credential_pattern
   * @return $this
   */
  public function setDbCredentialPattern($credential_pattern) {
    if (is_string($credential_pattern)) {
      $credential_pattern = DbCredentialTools::getDbCredentialsFromDbUrl($credential_pattern);
    }
    foreach ($this->site_uris as $site => $_) {
      $this->db_credentials[$site] = DbCredentialTools::substituteInDbCredentials($credential_pattern, ['{{site}}' => $site]);
    }
    return $this;
  }

  /**
   * @return \array[]
   */
  public function getDbCredentials() {
    return $this->db_credentials;
  }

  /**
   * @return \array[]
   */
  public function getAliases() {
    $multisite = count($this->site_uris) !== 1;
    $aliases = [];
    $site_list= [];
    // Add single site aliases.
    foreach ($this->site_uris as $site => $uris) {
      // Only use primary uri.
      $uri = $uris[0];
      $alias_name = $multisite ? $this->name . '.' . $site : $this->name;
      $aliases[$alias_name] = [
        'uri' => $uri,
        'root' => $this->docroot,
        'remote-host' => $this->server->getHost(),
        'remote-user' => $this->server->getUser(),
      ];
      $site_list[] = "@$alias_name";

    }
    if ($multisite) {
      // Add site-list installation alias.
      $aliases[$this->name] = [
        'site-list' => $site_list,
      ];
    }
    return $aliases;
  }

  /**
   * @return \string[]
   */
  public function getUriToSiteMap() {
    // @todo Care for port when needed.
    $sites_by_uri = [];
    foreach ($this->site_uris as $site => $uris) {
      foreach ($uris as $uri) {
        $sites_by_uri[parse_url($uri, PHP_URL_HOST)] = $site;
      }
    }
    return $sites_by_uri;
  }

  /**
   * @return string
   */
  public function getSiteId($site = 'default') {
    $user = $this->server->getUser();
    $host = $this->server->getHostForSiteId();
    $path = $this->docroot;
    // $path is absolute and already has a leading slash.
    return "$user@$host$path#$site";
  }

  /**
   * @return \array[]
   */
  public function getBaseUrls() {
    $base_urls = [];
    foreach ($this->site_uris as $site => $uris) {
      // @fixme Allow multiple uris
      $uri = $uris[0];
      $base_urls[$this->getSiteId($site)] = $uri;
    }
    return $base_urls;
  }

  /**
   * @return string
   */
  public function getName() {
    return $this->name;
  }

}