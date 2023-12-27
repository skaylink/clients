<?php namespace Skaylink\Clients;

use Illuminate\Support\Collection;

interface StoreInterface
{
  /**
   * @param  string $key
   * @return bool
   * @access public
   */
  public function has(string $key): bool;

  /**
   * @param  string                               $key
   * @param  \Illuminate\Support\Collection|null  $default
   * @return mixed
   * @access public
   */
  public function get(string $key, Collection $default = null);

  /**
   * @param  string                               $key
   * @param  \Illuminate\Support\Collection       $value
   * @return \Skaylink\Clients\StoreInterface
   * @access public
   */
  public function push(string $key, Collection $value): self;

  /**
   * @param  string                               $key
   * @param  mixed                                $value
   * @param  int                                  $ttl
   * @return \Skaylink\Clients\StoreInterface
   * @access public
   */
  public function set(string $key, $value, int $ttl): self;

  /**
   * @param  string                               $key
   * @return \App\Support\Store
   * @access public
   */
  public function forget(string $key): self;

  /**
   * @param  string                               $account
   * @return \Illuminate\Support\Collection|null
   * @access public
   */
  public function index(string $account): ?Collection;

  /**
   * @param  string                               $group
   * @param  \Illuminate\Support\Collection       $resource
   * @return void
   * @access public
   */
  public function persist(string $group, Collection $resource): void;
}
