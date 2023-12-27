<?php namespace Skaylink\Clients;

use Illuminate\Support\ServiceProvider;

class ClientsServiceProvider extends ServiceProvider
{
  /**
   * @var array $configs
   * @access protected
   */
  protected $configs = [
    'api' => 'api.php'
  ];

  /**
   * @return void
   * @access public
   */
  public function register(): void
  {
    if (!app()->configurationIsCached()) {
      foreach($this->configs as $name => $fileName) {
        $this->mergeConfigFrom($this->configPath($fileName), $name);
      }
    }
  }

  /**
   * @return void
   * @access public
   */
  public function boot(): void
  {
    if ($this->app->runningInConsole()) {
      foreach($this->configs as $fileName) {
        $this->publishes([$this->configPath($fileName) => config_path($fileName),
      ], 'api-config');
      }
    }
  }

  /**
   * @param  string    $fileName
   * @return string
   * @access protected
   */
  protected function configPath(string $fileName): string
  {
    return __DIR__ . '/../config/' . $fileName;
  }
}
