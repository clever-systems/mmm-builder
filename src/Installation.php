<?php
/**
 * @file Installation.php
 */

namespace clever_systems\mmm_builder;


use clever_systems\mmm_builder\RenderPhp\PhpFile;
use clever_systems\mmm_builder\Tools\DbCredentialTools;

class Installation {
  /** @var string */
  protected $name;
  /** @var ServerInterface */
  protected $server;
  /** @var Project */
  protected $project;
  /** @var string[][] */
  protected $site_uris;
  /** @var string */
  protected $docroot;
  /** @var array[] */
  protected $db_credentials = [];
  /** @var string */
  protected $use_environment_name;

  /**
   * Installation constructor.
   *
   * @param string $name
   * @param ServerInterface $server
   * @param Project $project
   */
  public function __construct($name, ServerInterface $server, Project $project) {
    $this->name = $name;
    $this->server = $server;
    $this->project = $project;

    $this->docroot = $this->server->normalizeDocroot($this->server->getDefaultDocroot());
    $this->use_environment_name = $name;
  }

  /**
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * @return string
   */
  public function getSiteId($site = 'default') {
    $user = $this->server->getUser();
    $host = $this->server->getShortHostName();
    $path = $this->docroot;
    // $path is absolute and already has a leading slash.
    return "$user@$host$path#$site";
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
   * @param string $uri
   * @param string $site
   * @return $this
   */
  public function addSite($uri, $site = 'default') {
    // @todo Validate uri.
    if (isset($this->site_uris[$site])) {
      throw new \UnexpectedValueException(sprintf('Site %s double-defined in installation %s.', $site, $this->getName()));
    }
    $this->site_uris[$site] = [$uri];
    return $this;
  }

  /**
   * @param string $uri
   * @param string $site
   * @return $this
   */
  public function addUri($uri, $site = 'default') {
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
      $replacements = ['{{installation}}' => $this->name, '{{site}}' => $site];
      $this->db_credentials[$site] = DbCredentialTools::substituteInDbCredentials($credential_pattern, $replacements);
    }
    return $this;
  }

  public function compileAliases(PhpFile $php) {
    $php->addToBody('');
    $php->addToBody("// Installation: $this->name");
    $multisite = count($this->site_uris) !== 1;
    $site_list= [];
    // Add single site aliases.
    foreach ($this->site_uris as $site => $uris) {
      // Only use primary uri.
      $uri = $uris[0];
      $alias_name = $multisite ? $this->name . '.' . $site : $this->name;
      $root = $this->docroot;
      $host = $this->server->getHost();
      $user = $this->server->getUser();
      $local_host_id = $this->server->getLocalHostId();
      $php->addToBody("\$aliases['$alias_name'] = [")
        ->addToBody("  'uri' => '$uri',")
        ->addToBody("  'root' => '$root',")
        ->addToBody("  '#mmm-local-host-id' => '$local_host_id',")
        ->addToBody('];');
      // Drush sitealias altering in root is broken, do it here.
      // https://github.com/drush-ops/drush/issues/2432
      $php->addToBody("if (!Runtime::getEnvironment()->match('$local_host_id')) {")
        ->addToBody("  \$aliases['$alias_name']['remote-user'] = '$user';")
        ->addToBody("  \$aliases['$alias_name']['remote-host'] = '$host';")
        ->addToBody('}');
      $site_list[] = "@$alias_name";
    }
    if ($multisite) {
      // Add site-list installation alias.
      $site_list_quoted = array_map(function($s) {return "'$s'";}, $site_list);
      $site_list_imploded = implode(', ', $site_list_quoted);
      $php->addToBody("\$aliases['$this->name'] = [")
        ->addToBody("'site-list' => [$site_list_imploded],")
        ->addToBody('];');
    }
    $php->addToBody("if (Runtime::getEnvironment()->match('$local_host_id$root')) {")
      ->addToBody("  \$aliases['this'] = \$aliases['$this->name'];")
      ->addToBody('}');
  }

  public function compileSitesPhp(PhpFile $php) {
    $php->addToBody('');
    $php->addToBody("// Installation: $this->name");
    foreach ($this->site_uris as $site => $uris) {
      foreach ($uris as $uri) {
        // @todo Care for port when needed.
        $host = parse_url($uri, PHP_URL_HOST);
        $php->addToBody("\$sites['$host'] = '$site';");
      }
    }
  }


  public function compileBaseUrls(PhpFile $php) {
    $settings_variable = $this->project->getSettingsVariable();
    $php->addToBody('');
    $php->addToBody("// Installation: $this->name");
    foreach ($this->site_uris as $site => $uris) {
      $site_id = $this->getSiteId($site);
      $php->addToBody("if (Runtime::getEnvironment()->match('$site_id')) {");
      $php->addToBody("  {$settings_variable}['mmm']['installation'] = '$this->name';");

      // Add drush "uri".
      $uri_map = array_combine($uris, $uris);
      $uri_map["http://$site"] = $uris[0];
      foreach ($uri_map as $uri_in => $uri) {
        $host = parse_url($uri_in, PHP_URL_HOST);
        if ($this->project->getDrupalMajorVersion() == 7) {
          $php->addToBody("  if (\$host === '$host') {");
          $php->addToBody("    \$base_url = '$uri'; return;");
          $php->addToBody('  }');
        }
        else {
          // D8 does not need base url anymore.
          $php->addToBody("  \$settings['trusted_host_patterns'][] = '$host';");
        }
      }
      $php->addToBody('  return;');
      $php->addToBody('}');
    }
  }

  public function compileDbCredentials(PhpFile $php) {
    $php->addToBody('');
    $php->addToBody("// Installation: $this->name");
    foreach ($this->db_credentials as $site => $db_credential) {
      $site_id = $this->getSiteId($site);
      $php->addToBody("if (Runtime::getEnvironment()->match('$site_id')) {")
        ->addToBody("  \$databases['default']['default'] = "
          // @todo Replace with better dumper.
          . var_export(array_filter($db_credential), TRUE) . '; return;')
        ->addToBody('}');
    }
  }

  public function alterHtaccess($content) {
    return $this->server->alterHtaccess($content);
  }

}
