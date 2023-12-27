<?php namespace Skaylink\Clients;

use Skaylink\Cmapi\Store;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class Cmapi
{
  const STORE = 'cmapi.credentials:';

  /**
   * @var string $bearer
   * @access protected
   */
  protected $bearer;

  /**
   * @var int $expires
   * @access protected
   */
  protected $expires;

  /**
   * @var \Illuminate\Support\Collection $config
   * @access protected
   */
  protected $config;

  /**
   * @param  array  $extra
   * @constructor
   */
  public function __construct(array $extra = [])
  {
    $store  = new Store;
    $this->config = $this->config($extra);
    $cache  = self::STORE . md5($this->config->toJson());
    if (!$store->has($cache)) {
      $oauth = $this->authenticate();
      $this->bearer = $oauth->get('access_token');
      abort_if(!$oauth->get('access_token'), 401, $oauth->get('message'));
      $store->set($cache, $this->bearer, $oauth->get('expires_in'));
    } else {
      $this->bearer = $store->get($cache);
    }
  }

  /**
   * @return \Illuminate\Support\Collection
   * @access private
   */
  private function authenticate(): Collection
  {
    $response = Http::post(
      $this->config->get('token'),
      $this->config->get('credentials')->toArray()
    );
    return recursive($response->json());
  }

  /**
   * @param  string  $uri
   * @return string
   * @access private
   */
  private function endpoint(string $uri): string
  {
    return sprintf('%s/%s', $this->config->get('endpoint'), $uri);
  }

  /**
   * @param  string   $url
   * @param  array    $params
   * @return array
   * @access private
   */
  private function headers(string $url, array $params): array
  {
    $body   = (!count($params)) ? '' : json_encode($params);
    $router = new UrlGenerator(new RouteCollection, new Request);
    $plain  = $router->to($url) . $body . $this->config->get('salt');
    return [$this->config->get('checksum') => Hash::make($plain)];
  }

  /**
   * @param  array                              $extendWith
   * @return \Illuminate\Support\Collection
   * @access protected
   */
  protected function config(array $extendWith = []): Collection
  {
    return recursive(array_merge(config('api.cmapi'), $extendWith));
  }

  /**
   * @param  string                           $method
   * @param  string                           $url
   * @param  array                            $params
   * @return \Illuminate\Support\Collection
   * @access private
   */
  private function call(string $method, string $url, array $params= []): ?Collection
  {
    $response = Http::accept('application/json')
      ->withToken($this->bearer)
      ->withHeaders($this->headers($url, $params))
      ->{$method}($url, $params);
    switch(true) {
      case $response->successful():
        return recursive($response->json());
      case $response->failed():
        jotError($response->json());
        return null;
    }
  }

  /**
   * @param  string                           $url
   * @param  array                            $params
   * @return \Illuminate\Support\Collection
   * @access private
   */
  private function get(string $url, array $params= []): ?Collection
  {
    return $this->call('get', $url, $params);
  }

  /**
   * @param  string                           $url
   * @param  array                            $params
   * @return \Illuminate\Support\Collection
   * @access private
   */
  private function post(string $url, array $params= []): ?Collection
  {
    return $this->call('post', $url, $params);
  }

  /**
   * @param  string                           $url
   * @param  array                            $params
   * @return \Illuminate\Support\Collection
   * @access private
   */
  private function put(string $url, array $params= []): ?Collection
  {
    return $this->call('put', $url, $params);
  }

  /**
   * @param  string                           $url
   * @param  array                            $params
   * @return \Illuminate\Support\Collection
   * @access private
   */
  private function patch(string $url, array $params= []): ?Collection
  {
    return $this->call('patch', $url, $params);
  }

  /**
   * @param  string                           $url
   * @param  array                            $params
   * @return \Illuminate\Support\Collection
   * @access private
   */
  private function delete(string $url, array $params= []): ?Collection
  {
    return $this->call('delete', $url, $params);
  }

  /**
   * @param  string                           $username
   * @param  string                           $password
   * @return bool
   * @access public
   * @static
   */
  public static function signIn(string $id, string $secret, string $salt): bool
  {
    $client = new self([
      'salt'        => $salt,
      'credentials' => [
        'client_id'     => $id,
        'client_secret' => $secret,
        'scope'         => '*',
        'grant_type'    => 'client_credentials'
      ]
    ]);
    return !is_null($client->bearer);
  }

  /**
   * @param  string                           $username
   * @param  string                           $password
   * @return \Illuminate\Support\Collection
   * @access public
   * @static
   */
  public static function signInAs(string $username, string $password): ?Collection
  {
    $client = new self([
      'salt'        => config('api.cmapi.salt'),
      'credentials' => array_merge(config('api.cmapi.credentials'), [
        'grant_type'  => 'password',
        'username'    => $username,
        'password'    => $password
      ])
    ]);
    return $client->get($client->endpoint('user'))->get('data');
  }

  /**
   * @param  string                           $account
   * @param  string                           $region
   * @return \Illuminate\Support\Collection
   * @access public
   */
  public function credentials(string $account, string $region): ?Collection
  {
    $uri = sprintf('accounts/%s/credentials', $account);
    return $this->post($this->endpoint($uri), ['region' => $region]);
  }
}
