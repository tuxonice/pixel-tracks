let mix = require('laravel-mix');

mix.copyDirectory('src/Resources/plugins', 'public/plugins');
mix.copyDirectory('src/Resources/css', 'public/css');
mix.copyDirectory('src/Resources/js', 'public/js');
mix.copyDirectory('src/Resources/images', 'public/img');