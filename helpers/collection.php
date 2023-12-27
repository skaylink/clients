<?php

use Illuminate\Support\Collection;

// extend collection with `recursive` macro
Collection::macro('recursive', function() {
  if ($this->has('@Use')) {
    $class = $this->pull('@Use');
    return new $class($this->recursive());
  }
  return $this->map(function ($value) {
    if (is_array($value) || is_object($value)) {
      return collect($value)->recursive();
    }
    return $value;
  });
});

/* return recursive collection */
if (!function_exists('recursive')) {
  function recursive(array $array): Collection {
    return collect($array)->recursive();
  }
}
