<?php

use Illuminate\Contracts\Database\Eloquent\Builder;

/* dump sql query from given builder object */
if (!function_exists('sql')) {
  function sql($query): string {
    return ($query instanceof Builder) ? vsprintf(
      str_replace(array('?'), array('\'%s\''),
      $query->toSql()), $query->getBindings()
    ) : null;
  }
}
/* dump given arguments */
if (!function_exists('d')) {
  function d() {
    call_user_func_array('dump', func_get_args());
  }
}
/* write log message */
if (!function_exists('jot')) {
  function jot() {
    $arguments = func_get_args();
    $mode = end($arguments);
    if (!in_array($mode, ['error', 'critical', 'warning', 'info', 'debug'])) {
      $mode = 'debug';
    } else {
      array_pop($arguments);
    }
    foreach($arguments as $param) {
      $casted = is_string($param) ? [$param] : (array) $param;
      logger()->{$mode}(json_encode($casted, JSON_UNESCAPED_SLASHES));
    }
  }
}
if (!function_exists('jotCritical')) {
  function jotCritical() { return jot(func_get_args(), 'critical'); }
}
if (!function_exists('jotError')) {
  function jotError() { return jot(func_get_args(), 'error'); }
}
if (!function_exists('jotWarning')) {
  function jotWarning() { return jot(func_get_args(), 'warning'); }
}
if (!function_exists('jotInfo')) {
  function jotInfo() { return jot(func_get_args(), 'info'); }
}
