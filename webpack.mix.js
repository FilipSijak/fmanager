const mix = require('laravel-mix');

mix.ts('resources/js/app.tsx', 'public/js')
    .react()
    .extract(['react', 'react-dom']);

mix.sass('resources/sass/global.scss', 'public/css');

// Cache busting
if (mix.inProduction()) {
    mix.version();
}
