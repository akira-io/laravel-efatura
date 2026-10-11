<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use PhpToken;
use Symfony\Component\Process\Process;

use const T_DNUMBER;
use const T_DOUBLE_CAST;
use const T_STRING;

final class TrackedSources
{
    public const string BANNED_MENTIONS = '/node-efatura|release-it|pnpm|(?<![\w.\/+-])v10(?![\w+\/])/i';

    private const string FLOAT_TYPE_REJECTED_BY_FISCAL_MONEY = 'int|float|string|Money';

    /**
     * @return list<string>
     */
    public static function trackedFiles(string ...$patterns): array
    {
        $listing = new Process(['git', 'ls-files', '-z', '--', ...$patterns], self::root());
        $listing->mustRun();

        return array_values(array_filter(explode("\0", $listing->getOutput())));
    }

    public static function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * @return list<string>
     */
    public static function floats(string $path, ?string $source = null): array
    {
        $tokens = PhpToken::tokenize($source ?? (string) file_get_contents(self::root() . '/' . $path));
        $found  = [];

        foreach ($tokens as $index => $token) {
            if (self::isFloat($token) && ! self::isRejectedFloatType($path, $tokens, $index)) {
                $found[] = $path . ':' . $token->line . ' ' . $token->text;
            }
        }

        return $found;
    }

    private static function isFloat(PhpToken $token): bool
    {
        if ($token->is([T_DNUMBER, T_DOUBLE_CAST])) {
            return true;
        }

        return $token->is(T_STRING) && \in_array(strtolower($token->text), ['float', 'floatval', 'double'], true);
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private static function isRejectedFloatType(string $path, array $tokens, int $index): bool
    {
        $union = implode('', array_map(static fn (PhpToken $token): string => $token->text, \array_slice($tokens, $index - 2, 7)));

        return $path === 'src/Money/FiscalMoney.php' && $union === self::FLOAT_TYPE_REJECTED_BY_FISCAL_MONEY;
    }
}
