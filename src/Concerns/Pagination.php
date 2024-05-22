<?php namespace Skaylink\Clients\Concerns;

use Skaylink\Clients\Concerns\Filter;
use Illuminate\Http\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Closure;
use Exception;
use Error;

trait Pagination
{
  use Filter;

  /**
   * @param  string                           $uri
   * @param  \Illuminate\Http\Request         $request
   * @param  array                            $filter
   * @return \Illuminate\Support\Collection   $collection
   *
   * @todo fix required alphabetic array key
   */
  public function fetch(string $uri, Request $request, array $filter = []): Collection
  {
    $params = $this->getFilterParams($request, $filter);
    return $this->get($uri, array_merge($params, [
      'limit'  => $request->get('limit', 100),
      'page'   => $request->get('page', 1)
    ]));
  }

  /**
   * @param  string                           $uri
   * @param  \Illuminate\Http\Request         $request
   * @param  array                            $filter
   * @return \Illuminate\Support\Collection   $collection
   */
  public function paginate(string $uri, Request $request, array $filter = []): Collection
  {
    try {
      $data     = collect();
      $params   = collect($this->getFilterParams($request, $filter));
      $response = $this->iterate($uri, $params, function($result) use ($data) {
        foreach($result as $collection) {
          $data->push($collection);
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
   * Paginate next via query string parameter "page"
   *
   * @param  string                             $uri
   * @param  \Illuminate\Support\Collection     $params
   * @param  \Closure                           $callback(\Illuminate\Support\Collection $data)
   * @return \Illuminate\Http\Client\Response   $response
   * @access private
   */
  private function iterate(string $uri, Collection $params, Closure $callback): Response
  {
    $url      = $this->endpoint($uri);
    $response = Http::acceptJson()
      ->withToken($this->bearer)
      ->withHeaders($this->headers('get', $url, $params->toArray()))
      ->get($url, $params);
    $body = recursive($response->json());
    $meta = optional($body->get('@meta'))->get('pagination');
    jot(['cmapi@call' => $url, 'meta' => $meta]);
    switch(true) {
      case $response->successful():
        $callback($body->get('data'));
        if ($meta->get('currentPage') != $meta->get('totalPages')) {
          $this->iterate($uri,
            $params->put('page', $meta->get('currentPage') + 1),
            $callback
          );
        }
        break;
      case $response->failed():
        jotError(['cmapi@pagination:iterate' => $body]);
        return $response;
    }
    return $response;
  }
}
