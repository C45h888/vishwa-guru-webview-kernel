<?php return array (
  'providers' => 
  array (
    0 => 'Laravel\\Tinker\\TinkerServiceProvider',
    1 => 'Carbon\\Laravel\\ServiceProvider',
    2 => 'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
    3 => 'Termwind\\Laravel\\TermwindServiceProvider',
  ),
  'eager' => 
  array (
    0 => 'Carbon\\Laravel\\ServiceProvider',
    1 => 'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
    2 => 'Termwind\\Laravel\\TermwindServiceProvider',
  ),
  'deferred' => 
  array (
    'command.tinker' => 'Laravel\\Tinker\\TinkerServiceProvider',
  ),
  'when' => 
  array (
    'Laravel\\Tinker\\TinkerServiceProvider' => 
    array (
    ),
  ),
);