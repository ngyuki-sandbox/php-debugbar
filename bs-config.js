module.exports = {
    serveStatic: [{
        route: '/vendor/ngyuki/php-debugbar/widgets',
        dir: `${__dirname}/src/Resources/widgets`,
    }],
    files: [
        'example/',
        'src/',
    ],
};
