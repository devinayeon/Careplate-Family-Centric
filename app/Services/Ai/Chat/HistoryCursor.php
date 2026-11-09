<?php

namespace App\Services\Ai\Chat;

use App\Exceptions\Ai\AiException;

/**
 * Opaque pagination cursor: base64url(JSON {v, k, s, p}). k = list kind, s = HMAC of kind+scope (owner and,
 * for history, session), p = last position. A cursor from another owner, session or list, or a forged or
 * malformed one, is INVALID_INPUT. It never carries user_id or SQL ordering; queries still filter by owner.
 */
final class HistoryCursor
{
    public const MAX_LENGTH = 500;

    /** @param list<int|string> $position */
    public static function encode(string $kind, string $scope, array $position): string
    {
        $json = json_encode(['v' => 1, 'k' => $kind, 's' => self::sign($kind, $scope), 'p' => $position], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @return list<int|string>|null null when no cursor was given
     *
     * @throws AiException INVALID_INPUT
     */
    public static function decode(?string $cursor, string $kind, string $scope): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }
        if (strlen($cursor) > self::MAX_LENGTH || preg_match('/^[A-Za-z0-9_-]+$/', $cursor) !== 1) {
            throw AiException::for('INVALID_INPUT');
        }

        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($data) || array_keys($data) !== ['v', 'k', 's', 'p'] || $data['v'] !== 1
            || $data['k'] !== $kind || ! is_string($data['s']) || ! hash_equals(self::sign($kind, $scope), $data['s'])
            || ! is_array($data['p']) || ! array_is_list($data['p'])) {
            throw AiException::for('INVALID_INPUT');
        }

        return $data['p'];
    }

    private static function sign(string $kind, string $scope): string
    {
        return substr(hash_hmac('sha256', $kind.'|'.$scope, (string) config('app.key')), 0, 32);
    }
}
