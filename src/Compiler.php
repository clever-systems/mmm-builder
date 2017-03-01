<?php
/**
 * @file Compiler.php
 */

namespace clever_systems\mmm_builder;

use clever_systems\mmm_builder\Commands\AlterFile;
use clever_systems\mmm_builder\Commands\Commands;
use clever_systems\mmm_builder\Commands\EnsureDirectory;
use clever_systems\mmm_builder\Commands\Symlink;
use clever_systems\mmm_builder\Commands\WriteFile;
use clever_systems\mmm_builder\RenderPhp\PhpFile;

class Compiler {
  /** @var Project */
  protected $project;

  protected function getInstallationName() {
    return 'dev'; // For now.
  }

  /**
   * Compiler constructor.
   * @param Project $project
   */
  public function __construct(Project $project) {
    $this->project = $project;
  }

  /**
   * @return \clever_systems\mmm_builder\Project
   */
  public function getProject() {
    return $this->project;
  }

  /**
   * @return string[]
   */
  public function getEnvironmentNames() {
    $environment_names = [];
    foreach ($this->project->getInstallations() as $installation) {
      $environment_names[] = $installation->getName();
    }
    return $environment_names;
  }

  public function compile(Commands $commands) {
    $drush_dir = ($this->project->getDrupalMajorVersion() == 8) ?
      '../drush' : 'sites/all/drush';

    $commands->add(new WriteFile('sites/sites.php', $this->compileSitesPhp()));
    $commands->add(new WriteFile("$drush_dir/aliases.drushrc.php", $this->compileAliases()));
    $commands->add(new WriteFile('../settings.baseurl.php', $this->compileBaseUrls()));
    $commands->add(new WriteFile('../settings.databases.php', $this->compileDbCredentials()));
  }

  public function compileAliases() {
    $php = new PhpFile();
    $php->addToHeader('// MMM autogenerated: Aliases');

    foreach ($this->project->getInstallations() as $installation) {
      $installation->compileAliases($php);
    }
    return (string)$php;
  }

  public function compileSitesPhp() {
    $php = new PhpFile();
    $php->addToHeader('// MMM autogenerated: Sites');

    foreach ($this->project->getInstallations() as $installation) {
      $installation->compileSitesPhp($php);
    }
    return (string)$php;
  }

  public function compileBaseUrls() {
    $settings_variable = $this->project->getSettingsVariable();
    $php = new PhpFile();
    $php->addToHeader('// MMM autogenerated: Base Urls');
    $php->addToHeader('use clever_systems\mmm_runtime\Runtime;');
    if ($this->project->getDrupalMajorVersion() == 7) {
      $php->addToHeader("\$host = rtrim(\$_SERVER['HTTP_HOST'], '.');");
    }

    foreach ($this->project->getInstallations() as $installation) {
      $installation->compileBaseUrls($php);
    }

    $php->addToFooter("if (empty({$settings_variable}['mmm']['installation'])) error_log('MMM Unknown host or site Id: ' . Runtime::getEnvironment()->getNormalizedSiteUrn());");
    return (string)$php;
  }

  public function compileDbCredentials() {
    $php = new PhpFile();
    $php->addToHeader('// MMM autogenerated: DB Credentials');
    $php->addToHeader('use clever_systems\mmm_runtime\Runtime;');

    foreach ($this->project->getInstallations() as $installation) {
      $installation->compileDbCredentials($php);
    }
    return (string)$php;
  }

  public function letInstallationsAlterHtaccess(Commands $commands) {
    $original_file = !drush_get_option('simulate')
      // Not simulated? Look at the correct location.
      ? '.htaccess.original'
      // Simulated ? Look at the previous location.
      : '.htaccess';
    foreach ($this->project->getInstallations() as $installation) {
      $installation_name = $installation->getName();
      $commands->add(new AlterFile($original_file, ".htaccess.$installation_name",
        function ($content) use ($installation) {
          return $installation->alterHtaccess($content);
        }));
    }
  }

  public function writeSettingsLocal(Commands $commands, $current_installation_name) {
    $environment_names = $this->getEnvironmentNames();
    foreach ($environment_names as $environment_name) {
      $content = ($environment_name === $current_installation_name)
        ? file_get_contents('sites/default/settings.php')
        . "\n\n// TODO: Clean up." : "<?php\n";
      $commands->add(new  WriteFile("../settings.local.$environment_name.php", $content));
    }
  }

  public static function prepare(Commands $commands) {
    Scaffolder::writeProject($commands);
  }

  public function scaffold(Commands $commands, $installation_name) {
    $environment_names = $this->getEnvironmentNames();

    foreach ($environment_names as $environment_name) {
      $commands->add(new EnsureDirectory("../crontab.d/$environment_name"));
    }
    $commands->add(new EnsureDirectory("../crontab.d/common"));
    $commands->add(new WriteFile("../crontab.d/common/50-cron",
      "0 * * * * drush -r \$DRUPAL_ROOT cron -y\n"));

    if (!file_exists('../docroot') && is_dir('../web')) {
      $commands->add(new Symlink('docroot', 'web'));
    }

    $commands->add(new WriteFile('../config-sync/.gitkeep', ''));

    $commands->add(new WriteFile('../settings.common.php', "<?php\n"));

    $this->writeSettingsLocal($commands, $installation_name);

    $drupal_major_version = $this->getProject()->getDrupalMajorVersion();
    Scaffolder::writeSettings($commands, $drupal_major_version);

    Scaffolder::writeBoxfile($commands);

    Scaffolder::writeGitignoreForComposer($commands);

    // Save htaccess to .original.
    $this->postUpdate($commands);

  }

  public static function preUpdate(Commands $commands) {
    Scaffolder::moveBackHtaccess($commands);
  }

  public function postUpdate(Commands $commands) {

    Scaffolder::wrieGitignoreForDrupal($commands);
    if (file_exists('.htaccess') && !is_link('.htaccess')) {
      Scaffolder::moveAwayHtaccess($commands);
      $this->letInstallationsAlterHtaccess($commands);
      Scaffolder::symlinkHtaccess($commands, $this->getInstallationName());
    }

    return $commands;
  }

  public function postClone(Commands $commands) {

    $commands->add(new EnsureDirectory('../private'));
    $commands->add(new EnsureDirectory('../tmp'));
    $commands->add(new EnsureDirectory('../logs'));

    Scaffolder::symlinkSettingsLocal($commands, $this->getInstallationName());
    Scaffolder::symlinkHtaccess($commands, $this->getInstallationName());

    return $commands;
  }

  public static function activateSite(Commands $commands, $site) {
    Scaffolder::delegateSettings($commands, $site);
  }
}
