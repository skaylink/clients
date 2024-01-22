<?php namespace Skaylink\Clients;

use Skaylink\Clients\Store;
use Skaylink\Clients\Concerns\Pagination;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Exception;

class Cmapi
{
  use Pagination;

  /* the cache-key prefix */
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
   * @var array $filter
   * @access protected
   */
  protected $filter = [];

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
      abort_if(!$oauth->get('access_token'),
        Response::HTTP_UNAUTHORIZED,
        $oauth->get('message'));
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
   * @param  string   $method
   * @param  string   $url
   * @param  array    $params
   * @return array    $headers
   * @access private
   */
  private function headers(string $method, string $url, array $params): array
  {
    switch(strtolower($method)) {
      case 'get':
      case 'delete':
        $option = RequestOptions::QUERY;
        break;
      // post, patch, put
      default:
        $option = RequestOptions::JSON;
    }
    if (RequestOptions::QUERY == $option && count($params)) {
      $sep  = (strstr($url, '?') == false) ? '?' : '&';
      $url .= ($sep . http_build_query($params));
      $params = [];
    }
    $body    = (!count($params)) ? '' : json_encode($params);
    $router  = new UrlGenerator(new RouteCollection, new Request);
    $plain   = $router->to($url) . $body . $this->config->get('salt');
    $headers = [$this->config->get('checksum') => Hash::make($plain)];
    // jot(array_merge($headers, ['plain' => $plain]));
    return $headers;
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
   * @param  string                               $method
   * @param  string                               $uri
   * @param  array                                $params
   * @return \Illuminate\Support\Collection|null
   * @access private
   */
  private function call(string $method, string $uri, array $params= []): ?Collection
  {
    $endpoint = $this->endpoint($uri);
    $response = Http::accept('application/json')
      ->withToken($this->bearer)
      ->withHeaders($this->headers($method, $endpoint, $params))
        ->{$method}($endpoint, $params);
    switch(true) {
      case $response->successful():
        return recursive($response->json());
      case $response->failed():
        jotError(['cmapi@call' => $response->json()]);
        return null;
    }
  }

  /**
   * @param  string                               $uri
   * @param  array                                $params
   * @return \Illuminate\Support\Collection|null
   * @access public
   */
  public function get(string $uri, array $params= []): ?Collection
  {
    return $this->call('get', $uri, $params);
  }

  /**
   * @param  string                               $uri
   * @param  array                                $params
   * @return \Illuminate\Support\Collection|null
   * @access public
   */
  public function post(string $uri, array $params= []): ?Collection
  {
    return $this->call('post', $uri, $params);
  }

  /**
   * @param  string                               $uri
   * @param  array                                $params
   * @return \Illuminate\Support\Collection|null
   * @access public
   */
  public function put(string $uri, array $params= []): ?Collection
  {
    return $this->call('put', $uri, $params);
  }

  /**
   * @param  string                               $uri
   * @param  array                                $params
   * @return \Illuminate\Support\Collection|null
   * @access public
   */
  public function patch(string $uri, array $params= []): ?Collection
  {
    return $this->call('patch', $uri, $params);
  }

  /**
   * @param  string                               $uri
   * @param  array                                $params
   * @return \Illuminate\Support\Collection|null
   * @access public
   */
  public function delete(string $uri, array $params= []): ?Collection
  {
    return $this->call('delete', $uri, $params);
  }

  /**
   * @param  string   $id
   * @param  string   $secret
   * @param  string   $salt
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
   * @param  string                               $username
   * @param  string                               $password
   * @return \Illuminate\Support\Collection|null
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
    return optional($client->get('user'))->get('data');
  }

  /**
   * Validate token
   *
   * @return \Illuminate\Support\Collection
   * @access public
   */
  public function validateToken(): Collection
  {
    try {
      $response = $this->get('user/validate-token');
    } catch(Exception $e) {
      $response = collect([
        'valid'   => false,
        'token'   => null,
        'status'  => $e->getMessage(),
        'code'    => Response::HTTP_BAD_REQUEST
      ]);
    }
    return $response;
  }


  /**
   * @return void
   * @access public
   */
  public function clearTokens(): void
  {
    (new Store)->forget(self::STORE . md5($this->config()->toJson()));
  }

  /**
   * Revoke current user client token
   *
   * @return boolean
   * @access public
   * @static
   */
  public static function revoke(): bool
  {
    try {
      $client   = new self;
      $response = $client->validateToken();
      if (Response::HTTP_OK != $response->get('code')) return false;
      $uri = sprintf('%ss/%s', config('api.cmapi.token'),
        $response->get('token'));
      return (Response::HTTP_NO_CONTENT == $client->delete($uri)->get('code'));
    } catch (Exception $e) {
      jot(['revoke' => $e->getMessage()]);
      return false;
    }
  }

  /**
   * @param  string                               $account
   * @param  string                               $region
   * @return \Illuminate\Support\Collection|null
   * @access public
   */
  public function credentials(string $account, string $region): ?Collection
  {
    $uri = sprintf('accounts/%s/credentials', $account);
    return $this->post($uri, ['region' => $region]);
  }
}
