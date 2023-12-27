<?php namespace Skaylink\Clients;

use Skaylink\Clients\StoreInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Store implements StoreInterface
{
  const INDEX = 'resource:index:';

  /**
   * @var \Illuminate\Cache\Repository $store
   * @access protected
   */
  protected $store;

  public function __construct()
  {
    $this->store = Cache::store(config('api.store.driver'));
  }

  /**
   * @param  string $key
   * @return bool
   * @access public
   */
  public function has(string $key): bool
  {
    return $this->store->has($key);
  }

  /**
   * @param  string                               $key
   * @param  \Illuminate\Support\Collection|null  $default
   * @return mixed
   * @access public
   */
  public function get(string $key, Collection $default = null)
  {
    return $this->store->get($key, !$default ? collect() : $default);
  }

  /**
   * @param  string                          $key
   * @param  \Illuminate\Support\Collection  $value
   * @return \Skaylink\Clients\Store
   * @access public
   */
  public function push(string $key, Collection $value): self
  {
    $this->store->forever($key, $value);
    return $this;
  }

  /**
   * @param  string                         $key
   * @param  mixed                          $value
   * @param  int                            $ttl
   * @return \Skaylink\Clients\Store
   * @access public
   */
  public function set(string $key, $value, int $ttl): self
  {
    $this->store->set($key, $value, $ttl);
    return $this;
  }

  /**
   * @param  string                         $key
   * @return \Skaylink\Clients\Store
   * @access public
   */
  public function forget(string $key): self
  {
    $this->store->forget($key);
    return $this;
  }

  /**
   * @param  string                          $account
   * @return \Illuminate\Support\Collection
   * @access public
   */
  public function index(string $account): ?Collection
  {
    return $this->get(self::INDEX . $account);
  }

  /**
   * @param  string                          $group
   * @param  \Illuminate\Support\Collection  $resource
   * @return void
   * @access public
   */
  public function persist(string $group, Collection $resource): void
  {
    $key      = self::INDEX . $group;
    $indicies = $this->get($key);
    // we have to create a unique url-friendly id
    $id = Str::of($resource->get('resourceId'))->explode('/')->last();
    $this->push($key, $indicies->put($id, now()));
    $this->push($id, $resource);
  }
}
