<?php
namespace SuperSoft\Plugin\Content\Sources\Support;

use Joomla\CMS\Factory;

/** Request-local serialized snapshots only; never shared mutable objects or rendered HTML. */
final class RequestCache
{
    private static array $entries = [];
    private static int $bytes = 0;

    public static function key(string $kind, $input): ?string
    {
        $app = Factory::getApplication();
        if (!method_exists($app, 'getIdentity')) { return null; }
        $user = $app->getIdentity();
        if (!is_object($user) || !method_exists($user, 'getAuthorisedViewLevels')) { return null; }
        $policy = [
            $user->id ?? null, $user->getAuthorisedViewLevels(),
            method_exists($user, 'getAuthorisedGroups') ? $user->getAuthorisedGroups() : [],
            $app->isClient('site'), method_exists($app, 'getLanguageFilter') ? $app->getLanguageFilter() : false,
            method_exists($app, 'getLanguage') ? $app->getLanguage()->getTag() : '',
        ];
        return $kind . ':' . hash('sha256', serialize([$policy, $input]));
    }

    public static function get(?string $key, bool &$hit)
    {
        $hit = $key !== null && isset(self::$entries[$key]);
        if (!$hit) { return null; }
        try { return unserialize(self::$entries[$key], ['allowed_classes' => true]); }
        catch (\Throwable $error) {
            self::$bytes -= strlen(self::$entries[$key]); unset(self::$entries[$key]);
            $hit = false; return null;
        }
    }

    public static function put(?string $key, $value): void
    {
        if ($key === null) { return; }
        try { $snapshot = serialize($value); } catch (\Throwable $error) { return; }
        $size = strlen($snapshot);
        if ($size > 524288) { return; }
        if (isset(self::$entries[$key])) { self::$bytes -= strlen(self::$entries[$key]); unset(self::$entries[$key]); }
        while (self::$entries && (count(self::$entries) >= 128 || self::$bytes + $size > 4194304)) {
            $first = array_key_first(self::$entries);
            self::$bytes -= strlen(self::$entries[$first]); unset(self::$entries[$first]);
        }
        self::$entries[$key] = $snapshot; self::$bytes += $size;
    }

    public static function remember(?string $key, callable $load)
    {
        $hit = false;
        $value = self::get($key, $hit);
        if ($hit) { return $value; }
        $value = $load();
        self::put($key, $value);
        $stored = false; $copy = self::get($key, $stored);
        return $stored ? $copy : $value;
    }

    public static function reset(): void { self::$entries = []; self::$bytes = 0; }
}
