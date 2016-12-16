<?php
/**
 * @file Compiler.php
 */

namespace clever_systems\mmm-builder;


class Compiler {
  /** @var Project */
  protected $project;

  /**
   * Compiler constructor.
   * @param Project $project
   */
  public function __construct(Project $project) {
    $this->project = $project;
  }

  public function compile() {
    file_put_contents('sites/sites.php', $this->compileSitesPhp());
    file_put_contents('sites/all/drush/aliases.drushrc.php', $this->compileAliases());
    file_put_contents('../settings.baseurl.php', $this->compileBaseurls());
    file_put_contents('../settings.databases.php', $this->compileDbcredentials());
    file_put_contents('../settings.php', $this->scaffoldSettings());
  }

  public function compileAliases() {
    $aliases = $this->project->getAliases();
    $aliases_as_php = var_export($aliases, TRUE);
    $content = <<<EOD
<?php
// MMM autogenerated
\$aliases = $aliases_as_php;

EOD;
    return $content;
  }

  public function compileSitesPhp() {
    $sites_data = $this->project->getUriToSiteMap();
    $content = <<<EOD
<?php
// MMM autogenerated

EOD;
    foreach ($sites_data as $host => $site) {
      $content .= "\$sites['$host'] = '$site';\n";
    }
    return $content;

  }

  /**
   * @return string
   */
  protected function compileBaseurls() {
    $base_urls = $this->project->getBaseUrls();
    $base_urls_as_php = var_export($base_urls, TRUE);
    $content = <<<EOD
<?php
// MM autogenerated
use clever_systems\mmm_runtime\Runtime;
\$base_url = Runtime::getEnvironment()->select($base_urls_as_php);

EOD;
    return $content;
  }

  /**
   * @return string
   */
  protected function compileDbcredentials() {
    $db_credentials = $this->project->getDbCredentials();
    $db_credentials_as_php = var_export($db_credentials, TRUE);
    $content = <<<EOD
<?php
// MMM autogenerated
use clever_systems\mmm_runtime\Runtime;
\$databases['default']['default'] = Runtime::getEnvironment()->select($db_credentials_as_php);

EOD;
    return $content;
  }

  /**
   * @return string
   */
  protected static function scaffoldSettings() {
    $content = <<<EOD
<?php
// MMM settings file.
require '../vendor/autoload.php';
use clever_systems\mmm_runtime\Runtime;

require '../settings.baseurl.php';
require '../settings.databases.php';
Runtime::getEnvironment()->settings();
include '../settings.common.php';
include '../settings.local.php';

EOD;
    return $content;
  }

}