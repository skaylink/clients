<?php namespace Skaylink\Concerns;

use Skaylink\Concerns\Filter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Closure;
use Exception;
use Error;

trait Pagination
{
  use Filter;

  /**
   * @param  string                           $url
   * @param  \Illuminate\Http\Request         $request
   * @param  array                            $filter
   * @return \Illuminate\Support\Collection   $collection
   */
  public function paginate(string $url, Request $request, array $filter = []): Collection
  {
    try {
      $data     = [];
      $params   = $this->getFilterParams($request, $filter);
      $response = $this->iterate($url, $params, function($result) use ($data) {
        foreach($result as $collection) {
          $data[] = $collection;
        }
      });
      return collect([
        'code' => $response->getStatusCode(),
        'data' => $data
      ]);
    } catch (Exception | Error $e) {
      return collect([
        'code'    => $e->getCode(),
        'message' => $e->getMessage(),
      ]);
    }
  }
  /**
   * paginate next via query string parameter "page"
   *
   * @param  string      $url
   * @param  array       $params
   * @param  \Closure    $callback(data, code, message)
   * @return mixed       $response
   * @access private
   */
  private function iterate(string $url, array $params = [], Closure $callback)
  {
    $response = Http::accept('application/json')
      ->withToken($this->bearer)
      ->withHeaders($this->headers($url, $params))
      ->get($url, $params);
    switch(true) {
      case $response->successful():
        $body = recursive($response->json());
        $callback($body->get('data'));
        if ($meta = optional($body->get('@meta'))->get('pagination')) {
          $page = (int) $meta->get('currentPage');
          while ($page < (int) $meta->get('totalPages')) {
            return $this->iterate($url, 
              array_merge($params, ['page' => ($page + 1)]), $callback);
          }
        }  
      case $response->failed():
        jotError($response->json());
        return;
    }
  }
}
