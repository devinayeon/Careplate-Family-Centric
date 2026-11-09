<?php

// The app runs in UTC (config/app.php). Without a connection time zone the MySQL TIMESTAMP columns are converted
// with the server's SYSTEM zone, which can shift stored times and depends on DST. The live MySQL check
// (SELECT @@session.time_zone) belongs to the MySQL-only suite and was not run while the server was down.

it('pins the mysql and mariadb sessions to UTC', function (string $connection) {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(config("database.connections.$connection.timezone"))->toBe('+00:00');
})->with(['mysql', 'mariadb']);

it('does not add a timezone to the sqlite connection', function () {
    expect(config('database.connections.sqlite'))->not->toHaveKey('timezone');
});
